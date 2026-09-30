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

// Dashboard & Settings strings.
$string['dashboard_subtitle'] = 'نظرة تنفيذية شاملة، الجدولة التشغيلية، ومؤشرات الأداء متعددة الفترات';
$string['org_resolution_mode_desc'] = 'حدد آلية احتساب وتوزيع الجهات والشركات المستفيدة: عبر تصنيفات المقررات، أو حقل الملف الشخصي (اسم الجهة)، أو النمط الهجين الذكي.';
$string['root_category_desc'] = 'حصر التقارير والاكتشاف التلقائي للدورات ضمن هذا التصنيف الرئيسي وتصنيفاته الفرعية.';
$string['workweek_mode_desc'] = 'تحديد أيام العمل المعتمدة لاحتساب ساعات التدريب المنفذة.';
$string['workweek_sun_thu'] = 'الأحد إلى الخميس (المعيار السعودي)';
$string['workweek_mon_fri'] = 'الإثنين إلى الجمعة (المعيار الدولي)';
$string['workweek_all_days'] = 'كافة أيام الأسبوع (تشغيل مستمر 7 أيام)';
$string['watermark_status'] = 'العلامة الزمنية لمستودع البيانات';
$string['watermark_status_desc'] = 'آخر يوم مكتمل ومُجمع في مستودع البيانات التاريخي: {$a}. يتم احتساب البيانات اللحظية ديناميكياً لما بعد هذا التاريخ.';

// Period Tabs & Toolbar.
$string['period_week'] = 'الجدول الأسبوعي';
$string['period_month'] = 'التقرير الشهري';
$string['period_annual'] = 'التراكمي السنوي';
$string['period_custom'] = 'فترة مخصصة';
$string['print'] = 'طباعة التقرير التنفيذي';
$string['apply_filters'] = 'تطبيق الفلترة';
$string['reset_filters'] = 'إعادة ضبط الفلاتر';
$string['all_organizations'] = 'كافة الجهات المستفيدة';
$string['all_program_types'] = 'كافة تصنيفات البرامج';
$string['all_locations'] = 'كافة المواقع والفروع';
$string['at_risk_alert_msg'] = 'تم رصد {$a} متدرب(اً) بحاجة إلى تدخل ومتابعة أكاديمية نظراً للانقطاع أو تدني نسب التحصيل في الدورات الجارية.';

// KPI Cards.
$string['metric_runs'] = 'البرامج المنفذة';
$string['metric_trainees'] = 'عدد المتدربين (مشاركات)';
$string['metric_training_hours'] = 'ساعات التدريب المنفذة';
$string['metric_completion_and_grade'] = 'نسبة الإنجاز ومستوى التحصيل';
$string['ytd'] = 'التراكمي السنوي';
$string['completed_trainees'] = 'منجز';
$string['avg_grade'] = 'متوسط الدرجات';

// Charts.
$string['chart_locations_title'] = 'توزيع المتدربين حسب الفروع والمواقع';
$string['chart_classifications_title'] = 'تصنيف البرامج التدريبية المنفذة';
$string['chart_trends_title'] = 'المسار الزمني التاريخي (آخر 6 أشهر)';

// Operational Table.
$string['operational_schedule'] = 'الجدول التشغيلي للبرامج التدريبية';
$string['export_excel'] = 'تصدير إلى إكسل';
$string['trainees_count'] = 'المتدربون';
$string['no_runs_found'] = 'لا توجد دورات تدريبية مطابقة لمعايير البحث المحددة.';

// Executive Left-Pane & SWA Presentation Strings.
$string['nav_weekly_plans'] = 'خطط التدريب الأسبوعي';
$string['nav_delivered_programs'] = 'البرامج التدريبية المنجزة';
$string['nav_trainees_reports'] = 'تقارير المتدربين';
$string['nav_management'] = 'إدارة الدورات واستيراد الجداول';

$string['weekly_schedule_title'] = 'جدول البرامج والدورات الأسبوعية';
$string['course_name'] = 'اسم البرنامج / الدورة';
$string['code_no'] = 'الرمز';
$string['duration'] = 'المدة';
$string['trainees_number'] = 'عدد المتدربين';
$string['daily_time'] = 'التوقيت اليومي';
$string['break_time'] = 'فترة الاستراحة';
$string['status'] = 'الحالة';
$string['plans_by_entity_title'] = 'خطة التدريب حسب الجهة المستفيدة ونوع البرنامج';
$string['entity'] = 'الجهة المستفيدة';
$string['execution_location'] = 'مقر التنفيذ';
$string['programs_count'] = 'عدد البرامج';
$string['groups_count'] = 'عدد المجموعات';
$string['total'] = 'الإجمالي';
$string['regional_branches_title'] = 'توزيع البرامج حسب الفروع والمراكز الإقليمية';
$string['branch'] = 'الفرع / المركز';
$string['courses_count'] = 'عدد الدورات';
$string['no_data_available'] = 'لا توجد بيانات متاحة حالياً.';

