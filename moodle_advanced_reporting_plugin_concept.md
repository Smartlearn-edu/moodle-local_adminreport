# Advanced Moodle Reporting Plugin — Initial Concept

## 1. Purpose

The PPTX should be treated as a reference for the client's reporting needs, not as the plugin specification.

The goal is to build a much more powerful Moodle reporting and management intelligence plugin around Moodle data.

The client's current report combines scheduled/planned training, actual/completed programs, participants, organizations/sectors, locations, program types, and cumulative trends.

## 2. What the client's current report contains

### A. Operational / upcoming training

The report needs information such as:

- Planned courses
- Organization/client
- Location
- Trainer
- Number of trainees
- Number of groups
- Course duration
- Classroom
- Training time
- Break time
- Exam time
- Course/program code

The detailed weekly schedule contains columns for course, code, duration, trainer, classroom, trainees, time, break, and exam time.

### B. Aggregated operational report

The report aggregates information by:

- Location
- Organization
- Program type
- Number of programs
- Number of groups
- Number of trainees

### C. Completed training

Completed programs are classified by types such as:

- Development course
- Qualification course
- Training diploma
- Qualification program

Both number of programs and number of trainees are reported.

### D. Organization / sector analysis

The report analyzes:

- Saudi Water Authority
- Companies
- Government / military
- Gulf countries
- Individuals
- Other sectors

It reports both programs and trainees by sector.

### E. Cumulative / historical reporting

The report includes:

- Monthly numbers
- Cumulative numbers
- Annual totals
- Program-type distribution
- Customer/organization distribution

## 3. Recommended Product Direction

Do not build a static "report".

Build a **Moodle Management Intelligence Dashboard**.

A possible main structure:

```text
Moodle Advanced Reports
──────────────────────────────────────────────

[ Overview ] [ Learners ] [ Courses ] [ Activity ]
[ Grades ] [ Engagement ] [ Trainers ] [ Organizations ]
[ Completion ] [ Certificates ] [ Custom Reports ]

Date:
[ Last 7 days ▼ ] [Start Date] → [End Date]

Filters:
Course | Category | Cohort | Organization | Trainer
Role | Location | Enrollment method | Program type
```

All dashboard components should respond to the selected filters.

## 4. Four Major Reporting Layers

### Layer 1 — Executive Overview

The administrator should immediately see KPIs such as:

- Learners
- Courses
- Completion
- Activity
- New enrollments
- Certificates
- Assignment submissions
- Quiz attempts

Then include:

- Activity trends
- Top courses
- Alerts
- Completion trends
- Enrollment trends

Example alerts:

```text
⚠ 23 learners inactive for 14+ days
⚠ 17 courses have low completion
⚠ 8 courses have assignments awaiting grading
⚠ 4 courses have no activity this week
```

## 5. General Information

### Users

- Total users
- Active users
- Suspended users
- New users
- Users logged in today
- Users inactive for 7/14/30/60/90 days
- Students
- Teachers
- Managers
- Custom roles
- Users by cohort
- Users by organization

### Courses

- Total courses
- Visible courses
- Hidden courses
- Courses created
- Courses updated
- Courses with activity
- Courses with no activity
- Courses by category
- Courses by teacher

### Enrollments

- Total enrollments
- New enrollments
- Unenrollments
- Active enrollments
- Expired enrollments
- Enrollment by method

### Learning

- Completion rate
- Activity completion
- Course completion
- Average grade
- Assignment submissions
- Quiz attempts
- Failed attempts
- Certificates issued

## 6. Weekly Report

The weekly report should be interactive rather than a static PowerPoint-style document.

### Weekly Summary

Example:

```text
This Week
30 Aug → 05 Sep 2026

New Learners              384
Course Enrollments        521
Active Learners           447
Completed Courses         183
Certificates              167
Assignments Submitted     932
Quiz Attempts            1,284
```

### Daily Activity

Show daily:

- Logins
- Activity
- Enrollments
- Completions

### Courses Running This Week

Possible table:

