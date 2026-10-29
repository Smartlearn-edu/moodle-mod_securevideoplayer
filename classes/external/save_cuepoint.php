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

namespace mod_securevideo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;
use stdClass;

/**
 * External web service to create or update an interactive question cuepoint.
 *
 * @package    mod_securevideo
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_cuepoint extends external_api {
    /**
     * Parameter definitions.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'                => new external_value(PARAM_INT, 'Course module ID'),
            'cueid'               => new external_value(PARAM_INT, 'Cuepoint ID (0 to create)', VALUE_DEFAULT, 0),
            'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID', VALUE_DEFAULT, 0),
            'questionid'          => new external_value(PARAM_INT, 'Question ID'),
            'timecode'            => new external_value(PARAM_INT, 'Timecode in seconds'),
            'title'               => new external_value(PARAM_TEXT, 'Cuepoint title / label', VALUE_DEFAULT, ''),
            'requirecorrect'      => new external_value(PARAM_INT, '1 = require correct answer', VALUE_DEFAULT, 1),
            'rewindonfail'        => new external_value(PARAM_INT, 'Seconds to rewind on failure', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute save.
     *
     * @param int $cmid Course module ID.
     * @param int $cueid Existing cuepoint ID or 0.
     * @param int $questionbankentryid Question bank entry ID.
     * @param int $questionid Question ID.
     * @param int $timecode Playback timestamp in seconds.
     * @param string $title Title or label.
     * @param int $requirecorrect 1 or 0.
     * @param int $rewindonfail Seconds to rewind.
     * @return array
     */
    public static function execute(
        int $cmid,
        int $cueid = 0,
        int $questionbankentryid = 0,
        int $questionid = 0,
        int $timecode = 0,
        string $title = '',
        int $requirecorrect = 1,
        int $rewindonfail = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'                => $cmid,
            'cueid'               => $cueid,
            'questionbankentryid' => $questionbankentryid,
            'questionid'          => $questionid,
            'timecode'            => $timecode,
            'title'               => $title,
            'requirecorrect'      => $requirecorrect,
            'rewindonfail'        => $rewindonfail,
        ]);

        $cm = get_coursemodule_from_id('securevideo', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (
            !has_capability('moodle/course:manageactivities', $context)
            && !has_capability('mod/securevideo:addinstance', $context)
        ) {
            throw new \moodle_exception('nopermissions', 'error', '', 'manage questions');
        }

        $instance = $DB->get_record('securevideo', ['id' => $cm->instance], '*', MUST_EXIST);

        // Resolve question bank entry ID if missing.
        $qbeid = $params['questionbankentryid'];
        if ($qbeid <= 0 && $params['questionid'] > 0) {
            $qbeid = (int)$DB->get_field_sql(
                "SELECT qv.questionbankentryid
                   FROM {question_versions} qv
                  WHERE qv.questionid = :qid",
                ['qid' => $params['questionid']],
                IGNORE_MULTIPLE
            );
        }

        $now = time();
        if ($params['cueid'] > 0) {
            $cue = $DB->get_record('securevideo_questions', [
                'id'         => $params['cueid'],
                'instanceid' => $instance->id,
            ], '*', MUST_EXIST);

            $cue->questionbankentryid = $qbeid;
            $cue->questionid          = $params['questionid'];
            $cue->timecode            = max(0, $params['timecode']);
            $cue->title               = $params['title'];
            $cue->requirecorrect      = !empty($params['requirecorrect']) ? 1 : 0;
            $cue->rewindonfail        = max(0, $params['rewindonfail']);
            $cue->timemodified        = $now;

            $DB->update_record('securevideo_questions', $cue);
            $savedid = $cue->id;
        } else {
            $cue = new stdClass();
            $cue->instanceid          = $instance->id;
            $cue->questionbankentryid = $qbeid;
            $cue->questionid          = $params['questionid'];
            $cue->timecode            = max(0, $params['timecode']);
            $cue->title               = $params['title'];
            $cue->requirecorrect      = !empty($params['requirecorrect']) ? 1 : 0;
            $cue->rewindonfail        = max(0, $params['rewindonfail']);
            $cue->timecreated         = $now;
            $cue->timemodified        = $now;

            $savedid = $DB->insert_record('securevideo_questions', $cue);
        }

        return [
            'status' => true,
            'cueid'  => (int)$savedid,
        ];
    }

    /**
     * Returns structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Operation status'),
            'cueid'  => new external_value(PARAM_INT, 'Saved cuepoint ID'),
        ]);
    }
}
