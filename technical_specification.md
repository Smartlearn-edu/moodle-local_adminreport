# Technical Specification: Advanced Moodle Management Intelligence & Analytics Plugin (`local_adminreport`)

* **Component:** `local_adminreport`
* **Target Version:** Moodle 4.5+ / 5.x Ready
* **Author:** Antigravity / SmartLearn Architecture
* **Status:** Architecture Approved — Ready for Phase 1 Implementation
* **Document Version:** 1.0.0

---

## 1. Executive Summary & Product Vision

The purpose of `local_adminreport` is to transform raw Moodle LMS data into an **Executive Management Intelligence Dashboard**. 

While the reference client report (Water Academy / أكاديمية المياه) originated as a manually assembled PowerPoint deck with operational training schedules, sector breakdowns, and annual cumulative figures, this plugin is designed as a **universal, enterprise-grade reporting framework** suitable for both high-capacity national institutions and commercial multi-tenant setups.

### Core Architectural Philosophy: The Analytics Triad
Every analytic insight in the system conforms to the universal data model:

$$\mathbf{Analytics} = \mathbf{Metric} \times \mathbf{Dimension} \times \mathbf{Time}$$

* **Metric:** Quantitative measurements (*Active Learners, Completions, Trainee Hours, Average Grades, Risk Score*).
* **Dimension:** Categorical slicing axes (*Organization/Client, Program Type, Campus/Location, Trainer, Cohort*).
* **Time:** Temporal window (*Weekly, Monthly, Annual Cumulative, Custom Range, Comparative Deltas*).

---

## 2. The 3-Tier Data & Execution Architecture

To guarantee **zero daytime performance degradation** on enterprise sites with tens of thousands of active learners and courses (such as `lms.swa.gov.sa`), the system decouples queries into three distinct operational tiers:

```
                           Moodle Core Database
                                    │
          ┌─────────────────────────┼─────────────────────────┐
          ▼                         ▼                         ▼
   [ 1. Real-Time ]        [ 2. Near-Real-Time ]      [ 3. Historical Warehouse ]
   Direct Indexed / MUC    Short-Lived Micro-Cache    Scheduled Aggregation Cron
   ────────────────────    ───────────────────────    ───────────────────────────
   • Active online users   • Today's enrollments      • 12-month progress trends
   • Total live courses    • Today's completions      • Annual cumulative totals
   • Ungraded assignments  • Today's active logins    • Multi-year cohort tracking
   • Live session counts   • Today's submissions      • Period-over-period comparisons
          │                         │                         │
          └─────────────────────────┼─────────────────────────┘
                                    ▼
                         [ Unified Reporting API ]
                                    ▼
                        [ ApexCharts & Dashboards ]
```

### Tier 1: Real-Time Operational Layer (Sub-second)
* **Scope:** Current system state where immediate accuracy is vital.
* **Mechanism:** Single-pass, index-constrained queries using Moodle's native API (`$DB`), shielded by short-lived in-memory request caching (`\cache::make('local_adminreport', 'realtime')`).
* **Protected Queries:** Count of active courses, pending assignment grading queue (`assign_submission`), current active user counts.

### Tier 2: Near-Real-Time Intraday Layer (5 to 15-Minute Micro-Cache)
* **Scope:** Today's dynamic activity (*e.g., enrollments happening today, completions logged since midnight*).
* **Mechanism:** Queries constrained to `timecreated >= midnight` or `timemodified >= midnight`. Results cached via Moodle Universal Cache (MUC) with a 10-minute TTL.
* **Benefit:** When executives refresh the dashboard during the day, they see today's progress without executing full-table scans.

### Tier 3: Historical Analytics Warehouse Layer (Pre-Aggregated via Cron)
* **Scope:** Long-term trends, weekly operational summaries, 12-month cumulative tracking, period-over-period comparisons (e.g., September 2026 vs August 2026, or vs 2025).
* **Mechanism:** Scheduled Background Task (`\local_adminreport\task\aggregate_analytics`) running off-peak (default: 02:00 AM).
* **Guarantees:**
  * **Zero N+1 Queries:** Operates strictly on bulk SQL aggregation (`GROUP BY` with window functions).
  * **Zero Daytime Locks:** The dashboard queries pre-aggregated fact tables (`mdl_local_adminreport_daily_stats`, `mdl_local_adminreport_monthly_stats`).

