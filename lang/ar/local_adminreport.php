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
 * Arabic language strings for local_adminreport.
 *
 * @package    local_adminreport
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الذكاء الإداري والتقارير المتقدمة';

// Capabilities.
$string['adminreport:view'] = 'عرض لوحة الذكاء الإداري والتقارير';
$string['adminreport:viewall'] = 'عرض كافة التقارير العامة لجميع الجهات والتصنيفات';
$string['adminreport:viewtrainees'] = 'عرض هويات المتدربين وقوائم التنبيه المبكر للطلاب المتعثرين';
$string['adminreport:export'] = 'تصدير بيانات التقارير إلى إكسل و CSV';
$string['adminreport:configure'] = 'إعداد أبعاد التقارير ومصادر البيانات وقواعد التنبيه';
$string['adminreport:manage'] = 'إدارة دورات البرامج واستيراد الجداول وإعادة احتساب المؤشرات';

// Tasks.
$string['task_aggregate_analytics'] = 'تجميع إحصائيات التقارير الليلية وتحديث العلامة الزمنية';
$string['task_auto_discover_runs'] = 'الاكتشاف التلقائي لدورات البرامج من المقررات';

// Privacy metadata.
$string['privacy:metadata:run_trainers'] = 'تخزين تعيينات المدربين على دورات البرامج التدريبية';
$string['privacy:metadata:run_trainers:run_id'] = 'معرف دورة البرنامج';
$string['privacy:metadata:run_trainers:userid'] = 'معرف المستخدم للمدرب في مودل';
$string['privacy:metadata:run_trainers:trainer_name'] = 'الاسم النصي للمدرب';
$string['privacy:metadata:run_trainers:is_primary'] = 'ما إذا كان هذا المدرب هو المدرب الرئيسي';
$string['privacy:metadata:scope'] = 'تخزين قواعد نطاق الصلاحيات للمستخدمين والأدوار';
$string['privacy:metadata:scope:scope_type'] = 'نوع النطاق (مستخدم أو دور)';
$string['privacy:metadata:scope:scope_id'] = 'معرف المستخدم أو الدور';
$string['privacy:metadata:scope:dim_member_id'] = 'معرف عضو البُعد المعين لهذا النطاق';
$string['privacy:metadata:scope:timemodified'] = 'وقت آخر تعديل لقاعدة النطاق';
$string['trainer_assignments'] = 'تعيينات المدربين';
$string['scoping_rules'] = 'قواعد نطاق صلاحيات التقارير';

// General terms & navigation.
$string['dashboard'] = 'لوحة التحكم';
$string['runs'] = 'دورات البرامج التدريبية';
$string['add_run'] = 'إضافة دورة تدريبية';
$string['edit_run'] = 'تعديل الدورة التدريبية';
$string['run_code'] = 'رمز الدورة';
$string['organizations'] = 'الجهات والشركات';
$string['organization'] = 'الجهة المستفيدة';
$string['sectors'] = 'القطاعات';
$string['sector'] = 'القطاع';
$string['locations'] = 'المواقع والفروع';
$string['location'] = 'الموقع / الفرع';
$string['program_types'] = 'تصنيفات البرامج';
$string['program_type'] = 'تصنيف البرنامج';
$string['dimensions'] = 'الأبعاد والتصنيفات';
$string['metrics'] = 'مؤشرات الأداء';
$string['settings'] = 'الإعدادات';
$string['dates'] = 'الفترة الزمنية';
$string['actions'] = 'الإجراءات';

// Logistics & Forms.
$string['schedule_logistics'] = 'الجدولة واللوجستيات';
$string['classroom'] = 'القاعة التدريبية';
$string['daily_start_time'] = 'وقت البدء اليومي';
$string['daily_end_time'] = 'وقت الانتهاء اليومي';
$string['break_duration_min'] = 'مدة الاستراحة (دقيقة)';
$string['exam_time'] = 'وقت الاختبار';
$string['trainer'] = 'المدرب';
$string['trainer_name'] = 'اسم المدرب';
$string['trainer_userid'] = 'معرف المستخدم للمدرب (اختياري)';
$string['is_cancelled'] = 'تحديد كدورة ملغاة';
$string['uncancel'] = 'استعادة الدورة';
$string['status_updated'] = 'تم تحديث حالة الدورة بنجاح.';
$string['enddate_before_startdate'] = 'لا يمكن أن يكون تاريخ الانتهاء قبل تاريخ البدء.';
$string['help_groupid'] = 'معرف المجموعة (Group ID)';
$string['help_groupid_help'] = 'إذا كانت هذه الدورة التدريبية تمثل مجموعة طلابية محددة داخل المقرر، أدخل معرف المجموعة هنا. اترك القيمة 0 لكامل المقرر.';

// Run statuses.
$string['status_cancelled'] = 'ملغاة';
$string['status_planned'] = 'مجدولة';
$string['status_running'] = 'جارية حالياً';
$string['status_completed'] = 'منفذة (مكتملة)';

// Importer.
$string['import_runs'] = 'استيراد دورات البرامج';
$string['back_to_runs'] = 'العودة لقائمة الدورات';
$string['download_sample_csv'] = 'تحميل نموذج CSV تجريبي';
$string['paste_csv_data'] = 'لصق محتوى CSV';
$string['import'] = 'استيراد البيانات';
$string['import_desc'] = 'استيراد دورات البرامج التدريبية المجدولة والسابقة دفعة واحدة عبر ملف CSV.';
$string['import_success'] = 'تم استيراد {$a} دورة تدريبية بنجاح.';
$string['import_failed'] = 'تعذر استيراد {$a} دورة. يرجى مراجعة الأخطاء التالية:';
$string['empty_file'] = 'محتوى ملف الاستيراد فارغ.';

// Dimension resolution & settings.
$string['org_resolution_mode'] = 'نمط تحديد وتوزيع الجهات';
$string['org_mode_category'] = 'تصنيف المقررات فقط';
$string['org_mode_user_profile'] = 'حقل الملف الشخصي للمستخدم فقط (اسم الجهة)';
$string['org_mode_hybrid'] = 'النمط الهجين الذكي (التصنيف كمرجع وتوزيع المتدربين حسب الملف)';
$string['root_category'] = 'التصنيف الرئيسي لبرامج التدريب';
$string['workweek_mode'] = 'جدول أيام العمل الأسبوعية';

// Early Warning & At-Risk Trainees.
$string['early_warning'] = 'نظام الإنذار المبكر للمتدربين المتعثرين';
$string['risk_never_accessed'] = 'لم يقم المتدرب بالدخول إلى المقرر حتى الآن';
$string['risk_inactive_days'] = 'غير نشط في المقرر منذ {$a} يوم';
$string['risk_grade_low'] = 'الدرجة الحالية {$a} (أقل من الحد الأدنى للاجتياز)';
$string['trainee_masked'] = 'متدرب #{$a}';
$string['unspecified'] = 'غير محدد';
$string['never'] = 'أبداً';
$string['total_at_risk'] = 'إجمالي المتدربين المتعثرين';
