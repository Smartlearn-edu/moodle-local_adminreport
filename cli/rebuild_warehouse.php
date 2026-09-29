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
 * CLI tool for warehouse administration, run synchronisation, and fact rebuilding.
 *
 * Usage from Moodle root:
 *   php local/adminreport/cli/rebuild_warehouse.php --help
 *   php local/adminreport/cli/rebuild_warehouse.php --seed-dimensions
 *   php local/adminreport/cli/rebuild_warehouse.php --sync-runs
 *   php local/adminreport/cli/rebuild_warehouse.php --rebuild-facts [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help'            => false,
    'seed-dimensions' => false,
    'sync-runs'       => false,
    'rebuild-facts'   => false,
    'from'            => '',
    'to'              => '',
], [
    'h' => 'help',
]);

if ($options['help'] || (!$options['seed-dimensions'] && !$options['sync-runs'] && !$options['rebuild-facts'])) {
    $help = "Executive Management Intelligence - Warehouse Administration CLI

Options:
  -h, --help           Print this help dialog.
  --seed-dimensions    Seed standard dimensions (Sectors, Program Classifications, Locations).
  --sync-runs          Auto-discover courses and create missing program run records.
  --rebuild-facts      Recalculate fact tables (daily and monthly stats) across dates.
  --from=YYYY-MM-DD    Start date for fact recalculation (default: 30 days ago).
  --to=YYYY-MM-DD      End date for fact recalculation (default: today).

Examples:
  \$ php local/adminreport/cli/rebuild_warehouse.php --seed-dimensions
  \$ php local/adminreport/cli/rebuild_warehouse.php --sync-runs
  \$ php local/adminreport/cli/rebuild_warehouse.php --rebuild-facts --from=2026-01-01 --to=2026-09-30
";
    cli_writeln($help);
    exit(0);
}

cli_heading('====================================================');
cli_heading('  local_adminreport: Warehouse Administration');
cli_heading('====================================================');

// 1. Seed Dimensions.
if ($options['seed-dimensions']) {
    cli_writeln('--> Seeding standard dimension types and members...');
    \local_adminreport\analytics\dimension_manager::ensure_default_dimension_types();
    cli_writeln('    Dimension seeding completed successfully.');
}

// 2. Synchronize Program Runs.
if ($options['sync-runs']) {
    cli_writeln('--> Auto-discovering and synchronizing courses to program runs...');
    $rootcategory = (int) get_config('local_adminreport', 'root_category');
    $created = \local_adminreport\task\auto_discover_runs::discover_all_runs($rootcategory);
    cli_writeln("    Runs synchronization completed. Created {$created} new run(s).");
}

// 3. Rebuild Fact Tables.
if ($options['rebuild-facts']) {
    cli_writeln('--> Rebuilding warehouse fact tables...');

    $fromdate = !empty($options['from']) ? strtotime($options['from']) : (time() - (30 * 86400));
    $todate   = !empty($options['to']) ? strtotime($options['to']) : time();

    if ($fromdate > $todate) {
        cli_error('Error: --from date cannot be later than --to date.');
    }

    cli_writeln('    Processing period: ' . date('Y-m-d', $fromdate) . ' to ' . date('Y-m-d', $todate));

    if (class_exists('\local_adminreport\analytics\query_engine')) {
        $res = \local_adminreport\analytics\query_engine::rebuild_warehouse_facts($fromdate, $todate);
        cli_writeln("    Warehouse facts successfully rebuilt: {$res['days']} day(s) aggregated, {$res['months']} month(s) rolled up.");
    } else {
        cli_writeln('    [NOTE] Fact calculation engine is scheduled in Phase 3.');
        cli_writeln('    Runs and dimensions are ready for Phase 3 aggregation.');
    }
}

cli_writeln("\nAll operations finished successfully.");
exit(0);