| Course | Students | Teacher | Progress | Completion |
|---|---:|---|---:|---:|
| Moodle Basics | 32 | Ahmed | 64% | 58% |
| Power BI | 25 | Ali | 71% | 68% |

### Learner Activity

- Most active learners
- Least active learners
- Learners at risk
- Learners who started but stopped
- Learners with overdue activities

## 7. Monthly Report

Monthly reporting should emphasize trends and comparisons.

### Monthly Trends

- Enrollment trend
- Course completion trend
- Active learner trend
- Certificate trend
- Activity trend

### Period Comparison

Allow:

```text
September 2026
vs
August 2026
```

Possible table:

| Metric | August | September | Change |
|---|---:|---:|---:|
| Active learners | 4,281 | 4,763 | +11.3% |
| Enrollments | 5,120 | 5,841 | +14.1% |
| Completions | 3,921 | 4,230 | +7.9% |
| Certificates | 3,102 | 3,477 | +12.1% |

Possible comparison modes:

- Previous period
- Same period last year
- Custom comparison period

## 8. Annual Report

The annual report should be a year-at-a-glance dashboard.

Example:

```text
2026

12,421       387       8,942       72%
Learners    Courses   Completions  Completion
```

Then show monthly:

| Month | Learners | Enrollments | Completions | Certificates |
|---|---:|---:|---:|---:|
| Jan | ... | ... | ... | ... |
| Feb | ... | ... | ... | ... |
| Mar | ... | ... | ... | ... |
| ... | ... | ... | ... | ... |

## 9. Custom Date

Custom date should not simply mean "from date to date".

The entire dashboard should recalculate based on:

```text
Start Date → End Date
```

Including:

- KPIs
- Charts
- Tables
- Rankings
- Trends
- Comparisons

Optional comparison:

```text
Compare with:
☐ Previous period
☐ Same period last year
☐ Custom comparison period
```

## 10. Global Filters

This can be one of the plugin's biggest selling points.

At the top:

```text
Date
[ This Month ▼ ]

Category
[ All Categories ▼ ]

Course
[ All Courses ▼ ]

Teacher
[ All Teachers ▼ ]

Cohort
[ All Cohorts ▼ ]

Organization
[ All Organizations ▼ ]

Location
[ All Locations ▼ ]
```

Every dashboard component should update accordingly.

For example, selecting one organization should immediately show:

- Its learners
- Enrollments
- Courses
- Completion
- Grades
- Activity
- Certificates
- Trends

## 11. Reporting Dimensions

The client's report contains dimensions that are not all naturally represented by standard Moodle fields.

Examples:

- Organization
- Sector
- Location
- Program Type
- Classroom
- Trainer
- Course Code
- Groups

Therefore, do not hard-code these dimensions into reporting queries.

Instead, create a configurable **Reporting Dimensions** system.

Example:

```text
Reporting Dimensions

Organization
├── Saudi Water Authority
├── ERWA
├── NWC
├── Private Companies
└── Government

Sector
├── Government
├── Private
├── Military
└── Individuals

Location
├── Riyadh
├── Jubail
├── Jeddah
├── Online
└── ...
```

Allow administrators to map dimensions to Moodle data sources, for example:

```text
Organization → Cohort
Organization → Custom user profile field
Organization → Course custom field
Organization → Course category
Organization → Group
```

This makes the plugin reusable across different Moodle installations.

## 12. Dimension-Based Reporting Architecture

Think of every report as:

**Metric + Dimension + Time**

Example:

```text
Metric:
Number of learners

Dimension:
Organization

Time:
September 2026
```

Result:

| Organization | Learners |
|---|---:|
| ERWA | 420 |
| NWC | 682 |
| Company X | 210 |
| Government | 91 |

Change the dimension to Location:

| Location | Learners |
|---|---:|
| Riyadh | 720 |
| Jubail | 390 |
| Jeddah | 120 |
| Online | 173 |

This architecture makes the reporting engine much more flexible.

## 13. Drill-Down

