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

namespace local_adminreport\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_adminreport\analytics\tier_stitcher;
use local_adminreport\analytics\early_warning;

/**
 * External Web Service API for fetching report data in local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report_data extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'period_type' => new external_value(PARAM_ALPHA, 'Preset period type (week, month, annual, custom)', VALUE_DEFAULT, 'week'),
            'start_date'  => new external_value(PARAM_INT, 'Start timestamp for custom period', VALUE_DEFAULT, 0),
            'end_date'    => new external_value(PARAM_INT, 'End timestamp for custom period', VALUE_DEFAULT, 0),
            'org_dim_id'  => new external_value(PARAM_INT, 'Organization dimension filter', VALUE_DEFAULT, 0),
            'type_dim_id' => new external_value(PARAM_INT, 'Program type dimension filter', VALUE_DEFAULT, 0),
            'loc_dim_id'  => new external_value(PARAM_INT, 'Location dimension filter', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute method to retrieve report data.
     *
     * @param string $period_type
     * @param int $start_date
     * @param int $end_date
     * @param int $org_dim_id
     * @param int $type_dim_id
     * @param int $loc_dim_id
     * @return array
     */
    public static function execute(
        string $period_type = 'week',
        int $start_date = 0,
        int $end_date = 0,
        int $org_dim_id = 0,
        int $type_dim_id = 0,
        int $loc_dim_id = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'period_type' => $period_type,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'org_dim_id'  => $org_dim_id,
            'type_dim_id' => $type_dim_id,
            'loc_dim_id'  => $loc_dim_id,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/adminreport:view', $context);

        $filters = [
            'org_dim_id'      => $params['org_dim_id'],
            'type_dim_id'     => $params['type_dim_id'],
            'location_dim_id' => $params['loc_dim_id'],
        ];

        // 1. Fetch stitched report data with MUC caching.
        $data = tier_stitcher::get_report_data(
            $params['period_type'],
            $params['start_date'],
            $params['end_date'],
            $filters,
            (int) $USER->id
        );

        // 2. Attach early warning summary count.
        $allowedorgs = tier_stitcher::get_user_allowed_orgs((int) $USER->id);
        $atrisk = early_warning::evaluate_at_risk_trainees($allowedorgs, null, false);
        $data['total_at_risk'] = $atrisk['total_at_risk'];

        return [
            'status' => 'success',
            'data'   => json_encode($data),
        ];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Status of request (success / error)'),
            'data'   => new external_value(PARAM_RAW, 'JSON encoded report dataset'),
        ]);
    }
}
