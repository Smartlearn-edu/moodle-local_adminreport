# Technical Specification: Advanced Moodle Management Intelligence & Analytics Plugin (`local_adminreport`)

* **Component:** `local_adminreport`
* **Target Platforms:** Moodle 4.5+ / 5.x Ready
* **License:** GNU GPL v3 or later (Bundled JS: MIT / Apache 2.0 compatible)
* **Status:** Complete Technical Specification & Data Model (Revision 2.1 — Pre-Implementation Freeze)

---

## 1. Architectural Principles & The Analytics Model

The plugin `local_adminreport` transforms raw Moodle LMS data into an executive-grade **Management Intelligence & Operational Analytics Dashboard** for Moodle administrators and scoped corporate leadership roles.

### Core Analytic Formula
Every report calculation adheres to:

$$\mathbf{Analytics} = \mathbf{Metric} \times \mathbf{Dimension} \times \mathbf{Time}$$

* **Metric:** Flow measurements (*Participations, Hours Delivered, Cohort Completions, Grade sums/counts*).
* **Dimension:** Normalized hierarchy (*Sector $\rightarrow$ Organization, Program Classification, Location/Campus*).
* **Time:** Watermark-anchored time windows (*Weekly, Monthly, Annual, Custom Date Range*).

---

## 2. Core Entities: The Offering / Run Lifecycle

### A. The Offering / Run Abstraction
A single program code (e.g., `32401 Intro to PM` or `EARO011025 Automation Sensors`) frequently recurs across different dates, classrooms, trainers, and regional branches. 

The plugin anchors all operational tracking to the **Offering / Run** (`mdl_local_adminreport_runs`):

```
mdl_local_adminreport_runs
├── id                  (Primary Key, BIGINT(10))
├── courseid            (FK to {course}.id, BIGINT(10))
├── groupid             (FK to {groups}.id, default 0 = entire course, BIGINT(10))
├── run_code            (String: Course/Run Code e.g. "32401", "EARO011025")
├── startdate           (Unix timestamp of run start, BIGINT(10))
├── enddate             (Unix timestamp of run end, BIGINT(10))
├── org_dim_id          (FK to dim_members: SWA, NWC, Erwaa, Nama...)
├── type_dim_id         (FK to dim_members: Developmental, Qualifying, Diploma...)
├── location_dim_id     (FK to dim_members: Riyadh, Jubail, Hail, Online...)
├── classroom           (String: Room / Lab e.g. "B1-15", "B1-79", "ON LINE")
├── daily_start_time    (String: "08:00")
├── daily_end_time      (String: "14:00")
├── break_duration_min  (Integer: Break in minutes, default 30)
├── exam_time           (String: "11:00")
├── is_cancelled        (TINYINT(1), default 0)
├── source              (Enum: 'moodle_course', 'imported', 'manual')
└── timemodified        (Unix timestamp, BIGINT(10))
```

### B. Dynamic Status Derivation
The lifecycle state of a run is **derived at runtime from dates** (avoiding stale state flags):
* If `is_cancelled = 1` $\rightarrow$ **Cancelled** (ملغي)
* If $\text{current\_time} < \text{startdate}$ $\rightarrow$ **Planned** (مخطط)
* If $\text{startdate} \le \text{current\_time} \le \text{enddate}$ $\rightarrow$ **Running** (قيد التنفيذ)
* If $\text{current\_time} > \text{enddate}$ $\rightarrow$ **Completed** (منجز)

### C. Multi-Trainer Support & Privacy
To accommodate runs with multiple trainers and external non-Moodle instructors without violating Moodle user constraints, trainers are stored in a dedicated junction table:

```
mdl_local_adminreport_run_trainers
├── id                  (Primary Key, BIGINT(10))
├── run_id              (FK to runs.id, BIGINT(10))
├── userid              (FK to {user}.id NULLABLE - for Moodle users)
├── trainer_name        (VARCHAR(255) NOT NULL - textual name for display)
└── is_primary          (TINYINT(1), default 1)
```