A major feature should be drill-down.

Example:

Dashboard:

```text
Completion Rate: 74%
```

Click it:

| Course | Students | Completed | Incomplete | Rate |
|---|---:|---:|---:|---:|
| Moodle Basics | 120 | 98 | 22 | 81.7% |
| Power BI | 85 | 54 | 31 | 63.5% |

Click Power BI:

| Student | Progress | Grade | Last Access |
|---|---:|---:|---|
| Ahmed | 91% | 88% | Today |
| Mohamed | 78% | 74% | Yesterday |

Click the learner and open the Moodle user profile.

This creates a natural:

**Dashboard → Metric → Course → Learner**

workflow.

## 14. At-Risk Reporting

This should be a major differentiator.

Create configurable risk rules.

Example:

```text
At-risk learner if:

No login for 14 days

OR

Course progress < 30%
AND course is >50% through its duration

OR

Failed 2+ quizzes

OR

Missing 3+ activities

OR

Grade below 50%
```

Dashboard:

```text
At-Risk Learners: 137
```

Table:

| Learner | Course | Risk | Reason | Last Access |
|---|---|---|---|---|
| Ahmed | Course A | High | No access 21 days | Aug 9 |
| Ali | Course B | Medium | Low progress | Aug 18 |

Risk rules should be configurable by administrators.

## 15. Trainer / Instructor Report

Possible trainer dashboard:

```text
Trainer: Ahmed

Courses                  8
Learners                 213
Completion               81%
Average grade             84%
Assignment grading        94%
Late grading               6
```

Include:

- Courses taught
- Students
- Completion rate
- Average grades
- Assignment grading workload
- Quiz performance
- Student activity
- Feedback activity

Be careful to distinguish workload/course outcomes from claims about individual trainer quality.

## 16. Course Analytics

Example:

```text
Course: Power BI

Enrolled                  124
Active                    112
Completed                  91
Completion                73.4%
Average grade              81%
Activities                 47
```

Include:

- Activity completion
- Most difficult activities
- Least accessed activities
- Quiz statistics
- Assignment statistics
- Grade distribution
- Learner progress
- Engagement over time

### Learning Funnel

```text
Enrolled       124
     ↓
Started        119
     ↓
50% progress   107
     ↓
Completed       91
     ↓
Certificate     87
```

## 17. Grade Analytics

Possible grade analytics:

- Average grade
- Median
- Highest
- Lowest
- Standard deviation
- Pass rate
- Fail rate
- Grade distribution
- Grade by course
- Grade by category
- Grade by teacher
- Grade by cohort
- Grade over time

Example:

```text
90–100   █████████
80–89    █████████████
70–79    ████████
60–69    ████
<60      ██
```

### Possible AI Grading Integration

Because the plugin ecosystem may include AI grading plugins, the reporting layer could eventually support:

- AI-graded submissions
- Pending AI grading
- Human-reviewed AI grades
- Average AI confidence
- Human vs AI grade differences

This should be optional and integration-based rather than hard-coded.

## 18. Moodle Health

Add a management/health section that the client's current PPT does not provide.

Possible indicators:

```text
Moodle Health

Users
████████████████████  Good

Courses
███████████████████░  Good

Activity
████████████████░░░░  Moderate

Completion
██████████████░░░░░░  Needs attention

Pending grading
███████████████████░  Good
```

Possible alerts:

- Courses with no activity
- Assignments with overdue grading
- Courses with very low completion
- Users inactive for a configurable period
- Courses with unusually high failure rates

This makes the plugin useful every day, not only for periodic reporting.

## 19. Export

Reports should support:

```text
[ Export Excel ]
[ Export CSV ]
[ Export PDF ]
[ Print ]
```

Potentially also:

- Export current filtered table
- Export complete report
- Export chart data
- Include/exclude charts in PDF

## 20. Scheduled Reports

A strong feature would be scheduled report generation.

Example:

