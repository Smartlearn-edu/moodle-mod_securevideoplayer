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
 * Define all the backup steps for mod_securevideoplayer.
 *
 * @package    mod_securevideoplayer
 * @category   backup
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete securevideoplayer structure for backup.
 */
class backup_securevideoplayer_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure of the backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        // Define root activity element.
        $securevideoplayer = new backup_nested_element('securevideoplayer', ['id'], [
            'name',
            'intro',
            'introformat',
            'mediaid',
            'watermarkenabled',
            'watermarkopacity',
            'preventseeking',
            'completionminpercent',
            'timecreated',
            'timemodified',
        ]);

        // Sources.
        $securevideoplayer->set_source_table('securevideoplayer', ['id' => backup::VAR_ACTIVITYID]);

        // Annotations.
        $securevideoplayer->annotate_files('mod_securevideoplayer', 'intro', null);

        return $this->prepare_activity_structure($securevideoplayer);
    }
}
