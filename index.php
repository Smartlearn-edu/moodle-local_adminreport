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

$period = optional_param('period', 'week', PARAM_ALPHA);
$start  = optional_param('start', 0, PARAM_INT);
$end    = optional_param('end', 0, PARAM_INT);
$tab    = optional_param('tab', 'plans', PARAM_ALPHA);

// 1. Resolve user scoping and capabilities.
$allowedorgs = \local_adminreport\analytics\tier_stitcher::get_user_allowed_orgs((int) $USER->id);
$canmanage = has_capability('local/adminreport:manage', $context);
$canviewtrainees = has_capability('local/adminreport:viewtrainees', $context);

// 2. Fetch initial stitched report data according to requested period.
$reportdata = \local_adminreport\analytics\tier_stitcher::get_report_data($period, $start, $end, [], (int) $USER->id);

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
    'initialPeriod' => $period,
    'initialTab'    => $tab,
    'initialData'   => $reportdata,
]]);

// Action handlers.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'sync_courses' && confirm_sesskey()) {
    require_capability('local/adminreport:manage', $context);
    $discovered = \local_adminreport\task\auto_discover_runs::execute();
    \core\notification::success(get_string('courses_synced_success', 'local_adminreport', $discovered));
    redirect(new moodle_url('/local/adminreport/index.php', ['period' => $period, 'tab' => $tab]));
}

// 6. Assemble template data.
$exportparams = ['period' => $period, 'start' => $start, 'end' => $end];

$templatedata = [
    'config'                   => [
        'wwwroot' => $CFG->wwwroot,
    ],
    'period_type'              => $reportdata['period_type'],
    'start_date_formatted'     => $reportdata['start_date_formatted'],
    'end_date_formatted'       => $reportdata['end_date_formatted'],
    'is_period_week'           => ($period === 'week'),
    'is_period_month'          => ($period === 'month'),
    'is_period_annual'         => ($period === 'annual'),
    'is_period_custom'         => ($period === 'custom'),
    'period_week_url'          => (new moodle_url('/local/adminreport/index.php', ['period' => 'week', 'tab' => $tab]))->out(false),
    'period_month_url'         => (new moodle_url('/local/adminreport/index.php', ['period' => 'month', 'tab' => $tab]))->out(false),
    'period_annual_url'        => (new moodle_url('/local/adminreport/index.php', ['period' => 'annual', 'tab' => $tab]))->out(false),
    'active_tab'               => $tab,
    'is_tab_plans'             => ($tab === 'plans'),
    'is_tab_delivered'         => ($tab === 'delivered'),
    'is_tab_trainees'          => ($tab === 'trainees'),
    'is_tab_management'        => ($tab === 'management'),
    'metrics'                  => $reportdata['metrics'],
    'ytd_metrics'              => $reportdata['ytd_metrics'],
    'runs'                     => $reportdata['runs'],
    'runs_count'               => $reportdata['runs_count'],
    'has_runs'                 => !empty($reportdata['runs']),
    'plans_by_entity'          => $reportdata['plans_by_entity'],
    'has_plans_by_entity'      => !empty($reportdata['plans_by_entity']['rows']),
    'plans_by_branch'          => $reportdata['plans_by_branch'],
    'has_plans_by_branch'      => !empty($reportdata['plans_by_branch']['rows']),
    'delivered_classification' => $reportdata['delivered_classification'],
    'delivered_sectors'        => $reportdata['delivered_sectors'],
    'delivered_corporate'      => $reportdata['delivered_corporate'],
    'has_delivered_corporate'  => !empty($reportdata['delivered_corporate']['rows']),
    'delivered_pies'           => $reportdata['delivered_pies'],
    'monthly_trajectory'       => $reportdata['monthly_trajectory'],
    'has_monthly_trajectory'   => !empty($reportdata['monthly_trajectory']['rows']),
    'strategic_partners'       => $reportdata['strategic_partners'],
    'has_strategic_partners'   => !empty($reportdata['strategic_partners']),
    'cumulative_summary'       => $reportdata['cumulative_summary'],
    'trainees_report'          => $reportdata['trainees_report'],
    'has_trainees_report'      => !empty($reportdata['trainees_report']),
    'trainees_count'           => $reportdata['trainees_count'],
    'organizations'            => array_values($orgmembers),
    'program_types'            => array_values($typemembers),
    'locations'                => array_values($locmembers),
    'total_at_risk'            => $totalatrisk,
    'can_manage'               => $canmanage,
    'can_view_trainees'        => $canviewtrainees,
    'sesskey'                  => sesskey(),
    'export_url_runs'          => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'plans_schedule'], $exportparams)))->out(false),
    'export_url_entity'        => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'plans_entity'], $exportparams)))->out(false),
    'export_url_branch'        => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'plans_branch'], $exportparams)))->out(false),
    'export_url_delivered'     => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'delivered_summary'], $exportparams)))->out(false),
    'export_url_corporate'     => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'delivered_corporate'], $exportparams)))->out(false),
    'export_url_trajectory'    => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'trajectory'], $exportparams)))->out(false),
    'export_url_trainees'      => (new moodle_url('/local/adminreport/export.php', array_merge(['table' => 'trainees'], $exportparams)))->out(false),
    'runs_manage_url'          => (new moodle_url('/local/adminreport/runs.php'))->out(false),
    'import_url'               => (new moodle_url('/local/adminreport/import.php'))->out(false),
    'sync_courses_url'         => (new moodle_url('/local/adminreport/index.php', ['action' => 'sync_courses', 'period' => $period, 'tab' => $tab, 'sesskey' => sesskey()]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_adminreport/dashboard', $templatedata);
echo $OUTPUT->footer();
