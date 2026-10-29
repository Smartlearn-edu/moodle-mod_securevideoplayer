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
 * Watch progress report for mod_securevideoplayer.
 *
 * @package    mod_securevideoplayer
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'securevideoplayer');
$instance = $DB->get_record('securevideoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/securevideoplayer:viewreports', $context);

$PAGE->set_url('/mod/securevideoplayer/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('report', 'mod_securevideoplayer'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Video details.
$media = null;
if (!empty($instance->mediaid)) {
    $media = $DB->get_record('local_securevideolibrary_media', ['id' => $instance->mediaid]);
}

$duration = (int)($media->duration ?? 0);
$durmins = floor($duration / 60);
$dursecs = $duration % 60;
$durationformatted = sprintf('%02d:%02d', $durmins, $dursecs);

// Enrolled users.
$users = get_enrolled_users($context, 'mod/securevideoplayer:view', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');

// Watch progress records indexed by userid.
$progressrecords = $DB->get_records(
    'securevideoplayer_progress',
    ['instanceid' => $instance->id],
    '',
    'userid, watchedseconds, percentcompleted, lastposition, completed, timemodified'
);

$studentsdata = [];
$totalenrolled = count($users);
$totalcompleted = 0;
$totalpercent = 0;

foreach ($users as $user) {
    $userpicture = $OUTPUT->user_picture($user, ['size' => 35]);
    $userfullname = fullname($user);
    $userprofileurl = (new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]))->out(false);

    $prog = $progressrecords[$user->id] ?? null;

    if ($prog) {
        $percent = (int)$prog->percentcompleted;
        $watchedsec = (int)$prog->watchedseconds;
        $lastpossec = (int)$prog->lastposition;
        $iscompleted = !empty($prog->completed);

        if ($iscompleted) {
            $totalcompleted++;
            $statusclass = 'badge bg-success';
            $statustext = get_string('completed', 'mod_securevideoplayer');
        } else if ($watchedsec > 0) {
            $statusclass = 'badge bg-primary';
            $statustext = get_string('in_progress', 'mod_securevideoplayer');
        } else {
            $statusclass = 'badge bg-secondary';
            $statustext = get_string('notstarted', 'mod_securevideoplayer');
        }

        $watchedformatted = sprintf('%02d:%02d', floor($watchedsec / 60), $watchedsec % 60);
        $lastposformatted = sprintf('%02d:%02d', floor($lastpossec / 60), $lastpossec % 60);
        $lastupdated = !empty($prog->timemodified) ?
            userdate($prog->timemodified, get_string('strftimedatetime', 'langconfig')) : '-';
    } else {
        $percent = 0;
        $statusclass = 'badge bg-secondary';
        $statustext = get_string('notstarted', 'mod_securevideoplayer');
        $watchedformatted = '00:00';
        $lastposformatted = '00:00';
        $lastupdated = '-';
    }

    $totalpercent += $percent;

    $studentsdata[] = [
        'userid' => $user->id,
        'userfullname' => $userfullname,
        'useremail' => $user->email,
        'userpicture' => $userpicture,
        'userprofileurl' => $userprofileurl,
        'percent' => $percent,
        'watchedformatted' => $watchedformatted,
        'lastposformatted' => $lastposformatted,
        'statusclass' => $statusclass,
        'statustext' => $statustext,
        'lastupdated' => $lastupdated,
    ];
}

$avgpercent = $totalenrolled > 0 ? (int)round($totalpercent / $totalenrolled) : 0;

$data = [
    'instancename' => format_string($instance->name),
    'videotitle' => $media ? format_string($media->title) : '',
    'durationformatted' => $durationformatted,
    'totalenrolled' => $totalenrolled,
    'totalcompleted' => $totalcompleted,
    'avgpercent' => $avgpercent,
    'hasstudents' => !empty($studentsdata),
    'students' => $studentsdata,
    'backurl' => (new moodle_url('/mod/securevideoplayer/view.php', ['id' => $cm->id]))->out(false),
    'completionminpercent' => (int)($instance->completionminpercent ?? 0),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_securevideoplayer/report', $data);
echo $OUTPUT->footer();
