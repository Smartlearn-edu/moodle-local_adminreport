# Implementation Plan - Moodle Management Intelligence & Advanced Reporting Plugin (`local_adminreport`)

Transform raw Moodle LMS data into an executive-grade **Management Intelligence & Operational Analytics Dashboard** for Moodle 4.5+ and 5.x.

## User Review Required

> [!IMPORTANT]
> **Key Architecture Decisions Approved in Revision 2.0**:
> 1. **Offering / Run Abstraction (`mdl_local_adminreport_runs`)**: Decouples program runs from raw course IDs to support recurring course codes, multi-batch schedules, and both 1-course-per-run and 1-course-with-groups topologies.
> 2. **One-Page Metric Dictionary**: Distinguishes additive trainee participations from non-additive unique individuals; derives trainee hours; avoids the average-of-averages fallacy by storing `grade_sum` and `grade_count`.
> 3. **Normalized Dimension Model**: Dimension Types (`sector`, `organization`, `program_type`, `location`) and Dimension Members with parent-child support (`Sector` $\rightarrow$ `Organization`).
> 4. **Tier-Stitching & Zero Double-Counting**: Strict date seam: Warehouse ($\le$ yesterday midnight) + Intraday ($\ge$ today midnight). MUC cache keys partitioned by viewer ID and capability scope.
> 5. **Privacy API**: Implements `\core_privacy\local\metadata\null_provider` as fact tables contain zero learner PII.

---

## Technical Architecture & Database Design

### 1. Database Schema (`db/install.xml`)
- `mdl_local_adminreport_dim_types`: Dimension definitions (Sector, Organization, Program Type, Location).
- `mdl_local_adminreport_dim_members`: Dimension members with hierarchical parent-child relationships.
- `mdl_local_adminreport_runs`: Program offerings/runs (Course ID, Group ID, Run Code, Start/End dates, Room, Timings).
- `mdl_local_adminreport_daily_stats`: Daily fact aggregations (Date, Run ID, Dimensions, Participations, Completions, Grade sum/count, Hours).
- `mdl_local_adminreport_monthly_stats`: Monthly and annual cumulative summaries (Year, Month, Dimensions, Cumulative metrics).

### 2. Permissions & Capabilities (`db/access.php`)
- `local/adminreport:view`: View executive dashboard.
- `local/adminreport:viewall`: View global data across all organizations and categories.
- `local/adminreport:viewtrainees`: View individual learner identities in early-warning at-risk lists.
- `local/adminreport:export`: Download Excel/CSV data exports.
- `local/adminreport:configure`: Manage dimension mappings and risk thresholds.
- `local/adminreport:manage`: Trigger manual re-sync and edit course operational run metadata.

---

## Proposed Changes

### Phase 1: Plugin Foundation & Core Moodle Infrastructure
- [NEW] [version.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/version.php) - Component declaration (`local_adminreport`), requires Moodle 4.5+ (`2024100700`+), maturity `MATURITY_ALPHA`.
- [NEW] [db/install.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/install.xml) - Complete XMLDB schema for dimension types, dimension members, runs, daily facts, and monthly warehouse tables.
- [NEW] [db/access.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/access.php) - Granular capability declarations with risk bitmasks.
- [NEW] [db/tasks.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/tasks.php) - Scheduled task registration (`\local_adminreport\task\aggregate_analytics` nightly at 02:00 AM).
- [NEW] [db/services.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/services.php) - External AJAX Web Service registration (`local_adminreport_get_report_data`).
- [NEW] [db/caches.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/caches.php) - MUC cache definition (`reports_data`) with scoped application TTL.
- [NEW] [classes/privacy/provider.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/privacy/provider.php) - GDPR compliance via `\core_privacy\local\metadata\null_provider`.
- [NEW] [thirdpartylibs.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/thirdpartylibs.xml) - Registration of ApexCharts (MIT License).

### Phase 2: Analytics Engine & Dimension Resolution
- [NEW] [classes/analytics/dimension_manager.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/dimension_manager.php) - Dynamic dimension resolver (Category hierarchy, Course Custom Fields, or Manual mapping).
- [NEW] [classes/analytics/query_engine.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/query_engine.php) - Single-pass, set-based SQL queries with zero N+1 loops.
- [NEW] [classes/analytics/tier_stitcher.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/tier_stitcher.php) - Cleanly unites warehouse historical facts with today's intraday counts with zero double-counting.
- [NEW] [classes/task/aggregate_analytics.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/aggregate_analytics.php) - Scheduled aggregation processor with rolling 7-day lookback upsert.
- [NEW] [classes/external/get_report_data.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/external/get_report_data.php) - External API controller for responsive AJAX filtering.

### Phase 3: Dashboard UI, Visualizations & Localization
- [NEW] [settings.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/settings.php) - Admin configuration area (Root category selector, workweek boundaries, default presets).
- [NEW] [index.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/index.php) - Main dashboard entry point with scope and capability enforcement.
- [NEW] [templates/dashboard.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/dashboard.mustache) - Responsive dashboard shell with full RTL support.
- [NEW] [templates/kpi_cards.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/kpi_cards.mustache) - Metric cards displaying period numbers and YTD context badges.
- [NEW] [templates/operational_table.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/operational_table.mustache) - Paginated, searchable operational course schedule table.
- [NEW] [amd/src/apexcharts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/apexcharts.js) - ApexCharts library wrapper.
- [NEW] [amd/src/charts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/charts.js) - ApexCharts initializer for Donut, Stacked Column, and Cumulative Line charts.
- [NEW] [amd/src/dashboard.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/dashboard.js) - AJAX filter controller for real-time period and organization switching.
- [NEW] [lang/en/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/en/local_adminreport.php) - English language strings.
- [NEW] [lang/ar/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/ar/local_adminreport.php) - Complete native Arabic localization.

### Phase 4: Export Engine & Verification
- [NEW] [classes/export/report_exporter.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/export/report_exporter.php) - Zero-dependency data export using Moodle's native `\core\dataformat`.
- [NEW] [export.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/export.php) - Export endpoint with strict sesskey and capability verification.
- [NEW] [tests/reconciliation_test.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/tests/reconciliation_test.php) - Mathematical reconciliation tests matching client reference golden targets (Slide 4: 22 runs, 337 trainees; Jubail 125 / Riyadh 96; Slide 6: YTD 285 runs, 4,363 trainees).

---

## Verification Plan

### Automated & Standards Checks
1. **PHP Syntax & Moodle Coding Standards**:
   - `php -l` on all PHP files.
   - Moodle CodeSniffer (`moodlecs`) compliance.
2. **AMD Transpilation & ESLint**:
   - Transpiled via Grunt with clean ESLint validation.
3. **Database Integrity**:
   - Validate `install.xml` schema against Moodle XMLDB Editor specifications.

### Mathematical Reconciliation Checks
- **Totals Consistency**: Sum of trainees across all organizations must equal the grand total; sum across all locations must equal the grand total.
- **Tier-Stitching Seam**: Verify that switching between yesterday and today produces continuous, gap-free, non-duplicating totals.
