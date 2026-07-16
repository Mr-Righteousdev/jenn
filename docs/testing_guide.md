# Testing Guide — Competency Profiling System

## Prerequisites

- PHP 8.x with PDO MySQL extension
- MySQL / MariaDB
- Composer (optional)
- Web browser

## Setup

```bash
# 1. Clone and enter the project
cd /path/to/jenn

# 2. Create the database
mysql -u root -p < database/schema.sql

# 3. Configure .env
# Edit .env with your database credentials and RapidAPI key:
#   DB_USERNAME=your_db_user
#   DB_PASSWORD=your_db_password
#   RAPIDAPI_KEY=your_rapidapi_key (get free at https://rapidapi.com/letscrape-6bRBa3QguO5/api/jsearch)

# 4. Seed sample data
php src/scripts/seed.php

# 5. Start the dev server
BASE_URL="" php -S 127.0.0.1:8080 -t public/
```

---

## Test Plan

### 1. Authentication (all roles)

| # | Test | Expected Result |
|---|------|----------------|
| 1.1 | Visit `http://127.0.0.1:8080/login.php` | Login form renders |
| 1.2 | Login as admin (admin@jenn.edu / admin123) | Redirected to admin dashboard |
| 1.3 | Login as lecturer (lecturer@jenn.edu / lecturer123) | Redirected to lecturer dashboard |
| 1.4 | Login as student (S100@student.jenn / student123) | Redirected to student dashboard |
| 1.5 | Visit `/register.php`, create a new student account | Account created, can login |
| 1.6 | Visit any admin page while logged in as student | 403 Unauthorized |

### 2. Student Features

| # | Test | Expected Result |
|---|------|----------------|
| 2.1 | Dashboard shows profile (reg no, course, year) | Correct student info |
| 2.2 | Radar chart renders with skill categories | Chart visible with data |
| 2.3 | Detailed skill table shows levels + progress bars | All assessed skills listed |
| 2.4 | Recent results table shows imported grades | Grades with color badges |
| 2.5 | Click **Job Matching** in nav | Job list page loads |
| 2.6 | Click a job title | Readiness score calculated |
| 2.7 | Score shows 3 bars: weighted, keyword, hybrid | All three percentages visible |
| 2.8 | Skill gaps section appears | Missing skills listed with severity |
| 2.9 | Recommendations appear | Course + YouTube + description per gap |
| 2.10 | Click **My Report** | Report page loads with scores table |
| 2.11 | Click **Print PDF** | Print-friendly layout opens |

### 3. Lecturer Features

| # | Test | Expected Result |
|---|------|----------------|
| 3.1 | Dashboard shows student count and assessment count | Correct counts |
| 3.2 | Click **Assess** in nav | Student dropdown visible |
| 3.3 | Select a student | Skill rating form loads |
| 3.4 | Set a skill to 85, add note, submit | Success message |
| 3.5 | Re-select same student, verify level updated | 85 still shown |
| 3.6 | Click **Reports** | Class report with stats loads |
| 3.7 | Bar chart for competency by category | Chart renders |
| 3.8 | CBC Level distribution shown | Student counts per level |
| 3.9 | Student detail table with all rows | All students listed |

### 4. Admin Features

| # | Test | Expected Result |
|---|------|----------------|
| 4.1 | Dashboard shows counts (CBC levels, skills, users, etc.) | Correct stats |
| 4.2 | **Admin → CBC Levels** → Add "Expert+" (91-100) | Level added to table |
| 4.3 | Edit the "Expert+" level | Form pre-filled, update works |
| 4.4 | Delete the test level | Level removed |
| 4.5 | **Admin → Skills** → Add "Django" (Web Dev, 0.70) | Skill added |
| 4.6 | Delete the test skill | Skill removed |
| 4.7 | **Admin → Import CSV** → Upload `sample_data/students.csv` | Results imported, students created |
| 4.8 | **Admin → Jobs → Fetch** → Query "developer", country "ug" | Jobs fetched from API |
| 4.9 | Verify duplicate prevention: fetch same query again | 0 new jobs stored (skipped) |
| 4.10 | **Admin → Jobs → Browse** | Table with skills badges renders |
| 4.11 | Click **Desc** on a job | Description expands |
| 4.12 | **Admin → Jobs → Add Manual** → Fill form → Submit | Job added to table |
| 4.13 | **Admin → Analytics** | All stat cards + charts render |

### 5. Edge Cases

| # | Test | Expected Result |
|---|------|----------------|
| 5.1 | Login with wrong password | Error: "Invalid email or password" |
| 5.2 | Register with existing email | Error: "Email already registered" |
| 5.3 | Register with password < 6 chars | Error: minimum length enforced |
| 5.4 | Student with no skills visits Job Matching | "No skills assessed yet" message |
| 5.5 | Student with no skills visits dashboard | Radar chart empty but page loads |
| 5.6 | Admin submits empty CSV | Error about missing columns |
| 5.7 | Fetch jobs without RAPIDAPI_KEY | Sample jobs used as fallback |

### 6. Batch Testing (CLI)

```bash
# Run matching for all students against all jobs
php -r "
require 'src/config/app.php';
require 'src/config/database.php';
require 'src/utils/MatchingEngine.php';
\$e = new MatchingEngine();
\$db = Database::getConnection();
foreach (\$db->query('SELECT student_id FROM students')->fetchAll() as \$s)
    foreach (\$db->query('SELECT job_id FROM jobs')->fetchAll() as \$j)
        \$e->calculateReadiness(\$s['student_id'], \$j['job_id']);
echo 'Done.';
"
```

---

## Common Issues

| Problem | Likely Cause | Fix |
|---------|-------------|-----|
| "No database selected" | Schema not run | `mysql -u root -p < database/schema.sql` |
| API returns 403 | Not subscribed to JSearch on RapidAPI | Visit https://rapidapi.com/.../jsearch and subscribe FREE |
| Blank page | PHP error | Run `php -l filename.php` to syntax-check |
| Redirects to `/jenn/...` not found | BASE_URL mismatch | Start with `BASE_URL="" php -S ...` or use the router |
| Chart not rendering | Chart.js missing | Check header.php includes the CDN script |
