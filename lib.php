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
 * Standard library callbacks and lifecycle hooks for mod_securevideo.
 *
 * @package    mod_securevideo
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in mod_securevideo.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function securevideo_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Add a new securevideo instance.
 *
 * @param stdClass $data Post data from the form.
 * @param mod_securevideo_mod_form|null $mform The form object.
 * @return int The ID of the newly created instance.
 */
function securevideo_add_instance(stdClass $data, ?mod_securevideo_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->watermarkenabled = !empty($data->watermarkenabled) ? 1 : 0;
    $data->watermarkopacity = isset($data->watermarkopacity) ? (int)$data->watermarkopacity : 35;
    $data->preventseeking   = !empty($data->preventseeking) ? 1 : 0;
    $data->completionminpercent = isset($data->completionminpercent) ? (int)$data->completionminpercent : 0;
    $data->completionrequirequestions = !empty($data->completionrequirequestions) ? 1 : 0;

    return $DB->insert_record('securevideo', $data);
}

/**
 * Update an existing securevideo instance.
 *
 * @param stdClass $data Post data from the form.
 * @param mod_securevideo_mod_form|null $mform The form object.
 * @return bool True on success.
 */
function securevideo_update_instance(stdClass $data, ?mod_securevideo_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $data->watermarkenabled = !empty($data->watermarkenabled) ? 1 : 0;
    $data->watermarkopacity = isset($data->watermarkopacity) ? (int)$data->watermarkopacity : 35;
    $data->preventseeking   = !empty($data->preventseeking) ? 1 : 0;
    $data->completionminpercent = isset($data->completionminpercent) ? (int)$data->completionminpercent : 0;
    $data->completionrequirequestions = !empty($data->completionrequirequestions) ? 1 : 0;

    return $DB->update_record('securevideo', $data);
}

/**
 * Delete an existing securevideo instance and associated student progress.
 *
 * @param int $id The instance ID.
 * @return bool True on success.
 */
function securevideo_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('securevideo', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('securevideo_progress', ['instanceid' => $id]);

    // Clean up Question Engine usages.
    $qubaids = $DB->get_fieldset_select(
        'securevideo_qattempts',
        'qubaid',
        'instanceid = :instanceid AND qubaid > 0',
        ['instanceid' => $id]
    );

    if (!empty($qubaids)) {
        \question_engine::delete_questions_usage_by_activities(new \qubaid_list($qubaids));
    }

    $DB->delete_records('securevideo_qattempts', ['instanceid' => $id]);
    $DB->delete_records('securevideo_questions', ['instanceid' => $id]);
    $DB->delete_records('securevideo', ['id' => $id]);

    return true;
}

/**
 * Extends the settings navigation to add Interactive Questions and Report tabs for teachers.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param navigation_node|null $navref The current node reference.
 * @return void
 */
function securevideo_extend_settings_navigation(
    settings_navigation $settingsnav,
    ?navigation_node $navref = null
): void {
    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'securevideo' || !$navref) {
        return;
    }

    $context = context_module::instance($cm->id);

    // Interactive Questions tab.
    if (has_capability('moodle/course:manageactivities', $context) || has_capability('mod/securevideo:addinstance', $context)) {
        $questionsurl = new moodle_url('/mod/securevideo/questions.php', ['id' => $cm->id]);
        $navref->add(
            get_string('interactivequestions', 'mod_securevideo'),
            $questionsurl,
            settings_navigation::TYPE_SETTING,
            null,
            'securevideo_questions',
            new pix_icon('i/questions', '')
        );
    }

    // Watch Progress Report tab.
    if (has_capability('mod/securevideo:viewreports', $context)) {
        $reporturl = new moodle_url('/mod/securevideo/report.php', ['id' => $cm->id]);
        $navref->add(
            get_string('report', 'mod_securevideo'),
            $reporturl,
            settings_navigation::TYPE_SETTING,
            null,
            'securevideo_report',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Serve files embedded in question text, feedback, or choices.
 *
 * @param stdClass $course Course object.
 * @param stdClass $context Context object.
 * @param string $component Component name (mod_securevideo).
 * @param string $filearea File area name.
 * @param int $qubaid Question usage ID.
 * @param int $slot Question slot number.
 * @param array $args Extra file path arguments.
 * @param bool $forcedownload Whether forced download is requested.
 * @param array $fileoptions File options.
 * @return void
 */
function securevideo_question_pluginfile(
    $course,
    $context,
    $component,
    $filearea,
    $qubaid,
    $slot,
    $args,
    $forcedownload,
    $fileoptions
): void {
    global $DB, $USER;

    require_login($course, false);

    $attempt = $DB->get_record('securevideo_qattempts', ['qubaid' => $qubaid, 'slot' => $slot]);
    if (!$attempt) {
        send_file_not_found();
    }

    $modcontext = context_module::instance($context->instanceid);
    if ($attempt->userid != $USER->id && !has_capability('mod/securevideo:viewreports', $modcontext)) {
        send_file_not_found();
    }

    $quba = \question_engine::load_questions_usage_by_activity($qubaid);

    $options = new \question_display_options();
    $options->feedback = \question_display_options::VISIBLE;
    $options->generalfeedback = \question_display_options::VISIBLE;
    $options->rightanswer = \question_display_options::VISIBLE;

    if (!$quba->check_file_access($slot, $options, $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/{$component}/{$filearea}/{$relativepath}";
    $file = $fs->get_file_by_hash(sha1($fullpath));

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $fileoptions);
}

/**
 * Obtains the automatic completion state for this module based on custom rules.
 *
 * @param stdClass $course The course object.
 * @param cm_info|stdClass $cm The course module object.
 * @param int $userid The user ID.
 * @param bool $type The completion type.
 * @return bool True if completed, false otherwise.
 */
function securevideo_get_completion_state(stdClass $course, $cm, int $userid, bool $type): bool {
    global $DB;

    $instance = $DB->get_record('securevideo', ['id' => $cm->instance], '*', MUST_EXIST);

    // Rule 1: Watch percentage.
    if (!empty($instance->completionminpercent)) {
        $progress = $DB->get_record('securevideo_progress', [
            'instanceid' => $instance->id,
            'userid'     => $userid,
        ]);

        if (!$progress || $progress->percentcompleted < $instance->completionminpercent) {
            return false;
        }
    }

    // Rule 2: Passing all required interactive questions.
    if (!empty($instance->completionrequirequestions)) {
        $totalquestions = $DB->count_records('securevideo_questions', [
            'instanceid'     => $instance->id,
            'requirecorrect' => 1,
        ]);

        if ($totalquestions > 0) {
            $sql = "SELECT COUNT(qa.id)
                      FROM {securevideo_qattempts} qa
                      JOIN {securevideo_questions} sq ON sq.id = qa.svquestionid
                     WHERE qa.instanceid = :instanceid
                       AND qa.userid = :userid
                       AND sq.requirecorrect = 1
                       AND qa.passed = 1";

            $passed = $DB->count_records_sql($sql, [
                'instanceid' => $instance->id,
                'userid'     => $userid,
            ]);

            if ($passed < $totalquestions) {
                return false;
            }
        }
    }

    return true;
}
