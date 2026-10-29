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
 * Defines backup_securevideo_activity_task class.
 *
 * @package    mod_securevideo
 * @category   backup
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/securevideo/backup/moodle2/backup_securevideo_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the securevideo instance.
 */
class backup_securevideo_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines a backup step to store the instance data in the securevideo.xml file.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_securevideo_activity_structure_step(
            'securevideo_structure',
            'securevideo.xml'
        ));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of securevideos.
        $search = '/(' . $base . '\/mod\/securevideo\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@SECUREVIDEOINDEX*$2@$', $content);

        // Link to securevideo view by moduleid.
        $search = '/(' . $base . '\/mod\/securevideo\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@SECUREVIDEOVIEWBYID*$2@$', $content);

        return $content;
    }
}
