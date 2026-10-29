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
 * Define all the restore steps for mod_securevideo.
 *
 * @package    mod_securevideo
 * @category   backup
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one securevideo activity.
 */
class restore_securevideo_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure of the restore step.
     *
     * @return array
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('securevideo', '/activity/securevideo');
        $paths[] = new restore_path_element('securevideo_question', '/activity/securevideo/questions/question');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a securevideo restore element.
     *
     * @param array $data The data to restore.
     * @return void
     */
    protected function process_securevideo(array $data): void {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('securevideo', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a question cuepoint restore element.
     *
     * @param array $data
     * @return void
     */
    protected function process_securevideo_question(array $data): void {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('securevideo');

        // Remap question ID if question was restored or exists.
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
        }

        $DB->insert_record('securevideo_questions', $data);
    }

    /**
     * Post-execution actions.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_securevideo', 'intro', null);
    }
}
