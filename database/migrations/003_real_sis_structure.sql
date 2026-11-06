-- Switch the university mirror to the real SIS (campus_dynamics) shape:
--   * no departments: programmes belong directly to faculties
--   * everything is keyed by the university's own codes (faculty '02', programme '02305', course 'BAIT 2111')
--   * which students come in is decided per programme (programmes.is_active)
--   * accounts are keyed by username (reg no / staff username); many students have no valid email
-- Clears all university-sourced people and their data (the sync rebuilds them).
-- Keeps admins, skills, CBC levels, jobs, roles and permissions.
USE jenn_db;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM users WHERE source = 'university';
DELETE FROM students;
DELETE FROM student_results;
DELETE FROM student_skills;
DELETE FROM skill_evidence;
DELETE FROM job_readiness;

ALTER TABLE students DROP FOREIGN KEY IF EXISTS fk_students_programme;
ALTER TABLE students DROP COLUMN IF EXISTS programme_id;
ALTER TABLE students ADD COLUMN IF NOT EXISTS programme_code VARCHAR(25) NULL AFTER year_of_study;

ALTER TABLE student_results DROP INDEX IF EXISTS uq_attempt;
ALTER TABLE student_results DROP COLUMN IF EXISTS course_unit_id;
ALTER TABLE student_results MODIFY course_code VARCHAR(25) NOT NULL;
ALTER TABLE student_results ADD UNIQUE KEY IF NOT EXISTS uq_attempt (student_id, course_code, academic_year);

DROP TABLE IF EXISTS course_unit_skills, course_units, programmes, departments, faculties;

CREATE TABLE faculties (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);
CREATE TABLE programmes (
    code VARCHAR(25) PRIMARY KEY,
    faculty_code VARCHAR(10) NULL,
    name VARCHAR(250) NOT NULL,
    duration_years DECIMAL(3,1) NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE, -- ours: bring this programme's students into the system
    FOREIGN KEY (faculty_code) REFERENCES faculties(code) ON DELETE SET NULL
);
CREATE TABLE course_units (
    code VARCHAR(25) PRIMARY KEY,
    name VARCHAR(250) NOT NULL,
    credit_units DECIMAL(4,1) NOT NULL DEFAULT 3
);
-- A programme's curriculum: which units, in which year and semester
CREATE TABLE programme_units (
    programme_code VARCHAR(25) NOT NULL,
    course_code VARCHAR(25) NOT NULL,
    year_of_study INT NOT NULL,
    semester INT NOT NULL,
    PRIMARY KEY (programme_code, course_code),
    FOREIGN KEY (programme_code) REFERENCES programmes(code) ON DELETE CASCADE,
    FOREIGN KEY (course_code) REFERENCES course_units(code) ON DELETE CASCADE
);
-- Ours: which skills each course unit builds, and how strongly
CREATE TABLE course_unit_skills (
    course_code VARCHAR(25) NOT NULL,
    skill_id INT NOT NULL,
    weight DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    PRIMARY KEY (course_code, skill_id),
    FOREIGN KEY (course_code) REFERENCES course_units(code) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

ALTER TABLE students ADD CONSTRAINT fk_students_programme FOREIGN KEY (programme_code) REFERENCES programmes(code) ON DELETE SET NULL;

ALTER TABLE users DROP COLUMN IF EXISTS department_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS faculty_code VARCHAR(10) NULL AFTER source;
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(60) NULL AFTER name;
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uq_username (username);
ALTER TABLE users MODIFY email VARCHAR(100) NULL;

UPDATE roles SET scope = 'faculty' WHERE scope = 'department';
ALTER TABLE roles MODIFY scope ENUM('all','faculty') NOT NULL DEFAULT 'faculty';
UPDATE roles SET description = 'Follows readiness and owns curriculum mapping for their faculty (departments are not in the university system yet).'
WHERE slug = 'head_of_department';
UPDATE roles SET description = 'Views students in their faculty and records practical evidence.' WHERE slug = 'lecturer';

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE student_results MODIFY grade VARCHAR(5) NULL; -- the SIS uses up to 5 characters
