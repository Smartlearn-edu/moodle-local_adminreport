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
 * Import program runs page for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login();
require_capability('local/adminreport:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/adminreport/import.php'));
$PAGE->set_title(get_string('import_runs', 'local_adminreport'));
$PAGE->set_heading(get_string('import_runs', 'local_adminreport'));

// Download sample template.
if (optional_param('download_sample', 0, PARAM_INT)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=runs_sample.csv');
    echo "course_shortname,run_code,start_date,end_date,organization,program_type,location,classroom,trainer_name,daily_start_time,daily_end_time,break_duration_min,exam_time\n";
    echo "course101,EARO011025,2026-09-06,2026-09-07,Erwaa,دورة تطويرية,Riyadh,B1-15,Sulaiman Alqarzai,08:00,14:00,30,11:00\n";
    echo "course102,32401,2026-09-06,2026-09-07,MOMC-NC,دورة تطويرية,Al Jouf,B1-79,Ahmed Al-Nahal,08:00,14:00,30,11:00\n";
    exit;
}

$results = null;
if ($csvdata = optional_param('csvdata', '', PARAM_RAW)) {
    require_sesskey();
    $results = \local_adminreport\import\run_importer::import_csv($csvdata);
}

echo $OUTPUT->header();

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::tag('h2', get_string('import_runs', 'local_adminreport'));
echo html_writer::link(new moodle_url('/local/adminreport/runs.php'), get_string('back_to_runs', 'local_adminreport'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

if ($results) {
    if ($results->imported > 0) {
        echo $OUTPUT->notification(get_string('import_success', 'local_adminreport', $results->imported), 'notifysuccess');
    }
    if ($results->failed > 0) {
        echo $OUTPUT->notification(get_string('import_failed', 'local_adminreport', $results->failed), 'notifyerror');
        echo html_writer::start_tag('ul');
        foreach ($results->errors as $err) {
            echo html_writer::tag('li', s($err));
        }
        echo html_writer::end_tag('ul');
    }
}

echo html_writer::start_div('card p-4 mb-4');
echo html_writer::tag('p', get_string('import_desc', 'local_adminreport'));
echo html_writer::link(new moodle_url('/local/adminreport/import.php', ['download_sample' => 1]), get_string('download_sample_csv', 'local_adminreport'), ['class' => 'btn btn-outline-primary mb-3']);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/adminreport/import.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group mb-3');
echo html_writer::tag('label', get_string('paste_csv_data', 'local_adminreport'), ['for' => 'csvdata', 'class' => 'font-weight-bold mb-2']);
echo html_writer::tag('textarea', '', ['name' => 'csvdata', 'id' => 'csvdata', 'rows' => 10, 'class' => 'form-control', 'placeholder' => "course_shortname,run_code,start_date,end_date,organization,program_type,location,classroom,trainer_name\n..."]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('import', 'local_adminreport'), 'class' => 'btn btn-success']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->footer();
