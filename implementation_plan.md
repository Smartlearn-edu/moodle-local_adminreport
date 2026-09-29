# Implementation Plan - Moodle Management Intelligence & Advanced Reporting Plugin (`local_adminreport`)

Transform raw Moodle LMS data into an executive-grade **Management Intelligence & Operational Analytics Dashboard** for Moodle 4.5+ and 5.x.

## User Review Required

> [!IMPORTANT]
> **Key Architecture Decisions Approved in Revision 2.1 (Pre-Implementation Freeze)**:
> 1. **Offering / Run Abstraction (`mdl_local_adminreport_runs`)**: Anchors analytics to runs rather than static course IDs, supporting repeated course codes across dates, branches, and multi-batch offerings.
> 2. **Stock vs. Flow Daily Facts**: Participations are stored once per run; training hours are stored per working day. Eliminates artificial participant-day ballooning.
> 3. **Audited Golden Targets**: Test assertions verify the mathematical sum of parts ($287$ runs and $4,409$ trainees annually), bypassing the arithmetic error in the client PPT summary box ($285$ / $4,363$).
> 4. **Dynamic Watermark Tier Seam**: Eliminates midnight cron race conditions by anchoring Intraday from `watermark + 1 sec` to `now`.
> 5. **Scope & Privacy Compliance**: Scoping table (`mdl_local_adminreport_scope`) with scoped cache keys; full Moodle Privacy Provider covering trainer and scope user IDs.
> 6. **Full Run Lifecycle**: Web form, CSV/XLSX importer, course auto-discovery task, and CLI rebuild utility.

---

## Technical Architecture & Database Design

### 1. Database Schema (`db/install.xml`)
- `mdl_local_adminreport_dim_types`: Dimension definitions (Sector, Organization, Program Type, Location).
- `mdl_local_adminreport_dim_members`: Dimension members with parent-child hierarchy (Sector $\rightarrow$ Organization).
- `mdl_local_adminreport_runs`: Program offerings/runs (Course ID, Group ID, Run Code, Start/End dates, Room, Timings, Cancelled flag).
- `mdl_local_adminreport_run_trainers`: Multi-trainer assignments per run (nullable user ID + trainer text name).
- `mdl_local_adminreport_daily_stats`: Daily flow facts (`stat_date`, `run_id`, `participations_flow`, `completions_flow`, `day_training_hours`, `grade_sum`, `grade_count`).
- `mdl_local_adminreport_monthly_stats`: Monthly warehouse rollups with location dimensions (`year`, `month`, `org_dim_id`, `type_dim_id`, `location_dim_id`, `runs_count`, `participations_count`, `completions_count`, `total_training_hours`).
- `mdl_local_adminreport_scope`: Scoping rules mapping user/role to dimension members.

### 2. Permissions & Capabilities (`db/access.php`)
- `local/adminreport:view`: View executive dashboard.
- `local/adminreport:viewall`: View global data across all organizations and categories.
- `local/adminreport:viewtrainees`: View individual learner identities in early-warning at-risk lists.
- `local/adminreport:export`: Download Excel/CSV data exports.
- `local/adminreport:configure`: Manage dimension mappings and risk thresholds.
- `local/adminreport:manage`: Edit course operational run metadata, import runs, and trigger data recalculations.

---

## Proposed Changes

### Phase 1: Plugin Foundation & Core Moodle Infrastructure
- [NEW] [version.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/version.php) - Component declaration (`local_adminreport`), requires Moodle 4.5+ (`2024100700`+), maturity `MATURITY_ALPHA`.
- [NEW] [db/install.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/install.xml) - Complete XMLDB schema with unique keys, foreign keys, and indexes.
- [NEW] [db/access.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/access.php) - Capabilities with risk bitmasks.
- [NEW] [db/tasks.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/tasks.php) - Scheduled tasks: `aggregate_analytics` (nightly) and `auto_discover_runs` (hourly).
- [NEW] [db/services.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/services.php) - External AJAX Web Service registration (`local_adminreport_get_report_data`).
- [NEW] [db/caches.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/caches.php) - MUC cache definition (`reports_data`) with scope-hashed application TTL.
- [NEW] [classes/privacy/provider.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/privacy/provider.php) - Full Moodle Privacy Provider exporting/deleting trainer and scope references.
- [NEW] [thirdpartylibs.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/thirdpartylibs.xml) - Registration of ApexCharts (MIT License).

