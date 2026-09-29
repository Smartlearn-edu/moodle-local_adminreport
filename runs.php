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
 * Program runs management page for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', 'list', PARAM_ALPHA);
$runid  = optional_param('id', 0, PARAM_INT);
$page   = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW);
$statusfilter = optional_param('status', '', PARAM_ALPHA);

$context = context_system::instance();
require_login();
require_capability('local/adminreport:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/adminreport/runs.php', ['action' => $action, 'id' => $runid]));
$PAGE->set_title(get_string('runs', 'local_adminreport'));
$PAGE->set_heading(get_string('runs', 'local_adminreport'));

// Handle Edit / Add Action.
if ($action === 'edit' || $action === 'add') {
    $mform = new \local_adminreport\form\run_form(null, ['runid' => $runid]);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/local/adminreport/runs.php'));
    } else if ($data = $mform->get_data()) {
        $now = time();

        $runrecord = (object) [
            'courseid'           => $data->courseid,
            'groupid'            => $data->groupid,
            'run_code'           => trim($data->run_code),
            'startdate'          => $data->startdate,
            'enddate'            => $data->enddate,
            'org_dim_id'         => $data->org_dim_id ?: null,
            'type_dim_id'        => $data->type_dim_id ?: null,
            'location_dim_id'    => $data->location_dim_id ?: null,
            'classroom'          => trim($data->classroom),
            'daily_start_time'   => trim($data->daily_start_time),
            'daily_end_time'     => trim($data->daily_end_time),
            'break_duration_min' => (int) $data->break_duration_min,
            'exam_time'          => trim($data->exam_time),
            'is_cancelled'       => !empty($data->is_cancelled) ? 1 : 0,
            'timemodified'       => $now,
        ];

        if ($data->id > 0) {
            $runrecord->id = $data->id;
            $DB->update_record('local_adminreport_runs', $runrecord);
            $savedrunid = $data->id;
        } else {
            $runrecord->source = 'manual';
            $savedrunid = $DB->insert_record('local_adminreport_runs', $runrecord);
        }

        // Update trainer assignment.
        if (!empty(trim($data->trainer_name))) {
            $DB->delete_records('local_adminreport_run_trainers', ['run_id' => $savedrunid]);
            $trainerrecord = (object) [
                'run_id'       => $savedrunid,
                'userid'       => !empty($data->trainer_userid) ? (int) $data->trainer_userid : null,
                'trainer_name' => trim($data->trainer_name),
                'is_primary'   => 1,
            ];
            $DB->insert_record('local_adminreport_run_trainers', $trainerrecord);
        }

        \core\notification::success(get_string('changessaved'));
        redirect(new moodle_url('/local/adminreport/runs.php'));
    }

    if ($runid > 0 && !$mform->is_submitted()) {
        $run = $DB->get_record('local_adminreport_runs', ['id' => $runid], '*', MUST_EXIST);
        $primarytrainer = $DB->get_record('local_adminreport_run_trainers', ['run_id' => $runid, 'is_primary' => 1]);
        if ($primarytrainer) {
            $run->trainer_name = $primarytrainer->trainer_name;
            $run->trainer_userid = $primarytrainer->userid;
        }
        $mform->set_data($run);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($runid > 0 ? get_string('edit_run', 'local_adminreport') : get_string('add_run', 'local_adminreport'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle Cancel Action.
if ($action === 'toggle_cancel' && $runid > 0) {
    require_sesskey();
    $run = $DB->get_record('local_adminreport_runs', ['id' => $runid], '*', MUST_EXIST);
    $run->is_cancelled = $run->is_cancelled ? 0 : 1;
    $run->timemodified = time();
    $DB->update_record('local_adminreport_runs', $run);
    \core\notification::success(get_string('status_updated', 'local_adminreport'));
    redirect(new moodle_url('/local/adminreport/runs.php'));
}

// List Runs.
echo $OUTPUT->header();

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::tag('h2', get_string('runs', 'local_adminreport'));
echo html_writer::start_div();
echo html_writer::link(new moodle_url('/local/adminreport/runs.php', ['action' => 'add']), get_string('add_run', 'local_adminreport'), ['class' => 'btn btn-primary me-2']);
echo html_writer::link(new moodle_url('/local/adminreport/import.php'), get_string('import_runs', 'local_adminreport'), ['class' => 'btn btn-secondary me-2']);
echo html_writer::link(new moodle_url('/local/adminreport/index.php'), get_string('dashboard', 'local_adminreport'), ['class' => 'btn btn-outline-dark']);
echo html_writer::end_div();
echo html_writer::end_div();

// Query runs.
$params = [];
$wheres = ['1=1'];

if (!empty($search)) {
    $wheres[] = "r.run_code LIKE :search OR c.fullname LIKE :searchc";
    $params['search'] = '%' . $search . '%';
    $params['searchc'] = '%' . $search . '%';
}

$now = time();
if ($statusfilter === 'cancelled') {
    $wheres[] = "r.is_cancelled = 1";
} else if ($statusfilter === 'planned') {
    $wheres[] = "r.is_cancelled = 0 AND r.startdate > :nowp";
    $params['nowp'] = $now;
} else if ($statusfilter === 'running') {
    $wheres[] = "r.is_cancelled = 0 AND r.startdate <= :nowr1 AND r.enddate >= :nowr2";
    $params['nowr1'] = $now;
    $params['nowr2'] = $now;
} else if ($statusfilter === 'completed') {
    $wheres[] = "r.is_cancelled = 0 AND r.enddate < :nowc";
    $params['nowc'] = $now;
}

$whereclause = implode(' AND ', $wheres);

$sql = "SELECT r.*,
               c.fullname AS coursename,
               om.name AS orgname,
               tm.name AS typename,
               lm.name AS locname,
               rt.trainer_name
          FROM {local_adminreport_runs} r
          JOIN {course} c ON c.id = r.courseid
     LEFT JOIN {local_adminreport_dim_members} om ON om.id = r.org_dim_id
     LEFT JOIN {local_adminreport_dim_members} tm ON tm.id = r.type_dim_id
     LEFT JOIN {local_adminreport_dim_members} lm ON lm.id = r.location_dim_id
     LEFT JOIN {local_adminreport_run_trainers} rt ON rt.run_id = r.id AND rt.is_primary = 1
         WHERE {$whereclause}
      ORDER BY r.startdate DESC";

$totalcount = $DB->count_records_sql("SELECT COUNT(1) FROM {local_adminreport_runs} r JOIN {course} c ON c.id = r.courseid WHERE {$whereclause}", $params);
$runs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Render table.
$table = new html_table();
$table->head = [
    get_string('run_code', 'local_adminreport'),
    get_string('course'),
    get_string('organization', 'local_adminreport'),
    get_string('program_type', 'local_adminreport'),
    get_string('location', 'local_adminreport'),
    get_string('dates', 'local_adminreport'),
    get_string('classroom', 'local_adminreport'),
    get_string('trainer', 'local_adminreport'),
    get_string('status'),
    get_string('actions'),
];

foreach ($runs as $r) {
    // Derive dynamic status badge.
    if ($r->is_cancelled) {
        $statusbadge = html_writer::span(get_string('status_cancelled', 'local_adminreport'), 'badge bg-danger');
    } else if ($now < $r->startdate) {
        $statusbadge = html_writer::span(get_string('status_planned', 'local_adminreport'), 'badge bg-info');
    } else if ($now >= $r->startdate && $now <= $r->enddate) {
        $statusbadge = html_writer::span(get_string('status_running', 'local_adminreport'), 'badge bg-warning text-dark');
    } else {
        $statusbadge = html_writer::span(get_string('status_completed', 'local_adminreport'), 'badge bg-success');
    }

    $dates = userdate($r->startdate, get_string('strftimedate')) . ' - ' . userdate($r->enddate, get_string('strftimedate'));

    $editurl = new moodle_url('/local/adminreport/runs.php', ['action' => 'edit', 'id' => $r->id]);
    $togglecancelurl = new moodle_url('/local/adminreport/runs.php', ['action' => 'toggle_cancel', 'id' => $r->id, 'sesskey' => sesskey()]);

    $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-primary me-1']);
    $canceltext = $r->is_cancelled ? get_string('uncancel', 'local_adminreport') : get_string('cancel');
    $actions .= html_writer::link($togglecancelurl, $canceltext, ['class' => 'btn btn-sm btn-outline-secondary']);

    $table->data[] = [
        html_writer::tag('strong', s($r->run_code)),
        html_writer::link(new moodle_url('/course/view.php', ['id' => $r->courseid]), s($r->coursename)),
        s($r->orgname ?: '-'),
        s($r->typename ?: '-'),
        s($r->locname ?: '-'),
        $dates,
        s($r->classroom ?: '-'),
        s($r->trainer_name ?: '-'),
        $statusbadge,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->paging_bar($totalcount, $page, $perpage, new moodle_url('/local/adminreport/runs.php', ['search' => $search, 'status' => $statusfilter]));

echo $OUTPUT->footer();
