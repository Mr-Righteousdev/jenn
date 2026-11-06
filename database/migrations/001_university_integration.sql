-- Switch from "we store and enter academic data" to "we read it from the university SIS".
-- Back up first: mariadb-dump jenn_db > backup.sql
-- Clears student-side data (students now come from the SIS sync). Keeps admins, skills, CBC levels and jobs.
USE jenn_db;

-- Read-only mirror of the university structure (ids are the SIS ids)
CREATE TABLE IF NOT EXISTS faculties (
    faculty_id INT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(150) NOT NULL
);
CREATE TABLE IF NOT EXISTS departments (
    department_id INT PRIMARY KEY,
    faculty_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE, -- ours: which departments use this system
    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS programmes (
    programme_id INT PRIMARY KEY,
    department_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(150) NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS course_units (
    course_unit_id INT PRIMARY KEY,
    department_id INT NOT NULL,
    code VARCHAR(12) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    year_of_study INT NOT NULL,
    semester INT NOT NULL,
    credit_units INT NOT NULL DEFAULT 4,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
);

-- Ours: which skills each course unit builds, and how strongly
CREATE TABLE IF NOT EXISTS course_unit_skills (
    course_unit_id INT NOT NULL,
    skill_id INT NOT NULL,
    weight DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    PRIMARY KEY (course_unit_id, skill_id),
    FOREIGN KEY (course_unit_id) REFERENCES course_units(course_unit_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

-- Ours: optional practical evidence from lecturers (internships, projects, soft skills)
CREATE TABLE IF NOT EXISTS skill_evidence (
    evidence_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    skill_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    level INT NOT NULL CHECK (level BETWEEN 0 AND 100),
    notes TEXT,
    recorded_on DATE NOT NULL,
    UNIQUE KEY uq_evidence (student_id, skill_id, lecturer_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sync_log (
    sync_id INT PRIMARY KEY AUTO_INCREMENT,
    started_at DATETIME NOT NULL,
    finished_at DATETIME,
    status ENUM('running','ok','failed') NOT NULL DEFAULT 'running',
    summary TEXT
);

-- Student-side data is rebuilt by the sync
DELETE FROM users WHERE role <> 'admin';
DELETE FROM job_readiness;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS source ENUM('local','university') NOT NULL DEFAULT 'local',
    ADD COLUMN IF NOT EXISTS department_id INT NULL;
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS programme_id INT NULL,
    ADD CONSTRAINT fk_students_programme FOREIGN KEY IF NOT EXISTS (programme_id) REFERENCES programmes(programme_id) ON DELETE SET NULL;
ALTER TABLE student_results
    ADD COLUMN IF NOT EXISTS course_unit_id INT NULL,
    ADD COLUMN IF NOT EXISTS mark DECIMAL(5,2) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_attempt (student_id, course_unit_id, academic_year);
ALTER TABLE student_skills
    ADD COLUMN IF NOT EXISTS academic_level INT NULL,
    ADD COLUMN IF NOT EXISTS evidence_level INT NULL;
-- Without this, ON DUPLICATE KEY in MatchingEngine never fired and re-matching duplicated rows
ALTER TABLE job_readiness
    ADD UNIQUE KEY IF NOT EXISTS uq_student_job (student_id, job_id);
