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
 * Phase 1 automated verification script for local_adminreport.
 *
 * Usage from Moodle root:
 * php local/adminreport/cli/test_phase1.php
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('====================================================');
cli_heading('  local_adminreport: Phase 1 Verification Suite');
cli_heading('====================================================');

$dbman = $DB->get_manager();
$passed = 0;
$failed = 0;

// 1. Check Database Tables.
cli_writeln("\n[1] Checking Database Tables (7 expected)...");
$tables = [
    'local_adminreport_dim_types',
    'local_adminreport_dim_members',
    'local_adminreport_runs',
    'local_adminreport_run_trainers',
    'local_adminreport_daily_stats',
    'local_adminreport_monthly_stats',
    'local_adminreport_scope',
];

foreach ($tables as $table) {
    if ($dbman->table_exists($table)) {
        cli_writeln("  [PASS] Table exists: mdl_{$table}");
        $passed++;
    } else {
        cli_problem("  [FAIL] Table missing: mdl_{$table}");
        $failed++;
    }
}

// 2. Check Capabilities.
cli_writeln("\n[2] Checking Capabilities in DB (6 expected)...");
$caps = [
    'local/adminreport:view',
    'local/adminreport:viewall',
    'local/adminreport:viewtrainees',
    'local/adminreport:export',
    'local/adminreport:configure',
    'local/adminreport:manage',
];

foreach ($caps as $cap) {
    $exists = $DB->record_exists('capabilities', ['name' => $cap]);
    if ($exists) {
        cli_writeln("  [PASS] Capability registered: {$cap}");
        $passed++;
    } else {
        cli_problem("  [FAIL] Capability not found in mdl_capabilities: {$cap}");
        $failed++;
    }
}

// 3. Check Scheduled Tasks.
cli_writeln("\n[3] Checking Scheduled Tasks...");
$taskclasses = [
    '\local_adminreport\task\aggregate_analytics',
    '\local_adminreport\task\auto_discover_runs',
];

foreach ($taskclasses as $taskclass) {
    if (class_exists($taskclass)) {
        $task = \core\task\manager::get_scheduled_task($taskclass);
        if ($task) {
            cli_writeln("  [PASS] Task registered and runnable: {$taskclass} ('" . $task->get_name() . "')");
            $passed++;
        } else {
            cli_problem("  [FAIL] Task class exists but not found in core\\task\\manager: {$taskclass}");
            $failed++;
        }
    } else {
        cli_problem("  [FAIL] Task class missing: {$taskclass}");
        $failed++;
    }
}

// 4. Check External Service.
cli_writeln("\n[4] Checking External Function Registration...");
$extfunc = 'local_adminreport_get_report_data';
$extrecord = $DB->get_record('external_functions', ['name' => $extfunc]);
if ($extrecord) {
    cli_writeln("  [PASS] External function registered: {$extfunc} -> {$extrecord->classname}");
    $passed++;
} else {
    cli_problem("  [FAIL] External function not found in mdl_external_functions: {$extfunc}");
    $failed++;
}

// 5. Check Privacy Provider.
cli_writeln("\n[5] Checking Privacy API Provider...");
if (class_exists('\local_adminreport\privacy\provider')) {
    $collection = new \core_privacy\local\metadata\collection('local_adminreport');
    $res = \local_adminreport\privacy\provider::get_metadata($collection);
    $items = $res->get_collection();
    cli_writeln("  [PASS] Privacy provider returned " . count($items) . " metadata items.");
    $passed++;
} else {
    cli_problem("  [FAIL] Privacy provider class not found.");
    $failed++;
}

// Summary.
cli_heading('====================================================');
if ($failed === 0) {
    cli_writeln("RESULT: ALL {$passed} CHECKS PASSED! Phase 1 is 100% verified.");
} else {
    cli_problem("RESULT: {$failed} checks failed, {$passed} checks passed.");
}
cli_heading('====================================================');
exit($failed > 0 ? 1 : 0);
