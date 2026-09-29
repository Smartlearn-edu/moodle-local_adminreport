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

namespace local_adminreport\task;

use local_adminreport\analytics\dimension_manager;

/**
 * Scheduled task for auto-discovering program runs from courses.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_discover_runs extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_auto_discover_runs', 'local_adminreport');
    }

    /**
     * Execute the auto discovery task.
     */
    public function execute(): void {
        mtrace('local_adminreport: auto_discover_runs started.');
        $rootcategory = (int) get_config('local_adminreport', 'root_category');
        $count = self::discover_all_runs($rootcategory);
        mtrace("local_adminreport: auto_discover_runs completed. Discovered and registered {$count} new run(s).");
    }

    /**
     * Auto discover courses and create default runs for those not yet registered.
     *
     * @param int $rootcategory Optional root category ID to restrict discovery.
     * @return int Number of runs created.
     */
    public static function discover_all_runs(int $rootcategory = 0): int {
        global $DB;

        // Ensure default dimension types and members exist before resolving.
        dimension_manager::ensure_default_dimension_types();

        $params = ['siteid' => SITEID];
        $categorywhere = '';

        if ($rootcategory > 0) {
            // Find root category and all its subcategories.
            $catids = [$rootcategory];
            $subcats = $DB->get_records_sql(
                "SELECT id, path FROM {course_categories} WHERE path LIKE :catpath OR id = :catid",
                ['catpath' => '%/' . $rootcategory . '/%', 'catid' => $rootcategory]
            );
            foreach ($subcats as $sc) {
                $catids[] = (int) $sc->id;
            }
            $catids = array_unique($catids);
            list($incatsql, $catparams) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
            $categorywhere = " AND c.category $incatsql";
            $params = array_merge($params, $catparams);
        }

        // Query active courses that do not yet have a base run record (groupid = 0).
        $sql = "SELECT c.id, c.category, c.fullname, c.shortname, c.idnumber, c.startdate, c.enddate, c.visible
                  FROM {course} c
             LEFT JOIN {local_adminreport_runs} r ON r.courseid = c.id AND r.groupid = 0
                 WHERE c.id <> :siteid
                   AND c.format <> 'site'
                   AND r.id IS NULL
                   {$categorywhere}
              ORDER BY c.startdate DESC, c.id DESC";

        $courses = $DB->get_records_sql($sql, $params);
        if (empty($courses)) {
            return 0;
        }

        $created = 0;
        $now = time();

        // Cache teacher role IDs.
        $teacherroleids = $DB->get_fieldset_select('role', 'id', "archetype IN ('editingteacher', 'teacher')");

        foreach ($courses as $course) {
            // 1. Resolve run schedule dates.
            $startdate = (int) $course->startdate;
            if ($startdate <= 0) {
                $startdate = $now;
            }

            $enddate = (int) $course->enddate;
            if ($enddate <= 0 || $enddate < $startdate) {
                // Default to standard 5-day training duration (Sunday to Thursday).
                $enddate = $startdate + (4 * 86400);
            }

            // 2. Generate run code.
            $runcode = !empty(trim($course->idnumber)) ? trim($course->idnumber) : ('C' . $course->id);

            // Ensure uniqueness of run_code if another run already has it.
            if ($DB->record_exists('local_adminreport_runs', ['run_code' => $runcode])) {
                $runcode .= '-' . $course->id;
            }

            // 3. Resolve dimensions from category, course metadata, and name patterns.
            $dims = dimension_manager::resolve_course_dimensions($course->id);

            $runrecord = (object) [
                'courseid'           => $course->id,
                'groupid'            => 0,
                'run_code'           => $runcode,
                'startdate'          => $startdate,
                'enddate'            => $enddate,
                'org_dim_id'         => $dims->org_dim_id,
                'type_dim_id'        => $dims->type_dim_id,
                'location_dim_id'    => $dims->location_dim_id,
                'classroom'          => $dims->classroom ?: '',
                'daily_start_time'   => '08:00',
                'daily_end_time'     => '14:00',
                'break_duration_min' => 30,
                'exam_time'          => '11:00',
                'is_cancelled'       => 0,
                'source'             => 'auto',
                'timemodified'       => $now,
            ];

            $runid = $DB->insert_record('local_adminreport_runs', $runrecord);

            // 4. Discover assigned teachers/trainers.
            if (!empty($teacherroleids)) {
                $context = \context_course::instance($course->id, IGNORE_MISSING);
                if ($context) {
                    list($rolesql, $roleparams) = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'r');
                    $tparams = array_merge(['contextid' => $context->id], $roleparams);
                    $trainersql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                                     FROM {role_assignments} ra
                                     JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                                    WHERE ra.contextid = :contextid
                                      AND ra.roleid $rolesql
                                 ORDER BY ra.id ASC";
                    $trainers = $DB->get_records_sql($trainersql, $tparams);

                    $isprimary = 1;
                    foreach ($trainers as $trainer) {
                        $fullname = fullname($trainer);
                        $DB->insert_record('local_adminreport_run_trainers', (object) [
                            'run_id'       => $runid,
                            'userid'       => $trainer->id,
                            'trainer_name' => $fullname,
                            'is_primary'   => $isprimary,
                        ]);
                        $isprimary = 0; // Only first teacher is primary.
                    }
                }
            }

            $created++;
        }

        return $created;
    }
}
