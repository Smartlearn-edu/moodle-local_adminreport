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
 * Management Intelligence & Operational Analytics Dashboard.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login();
require_capability('local/adminreport:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/adminreport/index.php'));
$PAGE->set_title(get_string('dashboard', 'local_adminreport'));
$PAGE->set_heading(get_string('pluginname', 'local_adminreport'));

// 1. Resolve user scoping and capabilities.
$allowedorgs = \local_adminreport\analytics\tier_stitcher::get_user_allowed_orgs((int) $USER->id);
$canmanage = has_capability('local/adminreport:manage', $context);
$canviewtrainees = has_capability('local/adminreport:viewtrainees', $context);

// 2. Fetch initial stitched report data (default preset: current week).
$reportdata = \local_adminreport\analytics\tier_stitcher::get_report_data('week', 0, 0, [], (int) $USER->id);

// 3. Early warning summary count.
$atrisk = \local_adminreport\analytics\early_warning::evaluate_at_risk_trainees($allowedorgs, null, false);
$totalatrisk = $atrisk['total_at_risk'];

// 4. Retrieve dimension members for dropdown filters (enforcing scoping on organizations).
$orgmembers = [];
$orgtype = $DB->get_record('local_adminreport_dim_types', ['code' => 'organization']);
if ($orgtype) {
    if ($allowedorgs === null) {
        $orgmembers = $DB->get_records('local_adminreport_dim_members', ['dim_type_id' => $orgtype->id], 'name ASC', 'id, name');
    } else if (!empty($allowedorgs)) {
        list($inorgsql, $inorgparams) = $DB->get_in_or_equal($allowedorgs, SQL_PARAMS_NAMED, 'org');
        $orgmembers = $DB->get_records_select(
            'local_adminreport_dim_members',
            "dim_type_id = :typeid AND id $inorgsql",
            array_merge(['typeid' => $orgtype->id], $inorgparams),
            'name ASC',
            'id, name'
        );
    }
}

$typemembers = [];
$typeentry = $DB->get_record('local_adminreport_dim_types', ['code' => 'program_type']);
if ($typeentry) {
    $typemembers = $DB->get_records('local_adminreport_dim_members', ['dim_type_id' => $typeentry->id], 'name ASC', 'id, name');
}

$locmembers = [];
$loctype = $DB->get_record('local_adminreport_dim_types', ['code' => 'location']);
if ($loctype) {
    $locmembers = $DB->get_records('local_adminreport_dim_members', ['dim_type_id' => $loctype->id], 'name ASC', 'id, name');
}

$isrtl = right_to_left();

// 5. Initialise AMD dashboard controller.
$PAGE->requires->js_call_amd('local_adminreport/dashboard', 'init', [[
    'isRtl'         => $isrtl,
    'initialPeriod' => 'week',
    'initialData'   => $reportdata,
]]);

// 6. Assemble template data.
$templatedata = [
    'config'         => [
        'wwwroot' => $CFG->wwwroot,
    ],
    'metrics'        => $reportdata['metrics'],
    'ytd_metrics'    => $reportdata['ytd_metrics'],
    'runs'           => $reportdata['runs'],
    'organizations'  => array_values($orgmembers),
    'program_types'  => array_values($typemembers),
    'locations'      => array_values($locmembers),
    'total_at_risk'  => $totalatrisk,
    'can_manage'     => $canmanage,
    'export_url'     => (new moodle_url('/local/adminreport/export.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_adminreport/dashboard', $templatedata);
echo $OUTPUT->footer();
