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
 * Phase 3 automated verification script for local_adminreport.
 *
 * Usage from Moodle root:
 *   php local/adminreport/cli/test_phase3.php
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('====================================================');
cli_heading('  local_adminreport: Phase 3 Verification Suite');
cli_heading('====================================================');

$passed = 0;
$failed = 0;

function assert_test(string $description, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        cli_writeln("  [PASS] {$description}");
        $passed++;
    } else {
        cli_writeln("  [FAIL] {$description} - {$detail}");
        $failed++;
    }
}

// ---------------------------------------------------------
// 1. Query Engine: Daily Hours & Workweek Calculation
// ---------------------------------------------------------
cli_heading('Test 1: Daily Net Training Hours & Workweek Rules');

$dummyrun = (object) [
    'daily_start_time'   => '08:00',
    'daily_end_time'     => '14:00',
    'break_duration_min' => 30,
];
$nethours = \local_adminreport\analytics\query_engine::calculate_run_daily_hours($dummyrun);
assert_test('Net training hours for 08:00-14:00 minus 30m break is 5.5h', $nethours === 5.5, "Computed: {$nethours}");

$dummyrun2 = (object) [
    'daily_start_time'   => '09:00',
    'daily_end_time'     => '15:00',
    'break_duration_min' => 60,
];
$nethours2 = \local_adminreport\analytics\query_engine::calculate_run_daily_hours($dummyrun2);
assert_test('Net training hours for 09:00-15:00 minus 60m break is 5.0h', $nethours2 === 5.0, "Computed: {$nethours2}");

// Sunday vs Friday in standard sun_thu workweek.
$sundayts = strtotime('next Sunday 10:00:00');
$fridayts = $sundayts + (5 * 86400); // 5 days after Sunday is Friday.
assert_test('Sunday is recognized as working day', \local_adminreport\analytics\query_engine::is_working_day($sundayts));
assert_test('Friday is recognized as non-working weekend', !\local_adminreport\analytics\query_engine::is_working_day($fridayts));

// ---------------------------------------------------------
// 2. Query Engine: Flow vs Stock Fact Calculation
// ---------------------------------------------------------
cli_heading('Test 2: Flow vs Stock Fact Computation');

// Ensure default dimensions exist.
\local_adminreport\analytics\dimension_manager::ensure_default_dimension_types();
$internalorg = \local_adminreport\analytics\dimension_manager::get_or_create_dim_member('organization', 'test_org', 'Test Org');

$runstart = strtotime('2026-09-06 00:00:00'); // Sunday
$runend   = strtotime('2026-09-10 00:00:00'); // Thursday

// Create temporary course and run.
$testcourse = (object)[
    'category'    => 1,
    'fullname'    => 'Test Analytics Course ' . time(),
    'shortname'   => 'TEST_ANALYTICS_' . time(),
    'idnumber'    => 'ANALYTICS-' . time(),
    'startdate'   => $runstart,
    'enddate'     => $runend,
    'visible'     => 1,
    'format'      => 'topics',
];
$courseid = $DB->insert_record('course', $testcourse);

$testrun = (object)[
    'courseid'           => $courseid,
    'groupid'            => 0,
    'run_code'           => 'RUN-FLOW-' . time(),
    'startdate'          => $runstart,
    'enddate'            => $runend,
    'org_dim_id'         => $internalorg,
    'type_dim_id'        => null,
    'location_dim_id'    => null,
    'classroom'          => 'Lab 1',
    'daily_start_time'   => '08:00',
    'daily_end_time'     => '14:00',
    'break_duration_min' => 30,
    'exam_time'          => '11:00',
    'is_cancelled'       => 0,
    'source'             => 'manual',
    'timemodified'       => time(),
];
$runid = $DB->insert_record('local_adminreport_runs', $testrun);
$testrun->id = $runid;

// Enroll 10 trainees in the course.
$enrolinstance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
if (!$enrolinstance) {
    $enrolinstance = (object)[
        'courseid'     => $courseid,
        'enrol'        => 'manual',
        'status'       => 0,
        'timemodified' => time(),
    ];
    $enrolid = $DB->insert_record('enrol', $enrolinstance);
} else {
    $enrolid = $enrolinstance->id;
}

