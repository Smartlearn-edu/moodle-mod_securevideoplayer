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

namespace mod_securevideo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use context_module;
use question_engine;
use qubaid_list;

/**
 * Privacy API provider for mod_securevideo.
 *
 * @package    mod_securevideo
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return metadata about user data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'securevideo_progress',
            [
                'userid'           => 'privacy:metadata:securevideo_progress:userid',
                'lastposition'     => 'privacy:metadata:securevideo_progress:lastposition',
                'maxposition'      => 'privacy:metadata:securevideo_progress:maxposition',
                'watchedseconds'   => 'privacy:metadata:securevideo_progress:watchedseconds',
                'percentcompleted' => 'privacy:metadata:securevideo_progress:percentcompleted',
                'timemodified'     => 'privacy:metadata:securevideo_progress:timemodified',
            ],
            'privacy:metadata:securevideo_progress'
        );

        $collection->add_database_table(
            'securevideo_qattempts',
            [
                'userid'        => 'privacy:metadata:securevideo_qattempts:userid',
                'svquestionid'  => 'privacy:metadata:securevideo_qattempts:svquestionid',
                'passed'        => 'privacy:metadata:securevideo_qattempts:passed',
                'attempts'      => 'privacy:metadata:securevideo_qattempts:attempts',
                'fraction'      => 'privacy:metadata:securevideo_qattempts:fraction',
                'timemodified'  => 'privacy:metadata:securevideo_qattempts:timemodified',
            ],
            'privacy:metadata:securevideo_qattempts'
        );

        $collection->link_subsystem('core_question', 'privacy:metadata:core_question');

        return $collection;
    }

    /**
     * Get the list of contexts where user has data.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {securevideo} p ON p.id = cm.instance
             LEFT JOIN {securevideo_progress} prog ON prog.instanceid = p.id AND prog.userid = :userid1
             LEFT JOIN {securevideo_qattempts} qatt ON qatt.instanceid = p.id AND qatt.userid = :userid2
                 WHERE prog.id IS NOT NULL OR qatt.id IS NOT NULL";

        $contextlist->add_from_sql($sql, [
            'modname' => 'securevideo',
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $sqlprog = "SELECT prog.userid
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {securevideo} p ON p.id = cm.instance
                      JOIN {securevideo_progress} prog ON prog.instanceid = p.id
                     WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sqlprog, [
            'modname' => 'securevideo',
            'cmid'    => $context->instanceid,
        ]);

        $sqlq = "SELECT qatt.userid
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   JOIN {securevideo} p ON p.id = cm.instance
                   JOIN {securevideo_qattempts} qatt ON qatt.instanceid = p.id
                  WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sqlq, [
            'modname' => 'securevideo',
            'cmid'    => $context->instanceid,
        ]);
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('securevideo', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $progress = $DB->get_record('securevideo_progress', [
                'instanceid' => $cm->instance,
                'userid'     => $user->id,
            ]);

            if ($progress) {
                $data = (object)[
                    'lastposition'     => $progress->lastposition,
                    'maxposition'      => $progress->maxposition,
                    'watchedseconds'   => $progress->watchedseconds,
                    'percentcompleted' => $progress->percentcompleted,
                    'timemodified'     => transform::datetime($progress->timemodified),
                ];

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_securevideo'), 'progress'],
                    $data
                );
            }

            // Export question attempts.
            $attempts = $DB->get_records('securevideo_qattempts', [
                'instanceid' => $cm->instance,
                'userid'     => $user->id,
            ]);

            if ($attempts) {
                $attemptdata = [];
                foreach ($attempts as $attempt) {
                    $attemptdata[] = (object)[
                        'svquestionid' => $attempt->svquestionid,
                        'passed'       => transform::yesno($attempt->passed),
                        'attempts'     => $attempt->attempts,
                        'fraction'     => $attempt->fraction,
                        'timemodified' => transform::datetime($attempt->timemodified),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_securevideo'), 'question_attempts'],
                    (object)['attempts' => $attemptdata]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('securevideo', $context->instanceid);
        if ($cm) {
            $DB->delete_records('securevideo_progress', ['instanceid' => $cm->instance]);

            $qubaids = $DB->get_fieldset_select(
                'securevideo_qattempts',
                'qubaid',
                'instanceid = :instanceid AND qubaid > 0',
                ['instanceid' => $cm->instance]
            );

            if (!empty($qubaids)) {
                question_engine::delete_questions_usage_by_activities(new qubaid_list($qubaids));
            }

            $DB->delete_records('securevideo_qattempts', ['instanceid' => $cm->instance]);
        }
    }

    /**
     * Delete user data for a specific user in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('securevideo', $context->instanceid);
            if ($cm) {
                $DB->delete_records('securevideo_progress', [
                    'instanceid' => $cm->instance,
                    'userid'     => $user->id,
                ]);

                $qubaids = $DB->get_fieldset_select(
                    'securevideo_qattempts',
                    'qubaid',
                    'instanceid = :instanceid AND userid = :userid AND qubaid > 0',
                    ['instanceid' => $cm->instance, 'userid' => $user->id]
                );

                if (!empty($qubaids)) {
                    question_engine::delete_questions_usage_by_activities(new qubaid_list($qubaids));
                }

                $DB->delete_records('securevideo_qattempts', [
                    'instanceid' => $cm->instance,
                    'userid'     => $user->id,
                ]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('securevideo', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['instanceid'] = $cm->instance;

        $DB->delete_records_select(
            'securevideo_progress',
            "instanceid = :instanceid AND userid $insql",
            $params
        );

        $qubaids = $DB->get_fieldset_select(
            'securevideo_qattempts',
            'qubaid',
            "instanceid = :instanceid AND userid $insql AND qubaid > 0",
            $params
        );

        if (!empty($qubaids)) {
            question_engine::delete_questions_usage_by_activities(new qubaid_list($qubaids));
        }

        $DB->delete_records_select(
            'securevideo_qattempts',
            "instanceid = :instanceid AND userid $insql",
            $params
        );
    }
}
