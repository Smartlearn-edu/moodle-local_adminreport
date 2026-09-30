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
 * 3-Tier analytics stitcher and caching engine.
 *
 * Stitches historical warehouse data (Tier 3) with real-time intraday
 * calculations (Tier 1) across the nightly watermark seam, backed by
 * the MUC application cache (Tier 2).
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tier_stitcher {

    /**
     * Resolve allowed organization dimension member IDs for a user based on capabilities and scoping rules.
     *
     * @param int|null $userid Optional user ID. If null, current logged-in user is used.
     * @return int[]|null Null indicates global access (all organizations allowed).
     */
    public static function get_user_allowed_orgs(?int $userid = null): ?array {
        global $USER, $DB;

        $targetuserid = $userid !== null ? $userid : (int) $USER->id;
        $context = \context_system::instance();

        // 1. Global View Capability grants unrestricted access.
        if (has_capability('local/adminreport:viewall', $context, $targetuserid)) {
            return null;
        }

        // 2. Resolve specific user or role scoping from {local_adminreport_scope}.
        $allowedorgs = [];

        // Check user direct scope.
        $userscopes = $DB->get_fieldset_select(
            'local_adminreport_scope',
            'dim_member_id',
            "scope_type = 'user' AND scope_id = :uid",
            ['uid' => $targetuserid]
        );
        if (!empty($userscopes)) {
            foreach ($userscopes as $id) {
                $allowedorgs[] = (int) $id;
            }
        }

        // Check role scopes assigned to the user.
        $userroles = $DB->get_fieldset_select(
            'role_assignments',
            'DISTINCT roleid',
            'userid = :uid',
            ['uid' => $targetuserid]
        );
        if (!empty($userroles)) {
            list($rolesql, $roleparams) = $DB->get_in_or_equal($userroles, SQL_PARAMS_NAMED, 'r');
            $rolescopes = $DB->get_fieldset_select(
                'local_adminreport_scope',
                'dim_member_id',
                "scope_type = 'role' AND scope_id $rolesql",
                $roleparams
            );
            if (!empty($rolescopes)) {
                foreach ($rolescopes as $id) {
                    $allowedorgs[] = (int) $id;
                }
            }
        }

        return array_unique($allowedorgs);
    }

    /**
     * Generate unique hash representing user's organization scope for caching.
     *
     * @param int[]|null $allowedorgs
     * @return string
     */
    public static function get_scope_hash(?array $allowedorgs): string {
        if ($allowedorgs === null) {
            return 'global';
        }
        if (empty($allowedorgs)) {
            return 'empty';
        }
        sort($allowedorgs);
        return md5(implode(',', $allowedorgs));
    }

    /**
     * Retrieve complete report dataset with 3-tier stitching and MUC caching.
     *
     * @param string $periodtype Period preset ('week', 'month', 'annual', 'custom').
     * @param int $periodstart Start timestamp.
     * @param int $periodend End timestamp.
     * @param array $filters Dimension filters (org_dim_id, type_dim_id, location_dim_id).
     * @param int|null $userid User requesting the report.
     * @param bool $skipcache Set true to force recalculation.
     * @return array
     */
    public static function get_report_data(
        string $periodtype = 'week',
        int $periodstart = 0,
        int $periodend = 0,
        array $filters = [],
        ?int $userid = null,
        bool $skipcache = false
    ): array {
        // Resolve time bounds if preset selected.
        list($start, $end) = self::resolve_period_dates($periodtype, $periodstart, $periodend);

        $allowedorgs = self::get_user_allowed_orgs($userid);
        $scopehash = self::get_scope_hash($allowedorgs);

        // MUC Cache lookup.
        $cache = \cache::make('local_adminreport', 'reports_data');
        $cachekey = 'rep_' . md5($scopehash . '_' . $start . '_' . $end . '_' . serialize($filters));

        if (!$skipcache) {
            $cached = $cache->get($cachekey);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        // 1. Calculate Period Metrics via Watermark Seam Stitching.
        $metrics = self::get_stitched_metrics($start, $end, $allowedorgs, $filters);

        // 2. Calculate YTD Metrics (from Jan 1 of the period end year to the period end).
        $ytdstart = mktime(0, 0, 0, 1, 1, (int) date('Y', $end));
        $ytdmetrics = self::get_stitched_metrics($ytdstart, $end, $allowedorgs, $filters);

        // 3. Retrieve Operational Schedule (Runs list with 10 reference columns).
        $runs = self::get_operational_runs($start, $end, $allowedorgs, $filters);

        // 4. Plans by Entity & Program Type (Slide 4 Table 2).
        $plansbyentity = self::get_plans_by_entity_and_type($start, $end, $allowedorgs, $filters);

        // 5. Regional Branches Summary (Slide 4 Table 3).
        $plansbybranch = self::get_plans_by_branch($start, $end, $allowedorgs, $filters);

        // 6. Delivered Programs Classification (Slide 5 Table 1 & Cards).
        $deliveredclass = self::get_delivered_classification_summary($start, $end, $allowedorgs, $filters);

        // 7. Delivered Sectors (Slide 5 Table 4).
        $deliveredsectors = self::get_delivered_sectors_summary($start, $end, $allowedorgs, $filters);

        // 8. Delivered Corporate Clients (Slide 5 Table 5).
        $deliveredcorp = self::get_delivered_corporate_details($start, $end, $allowedorgs, $filters);

        // 9. Delivered 4 Pie Charts (Slide 5).
        $deliveredpies = self::get_delivered_pie_charts_data($start, $end, $allowedorgs, $filters);

        // 10. 12-Month Annual Trajectory (Slide 6 Table 1).
        $trajectory = self::get_monthly_trajectory((int) date('Y', $end), $allowedorgs, $filters);

        // 11. Strategic Partners (Slide 6 Table 3).
        $partners = self::get_strategic_partners_list();

        // 12. Cumulative Summary (Slide 6 Table 4).
        $cumulativesummary = self::get_cumulative_summary((int) date('Y', $end), $allowedorgs, $filters);

        // 13. Trainees Report.
        $trainees = self::get_trainees_report($allowedorgs, $filters);

        // 14. Chart Distributions.
        $charts = self::generate_chart_data($start, $end, $allowedorgs, $filters);

        $data = [
            'period_type'              => $periodtype,
            'start_date'               => $start,
            'end_date'                 => $end,
            'start_date_formatted'     => userdate($start, get_string('strftimedate')),
            'end_date_formatted'       => userdate($end, get_string('strftimedate')),
            'scope_hash'               => $scopehash,
            'metrics'                  => (array) $metrics,
            'ytd_metrics'              => (array) $ytdmetrics,
            'runs'                     => $runs,
            'runs_count'               => count($runs),
            'plans_by_entity'          => $plansbyentity,
            'plans_by_branch'          => $plansbybranch,
            'delivered_classification' => $deliveredclass,
            'delivered_sectors'        => $deliveredsectors,
            'delivered_corporate'      => $deliveredcorp,
            'delivered_pies'           => $deliveredpies,
            'monthly_trajectory'       => $trajectory,
            'strategic_partners'       => $partners,
            'cumulative_summary'       => $cumulativesummary,
            'trainees_report'          => $trainees,
            'trainees_count'           => count($trainees),
            'charts'                   => $charts,
            'generated_at'             => time(),
        ];

        // Cache result for 10 minutes.
        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Stitch historical warehouse data with intraday facts across the watermark.
     *
     * @param int $periodstart
     * @param int $periodend
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return \stdClass
     */
    public static function get_stitched_metrics(
        int $periodstart,
        int $periodend,
        ?array $allowedorgs = null,
        array $filters = []
    ): \stdClass {
        $watermark = (int) get_config('local_adminreport', 'last_aggregated_watermark');
        if ($watermark <= 0) {
            // Default to yesterday midnight if watermark has not yet been set by cron.
            $watermark = strtotime('yesterday midnight');
        }

        $watermarkdayend = $watermark + 86399;

        // Case A: Entire period is in the warehouse (up to watermark).
        if ($periodend <= $watermarkdayend) {
            return query_engine::get_warehouse_metrics($periodstart, $periodend, $allowedorgs, $filters);
        }

        // Case B: Entire period is in intraday tier (after watermark).
        if ($periodstart > $watermarkdayend) {
            return self::compute_intraday_metrics($periodstart, $periodend, $allowedorgs, $filters);
        }

        // Case C: Period crosses the watermark seam.
        // Slice 1: Warehouse facts from periodstart to watermark.
        $warehousemetrics = query_engine::get_warehouse_metrics($periodstart, $watermarkdayend, $allowedorgs, $filters);

        // Slice 2: Intraday dynamic calculation from watermark+1 day to periodend.
        $intradaymetrics = self::compute_intraday_metrics($watermark + 86400, $periodend, $allowedorgs, $filters);

        // Stitch both slices together.
        $combined = (object) [
            'runs_count'           => $warehousemetrics->runs_count + $intradaymetrics->runs_count,
            'participations_count' => $warehousemetrics->participations_count + $intradaymetrics->participations_count,
            'completions_count'    => $warehousemetrics->completions_count + $intradaymetrics->completions_count,
            'total_training_hours' => round($warehousemetrics->total_training_hours + $intradaymetrics->total_training_hours, 2),
            'grade_sum'            => round($warehousemetrics->grade_sum + $intradaymetrics->grade_sum, 2),
            'grade_count'          => $warehousemetrics->grade_count + $intradaymetrics->grade_count,
        ];

        $combined->completion_rate = $combined->participations_count > 0
            ? round(($combined->completions_count / $combined->participations_count) * 100, 1)
            : 0.0;

        $combined->avg_grade = $combined->grade_count > 0
            ? round($combined->grade_sum / $combined->grade_count, 1)
            : 0.0;

        return $combined;
    }

    /**
     * Dynamically compute intraday facts for a recent date window.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return \stdClass
     */
    public static function compute_intraday_metrics(
        int $from,
        int $to,
        ?array $allowedorgs = null,
        array $filters = []
    ): \stdClass {
        global $DB;

        $startday = strtotime('midnight', $from);
        $endday = strtotime('midnight', $to);

        $params = ['fromdate' => $startday, 'todate' => $endday + 86399];
        $wheres = ['r.is_cancelled = 0', 'r.startdate <= :todate', 'r.enddate >= :fromdate'];

        if (!empty($filters['type_dim_id'])) {
            $wheres[] = "r.type_dim_id = :typefilter";
            $params['typefilter'] = (int) $filters['type_dim_id'];
        }
        if (!empty($filters['location_dim_id'])) {
            $wheres[] = "r.location_dim_id = :locfilter";
            $params['locfilter'] = (int) $filters['location_dim_id'];
        }

        $whereclause = implode(' AND ', $wheres);
        $sql = "SELECT r.* FROM {local_adminreport_runs} r WHERE {$whereclause}";
        $runs = $DB->get_records_sql($sql, $params);

        if (empty($runs)) {
            return query_engine::empty_metrics();
        }

        $runscountset = [];
        $totalparticipations = 0;
        $totalcompletions = 0;
        $totalhours = 0.0;
        $totalgradesum = 0.0;
        $totalgradecount = 0;

        $curday = $startday;
        while ($curday <= $endday) {
            foreach ($runs as $run) {
                $facts = query_engine::compute_run_facts($run, $curday);
                foreach ($facts as $fact) {
                    // Check scoping.
                    if ($allowedorgs !== null && !in_array($fact->org_dim_id, $allowedorgs, true)) {
                        continue;
                    }
                    // Check interactive org filter.
                    if (!empty($filters['org_dim_id']) && (int) $filters['org_dim_id'] !== $fact->org_dim_id) {
                        continue;
                    }

                    if ($fact->participations_flow > 0) {
                        $runscountset[$fact->run_id] = true;
                    }

                    $totalparticipations += $fact->participations_flow;
                    $totalcompletions += $fact->completions_flow;
                    $totalhours += $fact->day_training_hours;
                    $totalgradesum += $fact->grade_sum;
                    $totalgradecount += $fact->grade_count;
                }
            }
            $curday += 86400;
        }

        $res = (object) [
            'runs_count'           => count($runscountset),
            'participations_count' => $totalparticipations,
            'completions_count'    => $totalcompletions,
            'total_training_hours' => round($totalhours, 2),
            'grade_sum'            => round($totalgradesum, 2),
            'grade_count'          => $totalgradecount,
        ];

        $res->completion_rate = $res->participations_count > 0
            ? round(($res->completions_count / $res->participations_count) * 100, 1)
            : 0.0;
        $res->avg_grade = $res->grade_count > 0
            ? round($res->grade_sum / $res->grade_count, 1)
            : 0.0;

        return $res;
    }

    /**
     * Retrieve runs for the operational schedule table.
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_operational_runs(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        // Operational interval overlap: startdate <= pend AND enddate >= pstart.
        $wheres = ['r.startdate <= :pend', 'r.enddate >= :pstart'];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return [];
            }
            list($scopeinsql, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'sc');
            $wheres[] = "r.org_dim_id $scopeinsql";
            $params = array_merge($params, $scopeparams);
        }

        if (!empty($filters['org_dim_id'])) {
            $wheres[] = "r.org_dim_id = :orgf";
            $params['orgf'] = (int) $filters['org_dim_id'];
        }
        if (!empty($filters['type_dim_id'])) {
            $wheres[] = "r.type_dim_id = :typef";
            $params['typef'] = (int) $filters['type_dim_id'];
        }
        if (!empty($filters['location_dim_id'])) {
            $wheres[] = "r.location_dim_id = :locf";
            $params['locf'] = (int) $filters['location_dim_id'];
        }

        $whereclause = implode(' AND ', $wheres);

        $sql = "SELECT r.*,
                       c.fullname AS coursename,
                       om.name AS orgname,
                       tm.name AS typename,
                       lm.name AS locname,
                       rt.trainer_name
                  FROM {local_adminreport_runs} r
                  JOIN {course} c ON c.id = r.courseid
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
             LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
             LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
             LEFT JOIN {local_adminreport_run_trainers} rt ON rt.run_id = r.id AND rt.is_primary = 1
                 WHERE {$whereclause}
              ORDER BY r.startdate ASC";

        $records = $DB->get_records_sql($sql, $params);
        $runs = [];
        $now = time();

        foreach ($records as $r) {
            $status = 'completed';
            $statuslabel = get_string('status_completed', 'local_adminreport');
            $badgeclass = 'bg-success';

            if (!empty($r->is_cancelled)) {
                $status = 'cancelled';
                $statuslabel = get_string('status_cancelled', 'local_adminreport');
                $badgeclass = 'bg-danger';
            } else if ($now < $r->startdate) {
                $status = 'planned';
                $statuslabel = get_string('status_planned', 'local_adminreport');
                $badgeclass = 'bg-info';
            } else if ($now >= $r->startdate && $now <= $r->enddate) {
                $status = 'running';
                $statuslabel = get_string('status_running', 'local_adminreport');
                $badgeclass = 'bg-warning text-dark';
            }

            // Calculate active participant count.
            if ($r->groupid > 0) {
                $participants = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT gm.userid)
                       FROM {groups_members} gm
                       JOIN {enrol} e ON e.courseid = :cid
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0 AND ue.userid = gm.userid
                       JOIN {user} u ON u.id = gm.userid AND u.deleted = 0
                      WHERE gm.groupid = :gid",
                    ['cid' => $r->courseid, 'gid' => $r->groupid]
                );
            } else {
                $participants = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ue.userid)
                       FROM {enrol} e
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                       JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                      WHERE e.courseid = :cid",
                    ['cid' => $r->courseid]
                );
            }

            $traineecount = (int) $participants;
            if ($traineecount === 0) {
                $statcount = $DB->get_field_sql(
                    "SELECT SUM(participations_flow) FROM {local_adminreport_daily_stats} WHERE run_id = :rid",
                    ['rid' => $r->id]
                );
                if (!empty($statcount)) {
                    $traineecount = (int) $statcount;
                }
            }

            // Calculate duration in days.
            $durationdays = max(1, (int) round(($r->enddate - $r->startdate) / 86400));
            $durationlabel = $durationdays . ' ' . get_string('days', 'local_adminreport');

            // Daily start and end timings.
            $dailytime = (!empty($r->daily_start_time) && !empty($r->daily_end_time))
                ? $r->daily_start_time . ' - ' . $r->daily_end_time
                : '08:00 - 14:00';

            // Break time.
            $breaktime = !empty($r->break_duration_min)
                ? '09:00 - 09:30'
                : '09:00 - 09:30';

            // Exam time.
            $examtime = !empty($r->exam_time) ? $r->exam_time : '11:00';

            $runs[] = [
                'id'             => (int) $r->id,
                'run_code'       => $r->run_code,
                'coursename'     => $r->coursename,
                'courseid'       => (int) $r->courseid,
                'orgname'        => $r->orgname ?: '-',
                'typename'       => $r->typename ?: '-',
                'locname'        => $r->locname ?: '-',
                'classroom'      => $r->classroom ?: '-',
                'trainer_name'   => $r->trainer_name ?: '-',
                'duration_days'  => $durationdays,
                'duration_label' => $durationlabel,
                'daily_time'     => $dailytime,
                'break_time'     => $breaktime,
                'exam_time'      => $examtime,
                'startdate'      => userdate($r->startdate, get_string('strftimedate')),
                'enddate'        => userdate($r->enddate, get_string('strftimedate')),
                'participants'   => $traineecount,
                'status'         => $status,
                'status_label'   => $statuslabel,
                'badge_class'    => $badgeclass,
            ];
        }

        return $runs;
    }

    /**
     * Generate chart datasets for ApexCharts visualisations.
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function generate_chart_data(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        // 1. Locations Distribution (Donut Chart: Location => Trainees).
        $locparams = ['start' => $start, 'end' => $end];
        $locwheres = ['ds.stat_date >= :start', 'ds.stat_date <= :end'];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['locations' => [], 'classifications' => [], 'trends' => []];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'lsc');
            $locwheres[] = "ds.org_dim_id $scopein";
            $locparams = array_merge($locparams, $scopeparams);
        }

        $locsql = "SELECT lm.name AS location_name,
                          COALESCE(SUM(ds.participations_flow), 0) AS trainees
                     FROM {local_adminreport_daily_stats} ds
                     JOIN {local_adminreport_runs} r ON r.id = ds.run_id
                LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
                    WHERE " . implode(' AND ', $locwheres) . "
                 GROUP BY lm.name
                   HAVING SUM(ds.participations_flow) > 0
                 ORDER BY trainees DESC";

        $locrecords = $DB->get_records_sql($locsql, $locparams);
        $locations = [
            'labels' => [],
            'series' => [],
        ];
        foreach ($locrecords as $lr) {
            $locations['labels'][] = $lr->location_name ?: get_string('unspecified', 'local_adminreport');
            $locations['series'][] = (int) $lr->trainees;
        }

        // 2. Classifications Distribution (Stacked Column Chart: Program Type => Runs & Trainees).
        $typesql = "SELECT tm.name AS type_name,
                           COUNT(DISTINCT r.id) AS runs,
                           COALESCE(SUM(ds.participations_flow), 0) AS trainees
                      FROM {local_adminreport_daily_stats} ds
                      JOIN {local_adminreport_runs} r ON r.id = ds.run_id
                 LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
                     WHERE " . implode(' AND ', $locwheres) . "
                  GROUP BY tm.name
                    HAVING SUM(ds.participations_flow) > 0
                  ORDER BY trainees DESC";

        $typerecords = $DB->get_records_sql($typesql, $locparams);
        $classifications = [
            'categories' => [],
            'runs'       => [],
            'trainees'   => [],
        ];
        foreach ($typerecords as $tr) {
            $classifications['categories'][] = $tr->type_name ?: get_string('unspecified', 'local_adminreport');
            $classifications['runs'][]       = (int) $tr->runs;
            $classifications['trainees'][]   = (int) $tr->trainees;
        }

        // 3. Monthly Trends (Line Chart: 6-month historical trajectory).
        $curyear = (int) date('Y', $end);
        $curmonth = (int) date('n', $end);
        $trendrecords = $DB->get_records_sql(
            "SELECT ms.year, ms.month,
                    SUM(ms.runs_count) AS runs,
                    SUM(ms.participations_count) AS trainees
               FROM {local_adminreport_monthly_stats} ms
              WHERE (ms.year < :y1 OR (ms.year = :y2 AND ms.month <= :m1))
           GROUP BY ms.year, ms.month
           ORDER BY ms.year DESC, ms.month DESC",
            ['y1' => $curyear, 'y2' => $curyear, 'm1' => $curmonth],
            0,
            6
        );

        $trends = [
            'categories' => [],
            'runs'       => [],
            'trainees'   => [],
        ];
        // Reverse to display chronologically from oldest to newest.
        $reversed = array_reverse($trendrecords);
        foreach ($reversed as $tr) {
            $monthdate = mktime(0, 0, 0, (int)$tr->month, 1, (int)$tr->year);
            $trends['categories'][] = userdate($monthdate, '%B %Y');
            $trends['runs'][]       = (int) $tr->runs;
            $trends['trainees'][]   = (int) $tr->trainees;
        }

        return [
            'locations'       => $locations,
            'classifications' => $classifications,
            'trends'          => $trends,
        ];
    }

    /**
     * Resolve start and end timestamps based on period type.
     *
     * @param string $periodtype
     * @param int $start
     * @param int $end
     * @return array [int $startdate, int $enddate]
     */
    public static function resolve_period_dates(string $periodtype, int $start = 0, int $end = 0): array {
        $now = time();

        switch ($periodtype) {
            case 'week':
                // Current week: from last Sunday 00:00:00 to Thursday/Saturday 23:59:59.
                $startday = strtotime('last sunday midnight', $now);
                if (date('w', $now) == 0) {
                    $startday = strtotime('today midnight', $now);
                }
                $endday = $startday + (5 * 86400) - 1; // Thursday end of day.
                return [$startday, $endday];

            case 'month':
                // Current calendar month.
                $startday = mktime(0, 0, 0, (int) date('n', $now), 1, (int) date('Y', $now));
                $endday   = mktime(23, 59, 59, (int) date('n', $now), (int) date('t', $now), (int) date('Y', $now));
                return [$startday, $endday];

            case 'annual':
                // Current calendar year.
                $startday = mktime(0, 0, 0, 1, 1, (int) date('Y', $now));
                $endday   = mktime(23, 59, 59, 12, 31, (int) date('Y', $now));
                return [$startday, $endday];

            case 'custom':
            default:
                $resolvedstart = $start > 0 ? strtotime('midnight', $start) : ($now - (30 * 86400));
                $resolvedend   = $end > 0 ? (strtotime('midnight', $end) + 86399) : $now;
                return [$resolvedstart, $resolvedend];
        }
    }

    /**
     * Helper to return trainee count subquery joins and column expression for runs queries.
     *
     * @return array [string $joins, string $expr]
     */
    private static function get_run_trainees_sql_parts(): array {
        $joins = " LEFT JOIN (
                       SELECT r_sub.id AS run_id,
                              COUNT(DISTINCT CASE WHEN r_sub.groupid > 0 THEN gm.userid ELSE ue.userid END) AS trainees_count
                         FROM {local_adminreport_runs} r_sub
                         JOIN {enrol} e ON e.courseid = r_sub.courseid
                         JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                         JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                    LEFT JOIN {groups_members} gm ON gm.groupid = r_sub.groupid AND gm.userid = ue.userid
                        WHERE (r_sub.groupid = 0 OR gm.userid IS NOT NULL)
                        GROUP BY r_sub.id
                   ) rt ON rt.run_id = r.id
                   LEFT JOIN (
                       SELECT run_id, SUM(participations_flow) AS stat_trainees
                         FROM {local_adminreport_daily_stats}
                        GROUP BY run_id
                   ) ds_part ON ds_part.run_id = r.id ";

        $expr = "SUM(COALESCE(rt.trainees_count, ds_part.stat_trainees, 0))";

        return [$joins, $expr];
    }

    /**
     * Retrieve plans by entity and program type (Slide 4 Table 2).
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_plans_by_entity_and_type(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = ['r.is_cancelled = 0', 'r.startdate <= :pend', 'r.enddate >= :pstart'];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['rows' => [], 'total_programs' => 0, 'total_trainees' => 0, 'total_groups' => 0];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'sc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        if (!empty($filters['org_dim_id'])) {
            $wheres[] = "r.org_dim_id = :orgf";
            $params['orgf'] = (int) $filters['org_dim_id'];
        }
        if (!empty($filters['type_dim_id'])) {
            $wheres[] = "r.type_dim_id = :typef";
            $params['typef'] = (int) $filters['type_dim_id'];
        }
        if (!empty($filters['location_dim_id'])) {
            $wheres[] = "r.location_dim_id = :locf";
            $params['locf'] = (int) $filters['location_dim_id'];
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT om.name AS entity_name,
                       tm.name AS program_type,
                       lm.name AS location_name,
                       COUNT(DISTINCT r.id) AS programs_count,
                       {$traineeexpr} AS trainees_count,
                       COUNT(DISTINCT COALESCE(r.groupid, r.id)) AS groups_count
                  FROM {local_adminreport_runs} r
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
             LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
             LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
                       {$traineejoins}
                 WHERE {$whereclause}
              GROUP BY om.name, tm.name, lm.name
              ORDER BY programs_count DESC, trainees_count DESC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $totalprograms = 0;
        $totaltrainees = 0;
        $totalgroups = 0;

        foreach ($records as $rec) {
            $pcount = (int) $rec->programs_count;
            $tcount = (int) $rec->trainees_count;
            $gcount = (int) $rec->groups_count;

            $totalprograms += $pcount;
            $totaltrainees += $tcount;
            $totalgroups += $gcount;

            $rows[] = [
                'entity_name'    => $rec->entity_name ?: get_string('unspecified', 'local_adminreport'),
                'program_type'   => $rec->program_type ?: get_string('unspecified', 'local_adminreport'),
                'location_name'  => $rec->location_name ?: get_string('unspecified', 'local_adminreport'),
                'programs_count' => $pcount,
                'trainees_count' => $tcount,
                'groups_count'   => $gcount,
            ];
        }

        return [
            'rows'           => $rows,
            'total_programs' => $totalprograms,
            'total_trainees' => $totaltrainees,
            'total_groups'   => $totalgroups,
        ];
    }

    /**
     * Retrieve regional branch plans distribution (Slide 4 Table 3).
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_plans_by_branch(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = ['r.is_cancelled = 0', 'r.startdate <= :pend', 'r.enddate >= :pstart'];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['rows' => [], 'total_courses' => 0, 'total_trainees' => 0];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'bsc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        if (!empty($filters['org_dim_id'])) {
            $wheres[] = "r.org_dim_id = :orgf";
            $params['orgf'] = (int) $filters['org_dim_id'];
        }
        if (!empty($filters['type_dim_id'])) {
            $wheres[] = "r.type_dim_id = :typef";
            $params['typef'] = (int) $filters['type_dim_id'];
        }
        if (!empty($filters['location_dim_id'])) {
            $wheres[] = "r.location_dim_id = :locf";
            $params['locf'] = (int) $filters['location_dim_id'];
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT lm.name AS branch_name,
                       COUNT(DISTINCT r.id) AS courses_count,
                       {$traineeexpr} AS trainees_count
                  FROM {local_adminreport_runs} r
             LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
                       {$traineejoins}
                 WHERE {$whereclause}
              GROUP BY lm.name
              ORDER BY courses_count DESC, trainees_count DESC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $totalcourses = 0;
        $totaltrainees = 0;

        foreach ($records as $rec) {
            $ccount = (int) $rec->courses_count;
            $tcount = (int) $rec->trainees_count;
            $totalcourses += $ccount;
            $totaltrainees += $tcount;

            $rows[] = [
                'branch_name'    => $rec->branch_name ?: get_string('unspecified', 'local_adminreport'),
                'courses_count'  => $ccount,
                'trainees_count' => $tcount,
            ];
        }

        return [
            'rows'           => $rows,
            'total_courses'  => $totalcourses,
            'total_trainees' => $totaltrainees,
        ];
    }

    /**
     * Retrieve delivered programs summary by classification (Slide 5 Table 1 & Cards).
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_delivered_classification_summary(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = [
            'r.is_cancelled = 0',
            'r.enddate <= :pend',
            'r.startdate <= :pend',
            'r.enddate >= :pstart',
        ];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['cards' => [], 'total_runs' => 0, 'total_trainees' => 0];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'dsc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT tm.name AS type_name,
                       COUNT(DISTINCT r.id) AS runs_count,
                       {$traineeexpr} AS trainees_count
                  FROM {local_adminreport_runs} r
             LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
                       {$traineejoins}
                 WHERE {$whereclause}
              GROUP BY tm.name
              ORDER BY runs_count DESC";

        $records = $DB->get_records_sql($sql, $params);
        $totalruns = 0;
        $totaltrainees = 0;

        // Standard classification cards matching Slide 5
        $standardtypes = [
            'دورة تطويرية' => [
                'name' => 'دورة تطويرية', 'unit' => 'دورة', 'runs' => 0, 'trainees' => 0, 'badge' => 'border-primary',
            ],
            'دورة تأهيلية' => [
                'name' => 'دورة تأهيلية', 'unit' => 'دورة', 'runs' => 0, 'trainees' => 0, 'badge' => 'border-info',
            ],
            'برنامج تأهيلي' => [
                'name' => 'برنامج تأهيلي', 'unit' => 'برنامج', 'runs' => 0, 'trainees' => 0, 'badge' => 'border-warning',
            ],
            'دبلوم تدريبي' => [
                'name' => 'دبلوم تدريبي', 'unit' => 'ديبلوم', 'runs' => 0, 'trainees' => 0, 'badge' => 'border-success',
            ],
            'الملتقيات والبرامج العالمية وورش العمل' => [
                'name' => 'الملتقيات وورش العمل', 'unit' => 'فعالية', 'runs' => 0, 'trainees' => 0, 'badge' => 'border-secondary',
            ],
        ];

        foreach ($records as $rec) {
            $tname = trim($rec->type_name ?: '');
            $rcount = (int) $rec->runs_count;
            $tcount = (int) $rec->trainees_count;

            $totalruns += $rcount;
            $totaltrainees += $tcount;

            $matched = false;
            foreach ($standardtypes as $key => &$st) {
                if ($tname === $key || mb_stripos($tname, $key) !== false || mb_stripos($key, $tname) !== false) {
                    $st['runs'] += $rcount;
                    $st['trainees'] += $tcount;
                    $matched = true;
                    break;
                }
            }
            if (!$matched && !empty($tname)) {
                $standardtypes[$tname] = [
                    'name'     => $tname,
                    'unit'     => 'دورة',
                    'runs'     => $rcount,
                    'trainees' => $tcount,
                    'badge'    => 'border-dark',
                ];
            }
        }

        return [
            'cards'          => array_values($standardtypes),
            'total_runs'     => $totalruns,
            'total_trainees' => $totaltrainees,
        ];
    }

    /**
     * Retrieve delivered sectors summary (Slide 5 Table 4).
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_delivered_sectors_summary(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = [
            'r.is_cancelled = 0',
            'r.enddate <= :pend',
            'r.startdate <= :pend',
            'r.enddate >= :pstart',
        ];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['sectors' => [], 'total_runs' => 0, 'total_trainees' => 0];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'secsc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT COALESCE(sm.name, om.name, 'الشركات') AS sector_name,
                       COUNT(DISTINCT r.id) AS runs_count,
                       {$traineeexpr} AS trainees_count
                  FROM {local_adminreport_runs} r
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
             LEFT JOIN {local_adminreport_dim_members} sm ON sm.id = om.parent_id
                       {$traineejoins}
                 WHERE {$whereclause}
              GROUP BY COALESCE(sm.name, om.name, 'الشركات')
              ORDER BY runs_count DESC";

        $records = $DB->get_records_sql($sql, $params);
        $totalruns = 0;
        $totaltrainees = 0;

        $standardsectors = [
            'الهيئة' => ['name' => 'الهيئة', 'runs' => 0, 'trainees' => 0],
            'الشركات' => ['name' => 'الشركات', 'runs' => 0, 'trainees' => 0],
            'القطاع الحكومي والعسكري' => ['name' => 'القطاع الحكومي والعسكري', 'runs' => 0, 'trainees' => 0],
            'دول الخليج' => ['name' => 'دول الخليج', 'runs' => 0, 'trainees' => 0],
            'أفراد' => ['name' => 'أفراد', 'runs' => 0, 'trainees' => 0],
            'المسؤولية المجتمعية' => ['name' => 'المسؤولية المجتمعية', 'runs' => 0, 'trainees' => 0],
        ];

        foreach ($records as $rec) {
            $sname = trim($rec->sector_name ?: '');
            $rcount = (int) $rec->runs_count;
            $tcount = (int) $rec->trainees_count;
            $totalruns += $rcount;
            $totaltrainees += $tcount;

            $matched = false;
            foreach ($standardsectors as $key => &$ss) {
                if (mb_stripos($sname, $key) !== false || mb_stripos($key, $sname) !== false) {
                    $ss['runs'] += $rcount;
                    $ss['trainees'] += $tcount;
                    $matched = true;
                    break;
                }
            }
            if (!$matched && !empty($sname)) {
                $standardsectors[$sname] = [
                    'name'     => $sname,
                    'runs'     => $rcount,
                    'trainees' => $tcount,
                ];
            }
        }

        return [
            'sectors'        => array_values($standardsectors),
            'total_runs'     => $totalruns,
            'total_trainees' => $totaltrainees,
        ];
    }

    /**
     * Retrieve delivered corporate clients detail (Slide 5 Table 5).
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_delivered_corporate_details(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = [
            'r.is_cancelled = 0',
            'r.enddate <= :pend',
            'r.startdate <= :pend',
            'r.enddate >= :pstart',
        ];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['rows' => [], 'total_programs' => 0, 'total_trainees' => 0];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'corpsc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT om.name AS company_name,
                       COUNT(DISTINCT r.id) AS programs_count,
                       {$traineeexpr} AS trainees_count
                  FROM {local_adminreport_runs} r
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
                       {$traineejoins}
                 WHERE {$whereclause}
              GROUP BY om.name
              ORDER BY programs_count DESC, trainees_count DESC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $totalprograms = 0;
        $totaltrainees = 0;

        foreach ($records as $rec) {
            $pcount = (int) $rec->programs_count;
            $tcount = (int) $rec->trainees_count;
            $totalprograms += $pcount;
            $totaltrainees += $tcount;

            $rows[] = [
                'company_name'   => $rec->company_name ?: get_string('unspecified', 'local_adminreport'),
                'programs_count' => $pcount,
                'trainees_count' => $tcount,
            ];
        }

        return [
            'rows'           => $rows,
            'total_programs' => $totalprograms,
            'total_trainees' => $totaltrainees,
        ];
    }

    /**
     * Generate the 4 Pie Chart datasets matching Slide 5 of the reference report.
     *
     * @param int $start
     * @param int $end
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_delivered_pie_charts_data(
        int $start,
        int $end,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $params = ['pstart' => $start, 'pend' => $end];
        $wheres = [
            'r.is_cancelled = 0',
            'r.enddate <= :pend',
            'r.startdate <= :pend',
            'r.enddate >= :pstart',
        ];

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return [
                    'programs_by_type'   => ['labels' => [], 'series' => []],
                    'trainees_by_type'   => ['labels' => [], 'series' => []],
                    'programs_by_sector' => ['labels' => [], 'series' => []],
                    'trainees_by_sector' => ['labels' => [], 'series' => []],
                ];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'pieorg');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        $whereclause = implode(' AND ', $wheres);

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        // 1. By Program Type
        $sqltype = "SELECT tm.name AS label,
                           COUNT(DISTINCT r.id) AS runs,
                           {$traineeexpr} AS trainees
                      FROM {local_adminreport_runs} r
                 LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
                           {$traineejoins}
                     WHERE {$whereclause}
                  GROUP BY tm.name
                  ORDER BY runs DESC";
        $typerecs = $DB->get_records_sql($sqltype, $params);

        $progbytype = ['labels' => [], 'series' => []];
        $traineebytype = ['labels' => [], 'series' => []];

        foreach ($typerecs as $rec) {
            $lbl = $rec->label ?: get_string('unspecified', 'local_adminreport');
            $rval = (int) $rec->runs;
            $tval = (int) $rec->trainees;
            if ($rval > 0) {
                $progbytype['labels'][] = $lbl;
                $progbytype['series'][] = $rval;
            }
            if ($tval > 0) {
                $traineebytype['labels'][] = $lbl;
                $traineebytype['series'][] = $tval;
            }
        }

        // 2. By Sector
        $sqlsec = "SELECT COALESCE(sm.name, om.name, 'الشركات') AS label,
                          COUNT(DISTINCT r.id) AS runs,
                          {$traineeexpr} AS trainees
                     FROM {local_adminreport_runs} r
                LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
                LEFT JOIN {local_adminreport_dim_members} sm ON sm.id = om.parent_id
                          {$traineejoins}
                    WHERE {$whereclause}
                 GROUP BY COALESCE(sm.name, om.name, 'الشركات')
                 ORDER BY runs DESC";
        $secrecs = $DB->get_records_sql($sqlsec, $params);

        $progbysec = ['labels' => [], 'series' => []];
        $traineebysec = ['labels' => [], 'series' => []];

        foreach ($secrecs as $rec) {
            $lbl = $rec->label ?: get_string('unspecified', 'local_adminreport');
            $rval = (int) $rec->runs;
            $tval = (int) $rec->trainees;
            if ($rval > 0) {
                $progbysec['labels'][] = $lbl;
                $progbysec['series'][] = $rval;
            }
            if ($tval > 0) {
                $traineebysec['labels'][] = $lbl;
                $traineebysec['series'][] = $tval;
            }
        }

        return [
            'programs_by_type'   => $progbytype,
            'trainees_by_type'   => $traineebytype,
            'programs_by_sector' => $progbysec,
            'trainees_by_sector' => $traineebysec,
        ];
    }

    /**
     * Retrieve 12-Month Annual Trajectory table (Slide 6 Table 1).
     *
     * @param int $year
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_monthly_trajectory(
        int $year,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $monthnames = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $sql = "SELECT ms.month,
                       SUM(ms.runs_count) AS runs,
                       SUM(ms.participations_count) AS trainees
                  FROM {local_adminreport_monthly_stats} ms
                 WHERE ms.year = :year
              GROUP BY ms.month
              ORDER BY ms.month ASC";

        $dbmonths = $DB->get_records_sql($sql, ['year' => $year]);

        $rows = [];
        $cumruns = 0;
        $cumtrainees = 0;

        for ($m = 1; $m <= 12; $m++) {
            $mruns = isset($dbmonths[$m]) ? (int) $dbmonths[$m]->runs : 0;
            $mtrainees = isset($dbmonths[$m]) ? (int) $dbmonths[$m]->trainees : 0;

            $cumruns += $mruns;
            $cumtrainees += $mtrainees;

            $rows[] = [
                'month_num'           => $m,
                'month_name'          => $monthnames[$m],
                'month_runs'          => $mruns,
                'month_trainees'      => $mtrainees,
                'cumulative_runs'     => $cumruns,
                'cumulative_trainees' => $cumtrainees,
            ];
        }

        return [
            'year'                => $year,
            'rows'                => $rows,
            'annual_runs'         => $cumruns,
            'annual_trainees'     => $cumtrainees,
        ];
    }

    /**
     * Retrieve list of strategic partners and long-term programs (Slide 6 Table 3).
     *
     * @return array
     */
    public static function get_strategic_partners_list(): array {
        global $DB;

        list($traineejoins, $traineeexpr) = self::get_run_trainees_sql_parts();

        $sql = "SELECT DISTINCT om.name AS partner_name,
                       COUNT(DISTINCT r.id) AS programs_count,
                       {$traineeexpr} AS trainees_count
                  FROM {local_adminreport_runs} r
                  JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
             LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
                       {$traineejoins}
                 WHERE r.is_cancelled = 0
              GROUP BY om.name
              ORDER BY programs_count DESC";

        $records = $DB->get_records_sql($sql);
        $partners = [];
        foreach ($records as $p) {
            $partners[] = [
                'partner_name'   => $p->partner_name,
                'programs_count' => (int) $p->programs_count,
                'trainees_count' => (int) $p->trainees_count,
            ];
        }
        return $partners;
    }

    /**
     * Retrieve annual cumulative rollup (Slide 6 Table 4).
     *
     * @param int $year
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_cumulative_summary(
        int $year,
        ?array $allowedorgs = null,
        array $filters = []
    ): array {
        global $DB;

        $sql = "SELECT tm.name AS type_name,
                       SUM(ms.runs_count) AS runs_count,
                       SUM(ms.participations_count) AS trainees_count
                  FROM {local_adminreport_monthly_stats} ms
             LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = ms.type_dim_id
                 WHERE ms.year = :year
              GROUP BY tm.name";

        $records = $DB->get_records_sql($sql, ['year' => $year]);
        $totalruns = 0;
        $totaltrainees = 0;
        $breakdown = [];

        foreach ($records as $rec) {
            $rcount = (int) $rec->runs_count;
            $tcount = (int) $rec->trainees_count;
            $totalruns += $rcount;
            $totaltrainees += $tcount;
            $breakdown[] = [
                'type_name'      => $rec->type_name ?: get_string('unspecified', 'local_adminreport'),
                'runs_count'     => $rcount,
                'trainees_count' => $tcount,
            ];
        }

        return [
            'year'           => $year,
            'total_runs'     => $totalruns,
            'total_trainees' => $totaltrainees,
            'breakdown'      => $breakdown,
        ];
    }

    /**
     * Retrieve detailed trainee progress report.
     *
     * @param int[]|null $allowedorgs
     * @param array $filters
     * @return array
     */
    public static function get_trainees_report(?array $allowedorgs = null, array $filters = []): array {
        global $DB;

        $sql = "SELECT DISTINCT u.id AS userid,
                       u.firstname, u.lastname, u.email, u.idnumber,
                       r.id AS run_id, r.run_code, r.courseid,
                       c.fullname AS coursename,
                       om.name AS orgname,
                       lm.name AS locname
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {local_adminreport_runs} r ON r.courseid = c.id AND r.is_cancelled = 0
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
             LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
                 WHERE u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, [], 0, 100);
        $trainees = [];

        foreach ($records as $rec) {
            $completed = $DB->record_exists('course_completions', [
                'userid' => $rec->userid,
                'course' => $rec->courseid,
            ]);

            $statuslabel = $completed
                ? get_string('status_completed', 'local_adminreport')
                : get_string('status_in_progress', 'local_adminreport');
            $badgeclass = $completed ? 'bg-success' : 'bg-primary';

            $trainees[] = [
                'userid'       => (int) $rec->userid,
                'fullname'     => fullname($rec),
                'email'        => $rec->email,
                'idnumber'     => $rec->idnumber ?: '-',
                'coursename'   => $rec->coursename,
                'courseid'     => (int) $rec->courseid,
                'run_code'     => $rec->run_code,
                'orgname'      => $rec->orgname ?: '-',
                'locname'      => $rec->locname ?: '-',
                'status_label' => $statuslabel,
                'badge_class'  => $badgeclass,
            ];
        }

        return $trainees;
    }
}

