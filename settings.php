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
 * Admin settings for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Add direct navigation links under Site Administration -> Reports.
    $ADMIN->add('reports', new admin_externalpage(
        'local_adminreport_dashboard',
        get_string('dashboard', 'local_adminreport'),
        new moodle_url('/local/adminreport/index.php'),
        'local/adminreport:view'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_adminreport_runs',
        get_string('runs', 'local_adminreport'),
        new moodle_url('/local/adminreport/runs.php'),
        'local/adminreport:manage'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_adminreport_import',
        get_string('import_runs', 'local_adminreport'),
        new moodle_url('/local/adminreport/import.php'),
        'local/adminreport:manage'
    ));

    // 2. Settings page.
    $settings = new admin_settingpage('local_adminreport', get_string('settings', 'local_adminreport'));
    $ADMIN->add('reports', $settings);

    // Organization Resolution Mode.
    $orgmodes = [
        'hybrid'       => get_string('org_mode_hybrid', 'local_adminreport'),
        'category'     => get_string('org_mode_category', 'local_adminreport'),
        'user_profile' => get_string('org_mode_user_profile', 'local_adminreport'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_adminreport/org_resolution_mode',
        get_string('org_resolution_mode', 'local_adminreport'),
        get_string('org_resolution_mode_desc', 'local_adminreport'),
        'hybrid',
        $orgmodes
    ));

    // Root Reporting Category.
    $categories = [0 => get_string('all')];
    if ($allcategories = $DB->get_records_menu('course_categories', null, 'name ASC', 'id, name')) {
        $categories += $allcategories;
    }
    $settings->add(new admin_setting_configselect(
        'local_adminreport/root_category',
        get_string('root_category', 'local_adminreport'),
        get_string('root_category_desc', 'local_adminreport'),
        0,
        $categories
    ));

    // Workweek Mode.
    $workweekmodes = [
        'sun_thu'  => get_string('workweek_sun_thu', 'local_adminreport'),
        'mon_fri'  => get_string('workweek_mon_fri', 'local_adminreport'),
        'all_days' => get_string('workweek_all_days', 'local_adminreport'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_adminreport/workweek_mode',
        get_string('workweek_mode', 'local_adminreport'),
        get_string('workweek_mode_desc', 'local_adminreport'),
        'sun_thu',
        $workweekmodes
    ));

    // Watermark Status (Informational).
    $watermark = (int) get_config('local_adminreport', 'last_aggregated_watermark');
    $watermarkdisplay = $watermark > 0 ? userdate($watermark, get_string('strftimedatetimeshort')) : get_string('never');
    $settings->add(new admin_setting_description(
        'local_adminreport/watermark_info',
        get_string('watermark_status', 'local_adminreport'),
        get_string('watermark_status_desc', 'local_adminreport', $watermarkdisplay)
    ));
}