### Phase 2: Runs Lifecycle, Editors & Population Tools
- [NEW] [classes/form/run_form.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/form/run_form.php) - Moodle QuickForm to create/edit run schedules and classroom logistics.
- [NEW] [runs.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/runs.php) - Web interface to manage, search, filter, and cancel runs.
- [NEW] [classes/import/run_importer.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/import/run_importer.php) - CSV/XLSX importer for planned upcoming runs and historical records.
- [NEW] [classes/task/auto_discover_runs.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/auto_discover_runs.php) - Auto-discovers and creates default runs from Moodle courses.
- [NEW] [cli/rebuild_warehouse.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/cli/rebuild_warehouse.php) - Administrative CLI tool to rebuild facts across custom date windows.

### Phase 3: Analytics Engine, Watermark Tier Stitcher & Scoping
- [NEW] [classes/analytics/dimension_manager.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/dimension_manager.php) - Resolves dimension mappings and parent-child hierarchies.
- [NEW] [classes/analytics/query_engine.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/query_engine.php) - Single-pass SQL engine with unified `compute_run_facts($run, $date)` function.
- [NEW] [classes/analytics/tier_stitcher.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/tier_stitcher.php) - Watermark-based tier stitching (Warehouse $\le$ watermark + Intraday watermark+1 to now).
- [NEW] [classes/analytics/early_warning.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/early_warning.php) - On-demand at-risk trainee evaluation.
- [NEW] [classes/task/aggregate_analytics.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/aggregate_analytics.php) - Nightly cron task updating watermark with 7-day rolling lookback.
- [NEW] [classes/external/get_report_data.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/external/get_report_data.php) - External API endpoint with context validation and scope enforcement.

### Phase 4: Dashboard UI, Visualizations & Executive Print View
- [NEW] [settings.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/settings.php) - Admin configuration area (Root category selector, workweek boundaries, default presets).
- [NEW] [index.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/index.php) - Main dashboard controller with scope enforcement.
- [NEW] [templates/dashboard.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/dashboard.mustache) - Dashboard shell with full RTL support.
- [NEW] [templates/kpi_cards.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/kpi_cards.mustache) - Period KPI cards with YTD badges.
- [NEW] [templates/operational_table.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/operational_table.mustache) - Paginated, searchable operational schedule table.
- [NEW] [amd/src/apexcharts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/apexcharts.js) - Bundled ApexCharts 3.x.
- [NEW] [amd/src/charts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/charts.js) - ApexCharts initializer for Donut, Stacked Column, and Trend Line with RTL support.
- [NEW] [amd/src/dashboard.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/dashboard.js) - AJAX filter controller.
- [NEW] [styles.css](file:///home/mohammad/Dev/plugins/local/report/adminreport/styles.css) - Includes `@media print` rules for clean, executive-ready PDF printing.
- [NEW] [lang/en/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/en/local_adminreport.php) - English strings.
- [NEW] [lang/ar/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/ar/local_adminreport.php) - Arabic strings.

### Phase 5: Export Engine & Reconciliation Tests
- [NEW] [classes/export/report_exporter.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/export/report_exporter.php) - Data export via `\core\dataformat`.
- [NEW] [export.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/export.php) - Export endpoint with sesskey and scope enforcement.
- [NEW] [tests/fixtures/sample_runs.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/tests/fixtures/sample_runs.php) - Test fixtures mirroring the client reference deck.
- [NEW] [tests/reconciliation_test.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/tests/reconciliation_test.php) - Mathematical reconciliation tests asserting the true sum of parts ($337$ trainees planned, Jubail $125$ / Riyadh $96$, $258$ weekly delivered, $287$ runs / $4,409$ trainees annually).

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
- **Totals Consistency**: Sum of trainees across organizations must equal the grand total; sum across locations must equal the grand total.
- **Watermark Seam Verification**: Verify that moving the watermark date between yesterday and today produces continuous, gap-free, non-duplicating totals.
