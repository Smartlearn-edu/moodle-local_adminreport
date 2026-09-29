# Technical Specification: Advanced Moodle Management Intelligence & Analytics Plugin (`local_adminreport`)

* **Component:** `local_adminreport`
* **Target Platforms:** Moodle 4.5+ / 5.x Ready
* **License:** GNU GPL v3 or later (Bundled JS: MIT / Apache 2.0 compatible)
* **Status:** Complete Technical Specification & Data Model (Revision 2.0)

---

## 1. Architectural Overview & The Triad Model

The plugin `local_adminreport` delivers an executive-grade **Management Intelligence & Operational Analytics Dashboard** for Moodle administrators and designated leadership roles.

### Core Data Formula
Every analytic query in the system conforms to:

$$\mathbf{Analytics} = \mathbf{Metric} \times \mathbf{Dimension} \times \mathbf{Time}$$

* **Metric:** Additive counts, derived ratios, and sum/count aggregates (*Participations, Completions, Hours, Grade sums*).
* **Dimension:** Hierarchical categorical axes (*Sector $\rightarrow$ Organization, Program Classification, Location/Campus, Trainer*).
* **Time:** Temporal window with strict attribution boundaries (*Weekly, Monthly, Annual Cumulative, Custom Date Range*).

---

## 2. Core Entities: The Offering / Run Abstraction

### Resolving Course vs. Course Offering (Blocker 1 Fix)
In institutional training, a single program or course code (e.g., `32401 Intro to PM` or `EARO011025 Automation Sensors`) frequently runs multiple times across different dates, classrooms, trainers, and regional branches.

To prevent key collisions and support both **multi-course cloning** (1 Course = 1 Run) and **shared-course cohort grouping** (1 Course = Multiple Groups), the plugin introduces the **Offering / Run** entity:

```
mdl_local_adminreport_runs
├── id                  (Primary Key)
├── courseid            (FK to {course}.id)
├── groupid             (FK to {groups}.id - default 0 = entire course)
├── run_code            (Course/Run Code e.g. "32401", "EARO011025" - can repeat)
├── startdate           (Unix timestamp of run start)
├── enddate             (Unix timestamp of run end)
├── location_dim_id     (FK to dim_members: Riyadh, Jubail, Hail, Online...)
├── org_dim_id          (FK to dim_members: SWA, NWC, Erwaa, Nama...)
├── type_dim_id         (FK to dim_members: Development, Qualifying, Diploma...)
├── trainer_id          (FK to {user}.id - Primary assigned instructor)
├── classroom           (String: Room / Lab e.g. "B1-15", "B1-79", "ON LINE")
├── daily_start_time    (String: "08:00")
├── daily_end_time      (String: "14:00")
├── break_duration_min  (Integer: Break duration in minutes, default 30)
├── exam_time           (String: "11:00")
├── status              (Enum: 'planned', 'running', 'completed', 'cancelled')
└── timemodified        (Unix timestamp)
```

**Key Advantages:**
1. **No Key Collisions:** Multiple runs sharing the same code (`EARO011025`) or course ID have unique run IDs.
2. **Explicit Dates:** `startdate` and `enddate` are explicitly captured per run, enabling exact period attribution for operational scheduling and completion reporting.
3. **Flexible Moodle Topology:** Works whether the organization creates a new Moodle course per batch (`groupid = 0`) or uses groups within a master course (`groupid > 0`).

---

## 3. One-Page Metric Dictionary & Attribution Rules

### A. Metric Definitions & Formulas

