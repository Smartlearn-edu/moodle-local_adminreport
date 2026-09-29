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

namespace local_adminreport\task;

use local_adminreport\analytics\query_engine;

/**
 * Scheduled task for aggregating report analytics and updating watermark.
 *
 * Implements a rolling 7-day lookback window to absorb late grade entries
 * and offline log syncs, advancing the watermark upon successful completion.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregate_analytics extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_aggregate_analytics', 'local_adminreport');
    }

    /**
     * Execute the aggregation task.
     */
    public function execute(): void {
        mtrace('local_adminreport: aggregate_analytics started.');

        $yesterdaymidnight = strtotime('yesterday midnight');
        $watermark = (int) get_config('local_adminreport', 'last_aggregated_watermark');

        // Determine start timestamp:
        // Use rolling 7-day lookback (yesterday - 6 days), or resume from prior watermark if cron had a gap.
        $lookbackstart = $yesterdaymidnight - (6 * 86400);

        if ($watermark > 0 && $watermark < $lookbackstart) {
            // Gap detected: resume from immediately after last known watermark.
            $startdate = $watermark + 86400;
            mtrace('local_adminreport: Watermark gap detected. Catching up from ' . date('Y-m-d', $startdate));
        } else {
            $startdate = $lookbackstart;
        }

        mtrace('local_adminreport: Aggregating period from ' . date('Y-m-d', $startdate) . ' to ' . date('Y-m-d', $yesterdaymidnight));

        $result = query_engine::rebuild_warehouse_facts($startdate, $yesterdaymidnight);
        mtrace("local_adminreport: Successfully aggregated {$result['days']} day(s) and rolled up {$result['months']} month(s).");

        // Advance watermark to yesterday midnight.
        set_config('last_aggregated_watermark', $yesterdaymidnight, 'local_adminreport');
        mtrace('local_adminreport: Updated watermark to ' . date('Y-m-d H:i:s', $yesterdaymidnight));

        // Purge MUC reports_data cache so fresh views consume the updated warehouse.
        $cache = \cache::make('local_adminreport', 'reports_data');
        $cache->purge();
        mtrace('local_adminreport: Purged reports_data MUC cache.');

        mtrace('local_adminreport: aggregate_analytics completed.');
    }
}
