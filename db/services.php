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
 * Web service definitions for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_adminreport_get_report_data' => [
        'classname'     => 'local_adminreport\external\get_report_data',
        'methodname'    => 'execute',
        'description'   => 'Fetches aggregated report metrics, charts, and operational runs based on filter criteria',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/adminreport:view',
    ],
];

$services = [
    'Management Intelligence Service' => [
        'functions'       => [
            'local_adminreport_get_report_data',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'local_adminreport_service',
    ],
];