| Metric ID | Display Name | Definition & Formula | Additive? | Golden Target in Client Sample |
|---|---|---|---|---|
| `METRIC_RUNS` | عدد البرامج / الدورات | Distinct count of program runs falling within the period. | ✅ Additive across runs | **22 planned runs** (Slide 4)<br>**285 annual runs** (Slide 6) |
| `METRIC_PARTICIPATIONS` | عدد المتدربين (مشاركات) | Sum of active learner enrollments across all runs in period. *(1 student in 2 courses = 2 participations).* | ✅ Fully Additive across all dimensions & time | **337 trainees** (Slide 4)<br>**4,363 annual** (Slide 6) |
| `METRIC_UNIQUE_LEARNERS` | عدد الأفراد المتدربين | Distinct count of individual users (`COUNT(DISTINCT userid)`). | ❌ Non-additive (Calculated dynamically at query time) | Executive Secondary Metric |
| `METRIC_COMPLETIONS` | عدد المنجزين | Count of completed participations where completion was logged within period. | ✅ Fully Additive | **258 weekly** (Slide 5)<br>**4,130 annual** (Slide 6) |
| `METRIC_COMPLETION_RATE` | نسبة الإنجاز | $\frac{\text{METRIC\_COMPLETIONS}}{\text{METRIC\_PARTICIPATIONS}} \times 100$ | Derived ratio (%) | Percentage |
| `METRIC_TRAINEE_HOURS` | ساعات التدريب المنفذة | $\sum (\text{Net Daily Hours} \times \text{Duration in Days} \times \text{Participations})$<br>$\text{Net Daily Hours} = (\text{daily\_end} - \text{daily\_start}) - \frac{\text{break\_min}}{60}$. | ✅ Fully Additive | Calculated dynamically |
| `METRIC_AVG_GRADE` | متوسط الدرجات | Stored as `grade_sum` and `grade_count`. Formula: $\frac{\sum \text{grade\_sum}}{\sum \text{grade\_count}}$. *(Avoids the mathematically invalid average-of-averages).* | Derived ratio | Course/Sector Grade avg |

### B. Period Attribution Boundaries
* **Completed Programs (البرامج المنجزة):** Attributed by run **`enddate`** falling within the period (`enddate >= period_start AND enddate <= period_end`).
* **Operational / Scheduled Programs (خطط التدريب / الجارية):** Attributed by **interval overlap** (`startdate <= period_end AND enddate >= period_start`).
* **KPI Scope:** Primary dashboard KPI cards reflect the **currently selected filter period** (e.g. This Week = 22 runs / 337 trainees). Annual YTD figures are displayed as secondary context badges.

### C. Client Golden Test Reconciliation
The reporting engine must reproduce these exact reference totals from the sample dataset:
* **Weekly Planned (Slide 4):** Total = 22 runs, 337 trainees.
  * Jubail: 10 runs, 125 trainees
  * Riyadh: 5 runs, 96 trainees
  * Hail: 3 runs, 45 trainees
  * Oman: 1 run, 21 trainees
  * Online: 1 run, 20 trainees
  * Buraydah: 1 run, 15 trainees
  * Al-Jouf: 1 run, 15 trainees
* **Weekly Completed (Slide 5):** Developmental = 11 runs / 147 trainees; Qualifying = 2 runs / 26 trainees; Diplomas = 6 runs / 85 trainees.
* **Annual YTD (Slide 6):** Total = 285 runs, 4,363 trainees (Developmental: 271 / 4,130; Qualifying: 6 / 125; Diplomas: 10 / 154).

---

## 4. Normalized Dimension Model & Sector Hierarchy

Dimensions are decoupled from Moodle course categories, split cleanly into **Dimension Types** and **Dimension Members** with hierarchical parent-child relationships:

```
                  mdl_local_adminreport_dim_types
                  ├── id
                  ├── code           ('sector', 'organization', 'program_type', 'location')
                  ├── name           ('القطاع', 'الجهة', 'نوع البرنامج', 'الموقع')
                  ├── source_type    ('category', 'customfield', 'meta_manual')
                  └── source_config  (Config parameter e.g. root category ID, field shortname)
                                    │
                                    ▼
                  mdl_local_adminreport_dim_members
                  ├── id
                  ├── dim_type_id    (FK to dim_types.id)
                  ├── parent_id      (FK to self - supports Sector -> Organization)
                  ├── code           ('SWA', 'NWC', 'ERWAA', 'RIYADH', 'JUBAIL', 'DEV')
                  └── name           ('الهيئة السعودية للمياه', 'شركة المياه الوطنية', ...)
```

