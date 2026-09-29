# Implementation Plan - Moodle Management Intelligence & Advanced Reporting Plugin (`local_adminreport`)

Transform raw Moodle LMS data into an executive-grade **Management Intelligence & Operational Analytics Dashboard** for Moodle 4.5+ and 5.x.

## User Review Required

> [!IMPORTANT]
> **Key Architecture Decisions in Revision 2.2 (Dual Company Resolution & Phased Roadmap)**:
> 1. **Dual Company Resolution (الجهات)**: Supports companies defined both by **Course Categories** (for dedicated client courses) and by **User Profile Custom Field `CompanyName`** (for shared multi-company cohorts like *Alkhorayef + ILF + Bewater*). Configurable via Admin settings (Category Only, User Profile Field Only, or Smart Hybrid).
> 2. **Multi-Company Daily Fact Storage**: `mdl_local_adminreport_daily_stats` stores flow facts keyed by `(stat_date, run_id, org_dim_id)`, enabling precise company breakdowns even when trainees from multiple companies attend a single run.
> 3. **Phased Milestone Gates**: Execution is structured into 5 strictly sequential, verifiable phases. Each phase is self-contained and gated by review before advancing to the next.

---

## Technical Architecture & Database Design

### 1. Database Schema (`db/install.xml`)
- `mdl_local_adminreport_dim_types`: Dimension definitions (Sector, Organization, Program Type, Location).
- `mdl_local_adminreport_dim_members`: Dimension members with parent-child hierarchy (Sector $\rightarrow$ Organization).
- `mdl_local_adminreport_runs`: Program offerings/runs (Course ID, Group ID, Run Code, Start/End dates, Room, Timings, Cancelled flag).
- `mdl_local_adminreport_run_trainers`: Multi-trainer assignments per run (nullable user ID + trainer text name).
- `mdl_local_adminreport_daily_stats`: Daily flow facts keyed by `(stat_date, run_id, org_dim_id)`.
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

