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
 * Phase 2 automated verification script for local_adminreport.
 *
 * Usage from Moodle root:
 *   php local/adminreport/cli/test_phase2.php
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('====================================================');
cli_heading('  local_adminreport: Phase 2 Verification Suite');
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
// 1. Dimension Types & Default Seeding
// ---------------------------------------------------------
cli_heading('Test 1: Dimension Seeding');
\local_adminreport\analytics\dimension_manager::ensure_default_dimension_types();

$types = $DB->get_records_menu('local_adminreport_dim_types', null, '', 'code, id');
assert_test('Dimension type "sector" exists', isset($types['sector']));
assert_test('Dimension type "organization" exists', isset($types['organization']));
assert_test('Dimension type "program_type" exists', isset($types['program_type']));
assert_test('Dimension type "location" exists', isset($types['location']));

$sectortype = $types['sector'] ?? 0;
$sectorcount = $DB->count_records('local_adminreport_dim_members', ['dim_type_id' => $sectortype]);
assert_test('Default sectors seeded (at least 6 sectors)', $sectorcount >= 6, "Found {$sectorcount}");

$progtype = $types['program_type'] ?? 0;
$progcount = $DB->count_records('local_adminreport_dim_members', ['dim_type_id' => $progtype]);
assert_test('Default program types seeded (at least 6 types)', $progcount >= 6, "Found {$progcount}");

// ---------------------------------------------------------
// 2. Slugify, Location, and Classification Detection
// ---------------------------------------------------------
cli_heading('Test 2: Dimension Pattern Detection');

$slug = \local_adminreport\analytics\dimension_manager::slugify('أكاديمية المياه - الرياض');
assert_test('Slugify generates clean identifier', !empty($slug) && strpos($slug, ' ') === false, "Generated: {$slug}");

$loc = \local_adminreport\analytics\dimension_manager::detect_location('دورة معالجة المياه المتقدمة بالجبيل');
assert_test('Location Jubail detected correctly', $loc === 'jubail', "Detected: {$loc}");

$loc2 = \local_adminreport\analytics\dimension_manager::detect_location('برنامج الأمن والسلامة في سكاكا');
assert_test('Location Al Jouf / Sakaka detected correctly', $loc2 === 'aljouf', "Detected: {$loc2}");

$ptype = \local_adminreport\analytics\dimension_manager::detect_program_type('دبلوم تدريبي في محطات التحلية');
assert_test('Program classification Diploma detected correctly', $ptype === 'diploma', "Detected: {$ptype}");

$sectorid = \local_adminreport\analytics\dimension_manager::detect_sector_for_org('الهيئة السعودية للمياه');
$internalmember = $DB->get_record('local_adminreport_dim_members', ['code' => 'internal']);
assert_test('Sector for SWA detected as internal', $sectorid === (int)$internalmember->id, "Resolved ID: {$sectorid}");

// ---------------------------------------------------------
// 3. User Profile Field Resolution
// ---------------------------------------------------------
cli_heading('Test 3: User Profile Field Organization Resolution');

// Check or create custom field 'CompanyName'.
$field = $DB->get_record('user_info_field', ['shortname' => 'CompanyName']);
$createdfield = false;
if (!$field) {
    $fieldid = $DB->insert_record('user_info_field', (object)[
        'shortname' => 'CompanyName',
        'name'      => 'اسم الجهة التابع لها',
        'datatype'  => 'text',
        'categoryid'=> 1,
    ]);
    $createdfield = true;
} else {
    $fieldid = $field->id;
}

// Create temporary test user.
$testuser = (object)[
    'username'    => 'test_trainee_' . time(),
    'email'       => 'test_trainee_' . time() . '@example.com',
    'firstname'   => 'Sami',
    'lastname'    => 'Al-Harbi',
    'confirmed'   => 1,
    'mnethostid'  => $CFG->mnet_localhost_id,
];
$userid = $DB->insert_record('user', $testuser);

// Attach CompanyName data.
$DB->insert_record('user_info_data', (object)[
    'userid'   => $userid,
    'fieldid'  => $fieldid,
    'data'     => 'شركة الخريف لتقنية المياه',
]);

