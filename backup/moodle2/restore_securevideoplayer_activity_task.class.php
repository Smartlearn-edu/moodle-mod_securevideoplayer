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
 * Defines restore_securevideoplayer_activity_task class.
 *
 * @package    mod_securevideoplayer
 * @category   backup
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/securevideoplayer/backup/moodle2/restore_securevideoplayer_stepslib.php');

/**
 * Provides the steps to perform one complete restore of the securevideoplayer instance.
 */
class restore_securevideoplayer_activity_task extends restore_activity_task {
    /**
     * Define the settings for this activity.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define the steps for this activity.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_securevideoplayer_activity_structure_step(
            'securevideoplayer_structure',
            'securevideoplayer.xml'
        ));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        $contents = [];

        $contents[] = new restore_decode_content('securevideoplayer', ['intro'], 'securevideoplayer');

        return $contents;
    }

    /**
     * Define the decoding rules for links in the activity contents.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        $rules = [];

        $rules[] = new restore_decode_rule('SECUREVIDEOPLAYERVIEWBYID', '/mod/securevideoplayer/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('SECUREVIDEOPLAYERINDEX', '/mod/securevideoplayer/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        $rules = [];

        $rules[] = new restore_log_rule('securevideoplayer', 'add', 'view.php?id={id}', '{name}');
        $rules[] = new restore_log_rule('securevideoplayer', 'update', 'view.php?id={id}', '{name}');
        $rules[] = new restore_log_rule('securevideoplayer', 'view', 'view.php?id={id}', '{name}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the {@link restore_logs_processor} when restoring course logs.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course(): array {
        $rules = [];

        $rules[] = new restore_log_rule('securevideoplayer', 'view all', 'index.php?id={id}');

        return $rules;
    }
}
