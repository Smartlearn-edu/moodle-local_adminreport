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

namespace local_adminreport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle QuickForm for creating and editing program runs.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition(): void {
        global $DB;

        $mform = $this->_form;
        $runid = $this->_customdata['runid'] ?? 0;

        $mform->addElement('hidden', 'id', $runid);
        $mform->setType('id', PARAM_INT);

        // General Information Section.
        $mform->addElement('header', 'hdr_general', get_string('general'));

        // Course selection.
        $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname ASC', 'id, fullname');
        unset($courses[SITEID]);
        $mform->addElement('select', 'courseid', get_string('course'), $courses);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        // Optional Group.
        $mform->addElement('text', 'groupid', get_string('group'), ['size' => 10]);
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', 0);
        $mform->addHelpButton('groupid', 'help_groupid', 'local_adminreport');

        // Run Code.
        $mform->addElement('text', 'run_code', get_string('run_code', 'local_adminreport'), ['size' => 20]);
        $mform->setType('run_code', PARAM_TEXT);
        $mform->addRule('run_code', null, 'required', null, 'client');

        // Dimensions Section.
        $mform->addElement('header', 'hdr_dimensions', get_string('dimensions', 'local_adminreport'));

        // Organization.
        $orgtype = $DB->get_record('local_adminreport_dim_types', ['code' => 'organization']);
        $orgs = [0 => get_string('none')];
        if ($orgtype) {
            $orgmembers = $DB->get_records_menu('local_adminreport_dim_members', ['dim_type_id' => $orgtype->id], 'name ASC', 'id, name');
            $orgs += $orgmembers;
        }
        $mform->addElement('select', 'org_dim_id', get_string('organization', 'local_adminreport'), $orgs);
        $mform->setType('org_dim_id', PARAM_INT);

        // Program Type.
        $typeentry = $DB->get_record('local_adminreport_dim_types', ['code' => 'program_type']);
        $types = [0 => get_string('none')];
        if ($typeentry) {
            $typemembers = $DB->get_records_menu('local_adminreport_dim_members', ['dim_type_id' => $typeentry->id], 'name ASC', 'id, name');
            $types += $typemembers;
        }
        $mform->addElement('select', 'type_dim_id', get_string('program_type', 'local_adminreport'), $types);
        $mform->setType('type_dim_id', PARAM_INT);

        // Location / Campus.
        $loctype = $DB->get_record('local_adminreport_dim_types', ['code' => 'location']);
        $locations = [0 => get_string('none')];
        if ($loctype) {
            $locmembers = $DB->get_records_menu('local_adminreport_dim_members', ['dim_type_id' => $loctype->id], 'name ASC', 'id, name');
            $locations += $locmembers;
        }
        $mform->addElement('select', 'location_dim_id', get_string('location', 'local_adminreport'), $locations);
        $mform->setType('location_dim_id', PARAM_INT);

        // Schedule & Logistics Section.
        $mform->addElement('header', 'hdr_schedule', get_string('schedule_logistics', 'local_adminreport'));

        $mform->addElement('date_time_selector', 'startdate', get_string('startdate'));
        $mform->addElement('date_time_selector', 'enddate', get_string('enddate'));

        $mform->addElement('text', 'classroom', get_string('classroom', 'local_adminreport'), ['size' => 20]);
        $mform->setType('classroom', PARAM_TEXT);

        $mform->addElement('text', 'daily_start_time', get_string('daily_start_time', 'local_adminreport'), ['size' => 10]);
        $mform->setType('daily_start_time', PARAM_TEXT);
        $mform->setDefault('daily_start_time', '08:00');

        $mform->addElement('text', 'daily_end_time', get_string('daily_end_time', 'local_adminreport'), ['size' => 10]);
        $mform->setType('daily_end_time', PARAM_TEXT);
        $mform->setDefault('daily_end_time', '14:00');

        $mform->addElement('text', 'break_duration_min', get_string('break_duration_min', 'local_adminreport'), ['size' => 5]);
        $mform->setType('break_duration_min', PARAM_INT);
        $mform->setDefault('break_duration_min', 30);

        $mform->addElement('text', 'exam_time', get_string('exam_time', 'local_adminreport'), ['size' => 15]);
        $mform->setType('exam_time', PARAM_TEXT);
        $mform->setDefault('exam_time', '11:00');

        // Trainer Section.
        $mform->addElement('header', 'hdr_trainer', get_string('trainer', 'local_adminreport'));

        $mform->addElement('text', 'trainer_name', get_string('trainer_name', 'local_adminreport'), ['size' => 30]);
        $mform->setType('trainer_name', PARAM_TEXT);

        $mform->addElement('text', 'trainer_userid', get_string('trainer_userid', 'local_adminreport'), ['size' => 10]);
        $mform->setType('trainer_userid', PARAM_INT);

        // Status Section.
        $mform->addElement('header', 'hdr_status', get_string('status'));
        $mform->addElement('advcheckbox', 'is_cancelled', get_string('is_cancelled', 'local_adminreport'));
        $mform->setDefault('is_cancelled', 0);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ($data['startdate'] > $data['enddate']) {
            $errors['enddate'] = get_string('enddate_before_startdate', 'local_adminreport');
        }

        if (empty(trim($data['run_code']))) {
            $errors['run_code'] = get_string('required');
        }

        return $errors;
    }
}