$string['delivered_programs_title'] = 'الدورات والبرامج المنجزة';
$string['period_from_to'] = 'من {$a->from} إلى {$a->to}';
$string['program_definitions_title'] = 'التعريف المعتمد للبرامج والدورات';
$string['def_dev_title'] = 'دورة تطويرية';
$string['def_dev_desc'] = 'برنامج تدريبي قصير المدى يهدف إلى إكساب وتطوير مهارات محددة (يوم إلى أسبوعين).';
$string['def_qual_title'] = 'دورة تأهيلية';
$string['def_qual_desc'] = 'دورة تدريبية متخصصة تؤهل المتدرب للحصول على شهادات مهنية أو اجتياز متطلبات محددة.';
$string['def_assist_dip_title'] = 'دبلوم مشارك';
$string['def_assist_dip_desc'] = 'برنامج تدريبي أكاديمي ومهني متوسط المدى.';
$string['def_qual_prog_title'] = 'برنامج تأهيلي';
$string['def_qual_prog_desc'] = 'حزمة برامج تدريبية متكاملة لتأهيل الكوادر الفنية والقيادية.';
$string['def_dip_prog_title'] = 'دبلوم تدريبي';
$string['def_dip_prog_desc'] = 'برنامج دبلوم تطبيقي معتمد يمتد لعدة فصول تدريبية لتخريج كوادر تخصصية.';
$string['trainee'] = 'متدرب';
$string['trainees'] = 'متدربين';
$string['programs'] = 'برامج';
$string['sectors_summary_title'] = 'تصنيف البرامج والمتدربين حسب القطاعات';
$string['corporate_clients_detail_title'] = 'تفاصيل التدريب للجهات والشركات المستفيدة';
$string['beneficiary_client'] = 'الجهة / الشركة المستفيدة';
$string['annual_trajectory_title'] = 'المسار التراكمي السنوي للبرامج المنجزة';
$string['month'] = 'الشهر';
$string['monthly_delivered'] = 'المنجز خلال الشهر';
$string['cumulative_trajectory'] = 'المسار التراكمي';
$string['cumulative_programs'] = 'تراكمي البرامج';
$string['cumulative_trainees'] = 'تراكمي المتدربين';
$string['strategic_partners_title'] = 'الشركاء الاستراتيجيون والبرامج المشتركة المستمرة';

$string['chart_programs_by_type_title'] = 'نسبة البرامج حسب التصنيف';
$string['chart_trainees_by_type_title'] = 'نسبة المتدربين حسب التصنيف';
$string['chart_programs_by_sector_title'] = 'نسبة البرامج حسب القطاع';
$string['chart_trainees_by_sector_title'] = 'نسبة المتدربين حسب القطاع';

$string['trainees_report_title'] = 'سجل وبيانات المتدربين والمتابعة الأكاديمية';
$string['trainee_name'] = 'اسم المتدرب';
$string['company'] = 'جهة العمل / الشركة';
$string['no_trainees_found'] = 'لا توجد بيانات متدربين مطابقة للبحث.';

$string['sync_courses_title'] = 'المزامنة التلقائية للمقررات';
$string['sync_courses_desc'] = 'استكشاف المقررات الجديدة وإنشاء دورات تشغيلية لها';
$string['sync_courses_explanation'] = 'يقوم النظام بمسح تصنيفات المقررات في مودل واكتشاف البرامج الجديدة لربطها بجدول الدورات التشغيلية تلقائياً.';
$string['sync_courses_btn'] = 'بدء المزامنة التلقائية الآن';
$string['courses_synced_success'] = 'تمت المزامنة واكتشاف {$a} دورة/برنامج جديد بنجاح.';

$string['runs_management_title'] = 'إدارة الدورات والمواعيد';
$string['runs_management_desc'] = 'تعديل بيانات القاعات والمدربين وحالات البرامج';
$string['runs_management_explanation'] = 'شاشة إدارة تفصيلية تمكن المشرفين من تعديل توقيت البرامج، القاعات، المدربين، وإلغاء أو استئناف الدورات.';
$string['runs_manage_btn'] = 'فتح شاشة إدارة الدورات';

$string['import_schedule_title'] = 'استيراد جدول البرامج';
$string['import_schedule_desc'] = 'رفع جدول التدريب الأسبوعي أو السنوي عبر ملف CSV';
$string['import_schedule_explanation'] = 'إمكانية إدخال واستيراد الجداول التدريبية وقوائم البرامج دفعة واحدة بكل سهولة عبر ملفات CSV.';
$string['import_csv_btn'] = 'استيراد جدول جديد';

$string['nav_group_overview'] = 'لوحة المؤشرات التنفيذية';
$string['nav_group_analytics'] = 'المتدربون والمتابعة';
$string['nav_group_tools'] = 'الإدارة والأدوات';
$string['nav_manage_runs'] = 'إدارة سجلات البرامج';
$string['nav_import_schedule'] = 'استيراد الجداول التدريبية';
$string['kpi_active_runs'] = 'البرامج التدريبية';
$string['kpi_active_runs_sub'] = 'برنامج منفذ ومخطط';
$string['kpi_total_trainees'] = 'إجمالي المتدربين';
$string['kpi_total_trainees_sub'] = 'متدرب مسجل بالدورات';
$string['kpi_training_hours'] = 'ساعات التدريب';
$string['kpi_training_hours_sub'] = 'ساعة تدريبية فعلية';
$string['kpi_completion_rate'] = 'نسبة الإنجاز';
$string['kpi_completion_rate_sub'] = 'معدل إتمام البرامج';
$string['kpi_avg_grade'] = 'متوسط الدرجات';
$string['kpi_avg_grade_sub'] = 'من 100 درجة';
$string['kpi_total_branches'] = 'الفروع والمراكز';
$string['kpi_total_branches_sub'] = 'موقع ومركز تدريبي';
$string['kpi_at_risk'] = 'حالات المتابعة والإنذار';
$string['kpi_at_risk_sub'] = 'تحت الإنذار والمتابعة';
$string['welcome_title'] = 'مرحباً بك في لوحة التقارير والمؤشرات التنفيذية';
$string['welcome_subtitle'] = 'منظومة المتابعة التشغيلية والتحليل الإحصائي المتكامل للبرامج التدريبية';