### D. Run Management & Population Lifecycles
The plugin provides four creation mechanisms:
1. **Auto-Discovery Task:** Scans courses in configured categories; automatically creates runs for courses with `startdate`/`enddate`.
2. **Web Management Interface:** `runs.php` with Moodle QuickForm (`classes/form/run_form.php`) for editing run logistics (classroom, daily times, break, exam).
3. **Batch Importer (`classes/import/run_importer.php`):** Upload CSV/XLSX spreadsheets to populate planned upcoming runs or import historical legacy records (`source = 'imported'`).
4. **Rebuild Tool:** CLI (`cli/rebuild_warehouse.php`) and admin button to re-sync facts after bulk imports.

---

## 3. One-Page Metric Dictionary & Mathematical Hygiene

### A. Core Metric Definitions (Stock vs. Flow)
Daily fact records represent **flow (incremental activity)**, not stock. Storing enrollments on every day of a run would cause summing a 5-day week to report $5 \times 20 = 100$ participant-days instead of $20$ trainees.

| Metric ID | Display Name | Definition & Mathematical Computation | Additive Behavior |
|---|---|---|---|
| `METRIC_RUNS` | عدد البرامج | Count of distinct program runs attributed to the period. | ✅ Additive across runs |
| `METRIC_PARTICIPATIONS` | عدد المتدربين (مشاركات) | Total enrollments in attributed runs. **Recorded once per run** (on attribution date). | ✅ Fully additive across dimensions & time |
| `METRIC_UNIQUE_LEARNERS` | عدد الأفراد المتدربين | Distinct count of individuals (`COUNT(DISTINCT userid)`). | ❌ Non-additive. Evaluated dynamically at read time. |
| `METRIC_COMPLETIONS` | المتدربون المنجزون | Count of completed participations among the runs attributed to the period (**Cohort-Based**). | ✅ Fully additive |
| `METRIC_COMPLETION_RATE` | نسبة الإنجاز | $\frac{\text{METRIC\_COMPLETIONS}}{\text{METRIC\_PARTICIPATIONS}} \times 100$. | Derived ratio (0–100%) |
| `METRIC_TRAINEE_HOURS` | ساعات التدريب المنفذة | Recorded per working day for active runs: $\text{Daily Net Hours} \times \text{Participations}$.<br>$\text{Daily Net Hours} = (\text{daily\_end} - \text{daily\_start}) - \frac{\text{break\_min}}{60}$. | ✅ Fully additive |
| `METRIC_AVG_GRADE` | متوسط الدرجات | Stored as `grade_sum` and `grade_count`. Formula: $\frac{\sum \text{grade\_sum}}{\sum \text{grade\_count}}$. *(Eliminates the average-of-averages fallacy).* | Derived ratio |

### B. Period Attribution Rules
1. **Delivered Volume Reporting (البرامج المنفذة):**
   * Default setting: Attributed by run **`enddate`** (`enddate >= period_start AND enddate <= period_end`). Counted **exactly once**.
   * (Alternative configurable setting: by `startdate`).
2. **Operational Schedule & Capacity Reporting (خطط التدريب / الجارية):**
   * Uses **interval overlap** (`startdate <= period_end AND enddate >= period_start`).
   * Used for room utilization and weekly schedules.
   * **Rule:** Overlapping runs are *not* summed into historical volume totals to prevent multi-week diplomas from inflating run counts.

### C. Client Presentation Golden Test Targets (Audited Reconciliation)
The reference PPT contains human clerical errors in its summary box. The testing suite asserts the **sum of the parts**, not the erroneous summary totals:

* **Weekly Planned (Slide 4):** Total = **22 runs, 337 trainees**.
  * Jubail: 10 runs, 125 trainees
  * Riyadh: 5 runs, 96 trainees
  * Hail: 3 runs, 45 trainees
  * Oman: 1 run, 21 trainees
  * Online: 1 run, 20 trainees
  * Buraydah: 1 run, 15 trainees
  * Al-Jouf: 1 run, 15 trainees
  * *Sum reconciliation:* $125 + 96 + 45 + 21 + 20 + 15 + 15 = 337$ trainees (100% matched).
