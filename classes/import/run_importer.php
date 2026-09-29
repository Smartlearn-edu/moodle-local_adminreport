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

namespace local_adminreport\import;

use local_adminreport\analytics\dimension_manager;

/**
 * Batch CSV/XLSX importer for program runs.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_importer {

    /**
     * Import runs from a CSV file content or path.
     *
     * @param string $csvcontent The raw CSV content.
     * @return \stdClass Results object with counts and errors.
     */
    public static function import_csv(string $csvcontent): \stdClass {
        global $DB;

        $results = (object) [
            'imported' => 0,
            'updated'  => 0,
            'failed'   => 0,
            'errors'   => [],
        ];

        $lines = preg_split('/\r\n|\r|\n/', trim($csvcontent));
        if (empty($lines)) {
            $results->errors[] = get_string('empty_file', 'local_adminreport');
            return $results;
        }

        // Header detection.
        $header = str_getcsv(array_shift($lines));
        $header = array_map(function($h) {
            return strtolower(trim(str_replace([' ', '_', '-'], '', $h)));
        }, $header);

        $lineidx = 1;
        foreach ($lines as $line) {
            $lineidx++;
            if (empty(trim($line))) {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 3) {
                $results->failed++;
                $results->errors[] = "Line {$lineidx}: Insufficient columns.";
                continue;
            }

            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            // Identify course.
            $courseid = 0;
            if (!empty($data['courseid'])) {
                $courseid = (int) $data['courseid'];
            } else if (!empty($data['courseidnumber'])) {
                $courseid = (int) $DB->get_field('course', 'id', ['idnumber' => $data['courseidnumber']]);
            } else if (!empty($data['courseshortname'])) {
                $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $data['courseshortname']]);
            }

            if (!$courseid) {
                $results->failed++;
                $results->errors[] = "Line {$lineidx}: Course not found.";
                continue;
            }

            $runcode = !empty($data['runcode']) ? $data['runcode'] : 'RUN-' . $courseid;
            $startdate = !empty($data['startdate']) ? (is_numeric($data['startdate']) ? (int)$data['startdate'] : strtotime($data['startdate'])) : time();
            $enddate = !empty($data['enddate']) ? (is_numeric($data['enddate']) ? (int)$data['enddate'] : strtotime($data['enddate'])) : ($startdate + (5 * 86400));

            // Resolve Dimensions.
            $orgdimid = null;
            if (!empty($data['organization'])) {
                $orgcode = dimension_manager::slugify($data['organization']);
                $sectorid = dimension_manager::detect_sector_for_org($data['organization']);
                $orgdimid = dimension_manager::get_or_create_dim_member('organization', $orgcode, $data['organization'], $sectorid);
            }

            $typedimid = null;
            if (!empty($data['programtype'])) {
                $typecode = dimension_manager::detect_program_type($data['programtype']);
                if ($typecode) {
                    $typedimid = dimension_manager::get_or_create_dim_member('program_type', $typecode, $data['programtype']);
                }
            }

            $locdimid = null;
            if (!empty($data['location'])) {
                $loccode = dimension_manager::detect_location($data['location']) ?: dimension_manager::slugify($data['location']);
                $locdimid = dimension_manager::get_or_create_dim_member('location', $loccode, $data['location']);
            }

            $classroom = $data['classroom'] ?? '';
            $starttime = $data['dailystarttime'] ?? '08:00';
            $endtime = $data['dailyendtime'] ?? '14:00';
            $breakdur = isset($data['breakdurationmin']) ? (int) $data['breakdurationmin'] : 30;
            $examtime = $data['examtime'] ?? '11:00';

            // Insert Run.
            $runrecord = (object) [
                'courseid'           => $courseid,
                'groupid'            => 0,
                'run_code'           => $runcode,
                'startdate'          => $startdate,
                'enddate'            => $enddate,
                'org_dim_id'         => $orgdimid,
                'type_dim_id'        => $typedimid,
                'location_dim_id'    => $locdimid,
                'classroom'          => $classroom,
                'daily_start_time'   => $starttime,
                'daily_end_time'     => $endtime,
                'break_duration_min' => $breakdur,
                'exam_time'          => $examtime,
                'is_cancelled'       => 0,
                'source'             => 'imported',
                'timemodified'       => time(),
            ];

            $newrunid = $DB->insert_record('local_adminreport_runs', $runrecord);

            // Handle Trainer.
            if (!empty($data['trainername'])) {
                $trainerrecord = (object) [
                    'run_id'       => $newrunid,
                    'userid'       => null,
                    'trainer_name' => $data['trainername'],
                    'is_primary'   => 1,
                ];
                $DB->insert_record('local_adminreport_run_trainers', $trainerrecord);
            }

            $results->imported++;
        }

        return $results;
    }
}
