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
 * Interactive video library picker modal for mod_securevideo.
 *
 * @module     mod_securevideo/picker
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/templates'], function(Ajax, Notification, Templates) {
    'use strict';

    let currentCourseId = 0;
    let currentScope = 'my';
    let currentSearch = '';
    let searchDebounceTimeout = null;

    /**
     * Fetch videos from local_securevideolibrary via web service.
     */
    function fetchVideos() {
        const modalEl = document.getElementById('secureVideoPickerModal');
        if (!modalEl) {
            return;
        }

        const gridEl = modalEl.querySelector('.securevideo-picker-grid');
        const loadingEl = modalEl.querySelector('.securevideo-picker-loading');
        const emptyEl = modalEl.querySelector('.securevideo-picker-empty');
        const refreshBtn = modalEl.querySelector('.securevideo-picker-refresh i');

        if (loadingEl) {
            loadingEl.classList.remove('d-none');
        }
        if (emptyEl) {
            emptyEl.classList.add('d-none');
        }
        if (gridEl) {
            gridEl.innerHTML = '';
        }
        if (refreshBtn) {
            refreshBtn.classList.add('fa-spin');
        }

        const request = {
            methodname: 'local_securevideolibrary_get_videos',
            args: {
                courseid: currentCourseId,
                scope: currentScope,
                search: currentSearch,
                page: 0,
                perpage: 60
            }
        };

        Ajax.call([request])[0].done(function(response) {
            if (loadingEl) {
                loadingEl.classList.add('d-none');
            }
            if (refreshBtn) {
                refreshBtn.classList.remove('fa-spin');
            }

            const readyVideos = (response.videos || []).filter(function(v) {
                return v.status === 'ready';
            });

            if (readyVideos.length === 0) {
                if (emptyEl) {
                    emptyEl.classList.remove('d-none');
                }
                return;
            }

            const renderPromises = readyVideos.map(function(video) {
                return Templates.render('mod_securevideo/picker_card', video);
            });

            Promise.all(renderPromises).then(function(renderedHtmls) {
                if (gridEl) {
                    gridEl.innerHTML = renderedHtmls.join('');
                }
            }).catch(Notification.exception);
        }).fail(function(err) {
            if (loadingEl) {
                loadingEl.classList.add('d-none');
            }
            if (refreshBtn) {
                refreshBtn.classList.remove('fa-spin');
            }
            Notification.exception(err);
        });
    }

    /**
     * Update the preview container in mod_form.php when a video is selected.
     *
     * @param {Object} video Selected video data.
     */
    function applySelectedVideo(video) {
        const mediaIdInput = document.getElementById('id_mediaid');
        const nameInput = document.getElementById('id_name');
        const previewEl = document.getElementById('securevideo_selected_preview');

        if (mediaIdInput) {
            mediaIdInput.value = video.id;
        }

        // Auto-fill activity name if empty.
        if (nameInput && !nameInput.value.trim()) {
            nameInput.value = video.title;
        }

        if (previewEl) {
            const thumbHtml = video.thumbnail ?
                '<img src="' + video.thumbnail + '" alt="" class="w-100 h-100 object-fit-cover" style="object-fit: cover;">' :
                '<i class="fa fa-film fa-2x text-muted" aria-hidden="true"></i>';

            previewEl.innerHTML =
                '<div class="card border p-3 d-flex flex-row align-items-center gap-3 bg-light" style="max-width: 500px;">' +
                    '<div class="ratio ratio-16x9 bg-dark rounded overflow-hidden" style="width: 140px; flex-shrink: 0;">' +
                        thumbHtml +
                    '</div>' +
                    '<div class="flex-grow-1 overflow-hidden">' +
                        '<strong class="d-block text-truncate mb-1">' + video.title + '</strong>' +
                        '<span class="badge bg-secondary mb-1">' + video.duration + '</span>' +
                        '<div class="text-muted small">Selected Video #' + video.id + '</div>' +
                    '</div>' +
                '</div>';
        }

        // Close modal.
        const modalEl = document.getElementById('secureVideoPickerModal');
        if (modalEl) {
            if (window.bootstrap && window.bootstrap.Modal) {
                const bsModal = window.bootstrap.Modal.getInstance(modalEl) ||
                                window.bootstrap.Modal.getOrCreateInstance(modalEl);
                bsModal.hide();
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('hide');
            } else {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }
    }

    /**
     * Setup event listeners.
     */
    function setupEvents() {
        const modalEl = document.getElementById('secureVideoPickerModal');
        if (!modalEl) {
            return;
        }

        // Fetch on first modal open.
        modalEl.addEventListener('show.bs.modal', function() {
            fetchVideos();
        });

        // Scope tabs.
        const tabButtons = modalEl.querySelectorAll('.securevideo-picker-tabs button');
        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                tabButtons.forEach(function(b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentScope = btn.dataset.scope || 'my';
                fetchVideos();
            });
        });

        // Search input with debounce.
        const searchInput = modalEl.querySelector('.securevideo-picker-search');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchDebounceTimeout);
                searchDebounceTimeout = setTimeout(function() {
                    currentSearch = e.target.value.trim();
                    fetchVideos();
                }, 300);
            });
        }

        // Refresh button.
        const refreshBtn = modalEl.querySelector('.securevideo-picker-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                fetchVideos();
            });
        }

        // Select video button.
        modalEl.addEventListener('click', function(e) {
            const selectBtn = e.target.closest('.btn-select-video');
            if (!selectBtn) {
                return;
            }

            e.preventDefault();
            const videoData = {
                id: selectBtn.dataset.id,
                title: selectBtn.dataset.title,
                duration: selectBtn.dataset.duration,
                thumbnail: selectBtn.dataset.thumbnail
            };
            applySelectedVideo(videoData);
        });
    }

    return {
        /**
         * Initialize module.
         *
         * @param {Object} params Configuration parameters.
         */
        init: function(params) {
            currentCourseId = params.courseid || 0;
            setupEvents();
        }
    };
});
