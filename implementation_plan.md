# Implementation Plan - Moodle Management Intelligence & Advanced Reporting Plugin (`local_adminreport`)

Transform raw Moodle LMS data into an executive-grade **Management Intelligence & Operational Analytics Dashboard** with multi-tier caching, generic dimension mapping, and ApexCharts visualization.

## User Review Required

> [!IMPORTANT]
> **Data Strategy Realignment**:
> 1. **Tri-Tier Data Model**: Decoupled into **Real-Time** (live counts), **Near-Real-Time** (today's intraday activity with 10m MUC cache), and **Historical Warehouse** (pre-aggregated nightly cron task).
> 2. **Configurable Dimensions**: Course categories are treated as *one* configurable dimension source, alongside Course Custom Fields, User Profile Fields, and Cohorts.
> 3. **Operational Metadata Layer**: Provides a clean strategy for institutional scheduling fields (classroom, daily timings, break time, exam time) without corrupting Moodle core schemas.
> 4. **Scope Control**: V1 delivers a focused, high-impact cockpit with 4 KPIs, 2 ApexCharts, and an interactive operational table, paving the way for V2 deep drill-downs and automated dispatch.

---

## Technical Architecture & Database Design

### 1. Database Schema (`db/install.xml`)
- `mdl_local_adminreport_dims`: Dimension definitions (Organization, Program Type, Campus).
- `mdl_local_adminreport_meta`: Supplementary operational metadata per course (Classroom, schedule, exam time, break time).
- `mdl_local_adminreport_daily_stats`: Daily fact aggregations (date, course, dimensions, enrolments, completions, hours).
- `mdl_local_adminreport_monthly_stats`: Monthly and annual cumulative summaries for instant 12-month progress tracking.

### 2. Permissions & Capabilities (`db/access.php`)
- `local/adminreport:view`: View executive dashboard.
- `local/adminreport:viewall`: View global data across all organizations and categories.
- `local/adminreport:export`: Download Excel, CSV, and executive PDF.
- `local/adminreport:configure`: Manage dimension mappings and risk thresholds.
- `local/adminreport:manage`: Trigger manual re-sync and edit course operational metadata.

---

## Proposed Changes

### Phase 1: Plugin Foundation & Database Layer
- [NEW] [version.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/version.php) - Component declaration (`local_adminreport`), requires Moodle 4.5+ (build `2024100700`+).
- [NEW] [db/install.xml](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/install.xml) - Schema definition for dimensions, metadata, daily stats, and monthly warehouse tables.
- [NEW] [db/access.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/access.php) - Role capabilities and scoping permissions.
- [NEW] [db/tasks.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/db/tasks.php) - Registration of `\local_adminreport\task\aggregate_analytics` (nightly at 02:00 AM).
- [NEW] [classes/privacy/provider.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/privacy/provider.php) - Full GDPR compliance via Moodle Privacy API.

### Phase 2: Analytics Engine & Dimension Mapper
- [NEW] [classes/analytics/dimension_manager.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/dimension_manager.php) - Generic dimension resolver supporting Categories, Custom Fields, and Cohorts.
- [NEW] [classes/analytics/query_engine.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/analytics/query_engine.php) - High-performance set-based SQL engine with zero N+1 queries.
- [NEW] [classes/task/aggregate_analytics.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/task/aggregate_analytics.php) - Scheduled aggregation processor.

### Phase 3: Dashboard UI & Visualizations
- [NEW] [settings.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/settings.php) - Admin configuration area for root category selection, dimension mapping, and risk rules.
- [NEW] [index.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/index.php) - Main dashboard entry controller with permission checks.
- [NEW] [templates/dashboard.mustache](file:///home/mohammad/Dev/plugins/local/report/adminreport/templates/dashboard.mustache) - Focused V1 dashboard shell with RTL support.
- [NEW] [amd/src/charts.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/charts.js) - ApexCharts integration supporting Donut, Stacked Column, and Trend Line charts.
- [NEW] [amd/src/dashboard.js](file:///home/mohammad/Dev/plugins/local/report/adminreport/amd/src/dashboard.js) - AJAX filter controller for seamless date and organization filtering.
- [NEW] [lang/en/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/en/local_adminreport.php) - English language strings.
- [NEW] [lang/ar/local_adminreport.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/lang/ar/local_adminreport.php) - Complete Arabic localization.

### Phase 4: Export Engine
- [NEW] [classes/export/excel_builder.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/classes/export/excel_builder.php) - Multi-sheet Excel workbook generator (`.xlsx`).
- [NEW] [export.php](file:///home/mohammad/Dev/plugins/local/report/adminreport/export.php) - Endpoint for Excel and print-ready PDF export.

---

## Verification Plan

### Automated & Standards Checks
1. **PHP Syntax & Moodle Coding Standards**:
   - Run PHP lint check on all files.
   - Validate with Moodle CodeSniffer rules (`moodlecs`).
2. **AMD Transpilation & ESLint**:
   - Ensure AMD Javascript compiles without lint warnings.
3. **Database Integrity**:
   - Verify `install.xml` schema against Moodle XMLDB Editor specs.

### Performance Verification
- Measure query count during dashboard loading (must be $< 5$ queries for cached/aggregated dashboard).
- Verify zero N+1 queries during scheduled task batch execution.