### Hierarchy & Pivot Support
* **Sector $\rightarrow$ Organization:**
  * `Sector: Corporate (الشركات)` $\rightarrow$ Members: `NWC`, `Erwaa`, `WTCO`, `Alkhorayef`
  * `Sector: Government (الحكومي)` $\rightarrow$ Members: `SWA`, `Military Sectors`
  * `Sector: GCC (دول الخليج)` $\rightarrow$ Members: `Nama Oman`
* **Geographic Dimensions:** Location members (`الجبيل`, `الرياض`, `حائل`, `القصيم`, `الجوف`, `عمان`, `ON LINE`) are recorded across daily and monthly warehouse aggregations, enabling multi-month location pivots.
* **Dimension Resolution Grain (V1):** Dimensions are resolved at the **Course / Offering grain**. (Trainee-level cross-organization enrollment within a single shared run is deferred to V2).

---

## 5. The 3-Tier Data Architecture & Tier Stitching Algorithm

To guarantee maximum speed and zero table locking on enterprise instances:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                  3-TIER ENGINE                                         │
├────────────────────────────┬────────────────────────────┬──────────────────────────────┤
│ 1. Historical Warehouse    │ 2. Intraday Near-Real-Time │ 3. Real-Time Operational     │
│ (Nightly Cron Batch)       │ (10-minute MUC Cache)      │ (Live Direct Query / MUC)    │
├────────────────────────────┼────────────────────────────┼──────────────────────────────┤
│ • stat_date <= yesterday   │ • stat_date = today        │ • Current online users       │
│ • Zero enrollment queries  │ • timecreated >= midnight  │ • Active grading queue       │
│ • Bulk group-by tables     │ • Rolling 10m TTL cache    │ • Active running sessions    │
└────────────────────────────┴────────────────────────────┴──────────────────────────────┘
```

### Tier Stitching Algorithm (Zero Double-Counting)
When an administrator views a time period:
* **Scenario A: Closed Past Period (e.g., "Last Month" or "Last Week"):**
  $$\text{Data} = \text{Query}(mdl\_local\_adminreport\_daily\_stats \text{ WHERE } stat\_date \in [start, end])$$
  *(Entirely satisfied by warehouse tables in $< 10\text{ms}$ with zero live queries).*
* **Scenario B: Period Including Today (e.g., "This Week", "This Month", "Today"):**
  $$\text{Data} = \text{Warehouse}(start \to \text{yesterday 23:59:59}) + \text{Intraday}(\text{today 00:00:00} \to \text{now})$$
  * The date seam is absolute. The warehouse never contains today's partial day; intraday queries only process records created $\ge \text{today midnight}$. There is zero double-counting.

### Lookback Upsert Strategy
The nightly scheduled task (`\local_adminreport\task\aggregate_analytics`) recomputes a **rolling 7-day lookback window** using `UPSERT` on `(stat_date, run_id)`. This automatically catches retroactive grade submissions, attendance edits, or delayed completion logs.

### Multi-Tenant & Scoped Cache Keys
Moodle Universal Cache (MUC) keys are strictly partitioned by user permissions and scope:
```php
$cachekey = 'rep_' . md5($USER->id . '_' . $scopehash . '_' . $periodstart . '_' . $periodend . '_' . $filtershash);
```
This guarantees that a manager scoped to one sector never sees cached aggregated data from another sector.

---

## 6. Complete Database Schema (`db/install.xml`)

```
  ┌─────────────────────────────────┐
  │ mdl_local_adminreport_dim_types │
  ├─────────────────────────────────┤
  │ id                              │
  │ code VARCHAR(50)                │
  │ name VARCHAR(100)               │
  │ source_type VARCHAR(30)         │
  │ source_config TEXT              │
  └─────────────────────────────────┘
                   │
                   ▼
  ┌───────────────────────────────────┐
  │ mdl_local_adminreport_dim_members │
  ├───────────────────────────────────┤
  │ id                                │
  │ dim_type_id BIGINT(10)            │
  │ parent_id BIGINT(10) NULL         │
  │ code VARCHAR(50)                  │
  │ name VARCHAR(255)                 │
  └───────────────────────────────────┘
                   │
                   ▼
  ┌───────────────────────────────────┐
  │    mdl_local_adminreport_runs     │  <-- Offering / Run Entity
  ├───────────────────────────────────┤
  │ id BIGINT(10) AUTO_INCREMENT      │
  │ courseid BIGINT(10)               │
  │ groupid BIGINT(10) DEFAULT 0      │
  │ run_code VARCHAR(100)             │
  │ startdate BIGINT(10)              │
  │ enddate BIGINT(10)                │
  │ location_dim_id BIGINT(10)        │
  │ org_dim_id BIGINT(10)             │
  │ type_dim_id BIGINT(10)            │
  │ trainer_id BIGINT(10)             │
  │ classroom VARCHAR(100)            │
  │ daily_start_time VARCHAR(10)      │
  │ daily_end_time VARCHAR(10)        │
  │ break_duration_min INT(5)         │
  │ exam_time VARCHAR(50)             │
  │ status VARCHAR(20)                │
  │ timemodified BIGINT(10)           │
  └───────────────────────────────────┘
                   │
                   ▼
  ┌───────────────────────────────────┐
  │ mdl_local_adminreport_daily_stats │  <-- Daily Fact Table
  ├───────────────────────────────────┤
  │ id BIGINT(10) AUTO_INCREMENT      │
  │ stat_date BIGINT(10) (Midnight)   │
  │ run_id BIGINT(10)                 │
  │ org_dim_id BIGINT(10)             │
  │ type_dim_id BIGINT(10)            │
  │ location_dim_id BIGINT(10)        │
  │ participations_count INT(10)      │
  │ completions_count INT(10)         │
  │ grade_sum DECIMAL(10,2)           │
  │ grade_count INT(10)               │
  │ total_training_hours DECIMAL(10,2)│
  │ timemodified BIGINT(10)           │
  └───────────────────────────────────┘
                   │
                   ▼
  ┌─────────────────────────────────────┐
  │ mdl_local_adminreport_monthly_stats │  <-- Monthly Fact Warehouse
  ├─────────────────────────────────────┤
  │ id BIGINT(10) AUTO_INCREMENT        │
  │ year INT(4)                         │
  │ month INT(2)                        │
  │ org_dim_id BIGINT(10)               │
  │ type_dim_id BIGINT(10)              │
  │ location_dim_id BIGINT(10)          │
  │ runs_count INT(10)                  │
  │ participations_count INT(10)        │
  │ completions_count INT(10)           │
  │ cumulative_runs INT(10)             │
  │ cumulative_participations INT(10)   │
  │ cumulative_completions INT(10)      │
  │ timemodified BIGINT(10)             │
  └─────────────────────────────────────┘
