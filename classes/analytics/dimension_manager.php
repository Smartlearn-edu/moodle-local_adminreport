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

namespace local_adminreport\analytics;

/**
 * Dimension manager service for resolving organizations, sectors, program types, and locations.
 *
 * Implements Dual Company Resolution:
 * 1. Course Categories (Client Sponsoring Entity)
 * 2. User Profile Custom Field 'CompanyName' (Learner Affiliation)
 * 3. Smart Hybrid resolution with multi-client cohort distribution
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dimension_manager {

    /** @var string Mode 1: Resolve organization strictly by course category */
    public const ORG_MODE_CATEGORY = 'category';

    /** @var string Mode 2: Resolve organization strictly by user profile field */
    public const ORG_MODE_USER_PROFILE = 'user_profile';

    /** @var string Mode 3: Smart hybrid resolution */
    public const ORG_MODE_HYBRID = 'hybrid';

    /**
     * Ensure standard dimension types exist in the database.
     */
    public static function ensure_default_dimension_types(): void {
        global $DB;

        $types = [
            'sector' => [
                'name'          => 'القطاع / Sector',
                'source_type'   => 'manual',
                'source_config' => '',
            ],
            'organization' => [
                'name'          => 'الجهة / Organization',
                'source_type'   => 'hybrid',
                'source_config' => json_encode(['profile_field' => 'CompanyName', 'root_category' => 0]),
            ],
            'program_type' => [
                'name'          => 'تصنيف البرنامج / Program Type',
                'source_type'   => 'category',
                'source_config' => '',
            ],
            'location' => [
                'name'          => 'الموقع / Location',
                'source_type'   => 'manual',
                'source_config' => '',
            ],
        ];

        foreach ($types as $code => $data) {
            if (!$DB->record_exists('local_adminreport_dim_types', ['code' => $code])) {
                $record = (object) [
                    'code'          => $code,
                    'name'          => $data['name'],
                    'source_type'   => $data['source_type'],
                    'source_config' => $data['source_config'],
                ];
                $DB->insert_record('local_adminreport_dim_types', $record);
            }
        }

        self::ensure_default_sectors();
        self::ensure_default_program_types();
    }

    /**
     * Seed institutional sectors.
     */
    public static function ensure_default_sectors(): void {
        global $DB;

        $type = $DB->get_record('local_adminreport_dim_types', ['code' => 'sector']);
        if (!$type) {
            return;
        }

        $sectors = [
            'internal'   => 'الهيئة السعودية للمياه',
            'corporate'  => 'الشركات',
            'government' => 'القطاع الحكومي والعسكري',
            'gcc'        => 'دول الخليج',
            'individual' => 'أفراد',
            'csr'        => 'المسؤولية المجتمعية',
        ];

        foreach ($sectors as $code => $name) {
            self::get_or_create_dim_member('sector', $code, $name);
        }
    }

    /**
     * Seed institutional program classifications.
     */
    public static function ensure_default_program_types(): void {
        $types = [
            'dev_course'    => 'دورة تطويرية',
            'qual_course'   => 'دورة تأهيلية',
            'qual_prog'     => 'برنامج تأهيلي',
            'diploma'       => 'دبلوم تدريبي',
            'assoc_diploma' => 'دبلوم مساعد',
            'workshop'      => 'ملتقى / ورشة عمل',
        ];

        foreach ($types as $code => $name) {
            self::get_or_create_dim_member('program_type', $code, $name);
        }
    }

    /**
     * Get or create a dimension member.
     *
     * @param string $dimtypecode The dimension type code (e.g. 'organization', 'location').
     * @param string $code Unique code within this dimension type.
     * @param string $name Human readable display name.
     * @param int|null $parentid Optional parent member ID for hierarchies (e.g. Sector -> Org).
     * @return int The dimension member ID.
     */
    public static function get_or_create_dim_member(
        string $dimtypecode,
        string $code,
        string $name,
        ?int $parentid = null
    ): int {
        global $DB;

        $dimtype = $DB->get_record('local_adminreport_dim_types', ['code' => $dimtypecode]);
        if (!$dimtype) {
            self::ensure_default_dimension_types();
            $dimtype = $DB->get_record('local_adminreport_dim_types', ['code' => $dimtypecode]);
        }

        $existing = $DB->get_record('local_adminreport_dim_members', [
            'dim_type_id' => $dimtype->id,
            'code'        => $code,
        ]);

        if ($existing) {
            if ($parentid !== null && $existing->parent_id !== $parentid) {
                $existing->parent_id = $parentid;
                $existing->timemodified = time();
                $DB->update_record('local_adminreport_dim_members', $existing);
            }
            return (int) $existing->id;
        }

        $record = (object) [
            'dim_type_id'  => $dimtype->id,
            'parent_id'    => $parentid,
            'code'         => $code,
            'name'         => $name,
            'timemodified' => time(),
        ];

        return (int) $DB->insert_record('local_adminreport_dim_members', $record);
    }

    /**
     * Resolve dimensions for a given Moodle course based on category hierarchy and name patterns.
     *
     * @param int $courseid
     * @return \stdClass Dimensions object containing org_dim_id, type_dim_id, location_dim_id.
     */
    public static function resolve_course_dimensions(int $courseid): \stdClass {
        global $DB;

        $result = (object) [
            'org_dim_id'      => null,
            'type_dim_id'     => null,
            'location_dim_id' => null,
            'classroom'       => '',
        ];

        $course = $DB->get_record('course', ['id' => $courseid], 'id, category, fullname, shortname, idnumber');
        if (!$course || $course->id == SITEID) {
            return $result;
        }

        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        if (!$category) {
            return $result;
        }

        // 1. Resolve Organization from Category.
        $catname = trim($category->name);
        $orgcode = self::slugify($catname);
        if (!empty($orgcode)) {
            // Determine parent sector if possible.
            $parentsector = self::detect_sector_for_org($catname);
            $result->org_dim_id = self::get_or_create_dim_member('organization', $orgcode, $catname, $parentsector);
        }

        // 2. Resolve Program Type from Course or Category Name.
        $typecode = self::detect_program_type($course->fullname . ' ' . $catname);
        if ($typecode) {
            $member = $DB->get_record('local_adminreport_dim_members', ['code' => $typecode]);
            if ($member) {
                $result->type_dim_id = (int) $member->id;
            }
        }

        // 3. Resolve Location from Name or Category.
        $loccode = self::detect_location($course->fullname . ' ' . $catname);
        if ($loccode) {
            $locname = self::get_location_name($loccode);
            $result->location_dim_id = self::get_or_create_dim_member('location', $loccode, $locname);
        }

        return $result;
    }

    /**
     * Resolve a user's organization from their custom profile field 'CompanyName'.
     *
     * @param int $userid
     * @return int|null Organization dimension member ID.
     */
    public static function resolve_user_organization(int $userid): ?int {
        global $DB;

        if (empty($userid)) {
            return null;
        }

        // Look for custom user profile field named 'CompanyName'.
        $sql = "SELECT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uid.userid = :userid
                   AND uif.shortname = 'CompanyName'";

        $companyname = $DB->get_field_sql($sql, ['userid' => $userid]);
        if (empty($companyname) || trim($companyname) === '') {
            return null;
        }

        $cleancompany = trim($companyname);
        $code = self::slugify($cleancompany);
        if (empty($code)) {
            return null;
        }

        $sectorid = self::detect_sector_for_org($cleancompany);
        return self::get_or_create_dim_member('organization', $code, $cleancompany, $sectorid);
    }

    /**
     * Get the distribution of enrolled trainees across organizations for a given run.
     *
     * In Mode 1 (Category): Attributed 100% to run's org_dim_id.
     * In Mode 2 (User Profile): Grouped by individual trainee profile 'CompanyName'.
     * In Mode 3 (Smart Hybrid): Uses category if it's a specific corporate client;
     *                           otherwise distributes by individual trainee's 'CompanyName'.
     *
     * @param \stdClass|int $run
     * @return array Associative array of [org_dim_id => trainee_count]
     */
    public static function get_run_organizations_distribution($run): array {
        global $DB;

        if (is_numeric($run)) {
            $run = $DB->get_record('local_adminreport_runs', ['id' => (int) $run]);
        }

        if (!$run) {
            return [];
        }

        $mode = get_config('local_adminreport', 'org_resolution_mode') ?: self::ORG_MODE_HYBRID;

        // Fetch enrolled users in the run.
        $userids = self::get_run_enrolled_userids($run);
        if (empty($userids)) {
            return $run->org_dim_id ? [$run->org_dim_id => 0] : [];
        }

        $totalusers = count($userids);

        // Mode 1: Pure Category.
        if ($mode === self::ORG_MODE_CATEGORY && $run->org_dim_id) {
            return [$run->org_dim_id => $totalusers];
        }

        // Mode 2 or 3: Group by trainee profile field.
        $distribution = [];
        $unassigned = 0;

        foreach ($userids as $uid) {
            $orgid = self::resolve_user_organization($uid);
            if ($orgid) {
                if (!isset($distribution[$orgid])) {
                    $distribution[$orgid] = 0;
                }
                $distribution[$orgid]++;
            } else {
                $unassigned++;
            }
        }

        // In Smart Hybrid Mode:
        if ($mode === self::ORG_MODE_HYBRID) {
            // If the course has a primary client category and profile fields were largely empty,
            // or if all trainees match the category sponsor:
            if (empty($distribution) && $run->org_dim_id) {
                return [$run->org_dim_id => $totalusers];
            }
            // For any trainees without a profile company name, assign them to the run's primary category.
            if ($unassigned > 0 && $run->org_dim_id) {
                if (!isset($distribution[$run->org_dim_id])) {
                    $distribution[$run->org_dim_id] = 0;
                }
                $distribution[$run->org_dim_id] += $unassigned;
                $unassigned = 0;
            }
        }

        // Fallback if nothing matched:
        if (empty($distribution) && $run->org_dim_id) {
            return [$run->org_dim_id => $totalusers];
        }

        return $distribution;
    }

    /**
     * Get active enrolled user IDs for a run.
     *
     * @param \stdClass $run
     * @return int[]
     */
    public static function get_run_enrolled_userids(\stdClass $run): array {
        global $DB;

        if ($run->groupid > 0) {
            $sql = "SELECT DISTINCT gm.userid
                      FROM {groups_members} gm
                      JOIN {enrol} e ON e.courseid = :courseid
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = gm.userid AND ue.status = 0
                      JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                     WHERE gm.groupid = :groupid";
            return $DB->get_fieldset_sql($sql, ['courseid' => $run->courseid, 'groupid' => $run->groupid]);
        }

        $sql = "SELECT DISTINCT ue.userid
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                 WHERE e.courseid = :courseid";
        return $DB->get_fieldset_sql($sql, ['courseid' => $run->courseid]);
    }

    /**
     * Detect parent sector for a given company name.
     *
     * @param string $name
     * @return int|null Sector dimension member ID.
     */
    public static function detect_sector_for_org(string $name): ?int {
        global $DB;

        $name = mb_strtolower($name, 'UTF-8');

        $sectorcode = 'corporate'; // Default to corporate.
        if (strpos($name, 'الهيئة') !== false || strpos($name, 'swa') !== false) {
            $sectorcode = 'internal';
        } else if (strpos($name, 'حكومي') !== false || strpos($name, 'عسكري') !== false || strpos($name, 'وزارة') !== false) {
            $sectorcode = 'government';
        } else if (strpos($name, 'عمان') !== false || strpos($name, 'خليج') !== false || strpos($name, 'نماء') !== false) {
            $sectorcode = 'gcc';
        } else if (strpos($name, 'أفراد') !== false || strpos($name, 'عام') !== false) {
            $sectorcode = 'individual';
        } else if (strpos($name, 'مسؤولية') !== false || strpos($name, 'مجتمع') !== false) {
            $sectorcode = 'csr';
        }

        $sector = $DB->get_record('local_adminreport_dim_members', ['code' => $sectorcode]);
        return $sector ? (int) $sector->id : null;
    }

    /**
     * Detect program type from course or category name.
     *
     * @param string $text
     * @return string|null
     */
    public static function detect_program_type(string $text): ?string {
        $text = mb_strtolower($text, 'UTF-8');

        if (strpos($text, 'دبلوم تدريبي') !== false || strpos($text, 'diploma') !== false) {
            return 'diploma';
        }
        if (strpos($text, 'دبلوم مساعد') !== false) {
            return 'assoc_diploma';
        }
        if (strpos($text, 'برنامج تأهيلي') !== false) {
            return 'qual_prog';
        }
        if (strpos($text, 'تأهيلية') !== false || strpos($text, 'تأهيلي') !== false) {
            return 'qual_course';
        }
        if (strpos($text, 'ورشة') !== false || strpos($text, 'ملتقى') !== false || strpos($text, 'workshop') !== false) {
            return 'workshop';
        }
        if (strpos($text, 'تطويري') !== false || strpos($text, 'تطويرية') !== false || strpos($text, 'development') !== false) {
            return 'dev_course';
        }

        return 'dev_course'; // Default standard.
    }

    /**
     * Detect location from text.
     *
     * @param string $text
     * @return string|null Location code.
     */
    public static function detect_location(string $text): ?string {
        $text = mb_strtolower($text, 'UTF-8');

        if (strpos($text, 'جبيل') !== false || strpos($text, 'jubail') !== false) {
            return 'jubail';
        }
        if (strpos($text, 'رياض') !== false || strpos($text, 'riyadh') !== false) {
            return 'riyadh';
        }
        if (strpos($text, 'حائل') !== false || strpos($text, 'hail') !== false) {
            return 'hail';
        }
        if (strpos($text, 'بريدة') !== false || strpos($text, 'قصيم') !== false || strpos($text, 'buryadah') !== false) {
            return 'buraydah';
        }
        if (strpos($text, 'جوف') !== false || strpos($text, 'jouf') !== false || strpos($text, 'سكاكا') !== false) {
            return 'aljouf';
        }
        if (strpos($text, 'عرعر') !== false || strpos($text, 'arar') !== false) {
            return 'arar';
        }
        if (strpos($text, 'عمان') !== false || strpos($text, 'oman') !== false) {
            return 'oman';
        }
        if (strpos($text, 'أونلاين') !== false || strpos($text, 'online') !== false || strpos($text, 'عن بعد') !== false) {
            return 'online';
        }

        return null;
    }

    /**
     * Get display name for location code.
     *
     * @param string $code
     * @return string
     */
    public static function get_location_name(string $code): string {
        $names = [
            'jubail'   => 'الجبيل',
            'riyadh'   => 'الرياض',
            'hail'     => 'حائل',
            'buraydah' => 'بريدة',
            'aljouf'   => 'الجوف - سكاكا',
            'arar'     => 'عرعر',
            'oman'     => 'سلطنة عمان',
            'online'   => 'ON LINE (عن بعد)',
        ];
        return $names[$code] ?? $code;
    }

    /**
     * Utility slug generator for dimension codes.
     *
     * @param string $str
     * @return string
     */
    public static function slugify(string $str): string {
        $str = preg_replace('/[^\p{L}\p{N}]+/u', '_', $str);
        $str = trim($str, '_');
        return mb_substr($str, 0, 50, 'UTF-8');
    }
}
