# Defence Guide: Intelligent CBC-Aligned Student Competency System

**Case study:** St Lawrence University, Faculty of Science and Technology (IT programmes)
**Data:** the university's own Student Information System (`campus_dynamics`), read-only

This guide prepares you to present and defend the system. It covers what the system does, how every
technical part works and *why* it was built that way, a demo script that tells you what to say on each
screen, and a long bank of likely examiner questions with model answers.

Read it in this order:

1. **Part 1: The story.** Learn it well enough to tell in your own words.
2. **Part 2: Architecture.** You must be able to draw it on a whiteboard.
3. **Part 3: The algorithms.** Work through the examples by hand once. Examiners love the maths.
4. **Part 4: The real university data.** Know what was in it, what was wrong with it, and how the system copes.
5. **Part 6: The demo script.** Rehearse it at least twice with the real app.
6. **Part 8: The question bank.** Answer them out loud, not in your head.

Demo accounts are in **`docs/demo_accounts.local.md`**. That file holds real registration numbers, so it's
kept out of git. This guide deliberately contains no real names or registration numbers.

> **The golden rule of the defence:** say what the system *does*, explain *why* you chose that, and be
> honest about the limits. Examiners forgive limitations you name yourself. They punish ones they
> have to discover.

---

## Contents

- [Part 1: The story](#part-1-the-story)
- [Part 2: Architecture](#part-2-architecture)
- [Part 3: Algorithms with worked examples](#part-3-algorithms-with-worked-examples)
- [Part 4: The real university data and our database](#part-4-the-real-university-data-and-our-database)
- [Part 5: Roles, permissions and security](#part-5-roles-permissions-and-security)
- [Part 6: Demo script (what to say on each screen)](#part-6-demo-script)
- [Part 7: Integration with the university system](#part-7-integration-with-the-university-system)
- [Part 8: Question bank with model answers](#part-8-question-bank-with-model-answers)
- [Part 9: Limitations and future work](#part-9-limitations-and-future-work)
- [Part 10: Handling difficult moments](#part-10-handling-difficult-moments)
- [Part 11: Numbers to remember](#part-11-numbers-to-remember)
- [Part 12: Glossary](#part-12-glossary)
- [Appendix A: Setup and demo checklist](#appendix-a-setup-and-demo-checklist)
- [Appendix B: Where everything lives in the code](#appendix-b-where-everything-lives-in-the-code)

---

## Part 1: The story

### 1.1 The 30-second version (use this when someone asks "so what did you build?")

> "Universities measure students by grades, but employers hire for skills. My system closes that gap.
> It reads students' results directly from the university's records, turns those marks into a
> competency profile across skills like programming, databases and networking, places each student on a
> CBC level, and compares that profile with real job postings. Each student sees how ready they are for
> each job, exactly which skills are holding them back, and which upcoming course units or resources
> will close the gap. Staff see the same picture across their faculty or the whole university."

### 1.2 The 3-minute opening (start the defence with this)

**The problem.**
Graduates leave university with a transcript full of grades, but a grade in "Database Planning, Design
and Management" doesn't tell an employer whether the student can build a MySQL database. It doesn't tell
the student either. At the same time, the job market keeps moving. Students don't know which skills
employers are asking for, and faculties don't know whether their curriculum covers them.

**Why it matters for St Lawrence University.**
Uganda's education reforms are moving towards competency-based learning (CBC). The Faculty of Science
and Technology needs a way to see student *competencies*, not just *grades*, and to show how well its IT
students are prepared for the job market.

**What the system does.** There are four steps.
1. **It reads academic data from the university system.** Faculties, programmes, course units,
   students and results already live in the university's Student Information System, so we never
   re-enter them. We read them, read-only. The system runs on the university's real data: 14,124
   students and 370,961 results in the source, of which the 608 current students of the 5 IT programmes
   are brought in.
2. **It turns marks into competencies.** Every course unit is mapped to the skills it builds. A
   student's marks in those units become a skill level from 0 to 100 for each skill, and an overall
   CBC level (Novice, Intermediate, Proficient or Expert).
3. **It matches students to real jobs.** Job postings are fetched from a jobs API. The skills each job
   needs are detected, and every student is scored against every job with a hybrid algorithm.
4. **It explains the gap.** For each job, the student sees which required skills they meet, which are
   gaps, and how to close each gap. Course units still ahead in their own programme come first, then
   self-study resources.

**Who uses it.**
- Students see their profile and their job matches.
- Lecturers add practical evidence that grades can't show, such as internship or project work.
- Heads of department and deans follow readiness for their faculty and maintain the curriculum mapping.
- Career officers manage job postings.
- A super admin manages users, roles and the university connection.

**The key design decision.** The system does *not* replace the university portal. It does not mark,
store or edit grades. It is an *analytics layer on top of* the university's records. That keeps it
small, avoids duplicate data entry, and means the university's records stay the single source of
truth.

### 1.3 Objectives (say these clearly; examiners check them against your results)

**Main objective:**
To develop a web-based system that profiles students' competencies from their academic records,
aligns them to a CBC framework, and measures their readiness for real job market requirements.

**Specific objectives:**
1. To build a competency profile per student from university results, using a configurable mapping
   from course units to skills.
2. To place each student on an editable CBC scale (Novice → Expert).
3. To collect live job postings and identify the skills each one requires.
4. To compute a job-readiness score per student per job using a hybrid matching algorithm.
5. To identify skill gaps and recommend how to close them.
6. To give each type of user (student, lecturer, head of department, dean, career officer,
   administrator) the right view of the data, with role-based access control.

**For each objective, be ready to point at the screen that proves it.** Part 6 does this for you, and
the walkthrough guide (`docs/system_walkthrough.md`) checks each one.

### 1.4 Scope (and what is deliberately out of scope)

**In scope:**
- Reading the university's structure for **all faculties**: faculties, programmes, course units and
  curricula
- Students and results for the **IT programmes** (the case study):
  - Bachelor of Information Technology
  - BSc Computer Science
  - BSc Computer Engineering
  - BSc Telecommunication Engineering
  - Diploma in Information Technology
- Competency profiling, the CBC framework, job collection and skill detection
- Matching, gap analysis and recommendations
- Dashboards and analytics
- Roles and permissions

**Out of scope, and why:**

| Out of scope | Why |
|---|---|
| Entering or editing marks | The university portal already does this. Duplicating it would create two versions of the truth. |
| Student registration | Students already exist in the university system; they sign in with their portal login. |
| Applying for jobs inside the system | We link to the original posting. Application tracking belongs to employers' systems. |
| Students of other faculties | Their structure is already loaded. Switching on their programmes is one click, but they need their own skills framework (accounting skills aren't IT skills). |
| Departments | The university's system has no academic departments: programmes belong directly to faculties. The system follows the university's real structure. |

---

## Part 2: Architecture

### 2.1 The big picture (draw this on the whiteboard)

```
   UNIVERSITY (owns the records)                    OUR SYSTEM (analytics layer)
 ┌──────────────────────────────┐   read-only   ┌───────────────────────────────────────────┐
 │ Student Information System   │ ────────────► │  Sync          copies structure, students, │
 │  (campus_dynamics)           │   connector   │                results (one direction)    │
 │  faculties, programmes,      │               │      │                                    │
 │  courses, curricula,         │ ◄──────────── │  Login check   portal credentials verified │
 │  students, registrations,    │  verify login │      ▼                                    │
 │  results, portal logins      │               │  Curriculum mapping (course unit → skill)  │
 └──────────────────────────────┘               │      ▼                                    │
                                                │  Competency engine  marks → skill levels  │
   JOB MARKET                                   │                     → CBC level           │
 ┌──────────────────────────────┐               │      ▼                                    │
 │ JSearch jobs API (RapidAPI)  │ ────────────► │  Matching engine    profile × job → score │
 │ + manual job entry           │  fetch jobs   │      ▼                                    │
 └──────────────────────────────┘  detect skills│  Web app: dashboards, gaps, reports,      │
                                                │  roles & permissions                      │
                                                └───────────────────────────────────────────┘
```

**What to say:**
"There are two outside sources: the university's records and the job market. The university's data
comes in through one read-only connector. Jobs come in through an API. Everything in the middle is
ours: the mapping, the competency engine, the matching and the interface."

### 2.2 Technology stack and why each piece was chosen

| Layer | Technology | Why this choice (what to say) |
|---|---|---|
| Server language | **PHP 8** | Runs on almost any hosting, including a basic VPS, which matters for a university budget. Mature and easy for the university's IT staff to maintain. |
| Database | **MySQL / MariaDB** | The university's own system is MySQL, so data types and SQL match. Relational data (students → results → course units → skills) fits tables and joins naturally. |
| Database access | **PDO with prepared statements** | Protects against SQL injection because user input is never pasted into SQL text. |
| Interface | **HTML, CSS, Bootstrap 5** | Responsive out of the box, so it works on phones, which most students use. No build step. |
| Charts | **Chart.js** | Lightweight, free, interactive charts with tooltips. |
| Front-end logic | **Plain JavaScript** | Pages are server-rendered. Small things (filters, search, theme switching) don't need a framework. |
| Job data | **JSearch API (RapidAPI)** | Aggregates postings from LinkedIn, Indeed and local boards through one API, so there's no fragile web scraping. |
| Web server | **Nginx + PHP-FPM on a VPS** | Fast and standard. The deployment guide documents the setup. |
| Deployment | **GitHub Actions** | Every push to `main` deploys to the server automatically. |

**Likely follow-up: "Why not a framework like Laravel?"**
"For this size of project, plain PHP keeps the system small and transparent. The logic lives in a few
classes (`University`, `Sync`, `Competency`, `MatchingEngine`, `StudentProfile`), and the pages only
display results, so moving into a framework later would be straightforward."

### 2.3 Code organisation

```
public/                 web pages (what the browser requests)
  dashboard.php         overview; different content per role
  profile.php           student: My skills
  jobs.php              student: Job matches
  report.php            student: printable readiness report
  login.php
  admin/                university data, curriculum, skills, CBC levels, jobs, analytics, users, roles
  lecturer/             practical evidence, student list
  css/style.css         all styling, including light and dark themes
  js/app.js             theme switching, chart re-theming
src/
  config/               database connection and settings (.env)
  utils/
    University.php      THE connector to the university system (read-only, real schema)
    Sync.php            copies university data in
    Competency.php      turns marks into skill levels and CBC levels
    MatchingEngine.php  scores a student against a job
    StudentProfile.php  everything a student page needs (skills, sources, gaps)
    JobApi.php          fetches jobs and detects their skills
    auth.php            sign-in, roles, permissions, data scope, CSRF protection
    ui.php              icons, messages, name formatting
  scripts/
    load_university_dump.php   loads the academic tables from an SIS dump into a local read-only copy
    seed.php                   first-time setup: admin, sync, switch on IT programmes, draft mapping
    suggest_mapping.php        drafts a course-unit → skill mapping from course names
    sync.php                   run a sync from the command line (for a nightly schedule)
  views/layout/         shared header (navbar) and footer
database/
  schema.sql            full database structure
  migrations/           step-by-step upgrades of an existing database (001, 002, 003)
docs/                   guides, including this one
```

**What to say:** "The pages only *display*. The thinking happens in a few classes under `src/utils`.
I can test or replace the algorithm without touching the interface, and I can swap the university
connector without touching anything else."

### 2.4 The single most important design principle: one source of truth

The university's Student Information System (SIS) is the **system of record** for faculties,
programmes, course units, students and results. Our system **reads** these and never writes back.

- **Enforced, not just promised.** The connector opens the university database in a
  `READ ONLY` transaction mode. The database itself refuses any write attempt. This was tested: a
  delete attempt fails with "Cannot execute statement in a READ ONLY transaction".
- **We keep a local copy (a mirror) of what we need.** It's refreshed by a sync, which can run
  nightly. Why a copy rather than querying the university every time?
  - **Performance:** dashboards join results, skills and jobs across many tables. Doing that against
    a remote system on every page view would be slow and would load the university's server.
  - **Resilience:** if the university system is down, staff can still view analytics.
  - **Our own data needs to join theirs:** the curriculum mapping, lecturer evidence and job matches
    are ours, and they reference course units and students.
- **What is ours to store:**
  - the skills framework and CBC levels
  - the course-unit-to-skill mapping
  - jobs and their required skills
  - lecturer practical evidence
  - computed profiles and match scores
  - roles, permissions, and which programmes are in use

**What to say if asked "Isn't copying data dangerous?"**
"The copy is one-directional and disposable. If it were deleted, a sync rebuilds it from the
university's records. The only data that exists only in our system is data the university doesn't
have, like the curriculum mapping and job matches."

### 2.5 End-to-end data flow (walk through this slowly if asked "how does it work?")

1. **Sync** (Administration → University data → *Sync now*, or a nightly scheduled job):
   1. Copy **all** faculties, programmes, course units and each programme's curriculum.
      - Each unit's year and semester are worked out from when students actually took it, because the
        university's curriculum table files most units under Year 1 Semester 1.
   2. For programmes **switched on**, copy their *current* students (registered in either of the two most
      recent academic years) and those students' results.
   3. Copy staff accounts from the university's admin system. New staff start with the Lecturer role.
2. **Competency engine** runs right after the sync. For every student it:
   1. computes an academic level per skill from their marks and the curriculum mapping
   2. blends in any lecturer evidence (25%)
   3. assigns a CBC level from the average skill level
3. **Matching engine** scores every student against every job and stores the score, its parts and
   the gap analysis.
4. **Pages read the stored results.** Viewing a page never recalculates or changes data. That keeps
   pages fast and results consistent between users.
5. **Triggers to recalculate:**
   - a sync (about 5 seconds for everything)
   - saving a curriculum mapping (recalculates only the students who took that unit)
   - saving lecturer evidence (recalculates that one student)

---

## Part 3: Algorithms with worked examples

Practise these by hand. If an examiner asks "show me how a score is calculated", walk through the
example on the whiteboard.

### 3.1 The curriculum mapping (the bridge between grades and skills)

Every course unit is linked to the skills it builds, with a **weight** for how central the skill is
to that unit:

| Label in the app | Weight | Meaning |
|---|---|---|
| Core | 1.0 | The unit is mainly about this skill |
| Strong | 0.75 | A major part of the unit |
| Moderate | 0.5 | A significant part |
| Light | 0.25 | Touched on |

Real examples from the university's Bachelor of IT curriculum:
- *BAIT 2106 Web Scripting Using PHP* → PHP (core), MySQL (light)
- *BAIT 1104 Principles of Programming in C* → C++ (core), Problem Solving (strong)
- *BAIT2102 Data Communication and Networking* → TCP/IP (strong), DNS (moderate)

**Why weights and not a simple yes/no?**
A unit like "Web Scripting Using PHP" teaches PHP far more than it teaches MySQL. Without weights, a
student's PHP mark would count fully towards MySQL too, which overstates their MySQL ability.

**Who sets it, and the honest part:**
- **The university's system has no course descriptions,** so the mapping can't be read from the data.
- **The system drafts a first version from course names:** for example, "Web Scripting Using PHP" →
  PHP. It drafted mappings for 300 course units.
- **Faculty staff then review and correct it** on the Curriculum page (permission: "Map course units to
  skills"). Curricula change, and the people who teach them know best what each unit covers.
- **The algorithm stays fixed while the knowledge stays editable.**

### 3.2 From marks to a skill level (the academic level)

For each student and each skill:

```
                    Σ  ( best mark in unit  ×  mapping weight  ×  credit units )
academic level  =  ─────────────────────────────────────────────────────────────
                    Σ  ( mapping weight  ×  credit units )
```

summed over every course unit the student has taken that builds that skill.

- **Best mark:** if there's more than one attempt, the best counts, because the skill was eventually
  demonstrated. The university's system keeps one result per student per course anyway.
- **Credit units:** a 4-credit unit is a bigger body of work than a 3-credit unit, so it counts more,
  the same principle as CGPA.
- **Weighted average, not a sum:** a student who took more units isn't automatically "better". The level
  stays on the 0 to 100 scale.

**Worked example: a real third-year Computer Science student's MySQL level**

| Course unit | Mark | Weight | Credit units | weight × CU | mark × weight × CU |
|---|---|---|---|---|---|
| BAIT 1203 Principles of Database Programming | 90 | 0.75 | 4 | 3.0 | 270 |
| BAIT 2104 Database Planning, Design & Management | 86 | 0.75 | 4 | 3.0 | 258 |
| **Total** | | | | **6.0** | **528** |

Academic level = 528 ÷ 6.0 = **88**. The system stored exactly 88 for this student.

**What to say:** "The student's MySQL level of 88 comes from their two database units, where they
scored 90 and 86. Both are 4-credit units and both teach MySQL strongly, so they count equally. You
can open the skill on the student's *My skills* page and see exactly these units and marks."

### 3.3 Lecturer practical evidence (the 25%)

Grades can't show everything: internship performance, a strong final-year project, communication
skills. Lecturers can record a level from 0 to 100 per skill with a note, for students in their faculty.

```
final level = 0.75 × academic level + 0.25 × evidence level   (when both exist)
final level = academic level                                   (no evidence)
final level = evidence level                                   (no academic level, e.g. a skill no unit teaches)
```

If several lecturers give evidence for the same skill, their levels are averaged first.

**Worked example:** academic MySQL level 88, and an internship supervisor's report via a lecturer
gives 70.
Final = 0.75 × 88 + 0.25 × 70 = 66 + 17.5 = **83.5 → 84**

**Why 25%?**
"Academic results are the verified, moderated, institution-wide evidence, so they should dominate.
Lecturer evidence adds what grades miss, but it's one person's judgement, so it shouldn't overturn the
results. It's a single constant in the code (`Competency::EVIDENCE_SHARE`), so the university could
tune it."

**Why lecturers aren't "marking":**
"Lecturers can't enter grades; that's the portal's job. Evidence is *additional*, clearly labelled,
and every item shows who gave it, when and why."

### 3.4 The CBC level

The student's CBC level comes from the **average of their skill levels**, placed on the editable scale:

| Level | Range | Meaning (editable) |
|---|---|---|
| Novice | 0–40 | Needs supervision |
| Intermediate | 41–70 | Can work with guidance |
| Proficient | 71–90 | Works independently |
| Expert | 91–100 | Mentors others |

**Current spread among the 342 IT students with a profile:** Novice 24, Intermediate 242, Proficient 76,
Expert 0. The other 266 students have no results yet, so they have no level.

**Why editable?** "CBC frameworks differ between institutions and change over time. Hard-coding the
bands would tie the system to one version of the framework."

### 3.5 Detecting what skills a job needs

When jobs are fetched, the system scans each description for the names of skills in the framework
(whole words, ignoring case). Each skill found gets a weight based on how often it's mentioned:

```
weight = min(1.0, 0.3 + 0.1 × number of mentions)
```

One mention gives 0.4, three mentions give 0.6, and seven or more give the maximum, 1.0.

**Why frequency?** "A skill mentioned many times in a posting is usually central to the role."

**Be honest about the limit:**
"This is keyword detection, not real language understanding. It misses skills described in other words
('relational databases' instead of 'MySQL'). 7 of the 20 jobs had no recognisable skills, so they can't
be scored. The planned improvement is a screen where the career officer confirms each job's required
skills, with the detection as a first draft."

### 3.6 The job-readiness score (the hybrid matching algorithm)

```
match score = 0.6 × skills fit  +  0.4 × description fit
```

**Skills fit (60%)** is a weighted average of the student's level across the skills the job requires.
A missing skill counts as 0:

```
                Σ ( student level for skill × job weight for skill )
skills fit  =  ──────────────────────────────────────────────────────
                Σ ( job weight for skill )
```

**Description fit (40%)** is the student's average level across every framework skill whose name
appears in the job's title or description. It catches skills the text mentions even if they weren't
stored as formal requirements.

**Worked example** (illustrative numbers)

The job requires Python (weight 0.8), MySQL (0.7) and Communication (0.5). The student's levels are
Python 70, MySQL 76 and Communication 55.

- Skills fit = (70×0.8 + 76×0.7 + 55×0.5) ÷ (0.8 + 0.7 + 0.5)
  = (56 + 53.2 + 27.5) ÷ 2.0 = 136.7 ÷ 2.0 = **68.4**
- The description mentions Python, MySQL and Linux; the student has no Linux level, so that counts as 0.
  Description fit = (70 + 76 + 0) ÷ 3 = **48.7**
- Match = 0.6 × 68.4 + 0.4 × 48.7 = 41.0 + 19.5 = **60.5 → 60%**

**How the score is labelled** (the score is rounded first, so "70%" is never called "close"):

| Score | Label | Meaning |
|---|---|---|
| 70% and above | Ready to apply | The profile fits the role well |
| 50–69% | Close to ready | A few skills need work |
| Below 50% | Stretch role | Significant development needed |

**Why hybrid?**
"The weighted part is precise but only as good as the stored requirements. The description part is
broader and catches what the requirements missed. 60/40 favours the structured requirements because
they're weighted by importance."

**Why missing skills count as 0:**
"If a job needs Linux and the student has never studied it, that must lower the score. Otherwise a
student with fewer skills would look better than one with more."

> **A bug that was found and fixed (good to mention; it shows rigour).**
> The first version of the description score divided by the number of skills the *student* had, so a
> student with only a few skills could outscore a finalist. The fix measures against the skills the
> *job* mentions. Realistic data exposed a flaw that toy data hid.

### 3.7 Gap analysis

For a given job, any **required skill below 60** is a **gap**, and its size is `60 − current level`.

**Why 60?**
"60 is a C on the university's own grading scale (C = 60–64): a sound pass. On our CBC scale it's well
into Intermediate. It's a named constant (`MatchingEngine::GAP_THRESHOLD`)."

### 3.8 "Skills to grow next" (prioritising gaps across many jobs)

Across the student's **10 closest matches**, each gap gets an **impact**:

```
impact of a skill = Σ over those jobs ( job weight × (60 − student level) )
```

The highest impact comes first. That favours skills that are needed by many of the student's
best-fitting jobs, are important in those jobs, and are far below 60.

**What to say:** "This turns a pile of gaps into a prioritised to-do list."

### 3.9 Recommendations ("how to close the gap")

For each gap, in order:

1. **Course units still ahead in the student's own programme** that build the skill. These come from
   the programme's real curriculum, with the year and semester the unit is usually taken. For example,
   a first-year with a networking gap is told "BAIT2102 Data Communication and Networking, Year 2,
   semester 1 of your programme".
2. **Self-study links** to Coursera and YouTube searches for the skill.

**Why work them out live instead of storing them?**
"They depend on data that changes: the mapping, the results and the job's requirements. Working them
out when the page loads means they're never stale, and viewing a page never changes data."

---

## Part 4: The real university data and our database

### 4.1 What the university's database contained

The university provided a dump of its Student Information System, **`campus_dynamics`**: MySQL 5.6, 107
tables. It also contained HR, payroll and admissions data. The system uses **only the academic tables**,
and the loader script skips everything else.

| We need | University table | Contents |
|---|---|---|
| Faculties | `acad_faculty` | 6 real faculties (plus an "All" placeholder) |
| Programmes | `acad_programme` | 97 programmes |
| Course units | `acad_course` | ~6,350 units with credit units |
| Curricula | `acad_programmecourses` | which units belong to which programme |
| Students | `acad_student` | 14,124 students |
| Current year of study | `acad_registration` | registration per academic year and semester |
| Results | `acad_results` | 370,961 results: mark, grade, credit units, year, semester |
| Grading scale | `acad_gs_details` | A 80+ … F below 50, **identical** to the scale the system already used |
| Student logins | portal `my_aspnet_membership` | 50,801 portal accounts; usernames are registration numbers; passwords salted and hashed |
| Staff logins | admin `my_aspnet_users` + `user_faculties` | 47 admin-system accounts with their faculty |

**What it did *not* contain:**
- **Academic departments.** Programmes belong directly to faculties, so the system dropped departments
  and limits staff by faculty.
- **Course descriptions.** That's why the curriculum mapping is drafted from course names and reviewed
  by staff.
- **A lecturer list with names.** Staff records are usernames and emails; the HR staff table is
  essentially empty.

### 4.2 Data quality problems found, and how the system handles them

These are excellent defence material. They show the system was tested on messy real data, not just
clean examples.

| Problem in the university data | How the system copes |
|---|---|
| The curriculum table files most units under Year 1 Semester 1 (101 of 127 for Bachelor of IT) | Each unit's year and semester are worked out from when students *actually took it* in their results |
| 580 of 585 current IT students have no valid email | Accounts are keyed by registration number; email is optional |
| Some codes and usernames have stray spaces or tab characters | Every code and username is trimmed on the way in |
| Some registration numbers have up to 4 duplicate portal accounts | Sign-in checks every matching account |
| Marks above 100 exist in some programmes | Marks outside 0–100 are skipped |
| Malformed academic years (e.g. a stray tab before "2018/2019", or "Rese/1") | Only well-formed years like 2024/2025 are accepted |
| ~5,000 results with a junk grade "0.0" | Skill levels use the mark, never the letter grade |
| Grades up to 5 characters long | Our column was widened to match |
| Near-duplicate course codes (e.g. "BAIT 1203" and "BAIT1203" are different units) | Both are kept as the university has them; each is mapped separately |
| Only 273 of the 608 current IT students have a portal account in this copy | They can sign in; the others can't until accounts exist. Staff can still see all 608 |

### 4.3 Who is brought in

- **Structure for the whole university:**
  - 6 faculties, 97 programmes, 6,352 course units
  - 8,040 curriculum entries (which unit is in which programme, in which year and semester)
- **Students for programmes switched on:** the 5 IT programmes give **608 current students** (registered
  in 2024/25 or 2025/26) and **5,881 results**.
  - **342 students have a competency profile.**
  - **The other 266 are mostly new students with no results yet.** A profile appears automatically once
    their first results are recorded.
- **47 staff accounts** from the university's admin system, each with their faculty.

### 4.4 Our own database: three kinds of tables

| Kind | Tables | Who owns the truth |
|---|---|---|
| **Mirror of the university** (refreshed by sync, never edited by users) | `faculties`, `programmes`, `course_units`, `programme_units` (curricula), `students`, `student_results`; university accounts in `users` | The university |
| **Our own data** | `skills`, `cbc_levels`, `course_unit_skills` (the mapping), `skill_evidence`, `jobs`, `job_skills`, `roles`, `role_permissions`, `sync_log`; `programmes.is_active`; the local admin | This system |
| **Computed** (always rebuildable) | `student_skills` (levels), `job_readiness` (scores and gaps), `students.cbc_level_id` | Calculated by the engines |

### 4.5 Key tables explained

- **`users`**: everyone who can sign in.
  - `role` is the account type: `student` or `staff`.
  - `username` is the registration number or staff username.
  - `role_id` links staff to a role.
  - `source` is `university` or `local`.
  - `faculty_code` is the person's faculty, used for data scope.
  - `is_active` lets an admin deactivate an account.
- **`programmes`**: keyed by the university's code (e.g. `02305` for Bachelor of IT). `is_active` decides
  whether the programme's students are brought in.
- **`course_units`**: keyed by the university's course code (e.g. `BAIT 2106`), with credit units.
- **`programme_units`**: the curriculum: (programme, course unit, year, semester).
- **`course_unit_skills`**: *the curriculum mapping*: (course unit, skill, weight).
- **`student_results`**: one row per student, course and academic year, with mark and grade.
- **`student_skills`**: computed levels, keeping academic, evidence and final level separately so the
  interface can explain where a number came from.
- **`job_readiness`**: one row per (student, job), unique, with the score, its parts and the gaps.

### 4.6 Design choices to be ready to defend

- **Keyed by the university's own codes** (faculty `02`, programme `02305`, course `BAIT 2106`). A
  re-sync updates the same row instead of creating new ones, and anyone can cross-check a record
  against the university's system.
- **Unique keys that protect correctness:**
  - (student, job) in `job_readiness`. This was missing in an early version, so re-matching duplicated
    rows; adding it fixed that.
  - (student, course, year) in results
  - (student, skill, lecturer) in evidence
  - username in users
- **Normalised core:** skills, units and jobs are stored once and linked through join tables.
- **Migrations:** structural changes are numbered scripts in `database/migrations/`:
  - `001`: university integration
  - `002`: roles and permissions
  - `003`: the real university structure

  Backups are taken before each.

---

## Part 5: Roles, permissions and security

### 5.1 The model: role-based access control (RBAC) with data scope

Two questions decide what a staff member can do:

1. **What actions may they perform?** That's the **permissions** ticked for their role.
2. **Whose data may they see?** That's the role's **scope**: the **whole university** or **their
   faculty**. The faculty comes from the university's own records for that person.

**Permissions (11), grouped as on the Roles page:**

| Group | Permission |
|---|---|
| Overview and reporting | See the staff overview dashboard · View analytics · View student profiles and readiness |
| Teaching | Record practical evidence · Map course units to skills |
| Framework and jobs | Manage the skills framework · Manage CBC levels · Manage job postings |
| Administration | Sync university data and choose which programmes use the system · Assign roles to staff and deactivate accounts · Create and edit roles and their permissions |

**Built-in roles and their defaults:**

| Role | Sees data for | Default permissions |
|---|---|---|
| Super admin | Whole university | Everything (locked; can't be reduced) |
| Career officer | Whole university | Overview, analytics, students, jobs, skills |
| Dean | Their faculty | Overview, analytics, students, curriculum mapping |
| Head of department | Their faculty* | Overview, analytics, students, curriculum mapping, evidence |
| Lecturer | Their faculty | Students, practical evidence |

\* The university's system has no departments, so heads of department currently work at faculty level.

Students are a separate account type with their own pages, and they only ever see their own data.

**Why editable roles?** "Every institution organises responsibilities differently. With editable
roles a super admin can, for example, create an 'Internship coordinator' role without a developer."

**Why scope as well as permissions?** "Permissions alone would let any dean see every faculty's
students. Scope applies *least privilege*: you see what your job needs and no more."

### 5.2 How it's enforced ("can't someone just type the URL?")

- **Every protected page checks a permission on the server** before doing anything. Hiding a menu
  link is only convenience. Typing the address of a page you're not allowed to use returns "You don't
  have access" (HTTP 403).
- **Every query that lists students adds the scope condition,** so a dean's dashboard, analytics,
  student lists, evidence page and curriculum page only include their faculty.
- **Write actions re-check the scope.** For example, saving a curriculum mapping checks that the unit is
  in a programme the user may edit.

### 5.3 Safety rules (built to prevent lock-out and privilege escalation)

- You can't change your own role or deactivate yourself.
- The **last active super admin** can't be demoted or deactivated.
- Only a super admin can grant or remove the Super admin role.
- Built-in roles can't be deleted. A custom role can only be deleted when nobody has it.
- A sync never overwrites a role an admin assigned. New staff start as Lecturers.
- Deactivating someone takes effect on their next page view and blocks sign-in.

### 5.4 Security measures

| Threat | Protection in the system |
|---|---|
| SQL injection | All database access uses PDO **prepared statements** with real server-side preparation. |
| Cross-site scripting (XSS) | Everything displayed from the database or user input is escaped with `htmlspecialchars`. |
| Cross-site request forgery (CSRF) | Forms on the newer pages (users, roles, university data, curriculum, lecturer evidence) carry a secret per-session **token**; requests without it are refused. |
| Stolen passwords | The local admin's password is a **bcrypt** hash. University passwords are never copied into our system; they're checked against the university's records. |
| Session fixation | The session id is **regenerated** at sign-in. |
| Unauthorised access | Server-side permission checks on every page; scoped queries; deactivation. |
| Damaging the university's records | The connection to the university database is **read-only, enforced by the database**. |
| Leaking personal data | HR, payroll and admissions data are never loaded. The university dump and the file of demo accounts are excluded from git. The guides contain no real names. |
| Secrets in code | Credentials and API keys live in `.env`, not in the code. |

**Weaknesses to admit if asked** (Part 9 lists them):
- Some older admin forms (skills, jobs, CBC levels) still delete through plain links without CSRF tokens.
- There's no sign-in rate limiting yet.
- Development sign-in mode exists (5.5).

### 5.5 Sign-in design

- **Students sign in with their registration number** and portal password. **Staff sign in with their
  admin-system username** (or email) and password. The system checks the details against the
  university's login records and never stores the university password.
- **One local emergency super admin** has its own password, so the system can be administered if the
  university connection is down.
- **How portal passwords are stored:**
  - **Format:** ASP.NET membership: a salted hash, base64-encoded.
  - **What the code does:** it implements the standard ASP.NET verification methods.
  - **What's unconfirmed:** which exact method the university's server uses can't be confirmed from the
    data alone. It needs the university's IT team, or one known test password.
- **Development mode, until that's confirmed:** `UNI_AUTH_MODE=dev` lets any synced university account
  sign in with a shared development password, so the system can be demonstrated. It's off by default, is
  clearly documented, and must never be on in production.

**What to say:** "Students use the same login they already have. Because I won't guess at a
security-sensitive detail, the exact hash check is waiting on confirmation from the university's IT
team. For demonstration I use a development mode that's switched off in production."

- **Messages are specific but safe:**
  - Wrong details give "Those details don't match", which doesn't reveal which part was wrong.
  - A valid student whose programme isn't in use gets "your programme isn't using this system yet".
  - A deactivated account is told so.

### 5.6 Privacy and ethics (likely question, especially from non-technical examiners)

- **Data minimisation:** only the data needed for profiling is copied: name, registration number,
  programme, year and results. Date of birth, religion, nationality, phone numbers, photos, HR and
  payroll records are in the university's dump but are never loaded.
- **Least privilege:** faculty-scoped access.
- **Transparency:** students can see exactly how every number was produced.
- **No automated decisions about people:** the system advises students and informs staff. It doesn't
  admit, reject, rank for awards or grade anyone.
- **Law:** Uganda's **Data Protection and Privacy Act, 2019** requires lawful, minimal and secure
  processing of personal data. Production use should go through the university's data protection
  officer, with a data-sharing agreement.

---

## Part 6: Demo script

**Before you start:** see the checklist in Appendix A, and have **`docs/demo_accounts.local.md`** open
for the accounts. Use one browser window for the admin and a private window for the student and staff
accounts.

Total: about 12–15 minutes. For each screen, **say the "what", show it, then say the "why"**.

### Scene 1: Sign-in (30 seconds)
- **Show:** the sign-in page. Point to "Email or registration number".
- **Say:** "Nobody registers here. Students use their registration number and existing portal login.
  The system checks the details with the university's records."

### Scene 2: Admin overview (2 minutes)
Sign in as the **super admin**.
- **Show:** the top figures. Students 608, job-ready about 175 of the 342 with a profile, average best
  match, profile coverage, jobs, framework.
- **Say:** "These are real students from the university's IT programmes. Students with no results yet
  are excluded from the averages, because they have nothing to score."
- **Show:** *Top 15 students by best job match*, with the 70% line.
- **Show:** *Needs attention*, e.g. course units students have taken that aren't mapped yet, and jobs
  with no detected skills.
- **Say:** "The system tells administrators what to fix. Data quality is visible, not hidden."
- **Show:** competency by category next to in-demand skills. Hover a bar.
- **Say:** "On the left is what our students are good at; on the right is what employers ask for."

### Scene 3: University data (1.5 minutes)
Open **Administration → University data**.
- **Show:** sync status (source `campus_dynamics`, read-only), then faculties → programmes, with the 5 IT
  programmes switched on.
- **Say:** "All faculties and programmes come from the university's system. We switch on the programmes
  that use this system. For the case study, that's the 5 IT programmes in Science and Technology. *Sync
  now* refreshes everything. In production it runs nightly."
- **Say (optional):** "The university's system has no academic departments, so the system follows its
  real structure: faculties and programmes."

### Scene 4: Curriculum mapping (2 minutes)
Open **Curriculum**. Bachelor of Information Technology is selected.
- **Show:** units grouped by year and semester with skill tags. Open *BAIT 2106 Web Scripting Using PHP*.
- **Say:** "This is the bridge between grades and skills. The university's records don't describe what
  each unit teaches, so the system drafts a mapping from the course names and the faculty reviews it.
  When I save, every student who took this unit is recalculated."
- **Show:** "This unit is in N programmes" (shared units), and the "skills not taught" warning if shown.

### Scene 5: A student's view (3–4 minutes). The heart of the demo
In a private window, sign in as the **strong Year 3 student**.
- **Overview:**
  - **Show:** CBC level (Proficient), average skill level, ready-to-apply count, top matches.
- **My skills:**
  - **Show:** open **MySQL**.
  - **Say:** "88 comes from these two database units, with marks of 90 and 86, weighted equally because
    both are 4-credit units that teach MySQL strongly." *(This is the worked example from 3.2, on screen.)*
- **Job matches:**
  - **Show:** the filter chips. Open a job and point to the score split and the requirements table with
    the 60 marker.
- **Then a student with gaps:** sign in as the **Year 1 student with gaps**. Open a lower-scoring job.
  - **Show:** "How to close the gaps": *Course unit · Year 2* recommendations from their own programme.
  - **Say:** "The first recommendation is a unit the student will take anyway, from their real
    curriculum, so they know when the gap will close. Self-study links come second."
- **Optional:** the **new student with no results**.
  - **Say:** "Before any results exist there's no profile. The system says so honestly instead of
    inventing one."
- **My report:**
  - **Show:** *Print or save as PDF*.

### Scene 6: Lecturer evidence (1.5 minutes)
In a private window, sign in as the **staff account** (Lecturer role by default).
- **Show:** the sidebar only has Overview, Students and Practical evidence.
- **Say:** "Staff see only their faculty's students and only what their role allows."
- **Show:** Evidence: pick a student, add an evidence level with the note "Internship supervisor report",
  then save.
- **Say:** "Lecturers don't enter marks; that's the portal's job. They add practical evidence that grades
  can't show. It counts for 25% and is labelled with their name."

### Scene 7: Roles and permissions (2 minutes)
As admin, open **Administration → Roles and permissions**.
- **Show:** the matrix: rows are permissions, columns are roles, with "Sees data for" per role.
- **Say:** "Access is role-based and editable. Each role has permissions and a data scope."
- **Show:** Administration → Users. Change the staff account to *Dean* and save.
- **Say:** "Assigning a role is one click. You can't remove the last super admin, can't change your own
  role, and only a super admin can create another."
- **Optional:** refresh the staff window. Curriculum and Analytics appear, limited to their faculty.
  **Change them back to Lecturer afterwards.**

### Scene 8: Close (30 seconds)
**Say:** "To summarise:
- The system reads the university's real records without changing them.
- It turns grades into an explainable competency profile and CBC level.
- It measures readiness against real jobs and tells students exactly what to work on and where in their
  own curriculum they'll learn it.
- It gives each staff role the right view.
- The next step is the university's API, replacing the database copy, and confirming the portal password
  check."

---

## Part 7: Integration with the university system

### 7.1 Where things stand

- All university data flows through **one file: `src/utils/University.php`**. It's written for the
  university's real schema (`campus_dynamics`). It:
  - lists faculties, programmes, course units and curricula
  - lists current students and their results for chosen programmes
  - lists staff accounts
  - checks a person's sign-in details
- **Today it reads a local copy** loaded from the university's dump by
  `src/scripts/load_university_dump.php` (academic tables only).
- **To go live,** there are three options:

| Option | How | When to choose it |
|---|---|---|
| **A. Direct read-only database access** | IT creates a read-only database user, ideally limited to views exposing only the needed columns. We point `UNI_DB_*` settings at it; the SQL already matches their schema. | Fastest if the university allows it |
| **B. An API between their database and our app** (the plan) | A small service on the university side exposes the data as JSON over HTTPS. `University.php` calls the API instead of SQL. | Best practice: the university keeps control, and access can be audited and revoked |
| **C. Scheduled export** | The university exports the academic tables nightly; the loader script reads them. | If neither A nor B is allowed. This is effectively what the dump does today. |

### 7.2 Proposed API design (for option B), matched to their real tables

All endpoints are **read-only (GET)** except the sign-in check, over **HTTPS**, and authenticated with
an **API key or token**. The responses are JSON.

| Endpoint | Comes from | Returns |
|---|---|---|
| `GET /api/faculties` | `acad_faculty` | code, name |
| `GET /api/programmes` | `acad_programme` | code, faculty_code, name, duration |
| `GET /api/courses` | `acad_course` | code, name, credit_units |
| `GET /api/curricula` | `acad_programmecourses` (+ placement from results) | programme_code, course_code, year, semester |
| `GET /api/students?programme=…&current=1` | `acad_student` + `acad_registration` | reg_no, name, email, programme_code, year_of_study |
| `GET /api/results?programme=…&since=…` | `acad_results` | reg_no, course_code, academic_year, semester, mark, grade |
| `GET /api/staff` | admin-system accounts + faculties | username, name, email, faculty_code |
| `POST /api/auth/verify` | portal / admin membership | `{identifier, password}` → `{valid, username}`, verified on their side, so hashes never leave |

**Design points to mention:**
- **`since=` gives incremental sync:** only results changed since the last sync are sent.
- **Pagination** for large lists.
- **Verification on their side** removes the password-hash question entirely.
- **Our side doesn't change:** `Sync`, `Competency`, `MatchingEngine` and every page stay the same. Only
  `University.php` changes.

### 7.3 What we still need from the university's IT team

- [ ] The password hash method for the portal (or the `/auth/verify` endpoint), so development sign-in
  can be switched off
- [ ] A staff list with full names, emails and faculty (the admin-system accounts only have usernames)
- [ ] A read-only account or the API, plus network access
- [ ] Confirmation of how retakes and supplementary exams are recorded
- [ ] A data-sharing agreement / data protection approval

### 7.4 How to answer "Is it really integrated?"

"Yes, with the university's real data. The system reads the university's actual database schema:
608 current IT students, their 5,881 results, and every faculty, programme and curriculum. Today it
reads a read-only copy taken from the university's database. Switching to a live connection or an API
means changing the connection in one file, and we're building that API with the IT team."

---

## Part 8: Question bank with model answers

Practise these out loud. Answers are written to be *said*: short first, detail if asked.

### A. Problem and motivation

**Q1. What problem does your system solve?**
Grades don't tell students or employers what a student can actually *do*. The system converts
academic results into a skills profile, aligns it with a CBC framework, and measures it against real
job requirements, so students know where they stand and what to work on, and the faculty sees where
the curriculum meets the market.

**Q2. Why is this important now?**
Uganda is moving towards competency-based learning, graduate unemployment is a real concern, and
the IT job market changes fast. Students and faculties need evidence-based feedback.

**Q3. Who benefits?**
- **Students:** they know their readiness and their next steps.
- **Lecturers:** they can record what grades miss.
- **Deans and heads of department:** they get evidence for curriculum review.
- **Career officers:** they see which roles students fit.
- **The university:** better graduate outcomes.

**Q4. Why didn't you just use the university portal?**
The portal records grades. It doesn't model skills, connect to the job market or analyse gaps. My
system complements it: it reads the portal's data and adds the competency and job-matching layer.

**Q5. What's new or original here?**
1. An **explainable** grade-to-competency model: every level traces back to specific units, marks and
   evidence.
2. **Curriculum-aware recommendations** from the student's real programme curriculum.
3. **Curriculum feedback for the faculty:** skills employers want that no unit teaches.
4. It **works on the university's real, messy data,** not a toy dataset.

### B. Design and architecture

**Q6. Describe your architecture.**
A web application with a three-layer structure. Pages display data; classes like `Competency`,
`MatchingEngine` and `Sync` hold the logic; MySQL stores it. Two outside sources feed it: the
university's records through a read-only connector, and job postings through the JSearch API.

**Q7. Why PHP and MySQL?**
The university's own system is MySQL, so integration is natural. Both run on low-cost hosting, are
widely known, and prepared statements give strong protection against SQL injection.

**Q8. Why does the system keep a copy of university data instead of querying it live?**
Speed, resilience and joining. Dashboards join many tables, which is slow and heavy against a remote
system. If the university system is down, analytics still work. And our own data (the mapping, evidence
and matches) has to join with theirs. The copy is one-way and fully rebuildable.

**Q9. How do you make sure you never change the university's data?**
It's enforced by the database: the connection is opened in read-only mode, and any write is rejected by
the database itself. I tested it. The sync only ever copies *from* the university *to* us.

**Q10. What happens if the university system is down?**
Staff can still use dashboards on the last synced data. University users can't sign in until it's back,
and they get a clear "university system unavailable" message. The local emergency admin can still sign
in, and a failed sync is recorded with its error.

**Q11. How does the system scale?**
- **Today:** 608 students × 20 jobs = 12,160 match scores. A full sync takes about 5 seconds, and
  scoring every student against 10 new jobs about half a second. It was about 3 minutes until matching
  was rewritten to load the data once and score in memory, with identical results (checked pair by
  pair). Recalculation happens only on sync or edits, never on
  page views, so pages load in well under a second.
- **At university scale:** incremental sync (only changed results) and running the sync as a background
  job overnight.
- **Already done:** the dashboard originally drew one bar per student, which froze at 608 students. It
  now shows the top 15, with a link to the full list.

**Q12. Why is "Sync" separate from "Competency" and "Matching"?**
Single responsibility. Sync only moves data, Competency only turns marks into levels, Matching only
scores. Saving lecturer evidence recalculates one student without a sync.

### C. Algorithms

**Q13. How exactly is a skill level calculated?**
It's a weighted average of the student's best marks in the course units that build that skill. Each
mark is weighted by how central the skill is to the unit and by the unit's credit units. With lecturer
evidence, the final level is 75% academic and 25% evidence. *(Walk through the real MySQL example in 3.2:
90 and 86 → 88.)*

**Q14. Why the best mark?**
A passed retake shows the skill was eventually demonstrated. The university's system keeps one result
per student per course anyway.

**Q15. Why weight by credit units?**
Credit units reflect the amount of work in a unit, the same principle as CGPA.

**Q16. How is the job-readiness score computed?**
60% skills fit plus 40% description fit. *(Walk through the example in 3.6.)*

**Q17. Why 60/40? Isn't that arbitrary?**
It's a design parameter, set by reasoning, not learned from data. Structured requirements carry
importance weights, so they get more say. With outcome data (who got hired) these weights could be
tuned. That's future work.

**Q18. Why is 70% "ready", and 60 the gap threshold?**
60 is a C on the university's own grading scale, a sound pass. 70% for a whole job requires the
profile to fit the role well overall. Both are named constants.

**Q19. How do you decide what skills a job needs?**
Keyword detection over the description, with weight growing with mentions. It's a known limitation,
with a review screen planned.

**Q20. How do you know which skills a course unit teaches?**
The university's records have no course descriptions, so the system drafts a mapping from course
names. For example, "Web Scripting Using PHP" → PHP and "Data Communication and Networking" → TCP/IP and
DNS. The faculty then reviews it on the Curriculum page. It's honest about being a draft: the dashboard
counts units still unmapped (currently 89 that students have taken).

**Q21. Why not machine learning or AI?**
1. **Explainability:** every number can be followed by hand, which matters when advising students.
2. **Data:** machine learning needs labelled outcomes, such as who got hired, and those don't exist yet.
3. **Cost and maintenance.**

The best place to add it later is understanding job descriptions and course content.

**Q22. How do you prioritise gaps?**
Across the student's 10 best matches, each gap's impact is the job's weight for the skill times how far
below 60 the student is, summed. The highest comes first.

**Q23. How is the CBC level decided?**
It's the average skill level on the editable scale. Today among IT students with a profile: 24 Novice,
242 Intermediate, 76 Proficient.

**Q24. Some first-years score as high as finalists. Isn't that wrong?**
It reflects the job data more than the algorithm. Many collected postings ask mainly for
communication and problem solving, which first-years already show from their first-semester units.
For technical roles the system correctly shows first-years many gaps, with "coming up in Year 2"
recommendations. The fix is richer job requirements.

**Q25. What about skills no course unit teaches?**
The student can only show them through lecturer evidence. The Curriculum page flags these skills to
the faculty. If employers ask for them, that's a direct curriculum finding.

### D. Data

**Q26. Where does your data come from?**
From the university's own Student Information System, `campus_dynamics`. That's 14,124 students and
370,961 results in total, of which the 608 current students of the 5 IT programmes and their 5,881
results are used. Jobs are real postings from the JSearch API.

**Q27. What problems did you find in the real data?**
*(Pick 3 from the table in 4.2.)*
- The curriculum placed most units in Year 1 Semester 1, so I derive placement from when students
  actually took each unit.
- Almost no students have valid emails, so accounts are keyed by registration number.
- There are stray spaces and tabs in codes, duplicate portal accounts, and marks above 100.

All are handled in the one connector file.

**Q28. Why doesn't the system have departments?**
The university's system doesn't have academic departments: programmes belong directly to faculties.
I chose to follow the real structure rather than invent one. Staff are scoped by faculty, and
departments can be added later if the university records them.

**Q29. Why only IT students?**
The case study is the IT programmes, and the skills framework is IT-specific. The whole university's
structure is already loaded, so switching on another programme is one click. It would need its own
skills framework to be meaningful, though.

**Q30. Why do 266 students have no profile?**
They're mostly new students registered in 2025/26 who have no results yet. The system doesn't invent
a profile; one appears automatically after their first results are synced.

**Q31. Walk me through your database.**
Three groups: a mirror of university data, keyed by the university's own codes; our own data (skills,
CBC levels, the curriculum mapping, evidence, jobs, roles); and computed results. The key link is
`course_unit_skills`. *(Sketch: students → student_results → course_units → course_unit_skills →
skills ← job_skills ← jobs.)*

**Q32. How do you avoid duplicate data?**
Unique keys on natural identifiers, plus upserts in the sync. One real case was fixed: the match-scores
table originally lacked a unique key, so re-matching created duplicates.

### E. Security and privacy

**Q33. How do you protect against SQL injection?**
Every query that uses input goes through PDO prepared statements with emulation off.

**Q34. How are passwords handled?**
University passwords are never copied into our system; they're checked against the university's login
records. The local admin's password is a bcrypt hash. Sessions are regenerated at sign-in.

**Q35. The portal password check isn't confirmed. Isn't that a problem?**
It's a deliberate choice not to guess at a security-sensitive detail. The portal uses salted ASP.NET
membership hashes, and the code implements the standard verification methods. Which one the server uses
needs confirmation from the IT team, or better, a verification endpoint on their side. Meanwhile a
clearly documented development mode allows demonstration and is never enabled in production.

**Q36. What stops a lecturer seeing another faculty's students?**
Their role's scope adds a faculty condition to every student query, and every page checks permissions
on the server. Write actions recheck the scope.

**Q37. How do you protect student privacy?**
- **Data minimisation:** HR, payroll, dates of birth and the like are never loaded.
- **Least privilege:** faculty scope.
- **Transparency:** students see how their numbers are made.
- **No automated decisions.**
- **Real identifiers stay out of the repository and documents.**

Deployment should follow the Data Protection and Privacy Act 2019.

**Q38. Could a user give themselves more power?**
No. You can't change your own role. Only a super admin can grant Super admin. The last super admin
can't be removed. Changes need the permission and a valid security token.

### F. Roles

**Q39. Explain your access control model.**
Role-based access control with data scope. Permissions decide what you can do; scope (whole university
or faculty) decides whose data you see.

**Q40. Where do staff accounts come from?**
From the university's admin system: 47 accounts with their faculty. They start as Lecturers, and a super
admin assigns Dean or other roles. The university's records only hold usernames for these accounts, so
a full staff list with names is one of the things requested from IT.

### G. Testing and evaluation

**Q41. How did you test the system?**
- **End-to-end tests against the running app with real accounts,** for every role: sign-in by
  registration number and staff username, wrong passwords, programmes not in use, and deactivated
  accounts.
- **Every page checked for errors** with real data.
- **Algorithm checks by hand:** the real MySQL example (90 and 86 → 88) matches the stored value.
- **Data integrity:** exactly one score per student per job (12,160).
- **Security:** read-only enforcement, CSRF rejection, and permission and scope checks.
- **Testing on real data exposed real bugs that I fixed:**
  - the scoring flaw
  - duplicate scores
  - a dashboard that froze at 608 students
  - stray characters in usernames
  - grades longer than the column allowed

*(Be honest: there's no automated test suite yet. That's first in future work.)*

**Q42. How do you know the scores are meaningful?**
They're *correct* (they follow the documented formulas, checked by hand) and *sensible* (strong
students score higher on jobs needing their strong skills). Showing they're *predictive* needs outcome
data: compare readiness with internship and employment results once the system is in use.

**Q43. Did users evaluate it?**
*(Answer truthfully. If no formal study was done:)* "The system was reviewed with the project owner
throughout development. A formal usability evaluation with students and lecturers is planned for the
pilot."

### H. Integration and deployment

**Q44. How will you connect to the live university system?**
Through an API on the university's side: read-only endpoints for structure, students, staff and results,
plus a sign-in check. Only the connector file changes. *(Part 7.2.)*

**Q45. Why an API instead of direct database access?**
The university keeps control of what's exposed, nothing reaches into their database directly, access
can be logged and revoked, and their schema can change without breaking us.

**Q46. How is the system deployed?**
On a Linux VPS with Nginx and PHP-FPM, deployed automatically through GitHub Actions. The sync can be
scheduled nightly with cron.

**Q47. What happens when the curriculum changes?**
New units arrive through the sync and show as "Not mapped" on the Curriculum page. Once mapped, every
student who took them is recalculated.

### I. Broader questions

**Q48. What were the biggest challenges?**
1. **Designing the boundary with the university system:** read, don't duplicate.
2. **Making the score explainable.**
3. **Real data:** it broke assumptions that clean test data never would. There were no departments, no
   emails, a misleading curriculum table and stray characters in codes.

**Q49. What would you do differently?**
Get access to the real data structure earlier, since it changed the design (no departments, keys by
registration number). And build automated tests from the start.

**Q50. How could this be extended?**
*(Pick two or three from Part 9.2.)*

**Q51. How does this relate to CBC?**
CBC focuses on what learners can *do*. The system expresses achievement as competencies with levels,
links each competency to the learning that builds it (course units), and accepts practical evidence
alongside assessment, which is a core CBC idea.

**Q52. Could another university use this?**
Yes. Structure, skills, CBC levels, the mapping and roles are all data. Another university needs its own
connector (one file) and its own mapping.

---

## Part 9: Limitations and future work

**Naming limitations yourself, before an examiner does, shows maturity.**

### 9.1 Current limitations (honest list)

1. **The curriculum mapping is a draft** generated from course names. 89 units students have taken are
   still unmapped, and some drafts need correcting (e.g. a unit called "…Management" was given
   Communication). Faculty review is required.
2. **Job skill detection is keyword-based.** 7 of 20 jobs have no detected skills.
3. **The portal password check is unconfirmed,** so a development sign-in mode is used for demonstration.
4. **The university data is a copy** loaded from a dump, until the API is built.
5. **There's no lecturer list with names.** Staff accounts have usernames only.
6. **Only 273 of 608 current IT students have a portal account** in this copy, so the rest can't sign in
   yet.
7. **Parameters are reasoned, not learned:** 60/40, 25% evidence, the gap at 60, ready at 70.
8. **No automated test suite.**
9. **No history or trends yet,** even though results carry year and semester.
10. **Some older admin forms lack CSRF tokens.** There's also no sign-in rate limiting.
11. **One skills framework (IT).** Other faculties would need their own.
12. **No departments,** because the university's system doesn't have them.

### 9.2 Future work

- The **university API** and a confirmed portal sign-in (or single sign-on).
- **Faculty review tools** for the curriculum mapping, and **language-processing analysis** of job
  descriptions and course outlines.
- A **job requirements review** screen for career officers.
- **Automated tests** for the competency and matching engines.
- **Progress over time:** profile snapshots per semester.
- **Outcome tracking** (internships and employment) to validate and tune the weights.
- **Per-faculty skill frameworks,** so other faculties' programmes can be switched on meaningfully.
- An **employer view** and **notifications**.

---

## Part 10: Handling difficult moments

**If you don't know an answer:**
"That's a good question. I haven't tested that specifically. My expectation is [reasoned guess], and
I'd verify it by [how]." Never invent a number.

**If an examiner says the approach is too simple:**
"It's deliberately simple and explainable. Every score can be traced by hand, which matters when
advising students. And it works on the university's real, messy data, which is harder than it sounds."

**If they find a bug during the demo:**
Stay calm and narrate. "Thank you, that's a useful catch. What should happen is X." Then move on.

**If asked "so it's not connected live?":**
"It runs on the university's real data, read-only, with the university's real schema. What remains is
the live API connection instead of the copy, and that changes one file."

**If they ask about the draft mapping being wrong for a unit:**
"Yes, that one's a draft generated from the course name. Here's how a faculty member corrects it."
Then fix it live on the Curriculum page and save. Turning a weakness into a demonstration is very
effective.

**If they ask about something someone else built:**
"Charts use Chart.js, styling uses Bootstrap, and jobs come from the JSearch API. My work is the
competency model, matching, gap analysis, the university integration and data cleaning, and access
control."

**Pacing:** pause after each scene, point at the screen, look at the examiners, answer the question
asked, then stop.

---

## Part 11: Numbers to remember

| What | Value |
|---|---|
| Match score | 60% skills fit + 40% description fit |
| Ready to apply | 70% or more (Close: 50–69%; Stretch: below 50%) |
| Gap threshold | A required skill below 60 (a C on the university scale) |
| Evidence share | 25% (academic 75%) |
| CBC levels | Novice 0–40 · Intermediate 41–70 · Proficient 71–90 · Expert 91–100 |
| Mapping weights | Core 1.0 · Strong 0.75 · Moderate 0.5 · Light 0.25 |
| Job skill weight | 0.3 + 0.1 per mention, maximum 1.0 |
| University source | 14,124 students · 370,961 results · 6 faculties · 97 programmes · 6,352 course units |
| Brought in | 5 IT programmes · 608 current students · 5,881 results · 47 staff accounts |
| Profiles | 342 students (266 have no results yet) |
| CBC spread | 24 Novice · 242 Intermediate · 76 Proficient · 0 Expert |
| Job-ready | about 175 of 342 students with a profile |
| Match scores | 12,160 (608 × 20 jobs) |
| Jobs | 20 tracked, 13 with detected skills |
| Curriculum mapping | 300 units drafted, 89 taken units still unmapped |
| Sync / scoring time | about 5 seconds for a full sync; about 0.5 s to score 10 new jobs |
| Roles | 5 built-in + custom; 11 permissions; 2 scopes (whole university, faculty) |
| Grading scale | A 80+ · B+ 75 · B 70 · C+ 65 · C 60 · D+ 55 · D 50 · F below 50 (same as the university's) |

*These figures change after each sync and mapping review. Re-check them the day before the defence (the
walkthrough guide shows how).*

---

## Part 12: Glossary

- **CBC (Competency-Based Curriculum):** education that defines success by what learners can *do*.
- **Competency profile:** a student's level (0–100) in each skill of the framework.
- **Curriculum mapping:** the link from each course unit to the skills it builds, with weights.
- **SIS (Student Information System):** the university's system of record (`campus_dynamics`).
- **System of record / single source of truth:** the one authoritative place a piece of data lives.
- **Sync:** copying data from the SIS into our system, one way.
- **Connector:** the single module (`University.php`) that talks to the SIS.
- **Current students:** students registered in either of the two most recent academic years.
- **Upsert:** insert a row, or update it if it already exists.
- **Hybrid matching:** combining weighted requirements and description keywords.
- **Gap:** a required skill where the student is below 60.
- **RBAC:** role-based access control.
- **Scope:** whose data a role can see: whole university or faculty.
- **Least privilege:** each person gets only the access their job needs.
- **CSRF / XSS / SQL injection:** web attacks prevented by tokens, output escaping and prepared
  statements.
- **ASP.NET membership hash:** how the university portal stores passwords: salted and hashed.
- **Migration:** a script that upgrades an existing database's structure safely.

---

## Appendix A: Setup and demo checklist

### Accounts
See **`docs/demo_accounts.local.md`** (local only; contains real identifiers).

### Starting the app locally

```bash
cd ~/final_years/jenn
php -S localhost:8000 -t public public/router.php
# open http://localhost:8000
```

Rebuilding everything from scratch (only if something is broken; takes a few minutes):

```bash
php src/scripts/load_university_dump.php passbook.sql   # local read-only copy of the university data
php src/scripts/seed.php                                # admin, sync, switch on IT programmes, draft mapping
```

### The day before
- [ ] Run a sync (Administration → University data → Sync now) and confirm "Up to date".
- [ ] Sign in once with every account in `demo_accounts.local.md`.
- [ ] Check that the staff demo account has the **Lecturer** role (reset it if you practised promotions).
- [ ] Deactivate the leftover test admin `admin@test.com` on the Users page.
- [ ] Review and fix the mapping of the units you'll show (e.g. BAIT 2106, BAIT 1203, BAIT 2104).
- [ ] Re-check the figures in Part 11.
- [ ] Rehearse the demo script twice, timed.
- [ ] Light theme for the projector (user menu → Theme → Light).

### On the day
- [ ] Admin in one window; a **private window** for the student and staff accounts.
- [ ] Zoom the browser to 110–125%.
- [ ] Close other tabs and notifications.

---

## Appendix B: Where everything lives in the code

| Feature | File |
|---|---|
| Skill level calculation (weighted average, 25% evidence, CBC level) | `src/utils/Competency.php` |
| Match score, gap threshold, gap analysis | `src/utils/MatchingEngine.php` |
| Student skills with sources, growth areas, recommendations | `src/utils/StudentProfile.php` |
| Connection to the university (real schema, read-only, data cleaning) | `src/utils/University.php` |
| Sync from the university | `src/utils/Sync.php` |
| Loading the university dump (academic tables only) | `src/scripts/load_university_dump.php` |
| Draft curriculum mapping from course names | `src/scripts/suggest_mapping.php` |
| Job fetching and skill detection | `src/utils/JobApi.php` |
| Sign-in, roles, permissions, scope, CSRF | `src/utils/auth.php` |
| Navbar shown per permission | `src/views/layout/header.php` |
| Admin overview and student overview | `public/dashboard.php` |
| University data / curriculum mapping screens | `public/admin/university.php`, `public/admin/curriculum.php` |
| Roles matrix / users | `public/admin/roles.php`, `public/admin/users.php` |
| Lecturer evidence | `public/lecturer/assess.php` |
| Database structure and upgrades | `database/schema.sql`, `database/migrations/` |
| Deployment | `docs/deployment_guide.md`, `.github/workflows/deploy.yml` |

**Tip:** read `Competency.php`, `MatchingEngine.php` and `University.php` once before the defence. Being
able to point at the formula and at the data-cleaning rules in code is very convincing.
