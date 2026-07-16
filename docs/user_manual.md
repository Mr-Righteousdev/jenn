# User Manual — Competency Profiling System

## 1. Login & Registration

| Role | Default Email | Password |
|------|--------------|----------|
| Admin | admin@jenn.edu | admin123 |
| Lecturer | lecturer@jenn.edu | lecturer123 |
| Student (e.g.) | S100@student.jenn | student123 |

Visit the login page, enter credentials, and you are routed to your role-specific dashboard.

To register a new account, click **Register** and select your role. Students get a `students` profile row created automatically.

---

## 2. Student Workflow

### 2.1 View Dashboard
After login, your dashboard shows:
- **My Profile** — Registration number, course, year, CBC competency level
- **Skills Overview** — Radar chart of your assessed skill categories
- **Detailed Skill Levels** — Table with progress bars per skill
- **Available Jobs** — Count of jobs in the system with link to matching
- **Recent Results** — Imported academic grades

### 2.2 Job Matching
Click **Job Matching** in the nav bar.

1. Browse the jobs list — each shows a match score if previously calculated
2. Click any job to calculate your **readiness score**
3. The score breaks down into:
   - **Weighted Score (60%)** — Your skill levels × job importance weights
   - **Keyword Match (40%)** — How well your skill names appear in the job description
   - **Hybrid Score** — Final combined percentage
4. Below the score, see **Skill Gaps** — missing skills sorted by severity
5. **Recommendations** appear automatically: course suggestions, YouTube tutorial links, and skill descriptions

### 2.3 View Report
Click **My Report** for a printable summary:
- Average readiness score across all jobs
- Top recommendations
- Per-job score table with CBC level
- Print-friendly view (click Print PDF)

---

## 3. Lecturer Workflow

### 3.1 Assess Students
Click **Assess** in the nav bar.

1. Select a student from the dropdown
2. Rate each skill from **0–100**
3. Add optional notes
4. Click **Submit Assessment**

Skill levels are stored per lecturer; if you reassess, the previous values are updated.

### 3.2 View Reports
Click **Reports** to see class-wide statistics:
- **Competency by Category** — Bar chart of average levels per skill category
- **CBC Level Distribution** — How many students fall into Novice / Intermediate / Proficient / Expert
- **Student Detail** — Table showing each student's skill count, average level, and CBC level
- Print button for offline review

---

## 4. Admin Workflow

### 4.1 CBC Levels
Navigate to **Admin → CBC Levels**.

- **Add Level** — Enter name, score range (min–max), and description
- **Edit Level** — Click Edit to modify an existing level
- **Delete Level** — Removes the level (students assigned to it will have `NULL`)

Default levels: Novice (0–40), Intermediate (41–70), Proficient (71–90), Expert (91–100).

### 4.2 Skills
Navigate to **Admin → Skills**.

- **Add Skill** — Enter name, select category, set default weight (0.00–1.00)
- **Edit / Delete** — Same pattern as CBC Levels

Skills are the foundation of the matching engine. Each job description is scanned against these skill names to auto-link requirements.

### 4.3 Import Students (CSV)
Navigate to **Admin → Import CSV**.

Required columns: `registration_no`, `course_code`, `grade`

Optional columns: `name`, `email`, `course`, `year_of_study`, `course_name`, `semester`, `academic_year`

**How it works:**
1. Upload a CSV file
2. The system creates student accounts (if new) with password `student123`
3. Each grade is recorded in `student_results`
4. Grades are auto-mapped to skill levels (A=90, B=75, C=60, D=45, F=25)
5. Course names are matched to skill categories (e.g., "Database Management I" → Database Management skills)

### 4.4 Jobs
Navigate to **Admin → Jobs** for the job management panel.

**Browse tab** — View all imported jobs with their auto-matched skills (green/yellow/light badges for relevance). Click **Desc** to expand the full description.

**Fetch tab** — Pull live jobs from the JSearch API:
1. Enter a search query (e.g., "software developer")
2. Enter a country code (e.g., `ug`, `ke`, `us`)
3. Click **Fetch & Store**
4. Duplicate jobs (same title + company) are automatically skipped

**Add Manual tab** — Enter job title, company, description, and optional URL to add a job by hand.

### 4.5 Analytics
Navigate to **Admin → Analytics** for system-wide statistics:
- Total users, students, jobs, assessments
- Top skills across all students
- Users by role (doughnut chart)
- Jobs by source
- Top matched jobs with average scores

---

## 5. Logout
Click **Logout** in the nav bar to end your session.