```text
Scheduled Reports

Report:
Monthly Management Report

Period:
Previous month

Recipients:
admin@example.com
manager@example.com

Format:
PDF + Excel

Schedule:
1st day of every month
08:00
```

Also:

```text
Weekly Training Report
Every Sunday
```

The plugin could generate the report itself, while n8n could optionally consume an API/webhook.

## 21. Data Architecture

Do not calculate every metric directly from raw Moodle tables on every page load.

For small/current metrics, real-time queries may be appropriate.

For expensive historical analytics, especially:

- Millions of log records
- Daily activity
- Historical trends
- Cumulative statistics
- Complex user/course combinations

use an aggregation strategy.

Possible structure:

```text
report_daily_stats

date
courseid
categoryid
userid_count
enrollment_count
completion_count
activity_count
login_count
...
```

Then monthly/yearly charts can use aggregated data instead of repeatedly scanning `logstore_standard_log`.

The exact schema should be designed after defining the metrics and Moodle versions supported.

## 22. The PPT Demonstrates the Need for Automation

The source report contains evidence of manual-reporting problems.

For example, one section contains `#REF!` in a trainee calculation.

There are also sections where the report heading refers to one period while other sections contain different/historical periods.

This reinforces the value of using Moodle as the single source of truth:

```text
Moodle
   ↓
Reporting Engine
   ↓
Dashboard
   ├── Tables
   ├── Charts
   ├── KPIs
   ├── Drill-down
   ├── Excel
   ├── PDF
   └── Scheduled reports
```

instead of:

```text
Moodle
   ↓
Excel
   ↓
Manual calculations
   ↓
PowerPoint
   ↓
Human corrections
```

## 23. Suggested Plugin Structure

Possible product names:

- Smart Reports
- Advanced Moodle Analytics
- SmartLearn Reports

Possible navigation:

```text
Reports
│
├── Dashboard
├── General Overview
├── Learners
├── Courses
├── Enrollments
├── Completion
├── Grades
├── Activity
├── Trainers
├── Organizations
├── Certificates
├── At Risk
├── Trends
├── Custom Reports
└── Scheduled Reports
```

Every report should have a consistent structure:

```text
Date Range
Filters
KPIs
Charts
Tables
Drill-down
Export
```

## 24. Recommended Development Approach

Do not start coding the UI immediately.

First define a **reporting matrix**.

Example:

| Report | Metric | Moodle Source | Dimension | Time | Filter | Chart |
|---|---|---|---|---|---|---|
| Overview | Active users | user/log | Role | Date | Category | KPI |
| Enrollment | New enrollments | user_enrolments | Course | Date | Method | Line |
| Completion | Completion rate | completion | Course | Date | Category | Bar |
| Activity | Activity count | log | Course | Date | User | Line |
| Grades | Avg grade | grade | Course | Date | Category | Bar |
| Trainer | Learners | enrollment | Teacher | Date | Course | Table |
| Organization | Learners | Custom mapping | Organization | Date | Sector | Pie |
| At Risk | Risk users | Multiple | Course | Date | Risk | Table |

This will identify:

1. Which metrics are native Moodle data.
2. Which require plugin-specific data.
3. Which require a configurable mapping layer.
4. Which calculations should be real-time.
5. Which calculations should be aggregated/cached.

## 25. Proposed Screen-by-Screen Design Phase

Before writing the plugin code, design:

1. **Main Dashboard**
2. **General Information**
3. **Weekly Report**
4. **Monthly Report**
5. **Annual Report**
6. **Custom Date Report**
7. **Learner Analytics**
8. **Course Analytics**
9. **Trainer Analytics**
10. **Organization/Sector Analytics**
11. **At-Risk Analytics**
12. **Drill-down behavior**
13. **Global Filters**
14. **Export / Scheduled Reports**
15. **Plugin architecture**
16. **Database / aggregation strategy**
17. **Permissions and custom-role access**
18. **Configuration of reporting dimensions**

The key product decision is to make this a **generic Moodle reporting framework**, with the client's current report being the first use case, rather than building a one-off copy of their PowerPoint report.
