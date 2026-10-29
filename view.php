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
 * Course activity player view controller for mod_securevideoplayer.
 *
 * @package    mod_securevideoplayer
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$s  = optional_param('s', 0, PARAM_INT);  // securevideoplayer instance ID.

if ($id) {
    $cm = get_coursemodule_from_id('securevideoplayer', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $securevideoplayer = $DB->get_record('securevideoplayer', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($s) {
    $securevideoplayer = $DB->get_record('securevideoplayer', ['id' => $s], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $securevideoplayer->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('securevideoplayer', $securevideoplayer->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/securevideoplayer:view', $context);

// Trigger course_module_viewed event.
\mod_securevideoplayer\event\course_module_viewed::create_from_cm($cm)->trigger();

// Completion view tracking.
$completion = new \completion_info($course);
$completion->set_module_viewed($cm);

$pageurl = new moodle_url('/mod/securevideoplayer/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title($securevideoplayer->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_activity_record($securevideoplayer);

// Fetch linked media record from central library.
$media = null;
if (!empty($securevideoplayer->mediaid)) {
    $media = $DB->get_record('local_securevideolibrary_media', ['id' => $securevideoplayer->mediaid]);
}

echo $OUTPUT->header();

$intro = format_module_intro('securevideoplayer', $securevideoplayer, $cm->id);
if (!empty($intro)) {
    echo $intro;
}

if (!$media || $media->status !== 'ready') {
    if ($media && ($media->status === 'queued' || $media->status === 'processing')) {
        echo $OUTPUT->notification(get_string('videonotready', 'mod_securevideoplayer'), 'info');
    } else {
        echo $OUTPUT->notification(get_string('novideowarning', 'mod_securevideoplayer'), 'warning');
    }
} else {
    // Fetch user progress.
    $progress = $DB->get_record('securevideoplayer_progress', [
        'instanceid' => $securevideoplayer->id,
        'userid'     => $USER->id,
    ]);

    $playlisturl = (new moodle_url('/local/securevideolibrary/serve.php', [
        'mediaid' => $media->id,
        'file'    => 'playlist.m3u8',
    ]))->out(false);

    $thumbnailurl = (new moodle_url('/local/securevideolibrary/serve.php', [
        'mediaid' => $media->id,
        'file'    => 'thumbnail.jpg',
    ]))->out(false);

    $mins = floor($media->duration / 60);
    $secs = $media->duration % 60;
    $durationformatted = sprintf('%02d:%02d', $mins, $secs);

    $lastposition = $progress ? (int)$progress->lastposition : 0;
    $lastposmins = floor($lastposition / 60);
    $lastpossecs = $lastposition % 60;
    $lastposformatted = sprintf('%02d:%02d', $lastposmins, $lastpossecs);

    $percentcompleted = $progress ? (int)$progress->percentcompleted : 0;

    $playerdata = [
        'cmid'                 => (int)$cm->id,
        'title'                => $securevideoplayer->name,
        'playlisturl'          => $playlisturl,
        'thumbnailurl'         => $thumbnailurl,
        'duration'             => (int)$media->duration,
        'durationformatted'    => $durationformatted,
        'hasresume'            => ($lastposition > 15 && $lastposition < ($media->duration - 15)),
        'lastposition'         => $lastposition,
        'lastposformatted'     => $lastposformatted,
        'maxposition'          => $progress ? (int)$progress->maxposition : 0,
        'watchedseconds'       => $progress ? (int)$progress->watchedseconds : 0,
        'watchedintervals'     => $progress ? $progress->watchedintervals : '[]',
        'percentcompleted'     => $percentcompleted,
        'completionminpercent' => (int)$securevideoplayer->completionminpercent,
        'hascompletionrule'    => (!empty($securevideoplayer->completionminpercent)),
        'preventseeking'       => !empty($securevideoplayer->preventseeking),
        'watermarkenabled'     => !empty($securevideoplayer->watermarkenabled),
        'watermarkopacity'     => (float)($securevideoplayer->watermarkopacity / 100),
        'studentname'          => fullname($USER),
        'studentid'            => (int)$USER->id,
        'studentip'            => getremoteaddr(),
        'livetime'             => userdate(time(), get_string('strftimedatetimeshort', 'langconfig')),
    ];

    echo $OUTPUT->render_from_template('mod_securevideoplayer/player', $playerdata);
}

echo $OUTPUT->footer();
