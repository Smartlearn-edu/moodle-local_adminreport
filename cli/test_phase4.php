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
 * Phase 4 automated verification script for local_adminreport.
 *
 * Usage from Moodle root:
 *   php local/adminreport/cli/test_phase4.php
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('====================================================');
cli_heading('  local_adminreport: Phase 4 Verification Suite');
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

$plugindir = dirname(__DIR__);

// ---------------------------------------------------------
// 1. Mustache Templates Verification
// ---------------------------------------------------------
cli_heading('Test 1: Mustache Templates Verification');

$templates = [
    'dashboard.mustache',
    'kpi_cards.mustache',
    'operational_table.mustache',
];

foreach ($templates as $tmpl) {
    $path = $plugindir . '/templates/' . $tmpl;
    assert_test("Template {$tmpl} exists", file_exists($path));
    if (file_exists($path)) {
        $content = file_get_contents($path);
        assert_test("Template {$tmpl} has content", strlen($content) > 100);
    }
}

// ---------------------------------------------------------
// 2. AMD JavaScript & ApexCharts Assets
// ---------------------------------------------------------
cli_heading('Test 2: AMD JavaScript & ApexCharts Bundle');

$amdsrc = [
    'apexcharts.js',
    'charts.js',
    'dashboard.js',
];

foreach ($amdsrc as $file) {
    $srcpath = $plugindir . '/amd/src/' . $file;
    assert_test("AMD source {$file} exists", file_exists($srcpath));
    if (file_exists($srcpath)) {
        $content = file_get_contents($srcpath);
        assert_test("AMD source {$file} defines module", strpos($content, 'define(') !== false || strpos($content, 'ApexCharts') !== false);
    }
}

$amdbuild = [
    'apexcharts.min.js',
    'charts.min.js',
    'dashboard.min.js',
];

foreach ($amdbuild as $file) {
    $buildpath = $plugindir . '/amd/build/' . $file;
    assert_test("AMD build {$file} exists", file_exists($buildpath));
}

// ---------------------------------------------------------
// 3. Stylesheet & Print Media Rules
// ---------------------------------------------------------
cli_heading('Test 3: Stylesheet & Print Media Rules');

$csspath = $plugindir . '/styles.css';
assert_test('styles.css exists', file_exists($csspath));
if (file_exists($csspath)) {
    $css = file_get_contents($csspath);
    assert_test('styles.css contains @media print rules', strpos($css, '@media print') !== false);
    assert_test('styles.css contains KPI card styling', strpos($css, '.kpi-card') !== false);
}

// ---------------------------------------------------------
// 4. Language Strings Parity (English & Arabic)
// ---------------------------------------------------------
cli_heading('Test 4: Localization Parity (EN <-> AR)');

$enstrings = [];
$string = [];
require($plugindir . '/lang/en/local_adminreport.php');
$enstrings = $string;

$arstrings = [];
$string = [];
require($plugindir . '/lang/ar/local_adminreport.php');
$arstrings = $string;

assert_test('English strings loaded (>50 keys)', count($enstrings) > 50, 'Found ' . count($enstrings));
assert_test('Arabic strings loaded (>50 keys)', count($arstrings) > 50, 'Found ' . count($arstrings));

$missinginar = array_diff_key($enstrings, $arstrings);
$missinginen = array_diff_key($arstrings, $enstrings);

assert_test('100% Arabic coverage for all English keys', empty($missinginar), 'Missing in AR: ' . implode(', ', array_keys($missinginar)));
assert_test('100% English coverage for all Arabic keys', empty($missinginen), 'Missing in EN: ' . implode(', ', array_keys($missinginen)));

// ---------------------------------------------------------
// 5. Settings Configuration Verification
// ---------------------------------------------------------
cli_heading('Test 5: Settings Page Configuration');

$settingsfile = $plugindir . '/settings.php';
assert_test('settings.php exists', file_exists($settingsfile));

// ---------------------------------------------------------
// Final Summary
// ---------------------------------------------------------
cli_heading('====================================================');
cli_writeln("Phase 4 Test Results: {$passed} Passed, {$failed} Failed");
cli_heading('====================================================');

if ($failed > 0) {
    exit(1);
}
exit(0);