---

## 3. Classification of Client Data: Native Moodle vs. Training Metadata

The Water Academy reference presentation contains two distinct categories of data:

| Field in Client Report | Nature | Source Strategy in `local_adminreport` |
|---|---|---|
| **Course Name & Code** | Native / Hybrid | Native `course.fullname` & `course.idnumber` (or `shortname`). |
| **Enrolled Trainees** | Native Moodle | Count of active enrolments (`mdl_user_enrolments` via `mdl_enrol`). |
| **Completed Trainees** | Native Moodle | Course completion records (`mdl_course_completions`). |
| **Trainer / Faculty** | Native Moodle | Assigned Teacher role (`role_assignments` with editingteacher/teacher role). |
| **Grades & Passing Rate** | Native Moodle | Final grades from `mdl_grade_grades` where `itemtype = 'course'`. |
| **Client / Organization** (*Erwaa, Nama, NWC...*) | Training Metadata | **Configurable Dimension Source** (Category ancestor, Course custom field, or Cohort). |
| **Program Type** (*تطويري، تأهيلي، دبلوم*) | Training Metadata | **Configurable Dimension Source** (Category name regex / Course custom field / Metadata table). |
| **Campus / City** (*الجبيل، الرياض، حائل...*) | Training Metadata | Course custom field, Category location, or `mdl_local_adminreport_meta`. |
| **Classroom / Venue** (*B1-15, B1-79, Online*) | Training Metadata | Course custom field `classroom` or `mdl_local_adminreport_meta.classroom`. |
| **Daily Timing & Breaks** (*08:00–14:00, 09:00 break*) | Training Metadata | Stored in `mdl_local_adminreport_meta` or pulled from Face-to-Face / Seminar activity if present. |
| **Exam Time** (*11:00*) | Training Metadata | Stored in `mdl_local_adminreport_meta` or derived from first Quiz `timeopen`. |

### Handling Training-Management Metadata Without Hard-Coding
To avoid forcing the client to immediately re-structure their database, the plugin supports a **Cascading Fallback Engine**:
1. **Priority 1 (Native Moodle Structure):** If defined in Course Custom Fields (`mdl_customfield_data`), use it.
2. **Priority 2 (Category Hierarchy):** If course belongs to a subcategory under Parent Category `583`, inherit entity and program type from the category hierarchy.
3. **Priority 3 (Plugin Extension Table):** If operational details (e.g. Break time, Classroom code) are needed, admins can edit them directly in the course's "Report Metadata" tab provided by `local_adminreport`.

---

## 4. Configurable Reporting Dimensions System

A fundamental architectural principle is that **categories are NOT the only source of truth**. Categories represent one possible dimension source among many.

```
                             Reporting Dimension
                                      │
         ┌──────────────┬─────────────┼─────────────┬──────────────┐
         ▼              ▼             ▼             ▼              ▼
   Course Category  Course Custom   User Custom   Moodle Cohort  Plugin Course
   (Hierarchy/Path)     Field          Field      (e.g. Batches)   Meta Table
```

### Configurable Mapping Matrix
In the plugin administration UI, administrators define mappings:

| Dimension Name | Supported Sources | Default Mapping (Water Academy Mode) |
|---|---|---|
| **Organization / Client** | Course Category, Course Custom Field, User Profile Field, Cohort | `Course Category` (Subcategories of root cat `583`) |
| **Program Classification** | Course Custom Field, Category Name Pattern, Plugin Meta Table | `Category Name Regex` (*تطويرية, تأهيلية, دبلوم*) |
| **Location / Campus** | Course Custom Field, Category, User City, Plugin Meta Table | `Course Custom Field` or Plugin Meta Table |
| **Trainer / Faculty** | Teacher Role Assignment, Course Custom Field | `Moodle Role: Editing Teacher` |
| **Cohort / Batch** | Moodle Cohorts, Moodle Course Groups | `Moodle Cohorts / Groups` |

