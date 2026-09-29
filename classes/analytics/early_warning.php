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
 * Early warning service for detecting at-risk learners in active program runs.
 *
 * Evaluates learner activity recency, assessment progress, and attendance.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class early_warning {

    /** @var int Inactivity threshold in seconds (default: 3 days) */
    public const INACTIVITY_THRESHOLD = 259200;

    /** @var float Grade failure threshold (default: 60%) */
    public const GRADE_THRESHOLD_PERCENT = 60.0;

    /**
     * Evaluate active runs and return summary and trainee list of at-risk learners.
     *
     * @param int[]|null $allowedorgs Scoping filter (null for all).
     * @param int|null $runid Optional specific run ID to filter.
     * @param bool $canviewtrainees Whether the user has local/adminreport:viewtrainees capability.
     * @return array Structure containing summary metrics and list of at-risk trainees.
     */
    public static function evaluate_at_risk_trainees(
        ?array $allowedorgs = null,
        ?int $runid = null,
        bool $canviewtrainees = false
    ): array {
        global $DB;

        $now = time();

        // 1. Identify active running runs.
        $params = ['now1' => $now, 'now2' => $now];
        $wheres = ['r.is_cancelled = 0', 'r.startdate <= :now1', 'r.enddate >= :now2'];

        if ($runid !== null && $runid > 0) {
            $wheres[] = 'r.id = :runid';
            $params['runid'] = $runid;
        }

        if ($allowedorgs !== null) {
            if (empty($allowedorgs)) {
                return ['total_at_risk' => 0, 'trainees' => []];
            }
            list($scopein, $scopeparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'sc');
            $wheres[] = "r.org_dim_id $scopein";
            $params = array_merge($params, $scopeparams);
        }

        $whereclause = implode(' AND ', $wheres);
        $sql = "SELECT r.*,
                       c.fullname AS coursename,
                       om.name AS orgname
                  FROM {local_adminreport_runs} r
                  JOIN {course} c ON c.id = r.courseid
             LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
                 WHERE {$whereclause}";

        $runs = $DB->get_records_sql($sql, $params);
        if (empty($runs)) {
            return ['total_at_risk' => 0, 'trainees' => []];
        }

        $atrisktrainees = [];

        foreach ($runs as $run) {
            $userids = dimension_manager::get_run_enrolled_userids($run);
            if (empty($userids)) {
                continue;
            }

            list($uinsql, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

            // Query last course access.
            $laparams = array_merge(['courseid' => $run->courseid], $uinparams);
            $lastaccesssql = "SELECT userid, timeaccess
                                FROM {user_lastaccess}
                               WHERE courseid = :courseid AND userid $uinsql";
            $lastaccessmap = $DB->get_records_sql_menu($lastaccesssql, $laparams);

            // Query course final grades.
            $gradeparams = array_merge(['courseid' => $run->courseid], $uinparams);
            $gradesql = "SELECT gg.userid, gg.finalgrade, gi.grademax
                           FROM {grade_grades} gg
                           JOIN {grade_items} gi ON gi.id = gg.itemid
                          WHERE gi.courseid = :courseid
                            AND gi.itemtype = 'course'
                            AND gg.userid $uinsql";
            $graderecords = $DB->get_records_sql($gradesql, $gradeparams);

            // Fetch user identity data.
            $userrecords = $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, email');

            foreach ($userids as $uid) {
                $uid = (int) $uid;
                $user = $userrecords[$uid] ?? null;
                if (!$user) {
                    continue;
                }

                // Check organization scoping if run is shared.
                $userorgid = dimension_manager::resolve_user_organization($uid) ?: $run->org_dim_id;
                if ($allowedorgs !== null && !in_array($userorgid, $allowedorgs, true)) {
                    continue;
                }

                $reasons = [];

                // Check 1: Inactivity.
                $lastaccess = isset($lastaccessmap[$uid]) ? (int) $lastaccessmap[$uid] : 0;
                if ($lastaccess === 0) {
                    $reasons[] = get_string('risk_never_accessed', 'local_adminreport');
                } else if (($now - $lastaccess) > self::INACTIVITY_THRESHOLD) {
                    $daysago = (int) floor(($now - $lastaccess) / 86400);
                    $reasons[] = get_string('risk_inactive_days', 'local_adminreport', $daysago);
                }

                // Check 2: Low Grade.
                $gradepercent = null;
                if (isset($graderecords[$uid]) && $graderecords[$uid]->finalgrade !== null) {
                    $grademax = (float) ($graderecords[$uid]->grademax ?: 100);
                    $finalgrade = (float) $graderecords[$uid]->finalgrade;
                    $gradepercent = $grademax > 0 ? ($finalgrade / $grademax) * 100 : 0.0;

                    if ($gradepercent < self::GRADE_THRESHOLD_PERCENT) {
                        $reasons[] = get_string('risk_grade_low', 'local_adminreport', round($gradepercent, 1) . '%');
                    }
                }

                if (!empty($reasons)) {
                    // Respect privacy capability for individual trainee identities.
                    $displayname = $canviewtrainees
                        ? fullname($user)
                        : get_string('trainee_masked', 'local_adminreport', $uid);
                    $email = $canviewtrainees ? $user->email : '';

                    $atrisktrainees[] = [
                        'userid'        => $uid,
                        'fullname'      => $displayname,
                        'email'         => $email,
                        'courseid'      => (int) $run->courseid,
                        'coursename'    => $run->coursename,
                        'run_code'      => $run->run_code,
                        'orgname'       => $run->orgname ?: '-',
                        'lastaccess'    => $lastaccess > 0 ? userdate($lastaccess, get_string('strftimedate')) : get_string('never'),
                        'grade_percent' => $gradepercent !== null ? round($gradepercent, 1) . '%' : '-',
                        'risk_reasons'  => $reasons,
                    ];
                }
            }
        }

        return [
            'total_at_risk' => count($atrisktrainees),
            'trainees'      => $atrisktrainees,
        ];
    }
}
