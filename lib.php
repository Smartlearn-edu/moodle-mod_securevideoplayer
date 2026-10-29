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
 * Standard library callbacks and lifecycle hooks for mod_securevideoplayer.
 *
 * @package    mod_securevideoplayer
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in mod_securevideoplayer.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function securevideoplayer_supports(string $feature): ?bool {
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
 * Add a new securevideoplayer instance.
 *
 * @param stdClass $data Post data from the form.
 * @param mod_securevideoplayer_mod_form|null $mform The form object.
 * @return int The ID of the newly created instance.
 */
function securevideoplayer_add_instance(stdClass $data, ?mod_securevideoplayer_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->watermarkenabled = !empty($data->watermarkenabled) ? 1 : 0;
    $data->watermarkopacity = isset($data->watermarkopacity) ? (int)$data->watermarkopacity : 35;
    $data->preventseeking   = !empty($data->preventseeking) ? 1 : 0;
    $data->completionminpercent = isset($data->completionminpercent) ? (int)$data->completionminpercent : 0;

    return $DB->insert_record('securevideoplayer', $data);
}

/**
 * Update an existing securevideoplayer instance.
 *
 * @param stdClass $data Post data from the form.
 * @param mod_securevideoplayer_mod_form|null $mform The form object.
 * @return bool True on success.
 */
function securevideoplayer_update_instance(stdClass $data, ?mod_securevideoplayer_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $data->watermarkenabled = !empty($data->watermarkenabled) ? 1 : 0;
    $data->watermarkopacity = isset($data->watermarkopacity) ? (int)$data->watermarkopacity : 35;
    $data->preventseeking   = !empty($data->preventseeking) ? 1 : 0;
    $data->completionminpercent = isset($data->completionminpercent) ? (int)$data->completionminpercent : 0;

    return $DB->update_record('securevideoplayer', $data);
}

/**
 * Delete an existing securevideoplayer instance and associated student progress.
 *
 * @param int $id The instance ID.
 * @return bool True on success.
 */
function securevideoplayer_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('securevideoplayer', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('securevideoplayer_progress', ['instanceid' => $id]);
    $DB->delete_records('securevideoplayer', ['id' => $id]);

    return true;
}

/**
 * Extends the settings navigation to add the Watch Progress Report node for teachers.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param navigation_node|null $navref The current node reference.
 * @return void
 */
function securevideoplayer_extend_settings_navigation(
    settings_navigation $settingsnav,
    ?navigation_node $navref = null
): void {
    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'securevideoplayer' || !$navref) {
        return;
    }

    $context = context_module::instance($cm->id);
    if (has_capability('mod/securevideoplayer:viewreports', $context)) {
        $reporturl = new moodle_url('/mod/securevideoplayer/report.php', ['id' => $cm->id]);
        $navref->add(
            get_string('report', 'mod_securevideoplayer'),
            $reporturl,
            navigation_node::TYPE_SETTING,
            null,
            'securevideoplayer_report',
            new pix_icon('i/report', '')
        );
    }
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
function securevideoplayer_get_completion_state(stdClass $course, $cm, int $userid, bool $type): bool {
    global $DB;

    $instance = $DB->get_record('securevideoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

    if (empty($instance->completionminpercent)) {
        return true;
    }

    $progress = $DB->get_record('securevideoplayer_progress', [
        'instanceid' => $instance->id,
        'userid'     => $userid,
    ]);

    if (!$progress) {
        return false;
    }

    return ($progress->percentcompleted >= $instance->completionminpercent);
}