---

## 5. Metrics & Analytics Definitions

| Metric ID | Formula / Derivation | Tier |
|---|---|---|
| `METRIC_TOTAL_PROGRAMS` | Distinct count of active, visible courses within dimension scope. | Tier 1 (Cached) |
| `METRIC_ENROLLED_TRAINEES` | Distinct count of active users enrolled in courses within period. | Tier 1 / Tier 2 |
| `METRIC_COMPLETED_TRAINEES`| Distinct count of users with `timecompleted` within selected date range. | Tier 2 / Tier 3 |
| `METRIC_COMPLETION_RATE` | $\frac{\text{Completed Trainees}}{\text{Enrolled Trainees}} \times 100$ | Calculated |
| `METRIC_TRAINEE_HOURS` | $\sum (\text{Course Duration in Hours} \times \text{Enrolled/Completed Trainees})$. | Tier 3 (Aggregated) |
| `METRIC_AVERAGE_GRADE` | $\text{AVG}(\text{finalgrade})$ normalized to percentage scale (0–100%). | Tier 2 / Tier 3 |
| `METRIC_AT_RISK_COUNT` | Count of trainees violating one or more configurable risk rules. | Tier 2 (Cached) |

### Configurable At-Risk Early Warning Rules
Administrators can configure weighted thresholds in the plugin settings:
* **Rule 1 (Inactivity):** Trainee has not accessed the course for $\ge X$ days (default: 14 days).
* **Rule 2 (Lagging Progress):** Course time elapsed $> 50\%$ but learner activity completion $< 30\%$.
* **Rule 3 (Assessment Failure):** Learner has $\ge 2$ failed quiz attempts or average grade $< 50\%$.

---

## 6. Database Schema Design

The database schema utilizes an indexed, dimension-aware star structure:

```
  ┌─────────────────────────────────┐
  │   mdl_local_adminreport_dims    │  <-- Stores dynamic dimension definitions
  ├─────────────────────────────────┤
  │ id                              │
  │ dim_type (org, type, loc)       │
  │ name                            │
  │ source_type (cat, field, meta)  │
  │ source_id                       │
  └─────────────────────────────────┘
                   │
                   ▼
  ┌────────────────────────────────────────────────────────┐
  │         mdl_local_adminreport_daily_stats              │  <-- Pre-aggregated Daily Fact
  ├────────────────────────────────────────────────────────┤
  │ id                                                     │
  │ stat_date (timestamp at midnight)                      │
  │ courseid                                               │
  │ org_dim_id (FK to dims)                                │
  │ type_dim_id (FK to dims)                               │
  │ loc_dim_id (FK to dims)                                │
  │ new_enrolments                                         │
  │ active_learners                                        │
  │ completions                                            │
  │ avg_grade                                              │
  │ total_training_hours                                   │
  │ timemodified                                           │
  └────────────────────────────────────────────────────────┘
                   │
                   ▼
  ┌────────────────────────────────────────────────────────┐
  │        mdl_local_adminreport_monthly_stats             │  <-- Monthly & Annual Warehouse
  ├────────────────────────────────────────────────────────┤
  │ id                                                     │
  │ year (e.g. 2026)                                       │
  │ month (1-12)                                           │
  │ org_dim_id                                             │
  │ type_dim_id                                            │
  │ total_courses                                          │
  │ total_trainees                                         │
  │ total_completions                                      │
  │ cumulative_trainees                                    │
  │ cumulative_completions                                 │
  │ timemodified                                           │
  └────────────────────────────────────────────────────────┘
```

### Supplementary Operational Metadata Table
For operational fields not present in Moodle core (Classroom, schedule timings, exam times):