* **Weekly Delivered (Slide 5):** Total = **19 runs, 258 delivered participations** (*Note: labeled as delivered participations, NOT course completions; the sample has no completion logs*).
  * Developmental: 11 runs, 147 trainees
  * Qualifying: 2 runs, 26 trainees
  * Diplomas: 6 runs, 85 trainees (*Note: flagged as identical to planned diploma figures; verified as client copy-paste in deck*).
  * *Sum reconciliation:* $147 + 26 + 85 = 258$ participations.
* **Annual YTD Delivered (Slide 6):**
  * Developmental: 271 runs, 4,130 trainees
  * Qualifying: 6 runs, 125 trainees
  * Diplomas: 10 runs, 154 trainees
  * *Audit Note:* The slide summary box states $285$ runs and $4,363$ trainees, but the true sum of parts is:
    $$\text{True Sum} = 271 + 6 + 10 = \mathbf{287\text{ runs}}, \quad 4,130 + 125 + 154 = \mathbf{4,409\text{ trainees}}$$
  * The automated reconciliation tests assert **287 runs and 4,409 trainees**.

---

## 4. Normalized Dimension Model & Sector Hierarchy

```
  mdl_local_adminreport_dim_types
  ├── id (PK)
  ├── code             (UNIQUE: 'sector', 'organization', 'program_type', 'location')
  ├── name             (VARCHAR(100): Display title / lang string key)
  ├── source_type      (Enum: 'category', 'customfield', 'manual')
  └── source_config    (TEXT: Configuration JSON / Root Category ID / Field Shortname)
            │
            ▼
  mdl_local_adminreport_dim_members
  ├── id (PK)
  ├── dim_type_id      (FK to dim_types.id, BIGINT(10))
  ├── parent_id        (FK to self NULLABLE - supports Sector -> Organization hierarchy)
  ├── code             (VARCHAR(50): 'SWA', 'NWC', 'ERWAA', 'RIYADH', 'JUBAIL', 'DEV')
  ├── name             (VARCHAR(255): Display name)
  └── timemodified     (BIGINT(10))
```

### Hierarchy & Normalized Joins
* **Sector $\rightarrow$ Organization Mapping:**
  * Sector: `Corporate (الشركات)` $\rightarrow$ Members: `NWC`, `Erwaa`, `WTCO`, `Alkhorayef`
  * Sector: `Government & Military (الحكومي والعسكري)` $\rightarrow$ Members: `SWA`, `Military`
  * Sector: `GCC (دول الخليج)` $\rightarrow$ Members: `Nama Oman`
* **Zero Denormalization in Daily Facts:** Fact rows store only `run_id`. All dimension IDs (`org_dim_id`, `type_dim_id`, `location_dim_id`) are resolved by joining `mdl_local_adminreport_runs`. This eliminates data drift if a run's dimension mapping is edited.

---

## 5. Scope Resolution & Access Control

### A. The Scoping Table (`mdl_local_adminreport_scope`)
To govern which corporate managers or department heads see which organizations:

```
mdl_local_adminreport_scope
├── id (PK)
├── scope_type         (Enum: 'user', 'role')
├── scope_id           (BIGINT(10): userid or roleid)
├── dim_member_id      (FK to dim_members.id: Organization or Sector dimension member)
└── timemodified       (BIGINT(10))
```

### B. Scope Resolution & `$scopehash`
When a user accesses the dashboard, external API, or export:
1. If user has `local/adminreport:viewall` $\rightarrow$ `$allowed_org_ids = null` (Full Global Access).
2. Otherwise, query `mdl_local_adminreport_scope` for records matching `userid = $USER->id` or active assigned roles.
3. Compute `$scopehash = $allowed_org_ids === null ? 'global' : md5(implode(',', sort($allowed_org_ids)))`.
4. **Dropdown Filter Scoping:** The organization and sector dropdown menus query only `$allowed_org_ids`. A corporate manager never sees other companies in their filter list.
5. **SQL Enforcement:** All queries in `query_engine.php` and `export.php` enforce `WHERE r.org_dim_id IN (...)`.

