// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Hardened HLS player engine, dynamic moving watermark, anti-seek, and watch tracker.
 *
 * @module     mod_securevideoplayer/player
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Load HLS.js library locally via AMD or fallback to CDN.
     *
     * @return {Promise<window.Hls>}
     */
    function loadHls() {
        return new Promise(function(resolve, reject) {
            if (window.Hls) {
                resolve(window.Hls);
                return;
            }

            require(['local_securevideolibrary/hls'], function(HlsModule) {
                const Hls = HlsModule || window.Hls;
                if (Hls) {
                    resolve(Hls);
                    return;
                }
                loadCdn(resolve, reject);
            }, function() {
                loadCdn(resolve, reject);
            });
        });
    }

    /**
     * Fallback to CDN if local bundle is not present.
     *
     * @param {Function} resolve
     * @param {Function} reject
     */
    function loadCdn(resolve, reject) {
        if (window.Hls) {
            resolve(window.Hls);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js';
        script.onload = function() {
            resolve(window.Hls);
        };
        script.onerror = function(err) {
            reject(err);
        };
        document.head.appendChild(script);
    }

    /**
     * Format seconds into MM:SS or HH:MM:SS.
     *
     * @param {number} totalSeconds
     * @return {string}
     */
    function formatTime(totalSeconds) {
        const s = Math.max(0, Math.floor(totalSeconds));
        const hours = Math.floor(s / 3600);
        const mins = Math.floor((s % 3600) / 60);
        const secs = s % 60;

        if (hours > 0) {
            return String(hours).padStart(2, '0') + ':' +
                   String(mins).padStart(2, '0') + ':' +
                   String(secs).padStart(2, '0');
        }
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    /**
     * Merge overlapping intervals and compute total unique seconds watched.
     *
     * @param {Array<Array<number>>} intervals
     * @return {{merged: Array<Array<number>>, totalSeconds: number}}
     */
    function mergeIntervals(intervals) {
        if (!intervals || intervals.length === 0) {
            return {merged: [], totalSeconds: 0};
        }

        const sorted = intervals.slice().sort(function(a, b) {
            return a[0] - b[0];
        });

        const merged = [sorted[0]];

        for (let i = 1; i < sorted.length; i++) {
            const current = sorted[i];
            const last = merged[merged.length - 1];

            if (current[0] <= last[1]) {
                last[1] = Math.max(last[1], current[1]);
            } else {
                merged.push(current);
            }
        }

        let total = 0;
        merged.forEach(function(item) {
            total += Math.max(0, item[1] - item[0]);
        });

        return {merged: merged, totalSeconds: total};
    }

    /**
     * Main player controller instance.
     *
     * @param {HTMLElement} container
     */
    function SecurePlayer(container) {
        this.container = container;
        this.cmid = parseInt(container.dataset.cmid, 10);
        this.playlistUrl = container.dataset.playlisturl;
        this.duration = parseFloat(container.dataset.duration) || 0;
        this.lastPosition = parseFloat(container.dataset.lastposition) || 0;
        this.maxPosition = parseFloat(container.dataset.maxposition) || 0;
        this.watchedSeconds = parseInt(container.dataset.watchedseconds, 10) || 0;
        this.completionMinPercent = parseInt(container.dataset.completionminpercent, 10) || 0;
        this.preventSeeking = container.dataset.preventseeking === '1';
        this.watermarkEnabled = container.dataset.watermarkenabled === '1';
        this.watermarkOpacity = parseFloat(container.dataset.watermarkopacity) || 0.35;

        this.studentName = container.dataset.studentname || '';
        this.studentId = container.dataset.studentid || '';
        this.studentIp = container.dataset.studentip || '';
        this.liveTime = container.dataset.livetime || '';

        try {
            this.watchedIntervals = JSON.parse(container.dataset.watchedintervals || '[]');
        } catch (e) {
            this.watchedIntervals = [];
        }

        this.video = container.querySelector('#secureVideoElement');
        this.canvas = container.querySelector('#secureVideoWatermarkCanvas');
        this.hls = null;
        this.beaconInterval = null;
        this.watermarkInterval = null;
        this.isScrubbing = false;

        this.init();
    }

    SecurePlayer.prototype = {
        /**
         * Initialize player.
         */
        init: function() {
            this.setupControls();
            this.setupWatermark();
            this.setupAntiTamper();
            this.setupProgressBeacon();
            this.loadStream();
        },

        /**
         * Load HLS Stream into video element.
         */
        loadStream: function() {
            const self = this;
            const video = this.video;

            loadHls().then(function(Hls) {
                if (Hls.isSupported()) {
                    self.hls = new Hls({
                        debug: false,
                        enableWorker: true,
                        xhrSetup: function(xhr, url) {
                            xhr.withCredentials = true;
                            if (url && url.indexOf('key.php') !== -1) {
                                try {
                                    const urlObj = new URL(url, window.location.href);
                                    const token = urlObj.searchParams.get('token');
                                    if (token) {
                                        xhr.setRequestHeader('X-Moodle-Player-Token', token);
                                    }
                                } catch (e) {
                                    const match = /[?&]token=([a-fA-F0-9]+)/.exec(url);
                                    if (match) {
                                        xhr.setRequestHeader('X-Moodle-Player-Token', match[1]);
                                    }
                                }
                                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            }
                        }
                    });

                    self.hls.loadSource(self.playlistUrl);
                    self.hls.attachMedia(video);

                    self.hls.on(Hls.Events.MANIFEST_PARSED, function(event, data) {
                        self.setupQualityLevels(data.levels);
                    });
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = self.playlistUrl;
                }
            }).catch(Notification.exception);
        },

        /**
         * Setup custom UI controls.
         */
        setupControls: function() {
            const self = this;
            const video = this.video;
            const container = this.container;

            const playBtn = container.querySelector('.securevideo-btn-play');
            const bigPlayBtn = container.querySelector('.securevideo-btn-bigplay');
            const centerOverlay = container.querySelector('.securevideo-center-overlay');
            const muteBtn = container.querySelector('.securevideo-btn-mute');
            const volumeSlider = container.querySelector('.securevideo-volume-slider');
            const timelineWrapper = container.querySelector('.securevideo-timeline-wrapper');
            const progressBar = container.querySelector('.securevideo-progress-bar');
            const bufferedBar = container.querySelector('.securevideo-buffered-bar');
            const maxMarker = container.querySelector('.securevideo-max-marker');
            const currentTimeEl = container.querySelector('.securevideo-current-time');
            const fullscreenBtn = container.querySelector('.securevideo-btn-fullscreen');
            const speedItems = container.querySelectorAll('.securevideo-speed-menu button');
            const resumeToast = container.querySelector('#secureVideoResumeToast');
            const seekNotice = container.querySelector('#secureVideoSeekNotice');

            // Play / Pause toggle function.
            const togglePlay = function() {
                if (video.paused || video.ended) {
                    video.play().catch(function(err) {
                        console.warn('[SecureVideo] Autoplay blocked:', err);
                    });
                } else {
                    video.pause();
                }
            };

            if (playBtn) {
                playBtn.addEventListener('click', togglePlay);
            }
            if (bigPlayBtn) {
                bigPlayBtn.addEventListener('click', togglePlay);
            }
            if (centerOverlay) {
                centerOverlay.addEventListener('click', function(e) {
                    if (e.target === centerOverlay || e.target.closest('.securevideo-btn-bigplay')) {
                        togglePlay();
                    }
                });
            }

            video.addEventListener('play', function() {
                container.classList.add('is-playing');
                container.classList.remove('is-paused');
                if (playBtn) {
                    playBtn.innerHTML = '<i class="fa fa-pause" aria-hidden="true"></i>';
                }
            });

            video.addEventListener('pause', function() {
                container.classList.remove('is-playing');
                container.classList.add('is-paused');
                if (playBtn) {
                    playBtn.innerHTML = '<i class="fa fa-play" aria-hidden="true"></i>';
                }
                self.sendProgressBeacon();
            });

            video.addEventListener('ended', function() {
                container.classList.remove('is-playing');
                container.classList.add('is-paused');
                if (playBtn) {
                    playBtn.innerHTML = '<i class="fa fa-repeat" aria-hidden="true"></i>';
                }
                self.sendProgressBeacon();
            });

            // Volume & Mute.
            if (muteBtn && volumeSlider) {
                muteBtn.addEventListener('click', function() {
                    video.muted = !video.muted;
                    muteBtn.innerHTML = video.muted ?
                        '<i class="fa fa-volume-off" aria-hidden="true"></i>' :
                        '<i class="fa fa-volume-up" aria-hidden="true"></i>';
                });

                volumeSlider.addEventListener('input', function(e) {
                    video.volume = parseFloat(e.target.value);
                    video.muted = (video.volume === 0);
                    muteBtn.innerHTML = video.muted ?
                        '<i class="fa fa-volume-off" aria-hidden="true"></i>' :
                        '<i class="fa fa-volume-up" aria-hidden="true"></i>';
                });
            }

            // Timeline scrubbing.
            const handleTimelineSeek = function(e) {
                const rect = timelineWrapper.getBoundingClientRect();
                const clickX = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
                const targetTime = (clickX / rect.width) * (video.duration || self.duration);

                // Anti-seek check.
                if (self.preventSeeking && targetTime > (self.maxPosition + 2)) {
                    video.currentTime = self.maxPosition;
                    if (seekNotice) {
                        seekNotice.classList.remove('d-none');
                        setTimeout(function() {
                            seekNotice.classList.add('d-none');
                        }, 2000);
                    }
                    return;
                }

                video.currentTime = targetTime;
            };

            if (timelineWrapper) {
                timelineWrapper.addEventListener('click', handleTimelineSeek);
            }

            // Timeupdate listener.
            video.addEventListener('timeupdate', function() {
                const cur = video.currentTime;
                const dur = video.duration || self.duration;

                // Anti-seek enforcement.
                if (self.preventSeeking) {
                    if (cur > self.maxPosition + 2) {
                        video.currentTime = self.maxPosition;
                        if (seekNotice) {
                            seekNotice.classList.remove('d-none');
                            setTimeout(function() {
                                seekNotice.classList.add('d-none');
                            }, 2000);
                        }
                    } else if (cur > self.maxPosition) {
                        self.maxPosition = cur;
                    }
                } else if (cur > self.maxPosition) {
                    self.maxPosition = cur;
                }

                // Interval tracking for legitimate watch time.
                if (!video.paused && cur > 0) {
                    const sec = Math.floor(cur);
                    self.watchedIntervals.push([sec, sec + 1]);
                }

                // Update timeline progress bar.
                if (dur > 0) {
                    const percent = (cur / dur) * 100;
                    if (progressBar) {
                        progressBar.style.width = percent + '%';
                    }
                    if (maxMarker && self.preventSeeking) {
                        maxMarker.style.left = ((self.maxPosition / dur) * 100) + '%';
                    }
                }

                if (currentTimeEl) {
                    currentTimeEl.textContent = formatTime(cur);
                }

                // Update buffered range.
                if (video.buffered.length > 0 && dur > 0 && bufferedBar) {
                    const bufferedEnd = video.buffered.end(video.buffered.length - 1);
                    bufferedBar.style.width = ((bufferedEnd / dur) * 100) + '%';
                }
            });

            // Playback speed menu.
            speedItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const speed = parseFloat(item.dataset.speed);
                    video.playbackRate = speed;
                    speedItems.forEach(function(i) {
                        i.classList.remove('active');
                    });
                    item.classList.add('active');
                    const label = container.querySelector('.securevideo-current-speed');
                    if (label) {
                        label.textContent = speed + 'x';
                    }
                });
            });

            // Fullscreen wrapper toggle.
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', function() {
                    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                        if (container.requestFullscreen) {
                            container.requestFullscreen();
                        } else if (container.webkitRequestFullscreen) {
                            container.webkitRequestFullscreen();
                        }
                        fullscreenBtn.innerHTML = '<i class="fa fa-compress" aria-hidden="true"></i>';
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        }
                        fullscreenBtn.innerHTML = '<i class="fa fa-expand" aria-hidden="true"></i>';
                    }
                });
            }

            // Resume bookmark actions.
            if (resumeToast) {
                const resumeBtn = resumeToast.querySelector('.btn-action-resume');
                const dismissBtn = resumeToast.querySelector('.btn-action-dismiss');

                if (resumeBtn) {
                    resumeBtn.addEventListener('click', function() {
                        video.currentTime = self.lastPosition;
                        resumeToast.remove();
                        video.play();
                    });
                }
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function() {
                        resumeToast.remove();
                    });
                }
            }

            // Idle detection for hiding controls when playing.
            let idleTimeout = null;
            const resetIdle = function() {
                container.classList.remove('is-idle');
                clearTimeout(idleTimeout);
                if (!video.paused) {
                    idleTimeout = setTimeout(function() {
                        container.classList.add('is-idle');
                    }, 3000);
                }
            };

            container.addEventListener('mousemove', resetIdle);
            container.addEventListener('click', resetIdle);
        },

        /**
         * Setup ABR Quality level options.
         *
         * @param {Array} levels
         */
        setupQualityLevels: function(levels) {
            const self = this;
            const container = this.container;
            const qualityContainer = container.querySelector('.securevideo-quality-container');
            const qualityMenu = container.querySelector('.securevideo-quality-menu');
            const qualityLabel = container.querySelector('.securevideo-current-quality');

            if (!levels || levels.length <= 1 || !qualityContainer || !qualityMenu) {
                return;
            }

            qualityContainer.classList.remove('d-none');

            levels.forEach(function(level, idx) {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dropdown-item';
                btn.dataset.level = idx;
                btn.textContent = (level.height ? level.height + 'p' : 'Level ' + (idx + 1));

                btn.addEventListener('click', function() {
                    self.hls.currentLevel = idx;
                    qualityMenu.querySelectorAll('.dropdown-item').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    btn.classList.add('active');
                    if (qualityLabel) {
                        qualityLabel.textContent = btn.textContent;
                    }
                });

                li.appendChild(btn);
                qualityMenu.appendChild(li);
            });

            // Auto button.
            const autoBtn = qualityMenu.querySelector('[data-level="-1"]');
            if (autoBtn) {
                autoBtn.addEventListener('click', function() {
                    self.hls.currentLevel = -1;
                    qualityMenu.querySelectorAll('.dropdown-item').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    autoBtn.classList.add('active');
                    if (qualityLabel) {
                        qualityLabel.textContent = 'Auto';
                    }
                });
            }
        },

        /**
         * Setup dynamic moving canvas watermark.
         */
        setupWatermark: function() {
            if (!this.watermarkEnabled || !this.canvas) {
                return;
            }

            const self = this;
            const canvas = this.canvas;
            const ctx = canvas.getContext('2d');

            let currentX = 0.2;
            let currentY = 0.2;
            let targetX = 0.2;
            let targetY = 0.2;

            const resizeCanvas = function() {
                canvas.width = canvas.clientWidth || 800;
                canvas.height = canvas.clientHeight || 450;
            };

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            // Watermark text.
            const textLine1 = this.studentName + ' (ID: ' + this.studentId + ')';
            const textLine2 = 'IP: ' + this.studentIp + ' • ' + this.liveTime;

            // Pick a new quadrant every 6 seconds.
            const pickNewQuadrant = function() {
                targetX = 0.1 + Math.random() * 0.6;
                targetY = 0.1 + Math.random() * 0.65;
            };

            pickNewQuadrant();
            this.watermarkInterval = setInterval(pickNewQuadrant, 6000);

            // Smooth 60fps render loop.
            const render = function() {
                if (!canvas.isConnected) {
                    return;
                }

                currentX += (targetX - currentX) * 0.05;
                currentY += (targetY - currentY) * 0.05;

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                ctx.save();
                ctx.globalAlpha = self.watermarkOpacity;
                ctx.font = '13px system-ui, -apple-system, sans-serif';

                const padX = 14;
                const padY = 8;
                const width1 = ctx.measureText(textLine1).width;
                const width2 = ctx.measureText(textLine2).width;
                const boxW = Math.max(width1, width2) + (padX * 2);
                const boxH = 46;

                const posX = currentX * (canvas.width - boxW);
                const posY = currentY * (canvas.height - boxH);

                // Draw background pill.
                ctx.fillStyle = 'rgba(0, 0, 0, 0.65)';
                ctx.beginPath();
                ctx.roundRect(posX, posY, boxW, boxH, 8);
                ctx.fill();

                // Draw text.
                ctx.fillStyle = '#ffffff';
                ctx.fillText(textLine1, posX + padX, posY + 20);
                ctx.fillStyle = '#e2e8f0';
                ctx.fillText(textLine2, posX + padX, posY + 38);

                ctx.restore();

                requestAnimationFrame(render);
            };

            requestAnimationFrame(render);
        },

        /**
         * Setup MutationObserver and integrity timer against DevTools tampering.
         */
        setupAntiTamper: function() {
            if (!this.watermarkEnabled || !this.canvas) {
                return;
            }

            const self = this;
            const video = this.video;
            const container = this.container;

            const checkCanvasIntegrity = function() {
                const canvas = self.canvas;
                if (!canvas || !canvas.isConnected) {
                    console.warn('[SecureVideo] Watermark removed from DOM. Pausing playback.');
                    video.pause();
                    self.restoreWatermarkCanvas();
                    return;
                }

                const style = window.getComputedStyle(canvas);
                if (
                    style.display === 'none' ||
                    style.visibility === 'hidden' ||
                    parseFloat(style.opacity) < 0.05 ||
                    canvas.offsetWidth === 0 ||
                    canvas.offsetHeight === 0
                ) {
                    console.warn('[SecureVideo] Watermark style tampered. Pausing playback.');
                    video.pause();
                    canvas.style.display = 'block';
                    canvas.style.visibility = 'visible';
                    canvas.style.opacity = '1';
                }
            };

            const observer = new MutationObserver(function() {
                checkCanvasIntegrity();
            });

            observer.observe(container, {
                childList: true,
                attributes: true,
                subtree: true
            });

            setInterval(checkCanvasIntegrity, 1500);
        },

        /**
         * Re-create watermark canvas if removed by DevTools.
         */
        restoreWatermarkCanvas: function() {
            const ratioDiv = this.container.querySelector('.ratio');
            if (!ratioDiv) {
                return;
            }

            let canvas = this.container.querySelector('#secureVideoWatermarkCanvas');
            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.id = 'secureVideoWatermarkCanvas';
                canvas.className = 'securevideo-watermark-canvas position-absolute top-0 start-0 w-100 h-100';
                canvas.style.pointerEvents = 'none';
                canvas.style.zIndex = '15';
                ratioDiv.appendChild(canvas);
                this.canvas = canvas;
                this.setupWatermark();
            }
        },

        /**
         * Setup periodic progress beacon to Moodle backend.
         */
        setupProgressBeacon: function() {
            const self = this;

            this.beaconInterval = setInterval(function() {
                if (!self.video.paused) {
                    self.sendProgressBeacon();
                }
            }, 10000);

            window.addEventListener('beforeunload', function() {
                self.sendProgressBeacon();
            });
        },

        /**
         * Submit watch progress beacon via external web service.
         */
        sendProgressBeacon: function() {
            const self = this;
            const video = this.video;

            const mergedData = mergeIntervals(this.watchedIntervals);
            this.watchedIntervals = mergedData.merged;
            this.watchedSeconds = mergedData.totalSeconds;

            const cur = Math.floor(video.currentTime || 0);
            const maxPos = Math.floor(this.maxPosition || 0);

            const request = {
                methodname: 'mod_securevideoplayer_update_progress',
                args: {
                    cmid: self.cmid,
                    currentposition: cur,
                    maxposition: maxPos,
                    watchedseconds: self.watchedSeconds,
                    watchedintervals: JSON.stringify(self.watchedIntervals)
                }
            };

            Ajax.call([request])[0].done(function(response) {
                if (response && response.status) {
                    const badge = self.container.querySelector('.securevideo-progress-badge');
                    if (badge) {
                        const targetStr = self.completionMinPercent > 0 ?
                            ' (Target: ' + self.completionMinPercent + '%)' : '';
                        badge.textContent = 'Watched: ' + response.percentcompleted + '%' + targetStr;
                        if (response.iscompleted) {
                            badge.classList.remove('bg-secondary');
                            badge.classList.add('bg-success');
                        }
                    }
                }
            }).fail(function(err) {
                console.error('[SecureVideo] Progress beacon failed:', err);
            });
        }
    };

    return {
        /**
         * Initialize player module for container.
         *
         * @param {string} containerId
         */
        init: function(containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                new SecurePlayer(container);
            }
        }
    };
});