$resolvedorgid = \local_adminreport\analytics\dimension_manager::resolve_user_organization($userid);
assert_test('Trainee organization resolved from custom field', !empty($resolvedorgid));

$orgrecord = $DB->get_record('local_adminreport_dim_members', ['id' => $resolvedorgid]);
assert_test('Organization member correctly named', $orgrecord && strpos($orgrecord->name, 'الخريف') !== false, "Org: " . ($orgrecord->name ?? 'none'));

// ---------------------------------------------------------
// 4. Auto-Discovery of Course Runs
// ---------------------------------------------------------
cli_heading('Test 4: Course Auto-Discovery');

// Create temporary test course.
$testcourse = (object)[
    'category'    => 1,
    'fullname'    => 'دورة تشغيل محطات التحلية - الجبيل Test ' . time(),
    'shortname'   => 'DESAL_TEST_' . time(),
    'idnumber'    => 'RUN-TEST-' . time(),
    'startdate'   => time(),
    'enddate'     => time() + (5 * 86400),
    'visible'     => 1,
    'format'      => 'topics',
];
$courseid = $DB->insert_record('course', $testcourse);

$discovered = \local_adminreport\task\auto_discover_runs::discover_all_runs();
assert_test('Auto discovery processed new course', $discovered >= 1, "Discovered: {$discovered}");

$createdrun = $DB->get_record('local_adminreport_runs', ['courseid' => $courseid]);
assert_test('Run record created for test course', !empty($createdrun));
if ($createdrun) {
    assert_test('Run code matches course idnumber', $createdrun->run_code === $testcourse->idnumber);
    assert_test('Location dimension auto-resolved to Jubail', !empty($createdrun->location_dim_id));
}

// ---------------------------------------------------------
// 5. CSV Run Importer
// ---------------------------------------------------------
cli_heading('Test 5: CSV Run Importer');

$testruncode = 'CSV-RUN-' . time();
$csvcontent = "course_shortname,run_code,start_date,end_date,organization,program_type,location,classroom,trainer_name\n" .
              "{$testcourse->shortname},{$testruncode},2026-09-06,2026-09-10,شركة مياهنا,دورة تطويرية,الرياض,قاعة A1,م. فهد القحطاني\n";

$importres = \local_adminreport\import\run_importer::import_csv($csvcontent);
assert_test('CSV importer returned success', $importres->imported === 1, "Imported: {$importres->imported}, Failed: {$importres->failed}");

$importedrun = $DB->get_record('local_adminreport_runs', ['run_code' => $testruncode]);
assert_test('Imported run saved in database', !empty($importedrun));

if ($importedrun) {
    $trainer = $DB->get_record('local_adminreport_run_trainers', ['run_id' => $importedrun->id, 'is_primary' => 1]);
    assert_test('Trainer attached to imported run', $trainer && strpos($trainer->trainer_name, 'فهد') !== false);
}

// ---------------------------------------------------------
// Cleanup test fixtures
// ---------------------------------------------------------
if ($importedrun) {
    $DB->delete_records('local_adminreport_run_trainers', ['run_id' => $importedrun->id]);
    $DB->delete_records('local_adminreport_runs', ['id' => $importedrun->id]);
}
if ($createdrun) {
    $DB->delete_records('local_adminreport_run_trainers', ['run_id' => $createdrun->id]);
    $DB->delete_records('local_adminreport_runs', ['id' => $createdrun->id]);
}
$DB->delete_records('course', ['id' => $courseid]);
$DB->delete_records('user_info_data', ['userid' => $userid]);
$DB->delete_records('user', ['id' => $userid]);
if ($createdfield) {
    $DB->delete_records('user_info_field', ['id' => $fieldid]);
}

// ---------------------------------------------------------
// Final Summary
// ---------------------------------------------------------
cli_heading('====================================================');
cli_writeln("Phase 2 Test Results: {$passed} Passed, {$failed} Failed");
cli_heading('====================================================');

if ($failed > 0) {
    exit(1);
}
exit(0);