## Phased Implementation Roadmap

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 1: Plugin Foundation, Database Architecture & Core Infrastructure         │
│ (version.php, install.xml, access.php, services.php, caches.php, privacy, libs)  │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 2: Dual Dimension Resolution & Runs Management Lifecycle                   │
│ (Category + Profile Field CompanyName, runs.php, run_form, run_importer, auto)   │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 3: High-Performance Analytics Engine & Watermark Tier Stitcher             │
│ (query_engine with multi-company facts, tier_stitcher, cron task, external API)  │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 4: Executive Dashboard UI, ApexCharts & Print View                         │
│ (settings.php, index.php, Mustache templates, ApexCharts RTL, Print CSS, Lang)   │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 5: Export Engine & Mathematical Reconciliation Tests                       │
│ (dataformat XLSX/CSV export, tests/fixtures, reconciliation_test.php)            │
└──────────────────────────────────────────────────────────────────────────────────┘
```

---

### Detailed Deliverables by Phase

#### Phase 1: Foundation, Database Architecture & Core Infrastructure (COMPLETED)
*Goal: Establish the valid, installable Moodle plugin skeleton and complete relational schema.*
- [x] [version.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/version.php) - Component declaration (`local_adminreport`), requires Moodle 4.5+ (`2024100700`+), maturity `MATURITY_ALPHA`.
- [x] [db/install.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/install.xml) - Complete XMLDB schema with unique keys, foreign keys, and indexes for all 7 tables.
- [x] [db/access.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/access.php) - Capabilities with risk bitmasks.
- [x] [db/tasks.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/tasks.php) - Scheduled tasks: `aggregate_analytics` (nightly) and `auto_discover_runs` (hourly).
- [x] [db/services.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/services.php) - External AJAX Web Service registration (`local_adminreport_get_report_data`).
- [x] [db/caches.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/caches.php) - MUC cache definition (`reports_data`) with scope-hashed application TTL.
- [x] [classes/privacy/provider.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/privacy/provider.php) - Full Moodle Privacy Provider exporting/deleting trainer and scope references.
- [x] [thirdpartylibs.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/thirdpartylibs.xml) - Registration of ApexCharts (MIT License).

#### Phase 2: Dual Dimension Resolution & Runs Management (COMPLETED)
*Goal: Provide the data resolution and population layer supporting both Categories and User Profile Field `CompanyName`.*
- [x] [classes/analytics/dimension_manager.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/dimension_manager.php) - Dual company resolver: Category path, User Profile field `CompanyName` (`mdl_user_info_data`), or Smart Hybrid.
- [x] [classes/form/run_form.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/form/run_form.php) - Moodle QuickForm to create/edit run schedules and classroom logistics.
- [x] [runs.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/runs.php) - Web interface to manage, search, filter, and cancel runs.
- [x] [classes/import/run_importer.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/import/run_importer.php) - CSV/XLSX importer for planned upcoming runs and historical records.
- [x] [import.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/import.php) - Web interface to upload or paste CSV and download template.
- [x] [classes/task/auto_discover_runs.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/auto_discover_runs.php) - Auto-discovers and creates default runs from Moodle courses with teacher auto-assignment.
- [x] [cli/rebuild_warehouse.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/cli/rebuild_warehouse.php) - Administrative CLI tool to rebuild facts across custom date windows.
- [x] [cli/test_phase2.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/cli/test_phase2.php) - Phase 2 automated test suite covering dimensions, custom profile fields, auto-discovery, and CSV import.

#### Phase 3: Analytics Engine, Watermark Tier Stitcher & Scoping (COMPLETED)
*Goal: Build the high-performance calculation engine that aggregates data without table locks or N+1 queries.*
- [x] [classes/analytics/query_engine.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/query_engine.php) - Single-pass SQL engine with unified `compute_run_facts($run, $date)` supporting multi-company runs.
- [x] [classes/analytics/tier_stitcher.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/tier_stitcher.php) - Watermark-based tier stitching (Warehouse $\le$ watermark + Intraday watermark+1 to now).
- [x] [classes/analytics/early_warning.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/early_warning.php) - On-demand at-risk trainee evaluation.
- [x] [classes/task/aggregate_analytics.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/aggregate_analytics.php) - Nightly cron task updating watermark with 7-day rolling lookback.
- [x] [classes/external/get_report_data.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/external/get_report_data.php) - External API endpoint with context validation and scope enforcement.
- [x] [cli/test_phase3.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/cli/test_phase3.php) - Phase 3 automated test suite covering flow calculations, tier stitching, early warning, and scheduled task.

#### Phase 4: Dashboard UI, Visualizations & Executive Print View
*Goal: Deliver the responsive, bilingual RTL management dashboard.*
- [NEW] [settings.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/settings.php) - Admin configuration area (Organization resolution mode: Category vs `CompanyName` vs Hybrid; root category selector; workweek boundaries).
- [NEW] [index.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/index.php) - Main dashboard controller with scope enforcement.
- [NEW] [templates/dashboard.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/dashboard.mustache) - Dashboard shell with full RTL support.
- [NEW] [templates/kpi_cards.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/kpi_cards.mustache) - Period KPI cards with YTD badges.
- [NEW] [templates/operational_table.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/operational_table.mustache) - Paginated, searchable operational schedule table.
- [NEW] [amd/src/apexcharts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/apexcharts.js) - Bundled ApexCharts 3.x.
- [NEW] [amd/src/charts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/charts.js) - ApexCharts initializer for Donut, Stacked Column, and Trend Line with RTL support.
- [NEW] [amd/src/dashboard.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/dashboard.js) - AJAX filter controller.
- [NEW] [styles.css](file:///home/mohammad/Dev/plugins/local/report/adminreport/styles.css) - Includes `@media print` rules for clean, executive-ready PDF printing.
- [NEW] [lang/en/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/en/local_adminreport.php) - English strings.
- [NEW] [lang/ar/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/ar/local_adminreport.php) - Native Arabic strings.

#### Phase 5: Export Engine & Reconciliation Tests
*Goal: Provide Excel export and verify mathematical correctness against reference targets.*
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
- **Dual Company Verification**: Test runs where company is derived from Course Category vs runs where company is derived from user profile field `CompanyName`.
