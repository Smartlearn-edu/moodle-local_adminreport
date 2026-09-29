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
 * English language strings for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Management Intelligence & Advanced Reports';

// Capabilities.
$string['adminreport:view'] = 'View management intelligence dashboard';
$string['adminreport:viewall'] = 'View global reports across all organizations and categories';
$string['adminreport:viewtrainees'] = 'View individual learner identities and at-risk student lists';
$string['adminreport:export'] = 'Export report data to Excel and CSV';
$string['adminreport:configure'] = 'Configure reporting dimensions, sources, and thresholds';
$string['adminreport:manage'] = 'Manage course runs, import schedules, and recalculate analytics';

// Tasks.
$string['task_aggregate_analytics'] = 'Aggregate nightly report facts and update watermark';
$string['task_auto_discover_runs'] = 'Auto-discover program runs from courses';

// Privacy metadata.
$string['privacy:metadata:run_trainers'] = 'Stores trainer assignments to program runs';
$string['privacy:metadata:run_trainers:run_id'] = 'The ID of the program run';
$string['privacy:metadata:run_trainers:userid'] = 'The Moodle user ID of the trainer';
$string['privacy:metadata:run_trainers:trainer_name'] = 'The textual name of the trainer';
$string['privacy:metadata:run_trainers:is_primary'] = 'Whether this trainer is the primary instructor';
$string['privacy:metadata:scope'] = 'Stores scoped access rules for users and roles';
$string['privacy:metadata:scope:scope_type'] = 'The type of scope (user or role)';
$string['privacy:metadata:scope:scope_id'] = 'The user or role ID';
$string['privacy:metadata:scope:dim_member_id'] = 'The dimension member ID assigned to this scope';
$string['privacy:metadata:scope:timemodified'] = 'Timestamp when the scope rule was last modified';
$string['trainer_assignments'] = 'Trainer assignments';
$string['scoping_rules'] = 'Report access scoping rules';

// General terms & navigation.
$string['dashboard'] = 'Dashboard';
$string['runs'] = 'Program Runs';
$string['add_run'] = 'Add Program Run';
$string['edit_run'] = 'Edit Program Run';
$string['run_code'] = 'Run Code';
$string['organizations'] = 'Organizations';
$string['organization'] = 'Organization';
$string['sectors'] = 'Sectors';
$string['sector'] = 'Sector';
$string['locations'] = 'Locations';
$string['location'] = 'Location';
$string['program_types'] = 'Program Classifications';
$string['program_type'] = 'Program Classification';
$string['dimensions'] = 'Dimensions & Classifications';
$string['metrics'] = 'Metrics';
$string['settings'] = 'Settings';
$string['dates'] = 'Dates';
$string['actions'] = 'Actions';

// Logistics & Forms.
$string['schedule_logistics'] = 'Schedule & Logistics';
$string['classroom'] = 'Classroom / Hall';
$string['daily_start_time'] = 'Daily Start Time';
$string['daily_end_time'] = 'Daily End Time';
$string['break_duration_min'] = 'Break Duration (min)';
$string['exam_time'] = 'Exam Time';
$string['trainer'] = 'Trainer';
$string['trainer_name'] = 'Trainer Name';
$string['trainer_userid'] = 'Trainer User ID (optional)';
$string['is_cancelled'] = 'Mark as Cancelled';
$string['uncancel'] = 'Restore Run';
$string['status_updated'] = 'Run status updated successfully.';
$string['enddate_before_startdate'] = 'End date cannot be earlier than start date.';
$string['help_groupid'] = 'Group ID';
$string['help_groupid_help'] = 'If this run corresponds to a specific cohort group within the course, enter the group ID here. Enter 0 for the whole course.';

// Run statuses.
$string['status_cancelled'] = 'Cancelled';
$string['status_planned'] = 'Planned';
$string['status_running'] = 'In Progress';
$string['status_completed'] = 'Completed';

