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
     * Export operational schedule runs to a dataformat download.
     *
     * @param string $dataformat Format plugin name ('excel', 'csv', 'html', etc.)
     * @param string $periodtype Period preset ('week', 'month', 'annual', 'custom')
     * @param int $startdate Start timestamp for custom range
     * @param int $enddate End timestamp for custom range
     * @param array $filterdims Filter dimensions associative array
     * @param int $userid Scoping user ID
     */
    public static function export_operational_runs(
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
        $runs = $reportdata['runs'] ?? [];

        $filename = 'training_schedule_' . userdate(time(), '%Y%m%d_%H%M');

        $columns = [
            'run_code'         => get_string('run_code', 'local_adminreport'),
            'coursename'       => get_string('course', 'local_adminreport'),
            'orgname'          => get_string('organization', 'local_adminreport'),
            'typename'         => get_string('program_type', 'local_adminreport'),
            'locname'          => get_string('location', 'local_adminreport'),
            'startdate'        => get_string('startdate', 'local_adminreport'),
            'enddate'          => get_string('enddate', 'local_adminreport'),
            'classroom'        => get_string('classroom', 'local_adminreport'),
            'trainer_name'     => get_string('trainer', 'local_adminreport'),
            'participants'     => get_string('trainees', 'local_adminreport'),
            'status_label'     => get_string('status', 'local_adminreport'),
        ];

        $exportrows = [];
        foreach ($runs as $run) {
            $exportrows[] = [
                'run_code'     => $run['run_code'] ?? '',
                'coursename'   => $run['coursename'] ?? '',
                'orgname'      => $run['orgname'] ?? '',
                'typename'     => $run['typename'] ?? '',
                'locname'      => $run['locname'] ?? '',
                'startdate'    => $run['startdate'] ?? '',
                'enddate'      => $run['enddate'] ?? '',
                'classroom'    => $run['classroom'] ?? '',
                'trainer_name' => $run['trainer_name'] ?? '',
                'participants' => $run['participants'] ?? 0,
                'status_label' => $run['status_label'] ?? '',
            ];
        }

        \core\dataformat::download_data(
            $filename,
            $dataformat,
            $columns,
            $exportrows
        );
    }
}