$userids = [];
for ($i = 1; $i <= 10; $i++) {
    $uid = $DB->insert_record('user', (object)[
        'username'   => "flow_user_{$i}_" . time(),
        'email'      => "flow_{$i}_" . time() . "@example.com",
        'firstname'  => "Flow{$i}",
        'lastname'   => "Tester",
        'confirmed'  => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);
    $DB->insert_record('user_enrolments', (object)[
        'enrolid'      => $enrolid,
        'userid'       => $uid,
        'status'       => 0,
        'timestart'    => $runstart,
        'timecreated'  => time(),
        'timemodified' => time(),
    ]);
    $userids[] = $uid;
}

// Compute facts for Sunday (Day 1 - active, but NOT end date).
$sundayfacts = \local_adminreport\analytics\query_engine::compute_run_facts($testrun, $runstart);
assert_test('Sunday produces 1 fact record for the organization', count($sundayfacts) === 1);
if (!empty($sundayfacts)) {
    $sf = $sundayfacts[0];
    assert_test('Participations flow is 0 on Day 1 (not attributed until end date)', $sf->participations_flow === 0, "Got: {$sf->participations_flow}");
    assert_test('Training hours accumulate on Day 1 (5.5h * 10 = 55h)', $sf->day_training_hours == 55.0, "Got: {$sf->day_training_hours}");
}

// Compute facts for Thursday (Day 5 - attribution end date).
$thursdayfacts = \local_adminreport\analytics\query_engine::compute_run_facts($testrun, $runend);
assert_test('Thursday produces 1 fact record for the organization', count($thursdayfacts) === 1);
if (!empty($thursdayfacts)) {
    $tf = $thursdayfacts[0];
    assert_test('Participations flow is 10 on end date (flow logged once)', $tf->participations_flow === 10, "Got: {$tf->participations_flow}");
    assert_test('Training hours accumulate on Day 5 (5.5h * 10 = 55h)', $tf->day_training_hours == 55.0, "Got: {$tf->day_training_hours}");
}

// ---------------------------------------------------------
// 3. Fact Table Aggregation & Rollups
// ---------------------------------------------------------
cli_heading('Test 3: Daily Fact Aggregation & Monthly Rollup');

$aggrcount = \local_adminreport\analytics\query_engine::aggregate_day($runend);
assert_test('aggregate_day writes fact record into daily_stats', $aggrcount >= 1, "Written: {$aggrcount}");

$dailystat = $DB->get_record('local_adminreport_daily_stats', [
    'stat_date'  => $runend,
    'run_id'     => $runid,
    'org_dim_id' => $internalorg,
]);
assert_test('Daily stat record persisted correctly', !empty($dailystat) && $dailystat->participations_flow === 10);

$monthlycount = \local_adminreport\analytics\query_engine::aggregate_month(2026, 9);
assert_test('aggregate_month rolls up daily stats into monthly_stats', $monthlycount >= 1, "Written: {$monthlycount}");

// ---------------------------------------------------------
// 4. Tier Stitcher & Watermark Seam
// ---------------------------------------------------------
cli_heading('Test 4: Watermark Tier Seam Stitching');

// Test Case: Watermark at Wednesday, end date on Thursday.
$watermarkwednesday = $runstart + (3 * 86400);
set_config('last_aggregated_watermark', $watermarkwednesday, 'local_adminreport');

$stitched = \local_adminreport\analytics\tier_stitcher::get_stitched_metrics(
    $runstart,
    $runend,
    null,
    []
);
assert_test('Stitched metrics return valid object', is_object($stitched));
assert_test('Stitched participations count includes Thursday flow', $stitched->participations_count >= 10, "Got: {$stitched->participations_count}");

// Test Scope Hashing.
$hash1 = \local_adminreport\analytics\tier_stitcher::get_scope_hash(null);
$hash2 = \local_adminreport\analytics\tier_stitcher::get_scope_hash([1, 2, 3]);
$hash3 = \local_adminreport\analytics\tier_stitcher::get_scope_hash([3, 2, 1]);
assert_test('Global scope hash is "global"', $hash1 === 'global');
assert_test('Scope hash is order-independent', $hash2 === $hash3);

// ---------------------------------------------------------
// 5. Early Warning Trainee Evaluation
// ---------------------------------------------------------
cli_heading('Test 5: Early Warning Evaluation');

$atrisk = \local_adminreport\analytics\early_warning::evaluate_at_risk_trainees(null, null, false);
assert_test('Early warning evaluation returns valid structure', isset($atrisk['total_at_risk']) && isset($atrisk['trainees']));

// ---------------------------------------------------------
// 6. Aggregate Analytics Scheduled Task
// ---------------------------------------------------------
cli_heading('Test 6: Aggregate Analytics Scheduled Task');

$task = new \local_adminreport\task\aggregate_analytics();
assert_test('Scheduled task instantiated successfully', !empty($task->get_name()));

// ---------------------------------------------------------
// Cleanup test fixtures
// ---------------------------------------------------------
$DB->delete_records('local_adminreport_daily_stats', ['run_id' => $runid]);
$DB->delete_records('local_adminreport_monthly_stats', ['year' => 2026, 'month' => 9]);
$DB->delete_records('local_adminreport_runs', ['id' => $runid]);
foreach ($userids as $uid) {
    $DB->delete_records('user_enrolments', ['userid' => $uid]);
    $DB->delete_records('user', ['id' => $uid]);
}
$DB->delete_records('enrol', ['id' => $enrolid]);
$DB->delete_records('course', ['id' => $courseid]);

// ---------------------------------------------------------
// Final Summary
// ---------------------------------------------------------
cli_heading('====================================================');
cli_writeln("Phase 3 Test Results: {$passed} Passed, {$failed} Failed");
cli_heading('====================================================');

if ($failed > 0) {
    exit(1);
}
exit(0);
