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

namespace local_adminreport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return metadata about user data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The modified collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_adminreport_run_trainers',
            [
                'run_id'       => 'privacy:metadata:run_trainers:run_id',
                'userid'       => 'privacy:metadata:run_trainers:userid',
                'trainer_name' => 'privacy:metadata:run_trainers:trainer_name',
                'is_primary'   => 'privacy:metadata:run_trainers:is_primary',
            ],
            'privacy:metadata:run_trainers'
        );

        $collection->add_database_table(
            'local_adminreport_scope',
            [
                'scope_type'    => 'privacy:metadata:scope:scope_type',
                'scope_id'      => 'privacy:metadata:scope:scope_id',
                'dim_member_id' => 'privacy:metadata:scope:dim_member_id',
                'timemodified'  => 'privacy:metadata:scope:timemodified',
            ],
            'privacy:metadata:scope'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to find contexts for.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (EXISTS (SELECT 1 FROM {local_adminreport_run_trainers} t WHERE t.userid = :userid1)
                    OR  EXISTS (SELECT 1 FROM {local_adminreport_scope} s WHERE s.scope_type = 'user' AND s.scope_id = :userid2))";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1'      => $userid,
            'userid2'      => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid FROM {local_adminreport_run_trainers} WHERE userid IS NOT NULL";
        $userlist->add_from_sql('userid', $sql, []);

        $sql2 = "SELECT scope_id AS userid FROM {local_adminreport_scope} WHERE scope_type = 'user'";
        $userlist->add_from_sql('userid', $sql2, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The list of contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $syscontext = \context_system::instance();

        // Export trainer assignments.
        $trainerrecords = $DB->get_records('local_adminreport_run_trainers', ['userid' => $user->id]);
        if (!empty($trainerrecords)) {
            $data = [];
            foreach ($trainerrecords as $record) {
                $data[] = [
                    'run_id'       => $record->run_id,
                    'trainer_name' => $record->trainer_name,
                    'is_primary'   => $record->is_primary ? get_string('yes') : get_string('no'),
                ];
            }
            writer::with_context($syscontext)->export_data(
                [get_string('pluginname', 'local_adminreport'), get_string('trainer_assignments', 'local_adminreport')],
                (object) ['trainers' => $data]
            );
        }

        // Export scoping rules.
        $scoperecords = $DB->get_records('local_adminreport_scope', [
            'scope_type' => 'user',
            'scope_id'   => $user->id,
        ]);
        if (!empty($scoperecords)) {
            $data = [];
            foreach ($scoperecords as $record) {
                $data[] = [
                    'dim_member_id' => $record->dim_member_id,
                    'timemodified'  => \core_date::userdate($record->timemodified),
                ];
            }
            writer::with_context($syscontext)->export_data(
                [get_string('pluginname', 'local_adminreport'), get_string('scoping_rules', 'local_adminreport')],
                (object) ['scopes' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Anonymize user reference in trainer table.
        $DB->execute("UPDATE {local_adminreport_run_trainers} SET userid = NULL WHERE userid IS NOT NULL");

        // Delete user scopes.
        $DB->delete_records('local_adminreport_scope', ['scope_type' => 'user']);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The list of contexts to delete data for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        // Anonymize user reference in trainer table.
        $DB->execute(
            "UPDATE {local_adminreport_run_trainers} SET userid = NULL WHERE userid = :userid",
            ['userid' => $user->id]
        );

        // Delete user scopes.
        $DB->delete_records('local_adminreport_scope', [
            'scope_type' => 'user',
            'scope_id'   => $user->id,
        ]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->execute("UPDATE {local_adminreport_run_trainers} SET userid = NULL WHERE userid {$insql}", $inparams);

        $inparams['scopetype'] = 'user';
        $DB->execute("DELETE FROM {local_adminreport_scope} WHERE scope_type = :scopetype AND scope_id {$insql}", $inparams);
    }
}
