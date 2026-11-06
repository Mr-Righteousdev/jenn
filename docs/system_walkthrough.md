# Hands-on Walkthrough: How the Whole System Works, and Whether It Solves the Problem

This is a guided tour you do yourself, at your own pace, with the app running. You'll follow real data
from the university's database all the way to a student's job match, look under the hood at each step,
try a few experiments, and finish by judging whether the system solves the problem it was built for.

**Time:** about 2–3 hours if you do every step. Each stage stands alone, so you can stop and resume.
**You'll need:**
- the app running
- a terminal for a few database commands
- **`docs/demo_accounts.local.md`** open (it has the real accounts; it's not in git)

Each stage has:
- **Do:** what to click or run
- **Look for:** what you should see
- **What's happening:** the mechanism behind it
- **Why it matters:** how it serves the goal

> Tip: keep a notebook. Write down anything that surprises you or that you can't explain. Those are
> exactly the questions an examiner will ask, and Part 8 of `docs/defence_guide.md` probably answers them.

---

## Contents

- [Part 0: The problem and the end result we're aiming for](#part-0-the-problem-and-the-end-result-were-aiming-for)
- [Part 1: Get set up](#part-1-get-set-up)
- [Stage A: Look at the raw university data](#stage-a-look-at-the-raw-university-data)
- [Stage B: Bring it in with a sync](#stage-b-bring-it-in-with-a-sync)
- [Stage C: The curriculum mapping, the bridge from grades to skills](#stage-c-the-curriculum-mapping-the-bridge-from-grades-to-skills)
- [Stage D: Follow one student's marks into skill levels](#stage-d-follow-one-students-marks-into-skill-levels)
- [Stage E: The CBC level](#stage-e-the-cbc-level)
- [Stage F: The job market side](#stage-f-the-job-market-side)
- [Stage G: Matching a student to a job, checked by hand](#stage-g-matching-a-student-to-a-job-checked-by-hand)
- [Stage H: Gaps and what to do about them](#stage-h-gaps-and-what-to-do-about-them)
- [Stage I: Lecturer evidence, the 25%](#stage-i-lecturer-evidence-the-25)
- [Stage J: What staff see, and what decisions it supports](#stage-j-what-staff-see-and-what-decisions-it-supports)
- [Stage K: Roles, permissions and scope](#stage-k-roles-permissions-and-scope)
- [Stage L: Safety and honesty checks](#stage-l-safety-and-honesty-checks)
- [Stage M: A short code-reading tour](#stage-m-a-short-code-reading-tour)
- [Part 2: Is the problem solved? A scorecard](#part-2-is-the-problem-solved-a-scorecard)
- [Part 3: Things that may look wrong, and why](#part-3-things-that-may-look-wrong-and-why)
- [Part 4: Reset after experimenting](#part-4-reset-after-experimenting)

---

## Part 0: The problem and the end result we're aiming for

### The problem (from the project brief)

> Universities focus on grades, not practical skills or job readiness.

In practice that means:
1. A student finishes with a transcript of grades but can't say which *skills* they have or how strong
   they are.
2. Nobody can tell a student how ready they are for a *specific* job, or what's missing.
3. The faculty can't easily see whether its curriculum builds the skills employers ask for.
4. Grades alone miss practical abilities: internships, projects, soft skills.

### The end result the system is meant to achieve

When the system is in full use, this is what each person gets:

| Who | What they can do that they couldn't before |
|---|---|
| **A student** | Open one page and see their skill levels (not just grades), their CBC level, which real jobs they're ready for, which skills hold them back, and which upcoming course units will close those gaps |
| **A lecturer** | Record practical evidence (internship, project) that counts towards a student's competency, so ability beyond exams is recognised |
| **A dean / head of department** | See how their faculty's students are tracking against the job market, which skills are weak across the cohort, and which skills employers want that no course unit teaches |
| **A career officer** | Manage job postings and see which roles the students actually fit |
| **The university** | Evidence for curriculum review and graduate readiness, built on its own records, with no extra data entry |

At the end of this walkthrough (Part 2) you'll check each of these against what you saw.

### The journey you'll follow

```
University database ──sync──► our copy ──curriculum mapping──► skill levels ──► CBC level
                                                                    │
                     job postings ──skill detection──► job requirements
                                                                    │
                                                          match score + gaps + recommendations
                                                                    │
                                                   student pages · staff dashboards · reports
```

---

## Part 1: Get set up

**Do:**
```bash
cd ~/final_years/jenn
php -S localhost:8000 -t public public/router.php
```
Open http://localhost:8000. In a second terminal you'll run database commands. The database password
for user `jm` is in your `.env`:
```bash
mariadb -u jm -p jenn_db            # our system's database
mariadb -u jm -p campus_dynamics    # the read-only copy of the university's database
```

Open `docs/demo_accounts.local.md`. Throughout this guide:
- `<STRONG>` means the strong Year 3 student's registration number.
- `<GAPS>` means the Year 1 student with gaps.
- `<NEW>` means the new student with no results.
- `<STAFF>` means the staff username.

Use **one normal browser window for the admin** and **a private window for everyone else**, so the
sign-ins don't overwrite each other.

---

## Stage A: Look at the raw university data

**Goal:** see what the university's records look like before our system touches them.

**Do:** in the terminal, look at the strong student's raw results in the university's own tables:
```sql
-- in: mariadb -u jm -p campus_dynamics
SET @reg = '<STRONG>';
SELECT TRIM(courseid) AS course, score, grade, TRIM(acad) AS year, semester
FROM acad_results WHERE TRIM(regno) = @reg ORDER BY TRIM(acad), semester;

SELECT TRIM(courseID) AS code, courseName, CreditUnit FROM acad_course
WHERE TRIM(courseID) IN ('BAIT 1203', 'BAIT 2104', 'BAIT 2106');
```

**Look for:**
- Grades and marks per course unit, academic year and semester. Exactly what a transcript shows.
- Course names and credit units, but **no description of what skills a course teaches.**

**What's happening:** this is the university's Student Information System. It's the *system of record*:
it owns this data. Our system will only ever **read** it.

**Why it matters:** this is "the problem" in raw form. There are grades, but nothing says "this student
can build a MySQL database to level 88". Everything the system does next turns this into skills.

**Optional curiosity checks** (they show why the data needed cleaning):
```sql
SELECT COUNT(*) FROM acad_results WHERE score > 100;                    -- marks that can't be right
SELECT study_year, semester, COUNT(*) FROM acad_programmecourses
WHERE TRIM(progcode) = '02305' GROUP BY 1, 2;                           -- most units filed under Year 1 Sem 1
```

---

## Stage B: Bring it in with a sync

**Goal:** see how university data reaches our system, and confirm it never goes the other way.

**Do:**
1. Sign in as the **admin** (`admin@jenn.edu`).
2. Go to **Administration → University data**.
3. Read the status card: *source `localhost/campus_dynamics` · read-only*.
4. Expand **Faculty of Science and Technology**. Note the 5 IT programmes switched **In use**.
5. Click **Sync now**. Wait a few seconds and read the result message.

**Look for:**
- All 6 faculties and 97 programmes are listed, even though only 5 programmes are in use.
- The counts: students, staff accounts, results, course units, profiles.
- Each programme shows "N unmapped". Keep that in mind for Stage C.

**What's happening** (`src/utils/Sync.php` with `src/utils/University.php`):
1. **Structure for the whole university:** faculties, programmes, course units and each programme's
   curriculum. A unit's year and semester are worked out from when students actually took it.
2. **For programmes in use:** students registered in the two most recent academic years, and their results.
3. **Staff accounts** from the university's admin system.
4. **Then everyone's competency profile and every job match is recalculated.**

**Look under the hood:**
```sql
-- in: mariadb -u jm -p jenn_db
SELECT started_at, finished_at, status, summary FROM sync_log ORDER BY sync_id DESC LIMIT 3;
SET @reg = '<STRONG>';
SELECT r.course_code, r.course_name, r.mark, r.grade, r.academic_year
FROM student_results r JOIN students s USING(student_id) WHERE s.registration_no = @reg;
```
The results match what you saw in Stage A. That's our copy.

**Why it matters:**
- **No data entry:** nobody re-types results.
- **One source of truth:** the university's records stay authoritative.
- **Read-only:** the system can't damage them. You'll prove that in Stage L.

**Experiment (optional):** switch a non-IT programme **On**, sync, and see its students appear. Then
switch it **Off** again. It keeps already-synced students, so to remove them you'd re-run the setup in
Part 4.

---

## Stage C: The curriculum mapping, the bridge from grades to skills

**Goal:** understand the single piece of knowledge that turns grades into skills.

**Do:**
1. As admin, open **Curriculum**. *Bachelor of Information Technology* is selected.
2. Scroll through Year 1 → Year 3. Each unit shows the skills it builds as tags.
3. Click **BAIT 2106 Web Scripting Using PHP** (the pencil on the right). The editor opens.
4. Note: PHP is set to *Core*, MySQL to *Light*.

**Look for:**
- Figures at the top: *Units students take, mapped*, *Skills the curriculum builds*, *Skills not taught*.
- Units marked **Not mapped**. Their marks currently count towards nothing.
- "This unit is in N programmes". A mapping belongs to the unit, so it applies everywhere the unit is
  taught.
- Some drafts that are clearly wrong. For example, a unit with "Management" in its name was given
  Communication.

**What's happening:**
- **The university's data has no course descriptions,** so `src/scripts/suggest_mapping.php` drafted
  mappings from the course *names*.
- **The weights** are Core 1.0, Strong 0.75, Moderate 0.5 and Light 0.25: how central the skill is to
  the unit.
- **Saving** recalculates every student who took that unit.

**Why it matters:**
- **This is where the faculty's knowledge enters the system.** The quality of every skill level depends
  on this mapping.
- **"Skills not taught" is curriculum feedback in its own right:** employers ask for skills that no unit
  in the programme builds.

**Experiment (do this one, it's the most illuminating):**
1. Write down the strong student's **MySQL** level. Sign in as `<STRONG>` in the private window → **My
   skills** → MySQL. It should be 88.
2. As admin, open **BAIT 1203 Principles of Database Programming** and change MySQL from *Strong* to
   *Core*. Save. Read the message: "N student profiles recalculated".
3. Refresh the student's **My skills**. MySQL has changed (BAIT 1203 now weighs more).
4. **Change it back to Strong** and save. The level returns to 88.

You've just seen the mapping drive the profile, live.

---

## Stage D: Follow one student's marks into skill levels

**Goal:** prove to yourself that a skill level is exactly the formula, nothing hidden.

**Do:** as `<STRONG>`, open **My skills** and expand **MySQL**.

**Look for:** the units that produced it (BAIT 1203 and BAIT 2104), the marks (90 and 86), "how much it
builds this" (strong), and the level 88.

**Check it by hand with the formula:**
```
level = Σ(mark × weight × credit units) ÷ Σ(weight × credit units)
      = (90×0.75×4 + 86×0.75×4) ÷ (0.75×4 + 0.75×4)
      = (270 + 258) ÷ 6 = 88
```

**Check it with the database:**
```sql
-- in: mariadb -u jm -p jenn_db
SET @reg = '<STRONG>';
SELECT r.course_code, cu.name, r.mark, c.weight, cu.credit_units,
       c.weight * cu.credit_units AS w, r.mark * c.weight * cu.credit_units AS mw
FROM student_results r
JOIN students s USING(student_id)
JOIN course_unit_skills c ON c.course_code = r.course_code
JOIN course_units cu ON cu.code = r.course_code
JOIN skills sk ON sk.skill_id = c.skill_id
WHERE s.registration_no = @reg AND sk.skill_name = 'MySQL';
```
Add up `mw`, divide by the sum of `w`, and compare with what's stored:
```sql
SELECT ss.skill_level, ss.academic_level, ss.evidence_level
FROM student_skills ss JOIN students s USING(student_id) JOIN skills k USING(skill_id)
WHERE s.registration_no = @reg AND k.skill_name = 'MySQL';
```

**What's happening:** `src/utils/Competency.php` runs this exact calculation for every student and every
skill after each sync or mapping change, and stores the result in `student_skills`.

**Why it matters:**
- **Problem point 1 (no visible skills) is solved here:** the transcript has become a skill profile.
- **It's explainable:** a student, a lecturer or an examiner can trace every number.

**Try another skill:** repeat the SQL with `'C++'` or `'Problem Solving'` and check one by hand.

---

## Stage E: The CBC level

**Goal:** see how the overall level is decided and that the framework is editable.

**Do:**
1. As `<STRONG>`: **My skills** shows the CBC scale with a marker at their average level, e.g.
   *Proficient · Average level 82*.
2. As admin: **Framework → CBC levels** shows the four bands and how many students are in each.

**Look for:** the bands (Novice 0–40, Intermediate 41–70, Proficient 71–90, Expert 91–100) and the count
of students per band.

**What's happening:** the CBC level is the average of the student's skill levels, placed on the band
it falls in. Admins can rename levels or change ranges.

**Experiment (optional):** temporarily change *Proficient* to 75–90 and *Intermediate* to 41–74, then
run a sync. The counts shift. **Change them back afterwards** and sync again.

**Why it matters:** this is the "CBC-aligned" part of the project. Achievement is expressed as levels of
competence, not marks.

---

## Stage F: The job market side

**Goal:** see where job requirements come from, and their limits.

**Do:** as admin, open **Jobs**.
- Note the figures: jobs tracked, *with skills mapped*, *missing skills*.
- Click the eye icon on a job to read its description. Compare it with the **Mapped skills** tags.
- Turn on **Missing skills only** to see the postings with no detected skills.

**Look under the hood:**
```sql
SELECT j.job_id, j.title, GROUP_CONCAT(CONCAT(sk.skill_name, ' ', js.weight)) AS skills
FROM jobs j LEFT JOIN job_skills js USING(job_id) LEFT JOIN skills sk USING(skill_id)
GROUP BY j.job_id ORDER BY j.job_id;
```

**What's happening:** `src/utils/JobApi.php` scans each description for skill names. Each skill found
gets weight 0.3 + 0.1 per mention (maximum 1.0).

**Why it matters:**
- **This is the "market" half of the comparison.**
- **Be critical here:** count how many jobs have only one or two skills, or none. Thin job requirements
  are the biggest reason some scores look generous (see Part 3).

---

## Stage G: Matching a student to a job, checked by hand

**Goal:** understand the readiness score completely by recomputing one.

**Do:** as `<STRONG>`, open **Job matches**. The top job is selected. Note:
- the match score (e.g. 86%)
- **Skills fit** and **Description fit**
- the requirements table, with each skill's importance and the student's level

The job's id is in the address bar: `jobs.php?job_id=NN`.

**Recompute the skills fit:**
```sql
SET @reg = '<STRONG>'; SET @job = NN;    -- the job_id from the address bar
SELECT sk.skill_name, js.weight, COALESCE(ss.skill_level, 0) AS level,
       js.weight * COALESCE(ss.skill_level, 0) AS weighted
FROM job_skills js JOIN skills sk USING(skill_id)
LEFT JOIN student_skills ss ON ss.skill_id = js.skill_id
     AND ss.student_id = (SELECT student_id FROM students WHERE registration_no = @reg)
WHERE js.job_id = @job;

SELECT match_score, weighted_score, keyword_score FROM job_readiness
WHERE job_id = @job AND student_id = (SELECT student_id FROM students WHERE registration_no = @reg);
```
- **Skills fit** = sum of `weighted` ÷ sum of `weight`, which should equal `weighted_score`.
- **Match** = 0.6 × weighted_score + 0.4 × keyword_score, which should equal `match_score`.

*(Worked on the real data: a job needing C++ and JavaScript, both weight 0.4, with levels 92 and 83 →
(36.8 + 33.2) ÷ 0.8 = 87.5; then 0.6 × 87.5 + 0.4 × 83 = 85.7.)*

**What's happening:** `src/utils/MatchingEngine.php` computes:
- the **skills fit**: a weighted average over the job's required skills, where missing skills count as 0
- the **description fit**: the student's average level over every framework skill the job text mentions

Scores are stored for every student × job (12,160 of them), so pages just read them.

**Why it matters:** **problem point 2 (readiness for a specific job) is solved here,** with a number the
student can understand and trust.

---

## Stage H: Gaps and what to do about them

**Goal:** see the system turn a score into advice.

**Do:** in the private window, sign in as `<GAPS>` (a Year 1 student).
1. **Overview → Skills to grow next.** Read the list: each skill, how many top matches need it, and the
   *Coming up* course unit.
2. **Job matches:** choose a job in the *Stretch* or *Close* filter. Look at:
   - the requirements table: **Gap of N** tags, and the marker at 60
   - **How to close the gaps:** a *Course unit* from their own programme with its year and semester,
     then self-study links
3. **My report → Print or save as PDF:** the development plan.

**What's happening:**
- **A gap** is any required skill below 60.
- **"Skills to grow next"** ranks gaps across the student's 10 best matches by impact: job weight ×
  (60 − level).
- **Course-unit recommendations** come from the student's real programme curriculum: units that build the
  skill and are still ahead of them.

**Why it matters:**
- **This is the most useful thing for a student:** it tells them *what to do*, not just where they
  stand.
- **The recommended course units are already on their timetable,** so the advice is free and accredited.

**Contrast:** sign in as `<NEW>` (no results yet). The pages show no profile and say why. The system
doesn't invent a profile from nothing.

---

## Stage I: Lecturer evidence, the 25%

**Goal:** see how practical ability beyond exams enters the profile.

**Do:**
1. In the private window, sign in as `<STAFF>` (Lecturer role).
2. Notice the sidebar: only **Overview, Students, Practical evidence**.
3. **Evidence** → search for `<STRONG>` → find **Communication**:
   - Note *From results* and *Current level*.
   - Enter **95** with the note "Internship supervisor report". **Save evidence.**
4. Sign in as `<STRONG>` → **My skills → Communication**. You'll see:
   - the new level
   - the line "= 75% of [results] + 25% of 95"
   - the evidence item with the lecturer's name and date

**Check by hand:** new level = 0.75 × (academic level) + 0.25 × 95.

**Undo it:** back as `<STAFF>`, clear the 95 (leave the box empty) and save. The level returns.

**What's happening:**
- **Evidence is stored separately** in `skill_evidence`, so it never overwrites results.
- **That student's profile and matches are recalculated** straight away.

**Why it matters:** **problem point 4 (grades miss practical ability) is addressed here,** with a clear
audit trail (who, when and why) and a capped influence of 25%.

---

## Stage J: What staff see, and what decisions it supports

**Goal:** look at the system through a dean's eyes.

**Do:** as admin, go to **Overview**, then **Analytics**. For each panel, ask yourself "what decision
could someone make from this?"

| Panel | Decision it supports |
|---|---|
| Job-ready count and average best match | Is the cohort ready? Is it improving after curriculum changes? |
| Top 15 students | Who to put forward for internships and placements |
| Average competency by category | Which skill areas are weak across the faculty |
| Most in-demand skills | What employers ask for, compared with the category chart |
| Students by CBC level | How the cohort is distributed; whether interventions are needed |
| Needs attention | What to fix in the data (unmapped units, jobs without skills) |
| Readiness by programme / year | Which programmes or years need support |
| Curriculum → Skills not taught | Concrete curriculum gaps against the market |

**Why it matters:** **problem point 3 (the faculty can't see if its curriculum builds market skills)**
is addressed here, particularly by the category-versus-demand comparison and "Skills not taught".

---

## Stage K: Roles, permissions and scope

**Goal:** see that each person gets the right view, and nothing more.

**Do:**
1. As admin: **Administration → Roles and permissions**. Read the matrix. Each role has ticked permissions and a
   **Sees data for** setting (whole university or their faculty).
2. **Administration → Users:** change `<STAFF>` from *Lecturer* to **Dean** and save.
3. In the staff window, refresh. **Curriculum** and **Analytics** appear, and the overview says *"How
   students in Faculty of Science and Technology…"*, limited to their faculty.
4. **Try to cheat:** still as the staff member, type `http://localhost:8000/admin/users.php` in the
   address bar. You get **"You don't have access to this page"**.
5. **Try the safety rules** as admin:
   - Try to change your own role: refused.
   - Try to deactivate yourself: refused.
6. **Deactivate** `<STAFF>`, refresh their window (they're signed out), then try to sign in (refused).
   **Reactivate** them and **set them back to Lecturer.**

**What's happening:**
- **Every page checks a permission on the server.** Hiding a menu link is only convenience.
- **Every student query adds the scope condition.**

**Why it matters:**
- **Privacy:** staff only see students in their area.
- **Safety:** nobody can lock the system out or give themselves power.

---

## Stage L: Safety and honesty checks

**Goal:** confirm the system protects the university's data and is honest about what it doesn't know.

**1. The university database really is read-only from our side.**
```bash
cd ~/final_years/jenn
php -r 'require "src/utils/University.php"; $m = new ReflectionMethod("University", "db"); $m->setAccessible(true);
try { $m->invoke(null)->exec("DELETE FROM acad_results"); echo "WRITE ALLOWED\n"; }
catch (Exception $e) { echo "Blocked: ", $e->getMessage(), "\n"; }'
```
You should see **Blocked: … READ ONLY transaction**. Nothing was deleted.

**2. Sign-in messages** (sign out first, then try each):
- **Wrong password:** "Those details don't match…" (it doesn't say which part was wrong).
- **A student from a programme not in use:** "your programme isn't using this system yet".

**3. Sign-in honesty:** check `.env`. `UNI_AUTH_MODE=dev` means university accounts use the shared
development password, because the portal's hash method isn't confirmed yet. That's fine for practice
and must be off in production.

**4. Data minimisation:** confirm the university copy has no HR or payroll tables:
```sql
-- in: mariadb -u jm -p campus_dynamics
SHOW TABLES;
```
Only academic and login tables are there.

**Why it matters:** **trust.** A system that handles students' records must protect them, and must not
pretend to know things it doesn't.

---

## Stage M: A short code-reading tour

Read these five files in this order. Each is short. Look for the specific thing listed.

| # | File | Look for |
|---|---|---|
| 1 | `src/utils/University.php` | `SET SESSION TRANSACTION READ ONLY`; the data-cleaning rules in the header comment; `programmeUnits()` deriving year and semester from results |
| 2 | `src/utils/Sync.php` | The order: structure → students → staff → results → `Competency::recompute()` |
| 3 | `src/utils/Competency.php` | The SQL `SUM(b.mark * cus.weight * cu.credit_units) / SUM(cus.weight * cu.credit_units)` and `EVIDENCE_SHARE = 0.25` |
| 4 | `src/utils/MatchingEngine.php` | `0.6` and `0.4` in `calculateReadiness()`; `GAP_THRESHOLD = 60` |
| 5 | `src/utils/auth.php` | `PERMISSIONS`, `can()`, `studentScope()` |

Then pick one page, e.g. `public/profile.php`, and notice it only *displays* what `StudentProfile.php`
returns. The logic and the interface are separate.

---

## Part 2: Is the problem solved? A scorecard

Fill this in from what you actually saw. The verdicts below are an honest starting point; change them if
your own walkthrough says otherwise.

### Against the problem

| Problem | Where you saw it addressed | Verdict | Honest caveat |
|---|---|---|---|
| 1. Students can't see their skills, only grades | Stage D: *My skills* turns results into levels per skill, each traceable to units and marks | **Solved** | Only as good as the curriculum mapping, which is still a draft for many units |
| 2. Nobody can say how ready a student is for a specific job, or what's missing | Stages G and H: a score per job, gaps per requirement, prioritised "skills to grow" | **Solved for jobs with good requirements** | Job requirements come from keyword detection; 7 of 20 jobs have none, and many have only one or two skills |
| 3. The faculty can't see if the curriculum builds market skills | Stages C and J: category vs demand, "Skills not taught", unmapped units | **Partly solved** | The insight is there, but it depends on reviewing the mapping and on richer job data |
| 4. Grades miss practical ability | Stage I: lecturer evidence counts for 25%, with an audit trail | **Solved (mechanism in place)** | Needs lecturers to actually record evidence; none has been entered for real yet |
| (implicit) No extra data entry, no duplicate records | Stages A and B: reads the university's system, read-only | **Solved** | Uses a copy of the database until the university's API is ready |

### Against the specific objectives

| Objective | Evidence | Met? |
|---|---|---|
| 1. Competency profile from results, with configurable mapping | Stages C and D | ✅ Yes |
| 2. Editable CBC scale | Stage E | ✅ Yes |
| 3. Collect job postings and identify required skills | Stage F | ⚠️ Yes, but skill detection is basic |
| 4. Readiness score with a hybrid algorithm | Stage G (checked by hand) | ✅ Yes |
| 5. Identify gaps and recommend how to close them | Stage H | ✅ Yes |
| 6. Right view for each user type, with access control | Stages I, J and K | ✅ Yes |

### What would make it fully solved (the shortest path)

1. **Faculty review of the curriculum mapping** for the units students actually take. This is the
   biggest lever: every skill level depends on it.
2. **A job requirements review screen** so career officers confirm each job's skills. This is the second
   biggest lever: every match score depends on it.
3. **The university's API, and confirmed portal sign-in,** so it runs live and students use their real
   passwords.
4. **Lecturers start recording evidence** for internships and projects.
5. **Track outcomes** (who got internships and jobs) to check the scores predict something real, and to
   tune the 60/40 and 25% settings.

---

## Part 3: Things that may look wrong, and why

| You notice… | Why it happens | Is it a bug? |
|---|---|---|
| Some first-years score as high as finalists | Many job postings mainly require Communication / Problem Solving, which first-year units cover | No. It's thin job data; the fix is better job requirements |
| 266 students have no profile or CBC level | They're new 2025/26 students with no results yet | No. A profile appears after their first results |
| A unit has an odd skill (e.g. "…Management" → Communication) | The mapping was drafted from course names | It's a draft. Fix it on the Curriculum page |
| "BAIT 1203" and "BAIT1203" are different units | That's how the university's records have them | No. Each is mapped separately |
| Staff appear by username, not full name | The university's admin system only stores usernames | No. A staff list with names was requested from IT |
| Some students can't sign in | Only 273 of 608 have a portal account in this copy of the database | No. It reflects the data |
| Names display in title case though the university stores capitals | The display tidies ALL-CAPS names for readability | No. Stored data is unchanged |
| A full sync takes a few seconds | It recalculates 608 profiles and 12,160 match scores in memory | No; incremental sync would speed it up |
| The *Students* page (lecturer reports) looks older than the rest | It hasn't been restyled yet | Cosmetic |

---

## Part 4: Reset after experimenting

If you changed things during the walkthrough:

- **Curriculum weights:** set them back on the Curriculum page, or regenerate all drafts. This discards
  any manual review, so only do it if nobody has reviewed the mapping yet:
  ```bash
  mariadb -u jm -p jenn_db -e "DELETE FROM course_unit_skills"
  php src/scripts/suggest_mapping.php
  php src/scripts/sync.php
  ```
- **Lecturer evidence:** clear it on the Evidence page (empty box, then save).
- **Roles:** Users page → set staff back to Lecturer; reactivate anyone you deactivated.
- **CBC bands:** set them back to 0–40 / 41–70 / 71–90 / 91–100, then sync.
- **Everything, from scratch** (rebuilds the whole system from the university data, a few minutes).
  The second line **deletes our database**: jobs, roles you created, evidence and mapping reviews are
  lost. Take a backup first with `mariadb-dump -u jm -p jenn_db > backup.sql`.
  ```bash
  php src/scripts/load_university_dump.php passbook.sql
  mariadb -u jm -p -e "DROP DATABASE jenn_db" && mariadb -u jm -p < database/schema.sql
  php src/scripts/seed.php
  ```
  Jobs then need re-fetching from **Jobs → Fetch from API**.

When you've finished, read **Part 2 of `docs/defence_guide.md`** again. It'll make much more sense now
that you've seen each piece working.
