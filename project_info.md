## Development Plan: Intelligent CBC-Aligned Student Competency System

## 1. Project Overview
**Problem:** Universities focus on grades, not practical skills or job readiness.
**Solution:** A web-based system that profiles student competencies, matches them to real job market requirements, and provides actionable recommendations.
**Case Study:** St Lawrence University, Uganda (Department of Informatics and Engineering)

## 2. Core System Capabilities
| Capability | Description |
|------------|-------------|
| **Competency Profiling** | Track student skills across IT domains (programming, databases, networking, etc.) |
| **Job Market Integration** | Scrape live job postings and extract skill requirements |
| **Intelligent Matching** | Hybrid algorithm (weighted skills + keyword analysis) |
| **Gap Analysis** | Identify missing skills and generate multi-format recommendations |
| **CBC Alignment** | Editable competency framework with levels (Novice → Expert) |

## 3. Detailed Technical Specifications

### 3.1 Hybrid Matching Algorithm
```php
// Conceptual matching logic
function calculateJobReadiness($studentSkills, $jobRequirements) {
    // 1. Weighted Score (60% of total)
    $weightedScore = 0;
    $totalWeight = 0;
    foreach ($jobRequirements as $skill => $weight) {
        $studentLevel = $studentSkills[$skill] ?? 0; // 0-100 scale
        $weightedScore += ($studentLevel * $weight);
        $totalWeight += $weight;
    }
    $weightedScore = ($weightedScore / $totalWeight) * 100;

    // 2. Keyword Match (40% of total)
    $keywordMatch = calculateKeywordSimilarity($studentProfile, $jobDescription);

    // 3. Combined Score
    return ($weightedScore * 0.6) + ($keywordMatch * 0.4);
}
3.2 Editable CBC Framework
Admin-configurable competency levels: e.g., Novice (0-40), Intermediate (41-70), Proficient (71-90), Expert (91-100)

Skill categories: Programming, Web Development, Database Management, Server Configuration, Networking, Soft Skills

CRUD operations for skills and levels via admin panel

3.3 Job Data Pipeline
Scraping: Python script (or PHP with Goutte) to scrape job sites (e.g., LinkedIn, Indeed, local job boards)

Processing: Extract job title, description, required skills (using NLP or keyword extraction)

Storage: Store in jobs table with associated job_skills and skill_weights

Schedule: Manual trigger or cron job (for demo, manual refresh)

3.4 Recommendation Engine
For each skill gap, generate:

Internal courses: Mapped to university course codes (from student's curriculum)

Online resources: YouTube tutorials, Coursera, Udemy links (pre-loaded or searchable)

Skill descriptions: Plain text guidance on what to learn

4. Database Schema
Core Tables
sql
-- Users (all roles)
users (
    user_id INT PK AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    role ENUM('student','lecturer','admin'),
    created_at TIMESTAMP
)

-- Student profiles
students (
    student_id INT PK AUTO_INCREMENT,
    user_id INT FK -> users.user_id,
    registration_no VARCHAR(20) UNIQUE,
    course VARCHAR(100),
    year_of_study INT,
    cbc_level_id INT FK -> cbc_levels.level_id
)

-- CBC Framework (editable)
cbc_levels (
    level_id INT PK AUTO_INCREMENT,
    level_name VARCHAR(50), -- 'Novice', 'Proficient', etc.
    min_score INT,
    max_score INT,
    description TEXT
)

-- Skills master list (editable)
skills (
    skill_id INT PK AUTO_INCREMENT,
    skill_name VARCHAR(100) UNIQUE,
    category VARCHAR(50), -- 'Programming', 'Networking', etc.
    default_weight DECIMAL(3,2) -- 0.0 - 1.0
)

-- Student skill assessments
student_skills (
    id INT PK AUTO_INCREMENT,
    student_id INT FK -> students.student_id,
    skill_id INT FK -> skills.skill_id,
    skill_level INT CHECK(0-100), -- 0-100 scale
    assessed_by INT FK -> users.user_id,
    assessment_date DATE,
    notes TEXT
)

-- Jobs (from scraping)
jobs (
    job_id INT PK AUTO_INCREMENT,
    title VARCHAR(255),
    company VARCHAR(100),
    description TEXT,
    url VARCHAR(500),
    source VARCHAR(50), -- 'LinkedIn', 'Indeed', etc.
    scraped_date DATE
)

-- Job skill requirements
job_skills (
    id INT PK AUTO_INCREMENT,
    job_id INT FK -> jobs.job_id,
    skill_id INT FK -> skills.skill_id,
    weight DECIMAL(3,2), -- Importance of this skill for this job
    keyword_match_pattern VARCHAR(255) -- For keyword matching
)

-- Job readiness reports
job_readiness (
    readiness_id INT PK AUTO_INCREMENT,
    student_id INT FK -> students.student_id,
    job_id INT FK -> jobs.job_id,
    match_score DECIMAL(5,2), -- 0-100
    gap_analysis TEXT, -- JSON or text summary
    report_generated DATE
)

-- Recommendations
recommendations (
    rec_id INT PK AUTO_INCREMENT,
    student_id INT FK -> students.student_id,
    skill_id INT FK -> skills.skill_id,
    rec_type ENUM('course','online_resource','skill_description'),
    rec_title VARCHAR(255),
    rec_link VARCHAR(500), -- For online resources
    rec_description TEXT,
    is_actioned BOOLEAN DEFAULT FALSE
)

-- Student academic results (import)
student_results (
    result_id INT PK AUTO_INCREMENT,
    student_id INT FK -> students.student_id,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    grade VARCHAR(2),
    semester INT,
    academic_year VARCHAR(10)
)
5. User Interface Mockups (Conceptual)
5.1 Student Dashboard
text
+------------------------------------------+
|  Welcome, Jennifer!                       |
|  Job Readiness Score: 72% (Proficient)    |
+------------------------------------------+
|  Skills Radar    |  Recommended Jobs      |
|  [Chart.js]      |  - Junior Developer    |
|                   |  - IT Support         |
+------------------+-----------------------+
|  Skill Gaps & Recommendations             |
|  • Database Mgmt (Low) → Course: DBMS 101 |
|  • Networking (Med) → YouTube: Net Basics |
+------------------------------------------+
5.2 Lecturer Assessment View
text
+------------------------------------------+
|  Student: Jennifer N.                     |
|  Course: BIT Year 3                      |
+------------------------------------------+
|  Skill    | Current | Assess | Notes      |
|  Prog     | 85      | [___]  | Good logic |
|  Web Dev  | 70      | [___]  | Needs CSS |
+------------------------------------------+
|  [Submit Assessment]                       |
+------------------------------------------+
5.3 Admin CBC Configuration
text
+------------------------------------------+
|  Competency Level Editor                  |
|  Level Name: Proficient                   |
|  Score Range: 71 - 90                    |
|  Description: Can work independently     |
+------------------------------------------+
|  [Add Level] [Edit] [Delete]             |
+------------------------------------------+
|  Skill Editor                            |
|  Skill: Python Programming               |
|  Category: Programming                   |
|  Default Weight: 0.8                     |
+------------------------------------------+
6. Implementation Phases
Phase 1: Foundation (Week 1-2)
Setup: XAMPP, VS Code, project structure

Database: Create all tables (schema above)

Authentication: Login/register for 3 roles with session management

Basic UI: Bootstrap templates for dashboards

Phase 2: Core Data Input (Week 3-4)
Student Import: CSV upload for academic results

Skill Assessment: Lecturer form to rate student skills (0-100)

CBC Management: Admin CRUD for levels and skills

Student Dashboard: View profile, skills chart

Phase 3: Job Market Integration (Week 5-6)
Scraping Script: Python script to fetch job listings (use BeautifulSoup)

Job Database: Store jobs with extracted skills

Admin Job Management: View, edit, manually add jobs

Phase 4: Matching Engine (Week 7-8) 🧠 CRITICAL
Weighted Scoring: Implement algorithm using skill weights

Keyword Extraction: NLP or regex to match job descriptions to student profiles

Hybrid Score: Combine both for final readiness %

Gap Analysis: Identify top 3-5 missing skills

Phase 5: Recommendations & Reports (Week 9-10)
Recommendation Engine: Map gaps to courses/resources

Report Generation: PDF/HTML reports for students

Lecturer Reports: Class-wide competency summaries

Admin Reports: System usage, analytics

Phase 6: Testing & Polish (Week 11-12)
Sample Data: Import student data (provided by you)

Scraping Test: Fetch 50-100 sample jobs

User Testing: Get feedback from 2-3 lecturers and students

Bug Fixes & UI Refinements

7. OpenSpec Integration Workflow
Initialize OpenSpec
bash
cd your-project-root
openspec init
Create Specs for Key Features
bash
# Example: Authentication spec
openspec spec create auth-login
# Example: Student profiling spec
openspec spec create student-profile
# Example: Job matching spec
openspec spec create job-matching
Sample Spec: job-matching/spec.md
markdown
# Job Matching Specification

## Purpose
Calculate student readiness for specific job roles using hybrid algorithm.

## Requirements

### Requirement: Weighted Skill Scoring
The system SHALL calculate a weighted score based on student skill levels
and job requirement weights.

#### Scenario: Calculate weighted score
- GIVEN a student with Programming=85, DB=60
- AND a job requiring Programming(weight=0.8), DB(weight=0.6)
- WHEN the system computes the weighted score
- THEN it SHALL return (85*0.8 + 60*0.6) / (0.8+0.6) = 74.3%

### Requirement: Keyword Matching
The system SHALL extract keywords from job descriptions and match
against student profiles.

#### Scenario: Keyword extraction
- GIVEN a job description containing "Python, Django, PostgreSQL"
- WHEN the system extracts skills
- THEN it SHALL match against student's listed skills
- AND return a similarity score

### Requirement: Hybrid Readiness Score
The system SHALL combine weighted score (60%) and keyword match (40%)
to produce final readiness percentage.

#### Scenario: Generate readiness score
- GIVEN weighted score = 80 and keyword match = 70
- WHEN system combines scores
- THEN final score = (80*0.6) + (70*0.4) = 76%
Change-Driven Development
For each phase, create a change proposal:

bash
openspec change create implement-job-matching
This creates proposal.md, tasks.md, and spec deltas.

8. Development Tools & Commands
Project Setup
bash
# Folder structure
project-root/
├── public/
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── images/
├── src/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   └── utils/
├── scraping/
│   └── job_scraper.py
├── openspec/
│   ├── specs/
│   └── changes/
└── database/
    └── schema.sql
Key Technologies
Backend: PHP 8.x with PDO for database

Frontend: Bootstrap 5, Chart.js for visualizations

Scraping: Python 3 + BeautifulSoup/Requests

Local Dev: XAMPP (Apache + MySQL)

Sample Commands
bash
# Import student data
php src/scripts/import_students.php sample_data.csv

# Run job scraper
python scraping/job_scraper.py --site linkedin --keywords "IT,Developer"

# Generate readiness report
php src/scripts/generate_reports.php --student S001
9. Testing Data Strategy
Student Data (Provided by You)
Format: CSV/Excel with: Registration No, Course, Year, Course Codes & Grades

Mapping: We'll map course codes to skills (e.g., DBMS 101 → Database Management)

Initial Skill Levels: Derived from grades (A=90, B=75, C=60, etc.)

Job Data (Scraped)
Source: Local job sites (e.g., BrighterMonday Uganda, LinkedIn Uganda)

Quantity: ~50-100 jobs for testing

Processing: Extract job title, description, required skills

CBC Framework (Default)
text
Levels:
- Novice: 0-40 (Needs supervision)
- Intermediate: 41-70 (Can work with guidance)
- Proficient: 71-90 (Works independently)
- Expert: 91-100 (Mentors others)

Skill Categories:
- Programming (Python, Java, C++)
- Web Development (HTML, CSS, JS, PHP)
- Database Management (MySQL, PostgreSQL)
- Server Configuration (Linux, Apache)
- Networking (TCP/IP, DNS)
- Soft Skills (Communication, Problem Solving)
10. Risk Mitigation
Risk	Mitigation
Scraping blocked	Use respectful delays, user-agent rotation; fallback to manual job entry
Matching algorithm inaccurate	Allow admins to adjust weights; include feedback loop
Student data inconsistent	Build flexible import with mapping configuration
UI not intuitive	Early prototypes with user testing; iterate based on feedback
11. Success Criteria
System successfully imports student data (with results)

Admin can configure CBC levels and skills

Scraper pulls at least 50 real job listings

Hybrid matching generates realistic readiness scores

Recommendations are specific and actionable

At least 3 users (student, lecturer, admin) can complete their workflows

12. Documentation Plan
User Manual: For students, lecturers, admins

Technical Documentation: For future developers (OpenSpec specs)

Video Walkthrough: 5-minute demo of key features

Final Report: Summarizing system design, challenges, and results

Created: July 16, 2026
Last Updated: July 16, 2026
Status: Ready for implementation