```sql
CREATE TABLE {local_adminreport_meta} (
    id BIGINT(10) AUTO_INCREMENT PRIMARY KEY,
    courseid BIGINT(10) NOT NULL UNIQUE,
    classroom VARCHAR(100),
    location_name VARCHAR(100),
    daily_start_time VARCHAR(10),      -- e.g. "08:00"
    daily_end_time VARCHAR(10),        -- e.g. "14:00"
    break_time VARCHAR(50),            -- e.g. "09:00 - 09:30"
    exam_time VARCHAR(50),             -- e.g. "11:00"
    duration_days INT(5) DEFAULT 0,
    duration_hours INT(5) DEFAULT 0,
    timemodified BIGINT(10) NOT NULL
);
```

---

## 7. Permissions, Capabilities & Scoped Access

Security enforces the principle of least privilege, allowing role delegation beyond Site Admins:

### Defined Capabilities (`db/access.php`)
1. `local/adminreport:view` — Access the executive dashboard.
2. `local/adminreport:viewall` — View reports across all organizations and categories.
3. `local/adminreport:export` — Download reports in Excel (`.xlsx`), CSV, and executive PDF formats.
4. `local/adminreport:configure` — Configure reporting dimensions, risk rules, and category mappings.
5. `local/adminreport:manage` — Trigger manual data re-syncs and edit course operational metadata.

### Granular Data Scoping (Multi-Tenant & Sector Safe)
* **Global Access (Site Admins / General Directors):** Can view all sectors, entities, and locations.
* **Category-Scoped Access (Department Heads):** Restricted to viewing courses within their assigned course category tree (`cc.path LIKE '/583/...'`).
* **Organization-Scoped Access (Client Enterprise Manager):** Restricted to viewing their specific client dimension (*e.g., An NWC auditor sees only NWC courses and trainees*).

---

## 8. Dashboard User Experience: Focused V1 vs. Future V2

To ensure high impact and immediate delivery without feature bloat, we strictly demarcate **V1 (Immediate Focus)** from **V2 (Future Evolution)**:

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│                             V1 DASHBOARD WIREFRAME                                       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ Filter Bar ]                                                                           │
│ [ Preset: This Week ▼ ] [ Date: 2026-08-30 → 2026-09-03 ] [ Org: All ▼ ] [ Type: All ▼ ]  │
│                                                              [ ⟳ Refresh ] [ ⎙ Export ]  │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ KPI Cards ]                                                                            │
│ ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐                │
│ │   Programs   │   │   Trainees   │   │  Completion  │   │  Total Hours │                │
│ │     285      │   │    4,363     │   │    89.4%     │   │   18,420 h   │                │
│ └──────────────┘   └──────────────┘   └──────────────┘   └──────────────┘                │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ ApexCharts Row ]                                                                       │
│ ┌──────────────────────────────────────┐   ┌──────────────────────────────────────────┐  │
│ │ Chart 1: Trainees by Organization    │   │ Chart 2: Monthly Progress (Jan - Dec)    │  │
│ │ (ApexCharts Donut / Horizontal Bar)  │   │ (ApexCharts Stacked Bar / Trend Line)    │  │
│ └──────────────────────────────────────┘   └──────────────────────────────────────────┘  │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ Tabbed Operational Data Tables ]                                                       │
│ [ Tab 1: Weekly Schedule ] [ Tab 2: Entity Summary ] [ Tab 3: Annual Cumulative ]        │
│ ──────────────────────────────────────────────────────────────────────────────────────── │
│ Search: [______________]  Filter by Room: [All ▼]       Export: [ Excel | PDF | Print ]  │
│ Course Name       │ Code     │ Entity │ Duration │ Trainees │ Room/City │ Status        │
│ ──────────────────┼──────────┼────────┼──────────┼──────────┼───────────┼────────────── │
│ Adv. NRW Mgmt     │ 15403    │ Nama   │ 5 Days   │ 21       │ Oman      │ Completed     │
│ Power BI          │ EARA0311 │ Erwaa  │ 5 Days   │ 20       │ Riyadh    │ Completed     │
│ Electronics Fund. │ ELCT221  │ Alkhor │ 10 Days  │ 14       │ B1-77     │ Running       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ Early Warning & Attention Required ]                                                   │
│ ⚠️ 38 Trainees inactive for >14 days | ⚠️ 2 Courses with completion < 40% (View Details) │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

