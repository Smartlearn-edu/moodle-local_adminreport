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

/**
 * Controller endpoint for exporting operational schedule and analytics data.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();

// Check export capability (fallback to view if export capability is not explicitly denied).
if (!has_capability('local/adminreport:export', $context)) {
    require_capability('local/adminreport:view', $context);
}

$table     = optional_param('table', 'plans_schedule', PARAM_ALPHAEXT);
$format    = optional_param('format', 'excel', PARAM_ALPHA);
$period    = optional_param('period', 'week', PARAM_ALPHA);
$startdate = optional_param('start_date', optional_param('start', 0, PARAM_INT), PARAM_INT);
$enddate   = optional_param('end_date', optional_param('end', 0, PARAM_INT), PARAM_INT);
$orgdimid  = optional_param('org_dim_id', 0, PARAM_INT);
$typedimid = optional_param('type_dim_id', 0, PARAM_INT);
$locdimid  = optional_param('loc_dim_id', 0, PARAM_INT);

// Resolve scoping permissions.
$allowedorgs = \local_adminreport\analytics\tier_stitcher::get_user_allowed_orgs((int) $USER->id);
$filterdims = [];

if ($orgdimid > 0) {
    if ($allowedorgs === null || in_array($orgdimid, $allowedorgs)) {
        $filterdims['organization'] = $orgdimid;
    }
}
if ($typedimid > 0) {
    $filterdims['program_type'] = $typedimid;
}
if ($locdimid > 0) {
    $filterdims['location'] = $locdimid;
}

\local_adminreport\export\report_exporter::export_table(
    $table,
    $format,
    $period,
    $startdate,
    $enddate,
    $filterdims,
    (int) $USER->id
);
