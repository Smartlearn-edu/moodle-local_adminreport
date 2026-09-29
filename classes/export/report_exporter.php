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

namespace local_adminreport\export;

use local_adminreport\analytics\tier_stitcher;

/**
 * Report export engine using Moodle's native core dataformat.
 *
 * Streams operational schedule runs and executive analytics to Excel (.xlsx), CSV, etc.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_exporter {

    /**
     * Export any specified analytical table to a dataformat download.
     *
     * @param string $table Table identifier ('plans_schedule', 'plans_entity', 'plans_branch', 'delivered_summary', 'delivered_corporate', 'trajectory', 'trainees')
     * @param string $dataformat Format plugin name ('excel', 'csv', 'html', etc.)
     * @param string $periodtype Period preset ('week', 'month', 'annual', 'custom')
     * @param int $startdate Start timestamp for custom range
     * @param int $enddate End timestamp for custom range
     * @param array $filterdims Filter dimensions associative array
     * @param int $userid Scoping user ID
     */
    public static function export_table(
        string $table = 'plans_schedule',
        string $dataformat = 'excel',
        string $periodtype = 'week',
        int $startdate = 0,
        int $enddate = 0,
        array $filterdims = [],
        int $userid = 0
    ): void {
        global $CFG;
        require_once($CFG->libdir . '/dataformatlib.php');

        $reportdata = tier_stitcher::get_report_data($periodtype, $startdate, $enddate, $filterdims, $userid);
        $timestr = userdate(time(), '%Y%m%d_%H%M');

        switch ($table) {
            case 'plans_entity':
                $filename = 'plans_by_entity_' . $timestr;
                $columns = [
                    'entity_name'    => get_string('organization', 'local_adminreport'),
                    'program_type'   => get_string('program_type', 'local_adminreport'),
                    'location_name'  => get_string('location', 'local_adminreport'),
                    'programs_count' => get_string('programs_count', 'local_adminreport'),
                    'trainees_count' => get_string('trainees', 'local_adminreport'),
                    'groups_count'   => get_string('groups_count', 'local_adminreport'),
                ];
                $rows = $reportdata['plans_by_entity']['rows'] ?? [];
                break;

            case 'plans_branch':
                $filename = 'plans_by_branch_' . $timestr;
                $columns = [
                    'branch_name'    => get_string('branch', 'local_adminreport'),
                    'courses_count'  => get_string('courses_count', 'local_adminreport'),
                    'trainees_count' => get_string('trainees', 'local_adminreport'),
                ];
                $rows = $reportdata['plans_by_branch']['rows'] ?? [];
                break;

            case 'delivered_corporate':
                $filename = 'delivered_corporate_' . $timestr;
                $columns = [
                    'company_name'   => get_string('company', 'local_adminreport'),
                    'programs_count' => get_string('programs_count', 'local_adminreport'),
                    'trainees_count' => get_string('trainees', 'local_adminreport'),
                ];
                $rows = $reportdata['delivered_corporate']['rows'] ?? [];
                break;

            case 'trajectory':
                $filename = 'annual_trajectory_' . $timestr;
                $columns = [
                    'month_name'          => get_string('month', 'local_adminreport'),
                    'month_runs'          => get_string('month_programs', 'local_adminreport'),
                    'month_trainees'      => get_string('month_trainees', 'local_adminreport'),
                    'cumulative_runs'     => get_string('cumulative_programs', 'local_adminreport'),
                    'cumulative_trainees' => get_string('cumulative_trainees', 'local_adminreport'),
                ];
                $rows = $reportdata['monthly_trajectory']['rows'] ?? [];
                break;

            case 'trainees':
                $filename = 'trainees_report_' . $timestr;
                $columns = [
                    'fullname'     => get_string('trainee_name', 'local_adminreport'),
                    'idnumber'     => get_string('idnumber', 'local_adminreport'),
                    'email'        => get_string('email', 'local_adminreport'),
                    'orgname'      => get_string('organization', 'local_adminreport'),
                    'coursename'   => get_string('course', 'local_adminreport'),
                    'run_code'     => get_string('run_code', 'local_adminreport'),
                    'locname'      => get_string('location', 'local_adminreport'),
                    'status_label' => get_string('status', 'local_adminreport'),
                ];
                $rows = $reportdata['trainees_report'] ?? [];
                break;

            case 'plans_schedule':
            default:
                $filename = 'weekly_training_schedule_' . $timestr;
                $columns = [
                    'run_code'       => get_string('run_code', 'local_adminreport'),
                    'coursename'     => get_string('course', 'local_adminreport'),
                    'orgname'        => get_string('organization', 'local_adminreport'),
                    'typename'       => get_string('program_type', 'local_adminreport'),
                    'locname'        => get_string('location', 'local_adminreport'),
                    'classroom'      => get_string('classroom', 'local_adminreport'),
                    'trainer_name'   => get_string('trainer', 'local_adminreport'),
                    'duration_label' => get_string('duration', 'local_adminreport'),
                    'daily_time'     => get_string('daily_time', 'local_adminreport'),
                    'break_time'     => get_string('break_time', 'local_adminreport'),
                    'exam_time'      => get_string('exam_time', 'local_adminreport'),
                    'participants'   => get_string('trainees', 'local_adminreport'),
                    'status_label'   => get_string('status', 'local_adminreport'),
                ];
                $rows = $reportdata['runs'] ?? [];
                break;
        }

        \core\dataformat::download_data(
            $filename,
            $dataformat,
            $columns,
            $rows
        );
    }

    /**
     * Backward-compatible alias for exporting operational runs.
     */
    public static function export_operational_runs(
        string $dataformat = 'excel',
        string $periodtype = 'week',
        int $startdate = 0,
        int $enddate = 0,
        array $filterdims = [],
        int $userid = 0
    ): void {
        self::export_table('plans_schedule', $dataformat, $periodtype, $startdate, $enddate, $filterdims, $userid);
    }
}