// Importer.
$string['import_runs'] = 'Import Runs';
$string['back_to_runs'] = 'Back to Runs';
$string['download_sample_csv'] = 'Download Sample CSV';
$string['paste_csv_data'] = 'Paste CSV Content';
$string['import'] = 'Import';
$string['import_desc'] = 'Import upcoming and historical program runs in bulk using CSV format.';
$string['import_success'] = 'Successfully imported {$a} run(s).';
$string['import_failed'] = 'Failed to import {$a} run(s). Please review errors:';
$string['empty_file'] = 'The provided CSV content is empty.';

// Dimension resolution & settings.
$string['org_resolution_mode'] = 'Organization Resolution Mode';
$string['org_mode_category'] = 'Strict Course Category';
$string['org_mode_user_profile'] = 'Strict User Profile Field (CompanyName)';
$string['org_mode_hybrid'] = 'Smart Hybrid (Category fallback to Profile Field)';
$string['root_category'] = 'Root Reporting Category';
$string['workweek_mode'] = 'Workweek Schedule';

// Early Warning & At-Risk Trainees.
$string['early_warning'] = 'Early Warning & At-Risk Trainees';
$string['risk_never_accessed'] = 'Learner has not accessed the course yet';
$string['risk_inactive_days'] = 'Inactive for {$a} day(s)';
$string['risk_grade_low'] = 'Current grade is {$a} (below passing threshold)';
$string['trainee_masked'] = 'Trainee #{$a}';
$string['unspecified'] = 'Unspecified';
$string['never'] = 'Never';
$string['total_at_risk'] = 'Total At-Risk Trainees';

// Dashboard & Settings strings.
$string['dashboard_subtitle'] = 'Executive Overview, Operational Schedules, and Multi-Period Intelligence';
$string['org_resolution_mode_desc'] = 'Select how organizations/companies are attributed: via course categories, user profile custom field (CompanyName), or smart hybrid distribution.';
$string['root_category_desc'] = 'Restrict reporting and course auto-discovery to this parent category and its descendants.';
$string['workweek_mode_desc'] = 'Define the working days for daily training hours calculation.';
$string['workweek_sun_thu'] = 'Sunday to Thursday (Saudi Standard)';
$string['workweek_mon_fri'] = 'Monday to Friday (International Standard)';
$string['workweek_all_days'] = 'All 7 Days (Continuous Operation)';
$string['watermark_status'] = 'Warehouse Watermark';
$string['watermark_status_desc'] = 'Last closed day aggregated into the historical warehouse: {$a}. Intraday tier computes live facts thereafter.';

// Period Tabs & Toolbar.
$string['period_week'] = 'Weekly Schedule';
$string['period_month'] = 'Monthly View';
$string['period_annual'] = 'Annual Cumulative';
$string['period_custom'] = 'Custom Date Range';
$string['print'] = 'Print Executive Report';
$string['apply_filters'] = 'Apply Filter';
$string['reset_filters'] = 'Reset Filters';
$string['all_organizations'] = 'All Organizations';
$string['all_program_types'] = 'All Program Classifications';
$string['all_locations'] = 'All Locations';
$string['at_risk_alert_msg'] = 'Identified {$a} learner(s) requiring academic intervention due to prolonged inactivity or failing grades in active runs.';

// KPI Cards.
$string['metric_runs'] = 'Delivered Programs';
$string['metric_trainees'] = 'Trainees (Participations)';
$string['metric_training_hours'] = 'Delivered Training Hours';
$string['metric_completion_and_grade'] = 'Completion & Performance';
$string['ytd'] = 'YTD';
$string['completed_trainees'] = 'Completed';
$string['avg_grade'] = 'Avg Grade';

// Charts.
$string['chart_locations_title'] = 'Trainee Distribution by Branch / Location';
$string['chart_classifications_title'] = 'Delivered Programs by Classification';
$string['chart_trends_title'] = 'Historical Trajectory (Last 6 Months)';

// Operational Table.
$string['operational_schedule'] = 'Operational Training Schedule';
$string['export_excel'] = 'Export to Excel';
$string['trainees_count'] = 'Trainees';
$string['no_runs_found'] = 'No training program runs found matching the selected criteria.';
