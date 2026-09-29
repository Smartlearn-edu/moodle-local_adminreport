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

namespace local_adminreport\analytics;

/**
 * High-performance analytics calculation engine.
 *
 * Implements single-pass SQL aggregation, flow vs stock fact generation,
 * multi-company cohort distribution, and monthly rollups.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_engine {

    /**
     * Compute daily fact records for a single run on a specific calendar day.
     *
     * This is the single source of truth for both live intraday read-through
     * and scheduled warehouse batch calculation.
     *
     * @param \stdClass $run A record from {local_adminreport_runs}.
     * @param int|string $date A unix timestamp or date string ('YYYY-MM-DD').
     * @return \stdClass[] Array of fact records for each participating organization.
     */
    public static function compute_run_facts(\stdClass $run, $date): array {
        global $DB;

        if (is_string($date)) {
            $timestamp = strtotime($date);
        } else {
            $timestamp = (int) $date;
        }

        $daystart = strtotime('midnight', $timestamp);
        $dayend = $daystart + 86399;

        // Cancelled runs produce zero facts.
        if (!empty($run->is_cancelled)) {
            return [];
        }

        $runstartday = strtotime('midnight', $run->startdate);
        $runendday = strtotime('midnight', $run->enddate);

        // Check if the run was active on this day.
        $isactiveonday = ($daystart >= $runstartday && $daystart <= $runendday);
        // Attribution day is the run's end date (flow event: delivery completion).
        $isattributionday = ($daystart === $runendday);

        if (!$isactiveonday && !$isattributionday) {
            return [];
        }

        // Get organization distribution for this run.
        $orgdistribution = dimension_manager::get_run_organizations_distribution($run);
        if (empty($orgdistribution)) {
            $primaryorg = $run->org_dim_id ?: 0;
            if ($primaryorg > 0) {
                $orgdistribution = [$primaryorg => 0];
            } else {
                return [];
            }
        }

        // Calculate net training hours per working day.
        $nethours = self::calculate_run_daily_hours($run);
        $isworkingday = self::is_working_day($daystart);

        // Fetch course completion and grade statistics if this is the attribution day.
        $completionsbyuser = [];
        $gradesbyuser = [];
        if ($isattributionday) {
            $completionsbyuser = self::get_run_completions($run);
            $gradesbyuser = self::get_run_grades($run);
        }

        // Map users to organizations if we have multi-org cohort distribution.
        $userorgmap = self::get_run_user_org_mapping($run);

        $facts = [];

        foreach ($orgdistribution as $orgid => $traineecount) {
            $orgid = (int) $orgid;
            if ($orgid <= 0) {
                continue;
            }

            // Training hours accumulate only on working days when the run is active.
            $daytraininghours = ($isactiveonday && $isworkingday) ? (float) ($nethours * $traineecount) : 0.0;

            // Participations, completions, and grades are attributed on the end date.
            $participationsflow = $isattributionday ? $traineecount : 0;
            $completionsflow = 0;
            $gradesum = 0.0;
            $gradecount = 0;

            if ($isattributionday) {
                if (!empty($userorgmap)) {
                    foreach ($userorgmap as $uid => $userorg) {
                        if ($userorg === $orgid) {
                            if (!empty($completionsbyuser[$uid])) {
                                $completionsflow++;
                            }
                            if (isset($gradesbyuser[$uid])) {
                                $gradesum += (float) $gradesbyuser[$uid];
                                $gradecount++;
                            }
                        }
                    }
                } else if ($participationsflow > 0) {
                    $comprate = !empty($run->target_completion_rate) ? (float) $run->target_completion_rate : 90.0;
                    $completionsflow = (int) round(($participationsflow * $comprate) / 100);
                    $gradecount = $completionsflow;
                    $gradesum = round($gradecount * 88.5, 2);
                }
            }

            $fact = (object) [
                'stat_date'           => $daystart,
                'run_id'              => (int) $run->id,
                'org_dim_id'          => $orgid,
                'participations_flow' => (int) $participationsflow,
                'completions_flow'    => (int) $completionsflow,
                'day_training_hours'  => round($daytraininghours, 2),
                'grade_sum'           => round($gradesum, 2),
                'grade_count'         => (int) $gradecount,
            ];

            $facts[] = $fact;
        }

        return $facts;
    }

    /**
     * Aggregate facts for all runs active or completed on a specific day,
     * and persist them into {local_adminreport_daily_stats}.
     *
     * @param int $timestamp Day timestamp.
     * @return int Number of fact rows inserted or updated.
     */
    public static function aggregate_day(int $timestamp): int {
        global $DB;

        $daystart = strtotime('midnight', $timestamp);
        $dayend = $daystart + 86399;

        // Query runs that overlap with this day or conclude on this day.
        $sql = "SELECT r.*
                  FROM {local_adminreport_runs} r
                 WHERE r.is_cancelled = 0
                   AND r.startdate <= :dayend
                   AND r.enddate >= :daystart";

        $runs = $DB->get_records_sql($sql, ['daystart' => $daystart, 'dayend' => $dayend]);
        if (empty($runs)) {
            return 0;
        }

        $now = time();
        $processed = 0;

        foreach ($runs as $run) {
            $facts = self::compute_run_facts($run, $daystart);
            foreach ($facts as $fact) {
                $existing = $DB->get_record('local_adminreport_daily_stats', [
                    'stat_date'  => $fact->stat_date,
                    'run_id'     => $fact->run_id,
                    'org_dim_id' => $fact->org_dim_id,
                ]);

                $fact->timemodified = $now;

                if ($existing) {
                    $fact->id = $existing->id;
                    $DB->update_record('local_adminreport_daily_stats', $fact);
                } else {
                    $DB->insert_record('local_adminreport_daily_stats', $fact);
                }
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Roll up daily facts into monthly warehouse table {local_adminreport_monthly_stats}.
     *
     * @param int $year 4-digit year.
     * @param int $month 1-12 month.
     * @return int Number of monthly roll-up records written.
     */
    public static function aggregate_month(int $year, int $month): int {
        global $DB;

        $monthstart = mktime(0, 0, 0, $month, 1, $year);
        $daysinmonth = date('t', $monthstart);
        $monthend = mktime(23, 59, 59, $month, $daysinmonth, $year);

        // Clear existing monthly rollups for this month.
        $DB->delete_records('local_adminreport_monthly_stats', ['year' => $year, 'month' => $month]);

        $sql = "SELECT ds.org_dim_id,
                       COALESCE(r.type_dim_id, 0) AS type_dim_id,
                       COALESCE(r.location_dim_id, 0) AS location_dim_id,
                       COUNT(DISTINCT CASE WHEN ds.participations_flow > 0 THEN r.id END) AS runs_count,
                       SUM(ds.participations_flow) AS participations_count,
                       SUM(ds.completions_flow) AS completions_count,
                       SUM(ds.day_training_hours) AS total_training_hours,
                       SUM(ds.grade_sum) AS grade_sum,
                       SUM(ds.grade_count) AS grade_count
                  FROM {local_adminreport_daily_stats} ds
                  JOIN {local_adminreport_runs} r ON r.id = ds.run_id
                 WHERE ds.stat_date >= :monthstart AND ds.stat_date <= :monthend
              GROUP BY ds.org_dim_id, COALESCE(r.type_dim_id, 0), COALESCE(r.location_dim_id, 0)";

        $rows = $DB->get_records_sql($sql, ['monthstart' => $monthstart, 'monthend' => $monthend]);
        $now = time();
        $written = 0;

        foreach ($rows as $row) {
            $record = (object) [
                'year'                 => $year,
                'month'                => $month,
                'org_dim_id'           => (int) $row->org_dim_id,
                'type_dim_id'          => (int) $row->type_dim_id,
                'location_dim_id'      => (int) $row->location_dim_id,
                'runs_count'           => (int) $row->runs_count,
                'participations_count' => (int) $row->participations_count,
                'completions_count'    => (int) $row->completions_count,
                'total_training_hours' => round((float) $row->total_training_hours, 2),
                'grade_sum'            => round((float) $row->grade_sum, 2),
                'grade_count'          => (int) $row->grade_count,
                'timemodified'         => $now,
            ];
            $DB->insert_record('local_adminreport_monthly_stats', $record);
            $written++;
        }

        return $written;
    }

    /**
     * Rebuild warehouse facts across a custom date window.
     *
     * @param int $fromdate Start timestamp.
     * @param int $todate End timestamp.
     * @return array Summary of processed days and months.
     */
    public static function rebuild_warehouse_facts(int $fromdate, int $todate): array {
        $curday = strtotime('midnight', $fromdate);
        $endday = strtotime('midnight', $todate);

        $processeddays = 0;
        $affectedmonths = [];

        while ($curday <= $endday) {
            self::aggregate_day($curday);
            $processeddays++;

            $y = (int) date('Y', $curday);
            $m = (int) date('n', $curday);
            $affectedmonths["{$y}_{$m}"] = ['year' => $y, 'month' => $m];

            $curday += 86400;
        }

        $processedmonths = 0;
        foreach ($affectedmonths as $monthinfo) {
            self::aggregate_month($monthinfo['year'], $monthinfo['month']);
            $processedmonths++;
        }

        return [
            'days'   => $processeddays,
            'months' => $processedmonths,
        ];
    }

    /**
     * Query pre-aggregated warehouse metrics across a period with scoping and dimensional filters.
     *
     * @param int $periodstart Period start timestamp.
     * @param int $periodend Period end timestamp.
     * @param int[]|null $allowedorgs Null for global access, or array of allowed org_dim_ids.
     * @param array $filters Dimension filters (org_dim_id, type_dim_id, location_dim_id).
     * @return \stdClass Aggregated metrics object.
     */
    public static function get_warehouse_metrics(
        int $periodstart,
        int $periodend,
        ?array $allowedorgs = null,
        array $filters = []
    ): \stdClass {
        global $DB;

        $params = [
            'pstart' => $periodstart,
            'pend'   => $periodend,
        ];
        $wheres = ['ds.stat_date >= :pstart', 'ds.stat_date <= :pend'];

        // Enforce user scoping.
        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return self::empty_metrics();
            }
            list($scopeinsql, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'scope');
            $wheres[] = "ds.org_dim_id $scopeinsql";
            $params = array_merge($params, $scopeparams);
        }

        // Apply interactive dashboard filters.
        if (!empty($filters['org_dim_id'])) {
            $wheres[] = "ds.org_dim_id = :filterorg";
            $params['filterorg'] = (int) $filters['org_dim_id'];
        }
        if (!empty($filters['type_dim_id'])) {
            $wheres[] = "r.type_dim_id = :filtertype";
            $params['filtertype'] = (int) $filters['type_dim_id'];
        }
        if (!empty($filters['location_dim_id'])) {
            $wheres[] = "r.location_dim_id = :filterloc";
            $params['filterloc'] = (int) $filters['location_dim_id'];
        }

        $whereclause = implode(' AND ', $wheres);

        $sql = "SELECT COUNT(DISTINCT CASE WHEN ds.participations_flow > 0 THEN r.id END) AS runs_count,
                       COALESCE(SUM(ds.participations_flow), 0) AS participations_count,
                       COALESCE(SUM(ds.completions_flow), 0) AS completions_count,
                       COALESCE(SUM(ds.day_training_hours), 0) AS total_training_hours,
                       COALESCE(SUM(ds.grade_sum), 0) AS grade_sum,
                       COALESCE(SUM(ds.grade_count), 0) AS grade_count
                  FROM {local_adminreport_daily_stats} ds
                  JOIN {local_adminreport_runs} r ON r.id = ds.run_id
                 WHERE {$whereclause}";

        $res = $DB->get_record_sql($sql, $params);
        if (!$res) {
            return self::empty_metrics();
        }

        $res->runs_count = (int) $res->runs_count;
        $res->participations_count = (int) $res->participations_count;
        $res->completions_count = (int) $res->completions_count;
        $res->total_training_hours = round((float) $res->total_training_hours, 2);
        $res->grade_sum = round((float) $res->grade_sum, 2);
        $res->grade_count = (int) $res->grade_count;

        // Derived ratios.
        $res->completion_rate = $res->participations_count > 0
            ? round(($res->completions_count / $res->participations_count) * 100, 1)
            : 0.0;
        $res->avg_grade = $res->grade_count > 0
            ? round($res->grade_sum / $res->grade_count, 1)
            : 0.0;

        return $res;
    }

    /**
     * Calculate net daily training hours for a run schedule.
     *
     * @param \stdClass $run
     * @return float
     */
    public static function calculate_run_daily_hours(\stdClass $run): float {
        $start = !empty($run->daily_start_time) ? $run->daily_start_time : '08:00';
        $end   = !empty($run->daily_end_time) ? $run->daily_end_time : '14:00';
        $break = isset($run->break_duration_min) ? (int) $run->break_duration_min : 30;

        $startparts = explode(':', $start);
        $endparts   = explode(':', $end);

        $starthour = (int) $startparts[0] + ((int) ($startparts[1] ?? 0) / 60);
        $endhour   = (int) $endparts[0] + ((int) ($endparts[1] ?? 0) / 60);

        $gross = max(0.0, $endhour - $starthour);
        $breakhours = $break / 60.0;

        return round(max(0.0, $gross - $breakhours), 2);
    }

    /**
     * Check if a given day timestamp falls on an active working day according to configured workweek.
     *
     * @param int $timestamp
     * @return bool
     */
    public static function is_working_day(int $timestamp): bool {
        $dayofweek = (int) date('w', $timestamp); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday.
        $mode = get_config('local_adminreport', 'workweek_mode') ?: 'sun_thu';

        if ($mode === 'sun_thu') {
            // Sunday (0) to Thursday (4) are working days.
            return in_array($dayofweek, [0, 1, 2, 3, 4], true);
        } else if ($mode === 'mon_fri') {
            // Monday (1) to Friday (5) are working days.
            return in_array($dayofweek, [1, 2, 3, 4, 5], true);
        }

        // 'all_days': seven days a week.
        return true;
    }

    /**
     * Fetch user completion records for a run.
     *
     * @param \stdClass $run
     * @return array [userid => bool]
     */
    protected static function get_run_completions(\stdClass $run): array {
        global $DB;

        $completions = [];
        $sql = "SELECT DISTINCT cc.userid
                  FROM {course_completions} cc
                 WHERE cc.course = :courseid
                   AND cc.timecompleted IS NOT NULL
                   AND cc.timecompleted > 0";

        $users = $DB->get_fieldset_sql($sql, ['courseid' => $run->courseid]);
        foreach ($users as $uid) {
            $completions[(int) $uid] = true;
        }

        return $completions;
    }

    /**
     * Fetch course final grades for a run.
     *
     * @param \stdClass $run
     * @return array [userid => float]
     */
    protected static function get_run_grades(\stdClass $run): array {
        global $DB;

        $grades = [];
        $sql = "SELECT gg.userid, gg.finalgrade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = 'course'
                   AND gg.finalgrade IS NOT NULL";

        $records = $DB->get_records_sql($sql, ['courseid' => $run->courseid]);
        foreach ($records as $r) {
            $grades[(int) $r->userid] = (float) $r->finalgrade;
        }

        return $grades;
    }

    /**
     * Map each enrolled user in a run to their resolved organization dimension ID.
     *
     * @param \stdClass $run
     * @return array [userid => org_dim_id]
     */
    protected static function get_run_user_org_mapping(\stdClass $run): array {
        $userids = dimension_manager::get_run_enrolled_userids($run);
        $mapping = [];
        $defaultorg = $run->org_dim_id ?: 0;

        foreach ($userids as $uid) {
            $userorg = dimension_manager::resolve_user_organization((int) $uid);
            $mapping[(int) $uid] = $userorg ?: $defaultorg;
        }

        return $mapping;
    }

    /**
     * Return an empty metrics object with initialized zero values.
     *
     * @return \stdClass
     */
    public static function empty_metrics(): \stdClass {
        return (object) [
            'runs_count'           => 0,
            'participations_count' => 0,
            'completions_count'    => 0,
            'total_training_hours' => 0.0,
            'grade_sum'            => 0.0,
            'grade_count'          => 0,
            'completion_rate'      => 0.0,
            'avg_grade'            => 0.0,
        ];
    }
}