```

---

## 7. Permissions, Scoping & Privacy Compliance

### Capabilities (`db/access.php`)
1. `local/adminreport:view` — Access the executive reporting dashboard.
2. `local/adminreport:viewall` — Bypass category/sector restrictions to view global reports.
3. `local/adminreport:viewtrainees` — View individual learner identity and at-risk student lists. *(Protects learner privacy).*
4. `local/adminreport:export` — Export data to Excel/CSV and execute PDF prints.
5. `local/adminreport:configure` — Configure dimension mappings and aggregation thresholds.
6. `local/adminreport:manage` — Edit course run metadata and trigger safe background data recalculations.

### Privacy API Implementation (`classes/privacy/provider.php`)
Because the analytics fact tables store only aggregated numeric counts and dimension identifiers without user IDs, the plugin implements:
```php
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata:nullprovider';
    }
}
```

---

## 8. Focused V1 User Interface Architecture

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│  Moodle Management Intelligence & Operational Analytics                                  │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│  [ Preset: This Week ▼ ]  [ 2026-08-30 → 2026-09-03 ]  [ Sector: All ▼ ] [ Org: All ▼ ] │
│  [ Location: All ▼ ]  [ Program Type: All ▼ ]            [ ⟳ Refresh ]  [ ⎙ Export XLSX ]│
├──────────────────────────────────────────────────────────────────────────────────────────┤
│  EXECUTIVE KPI SUMMARY CARDS                                                             │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐          │
│  │  Program Runs  │  │  Participations│  │Completion Rate │  │ Trainee Hours  │          │
│  │       22       │  │      337       │  │     92.4%      │  │    1,820 hrs   │          │
│  │  YTD: 285 runs │  │ YTD: 4,363 tra.│  │ YTD: 94.6%     │  │ YTD: 24,180 hrs│          │
│  └────────────────┘  └────────────────┘  └────────────────┘  └────────────────┘          │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│  APEXCHARTS ROW                                                                          │
│  ┌──────────────────────────────────────┐  ┌──────────────────────────────────────────┐  │
│  │ Chart 1: Participations by Org/Sector│  │ Chart 2: Monthly Progress (Jan - Dec)    │  │
│  │ (ApexCharts Donut / Horizontal Bar)  │  │ (ApexCharts Stacked Bar / Trend Line)    │  │
│  └──────────────────────────────────────┘  └──────────────────────────────────────────┘  │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│  TABBED OPERATIONAL TABLES                                                               │
│  [ 📅 Weekly Operational Schedule ]  [ 🏢 Entity Summary ]  [ 📈 Annual Cumulative ]     │
│  ─────────────────────────────────────────────────────────────────────────────────────── │
│  Search: [_______________]  Room: [All Rooms ▼]              Export: [ Excel | CSV ]     │
│  Course Name       │ Code     │ Entity │ Duration │ Trainees │ Room/City │ Status        │
│  ──────────────────┼──────────┼────────┼──────────┼──────────┼───────────┼────────────── │
│  Adv. NRW Mgmt     │ 15403    │ Nama   │ 5 Days   │ 21       │ Oman      │ Completed     │
│  Power BI          │ EARA0311 │ Erwaa  │ 5 Days   │ 20       │ Riyadh    │ Completed     │
│  Electronics Fund. │ ELCT221  │ Alkhor │ 10 Days  │ 14       │ B1-77     │ Running       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│  EARLY WARNING & ATTENTION REQUIRED                                                      │
│  ⚠️ 38 Trainees inactive for >14 days | ⚠️ 2 Courses with completion < 40% (View Details) │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 9. Technology Stack & Moodle Conventions

* **Visualization:** **ApexCharts 3.x** (MIT License, bundled under `amd/src/apexcharts.js`, documented in `thirdpartylibs.xml`). Features full Arabic RTL support and client-side SVG/PNG/CSV download.
* **AJAX Web Service:** Standard Moodle External API (`db/services.php` $\rightarrow$ `classes/external/get_report_data.php`) with strict sesskey validation and parameter typing.
* **Moodle Universal Cache (MUC):** Registered in `db/caches.php` for request and intraday application caching.
* **Export Engine:** Native Moodle `\core\dataformat::download_data()` for zero-dependency Excel/CSV streaming.
* **Timezone Safety:** All timestamp operations use `\core_date::get_user_timezone()` and site calendar settings (configurable Sunday–Thursday vs Monday–Friday workweeks).
