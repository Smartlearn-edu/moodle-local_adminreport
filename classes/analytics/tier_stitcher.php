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

        // 3. Retrieve Operational Schedule (Runs list).
        $runs = self::get_operational_runs($start, $end, $allowedorgs, $filters);

        // 4. Generate Chart Distributions.
        $charts = self::generate_chart_data($start, $end, $allowedorgs, $filters);

        $data = [
            'period_type'  => $periodtype,
            'start_date'   => $start,
            'end_date'     => $end,
            'scope_hash'   => $scopehash,
            'metrics'      => (array) $metrics,
            'ytd_metrics'  => (array) $ytdmetrics,
            'runs'         => $runs,
            'charts'       => $charts,
            'generated_at' => time(),
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

            // Estimate/Fetch participant count.
            $participants = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ue.userid)
                   FROM {enrol} e
                   JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  WHERE e.courseid = :cid",
                ['cid' => $r->courseid]
            );

            $runs[] = [
                'id'           => (int) $r->id,
                'run_code'     => $r->run_code,
                'coursename'   => $r->coursename,
                'courseid'     => (int) $r->courseid,
                'orgname'      => $r->orgname ?: '-',
                'typename'     => $r->typename ?: '-',
                'locname'      => $r->locname ?: '-',
                'classroom'    => $r->classroom ?: '-',
                'trainer_name' => $r->trainer_name ?: '-',
                'startdate'    => userdate($r->startdate, get_string('strftimedate')),
                'enddate'      => userdate($r->enddate, get_string('strftimedate')),
                'participants' => (int) $participants,
                'status'       => $status,
                'status_label' => $statuslabel,
                'badge_class'  => $badgeclass,
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
}