---

## 6. Watermark Tier Seam & Unification Algorithm

To guarantee zero double-counting, zero downtime, and resilience against cron delays:

```
                        Timeline of Analytics Tiers
                        
Earlier Days          Watermark Date (e.g. Yesterday 23:59:59)            Now
───────┬───────────────────────────┬───────────────────────────────────────►
       │                           │                                       │
       │    WAREHOUSE TIER         │            INTRADAY TIER              │
       │    (Pre-aggregated facts) │            (Dynamic read-through)     │
       │    Read from stats tables │            compute_run_facts(run, day)│
       └───────────────────────────┴───────────────────────────────────────┘
                                   ▲
                             Watermark Timestamp
                   (Updated only upon successful cron run)
```

1. **The Watermark (`config_plugins: local_adminreport/last_aggregated_watermark`):**
   * Stores the midnight timestamp of the last day successfully aggregated by the nightly task.
   * If cron runs at 02:00 AM, yesterday becomes the watermark.
   * If cron fails, the watermark remains at the prior day, and Intraday seamlessly covers from `watermark + 1 sec` to `now`.
2. **Unified Fact Calculator:**
   * Both the nightly cron batch and the live intraday calculator call the **exact same function**:
     ```php
     \local_adminreport\analytics\query_engine::compute_run_facts($run, $date);
     ```
   * Guarantees identical math between live and historical views.
3. **MUC Cache Key Definition:**
   * Shared among users with identical scope:
     ```php
     $cachekey = 'rep_' . md5($scopehash . '_' . $periodstart . '_' . $periodend . '_' . $filtershash);
     ```

---

