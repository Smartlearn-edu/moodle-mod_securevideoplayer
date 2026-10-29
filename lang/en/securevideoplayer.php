<?php
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
 * English strings for mod_securevideoplayer.
 *
 * @package    mod_securevideoplayer
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Secure Video Player';
$string['modulename'] = 'Secure Video Player';
$string['modulenameplural'] = 'Secure Video Players';
$string['modulename_help'] = 'Protected HTML5 video player with AES-128 HLS encryption, anti-download shielding, dynamic moving watermark, and granular watch-time completion tracking.';
$string['pluginadministration'] = 'Secure Video Player administration';

// Capabilities.
$string['securevideoplayer:addinstance'] = 'Add a new secure video player activity';
$string['securevideoplayer:view'] = 'View the secure video player';
$string['securevideoplayer:viewreports'] = 'View student watch progress reports';

// Activity Form & Video Selection.
$string['videoselection'] = 'Video Selection';
$string['selectvideo'] = 'Select Video';
$string['selectfromlibrary'] = 'Select from Video Library';
$string['novideoselected'] = 'No video selected yet.';
$string['selectedvideo'] = 'Selected Video:';
$string['changevideo'] = 'Change Video';
$string['uploadnewvideo'] = 'Upload New Video';
$string['refreshlist'] = 'Refresh List';
$string['loadingvideos'] = 'Loading videos from library...';
$string['searchvideos'] = 'Search videos...';
$string['scope_my'] = 'My uploads';
$string['scope_course'] = 'Course videos';
$string['scope_all'] = 'All accessible';
$string['selectthisvideo'] = 'Select This Video';
$string['err_novideoselected'] = 'You must select a video from the library to save this activity.';

// Playback & Security Options.
$string['playbacksecurity'] = 'Playback & Security Options';
$string['watermarkenabled'] = 'Enable dynamic moving watermark';
$string['watermarkenabled_help'] = 'If enabled, a floating semi-transparent watermark displaying the student\'s name, user ID, IP address, and live clock will move across the screen to deter screen recordings and unauthorized distribution.';
$string['watermarkopacity'] = 'Watermark opacity (%)';
$string['watermarkopacity_help'] = 'Set the opacity of the moving watermark overlay between 10% and 100% (default: 35%).';
$string['preventseeking'] = 'Prevent forward seeking';
$string['preventseeking_help'] = 'If enabled, students cannot skip ahead or scrub forward past the furthest point they have legitimately watched.';

// Completion Rules.
$string['completionminpercent'] = 'Require minimum watch percentage';
$string['completionminpercent_help'] = 'If set (e.g. 80%), the activity will only be marked complete once the student has uniquely watched at least this percentage of the video. Scrubbing or skipping ahead without watching does not count towards completion.';
$string['completionminpercent_desc'] = 'Student must watch at least {$a}% of the video';

// Player UI Strings.
$string['resumeprompt'] = 'You previously watched up to {$a}. Would you like to resume?';
$string['resume'] = 'Resume';
$string['startover'] = 'Start Over';
$string['watchprogress'] = 'Watched: {$a->percent}% (Target: {$a->min}%)';
$string['watchprogress_no_min'] = 'Watched: {$a}%';
$string['novideowarning'] = 'No valid video has been linked to this activity yet.';
$string['videonotready'] = 'This video is currently being transcoded and encrypted. Please check back in a few moments.';
$string['tamperwarning'] = 'Security warning: Watermark tampering detected. Video playback has been paused.';
$string['play'] = 'Play';
$string['pause'] = 'Pause';
$string['mute'] = 'Mute';
$string['unmute'] = 'Unmute';
$string['fullscreen'] = 'Fullscreen';
$string['exitfullscreen'] = 'Exit Fullscreen';
$string['speed'] = 'Speed';
$string['quality'] = 'Quality';
$string['auto'] = 'Auto';
$string['seekinglocked'] = 'Forward seeking is disabled for this activity.';

// Teacher Report.
$string['report'] = 'Watch Progress Report';
$string['report_title'] = 'Student Watch Progress Report';
$string['report_summary_total'] = 'Enrolled Students';
$string['report_summary_completed'] = 'Completed';
$string['report_summary_avg'] = 'Average Watch Progress';
$string['student'] = 'Student';
$string['percentcompleted'] = 'Progress';
$string['watchedtime'] = 'Unique Time Watched';
$string['lastposition'] = 'Last Position';
$string['status'] = 'Status';
$string['completed'] = 'Completed';
$string['in_progress'] = 'In Progress';
$string['notstarted'] = 'Not Started';
$string['backtovideo'] = 'Back to Video';
$string['nostudentsfound'] = 'No enrolled students found in this course.';

// Privacy API.
$string['privacy:metadata:securevideoplayer_progress'] = 'Stores watch progress, bookmarks, and completion tracking for each student.';
$string['privacy:metadata:securevideoplayer_progress:userid'] = 'The user ID of the student.';
$string['privacy:metadata:securevideoplayer_progress:lastposition'] = 'The last playback timestamp in seconds for resume bookmarking.';
$string['privacy:metadata:securevideoplayer_progress:maxposition'] = 'The furthest position reached in seconds for forward-seek locking.';
$string['privacy:metadata:securevideoplayer_progress:watchedseconds'] = 'Total unique seconds watched across sessions.';
$string['privacy:metadata:securevideoplayer_progress:percentcompleted'] = 'Calculated percentage of video watched.';
$string['privacy:metadata:securevideoplayer_progress:timemodified'] = 'The timestamp when progress was last recorded.';