### V1 Scope (Current Target)
1. **Unified Global Filter Bar:** Date range presets (Weekly, Monthly, Annual, Custom), Organization filter, Program type filter.
2. **4 Core Executive KPI Cards:** Total Programs, Total Enrolled Trainees, Average Completion Rate, Total Training Hours.
3. **2 Primary ApexCharts:**
   - Trainees by Sponsoring Entity (Donut/Bar chart with full Arabic RTL).
   - Monthly Progression & Cumulative Trainees (Stacked Column & Trend Line).
4. **Interactive Operational Table:** Replaces PPT slides 2–4 with sortable, searchable, paginated table with live status badges.
5. **Early Warning Indicator:** Simple alert box identifying inactive/at-risk trainees.
6. **Executive Export:** One-click clean export to Excel (`.xlsx`) and formatted Print/PDF view.

### V2 Scope (Future Phase)
1. **Interactive Multi-Level Drill-Down:** Clicking a chart segment filters the table, and clicking a course reveals individual trainee progress matrices.
2. **Automated Scheduled Email/PDF Dispatch:** Weekly/Monthly scheduled emails to leadership with PDF attachments via Cron.
3. **Trainer Workload & Evaluation Matrix:** Tracking trainer teaching hours, grading turnaround times, and learner feedback scores.
4. **AI-Driven Predictive Completion:** Early risk prediction using regression analysis over login frequency and quiz attempts.

---

## 9. Implementation File Structure (`local/adminreport`)

```
local/adminreport/
├── classes/
│   ├── analytics/
│   │   ├── query_engine.php         <-- Set-based, high-performance SQL engine (Zero N+1)
│   │   ├── dimension_manager.php    <-- Configurable dimension resolution service
│   │   └── warehouse_aggregator.php <-- Nightly cron batch processor
│   ├── output/
│   │   ├── renderer.php             <-- Plugin main renderer
│   │   └── dashboard.php            <-- Dashboard renderable with MUC caching
│   ├── task/
│   │   └── aggregate_analytics.php  <-- Scheduled task (\core\task\scheduled_task)
│   └── privacy/
│       └── provider.php             <-- Moodle Privacy API compliance (GDPR)
├── db/
│   ├── access.php                   <-- Defined capabilities
│   ├── install.xml                  <-- Fact & dimension table definitions
│   ├── tasks.php                    <-- Scheduled task registration
│   └── upgrade.php                  <-- Database migration handlers
├── amd/
│   ├── src/
│   │   ├── dashboard.js             <-- AJAX filter controller & dynamic table updates
│   │   └── charts.js                <-- ApexCharts initializer with RTL/Arabic support
│   └── build/                       <-- Compiled AMD modules
├── templates/
│   ├── dashboard.mustache           <-- Main layout shell
│   ├── filter_bar.mustache          <-- Global filter controls
│   ├── kpi_cards.mustache           <-- Metric cards with trend indicators
│   └── operational_table.mustache   <-- Responsive data table
├── lang/
│   ├── en/local_adminreport.php     <-- English language strings
│   └── ar/local_adminreport.php     <-- Complete native Arabic localization
├── index.php                        <-- Main report dashboard controller
├── settings.php                     <-- Dimension mapping & plugin admin settings
└── version.php                      <-- Frankenstyle version & dependencies
```

---

## 10. Verification & Quality Assurance Plan

1. **Enterprise Performance Testing:**
   * Execute synthetic query profiling against test databases with $10,000+$ courses and $100,000+$ enrollments.
   * Verify that dashboard load times remain under $200\text{ ms}$ on cached views and zero table locks occur on live tables.
2. **Moodle Coding Standards & Sniffer:**
   * Pass PHP_CodeSniffer with Moodle rules (`moodlecs`).
   * Pass Javascript ESLint via `grunt amd`.
3. **Database Compatibility:**
   * Enforce cross-database SQL syntax compatible with both PostgreSQL and MySQL/MariaDB.
4. **RTL & Visual Inspection:**
   * Test Arabic typography and RTL orientation across all ApexCharts and data tables.