## 7. Complete XMLDB Database Schema (`db/install.xml`)

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/adminreport/db" VERSION="20260929" COMMENT="XMLDB schema for local_adminreport">
  <TABLES>
    <TABLE NAME="local_adminreport_dim_types" COMMENT="Dimension definitions">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="code" TYPE="char" LENGTH="50" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="source_type" TYPE="char" LENGTH="30" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="source_config" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="code_uniq" TYPE="unique" FIELDS="code"/>
      </KEYS>
    </TABLE>

    <TABLE NAME="local_adminreport_dim_members" COMMENT="Dimension members and hierarchy">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="dim_type_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="parent_id" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="code" TYPE="char" LENGTH="50" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="dim_type_fk" TYPE="foreign" FIELDS="dim_type_id" REFTABLE="local_adminreport_dim_types" REFFIELDS="id"/>
        <KEY NAME="type_code_uniq" TYPE="unique" FIELDS="dim_type_id, code"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="parent_idx" UNIQUE="false" FIELDS="parent_id"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_adminreport_runs" COMMENT="Program offerings and execution runs">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="groupid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="run_code" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="startdate" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="enddate" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="org_dim_id" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="type_dim_id" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="location_dim_id" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="classroom" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="daily_start_time" TYPE="char" LENGTH="10" NOTNULL="false" DEFAULT="08:00" SEQUENCE="false"/>
        <FIELD NAME="daily_end_time" TYPE="char" LENGTH="10" NOTNULL="false" DEFAULT="14:00" SEQUENCE="false"/>
        <FIELD NAME="break_duration_min" TYPE="int" LENGTH="5" NOTNULL="true" DEFAULT="30" SEQUENCE="false"/>
        <FIELD NAME="exam_time" TYPE="char" LENGTH="50" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="is_cancelled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="source" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="moodle_course" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="course_fk" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="dates_idx" UNIQUE="false" FIELDS="startdate, enddate"/>
        <INDEX NAME="org_idx" UNIQUE="false" FIELDS="org_dim_id"/>
        <INDEX NAME="type_idx" UNIQUE="false" FIELDS="type_dim_id"/>
        <INDEX NAME="loc_idx" UNIQUE="false" FIELDS="location_dim_id"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_adminreport_run_trainers" COMMENT="Trainers assigned to runs">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="run_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="trainer_name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="is_primary" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="run_fk" TYPE="foreign" FIELDS="run_id" REFTABLE="local_adminreport_runs" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="user_idx" UNIQUE="false" FIELDS="userid"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_adminreport_daily_stats" COMMENT="Daily flow analytics facts">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="stat_date" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="run_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="participations_flow" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="completions_flow" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="day_training_hours" TYPE="number" LENGTH="10" DECIMALS="2" NOTNULL="true" DEFAULT="0.00" SEQUENCE="false"/>
        <FIELD NAME="grade_sum" TYPE="number" LENGTH="12" DECIMALS="2" NOTNULL="true" DEFAULT="0.00" SEQUENCE="false"/>
        <FIELD NAME="grade_count" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="run_fk" TYPE="foreign" FIELDS="run_id" REFTABLE="local_adminreport_runs" REFFIELDS="id"/>
        <KEY NAME="date_run_uniq" TYPE="unique" FIELDS="stat_date, run_id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="stat_date_idx" UNIQUE="false" FIELDS="stat_date"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_adminreport_monthly_stats" COMMENT="Monthly rolled-up fact warehouse">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="year" TYPE="int" LENGTH="4" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="month" TYPE="int" LENGTH="2" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="org_dim_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="type_dim_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="location_dim_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="runs_count" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="participations_count" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="completions_count" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="total_training_hours" TYPE="number" LENGTH="12" DECIMALS="2" NOTNULL="true" DEFAULT="0.00" SEQUENCE="false"/>
        <FIELD NAME="grade_sum" TYPE="number" LENGTH="14" DECIMALS="2" NOTNULL="true" DEFAULT="0.00" SEQUENCE="false"/>
        <FIELD NAME="grade_count" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="month_dim_uniq" TYPE="unique" FIELDS="year, month, org_dim_id, type_dim_id, location_dim_id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="year_month_idx" UNIQUE="false" FIELDS="year, month"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_adminreport_scope" COMMENT="User and role dimension access scoping">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="scope_type" TYPE="char" LENGTH="20" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="scope_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="dim_member_id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="dim_member_fk" TYPE="foreign" FIELDS="dim_member_id" REFTABLE="local_adminreport_dim_members" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="scope_idx" UNIQUE="false" FIELDS="scope_type, scope_id"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

---

## 8. Privacy API Compliance (`classes/privacy/provider.php`)

Because `mdl_local_adminreport_run_trainers.userid` and `mdl_local_adminreport_scope.scope_id` associate Moodle user IDs with records, the plugin implements the complete **Moodle Privacy API**:
* `get_metadata()`: Registers trainer assignments in `local_adminreport_run_trainers` and scoping in `local_adminreport_scope`.
* `get_contexts_for_userid()`: Returns the system context for scoped records.
* `export_user_data()`: Exports trainer assignment history and report access roles.
* `delete_data_for_user()`: Anonymizes trainer records and removes personal scoping entries.

---

## 9. Early Warning / At-Risk Architecture (V1 Scope Boundary)

* **Definition:** A trainee is evaluated as *At-Risk* if:
  1. `inactivity_days >= 14` in an active run, OR
  2. `progress_pct < 30%` while run time elapsed $> 50\%$.
* **Execution:** Computed on-demand via `\local_adminreport\analytics\early_warning::get_summary()`.
* **Privacy & Cache Safety:** Individual learner names are **never stored in MUC**. The dashboard displays an aggregate summary badge. The individual list modal requires `local/adminreport:viewtrainees` and streams live with sesskey verification.

---

## 10. Front-End, Charts & Print Styling

* **ApexCharts 3.x:** Bundled in `amd/src/apexcharts.js` under MIT License (logged in `thirdpartylibs.xml`).
* **RTL Verification:** Tested with Arabic labels, right-aligned tooltips, and reversed Cartesian axes.
* **Executive Print View:** Handled via clean CSS print media queries (`styles.css` with `@media print`) removing filter controls and rendering high-contrast, page-break-safe tables.
