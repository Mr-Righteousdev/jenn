-- Competency Profiling System: database structure (generated from the live schema, then defaults appended)
-- Fresh install:  mariadb -u USER -p < database/schema.sql
-- University data comes from the SIS through src/utils/University.php; see docs/deployment_guide.md
CREATE DATABASE IF NOT EXISTS jenn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jenn_db;
SET FOREIGN_KEY_CHECKS = 0;

/*M!999999\- enable the sandbox mode */ 
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
CREATE TABLE IF NOT EXISTS `cbc_levels` (
  `level_id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL,
  `min_score` int(11) NOT NULL,
  `max_score` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `course_unit_skills` (
  `course_code` varchar(25) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `weight` decimal(3,2) NOT NULL DEFAULT 0.50,
  PRIMARY KEY (`course_code`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  FOREIGN KEY (`course_code`) REFERENCES `course_units` (`code`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `course_units` (
  `code` varchar(25) NOT NULL,
  `name` varchar(250) NOT NULL,
  `credit_units` decimal(4,1) NOT NULL DEFAULT 3.0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `faculties` (
  `code` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `job_readiness` (
  `readiness_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `match_score` decimal(5,2) DEFAULT NULL,
  `weighted_score` decimal(5,2) DEFAULT NULL,
  `keyword_score` decimal(5,2) DEFAULT NULL,
  `gap_analysis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gap_analysis`)),
  `report_generated` date DEFAULT NULL,
  PRIMARY KEY (`readiness_id`),
  UNIQUE KEY `uq_student_job` (`student_id`,`job_id`),
  KEY `job_id` (`job_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `job_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `weight` decimal(3,2) DEFAULT 0.50,
  `keyword_match_pattern` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  KEY `skill_id` (`skill_id`),
  FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `jobs` (
  `job_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `company` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `scraped_date` date DEFAULT NULL,
  PRIMARY KEY (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `programme_units` (
  `programme_code` varchar(25) NOT NULL,
  `course_code` varchar(25) NOT NULL,
  `year_of_study` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  PRIMARY KEY (`programme_code`,`course_code`),
  KEY `course_code` (`course_code`),
  FOREIGN KEY (`programme_code`) REFERENCES `programmes` (`code`) ON DELETE CASCADE,
  FOREIGN KEY (`course_code`) REFERENCES `course_units` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `programmes` (
  `code` varchar(25) NOT NULL,
  `faculty_code` varchar(10) DEFAULT NULL,
  `name` varchar(250) NOT NULL,
  `duration_years` decimal(3,1) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`),
  KEY `faculty_code` (`faculty_code`),
  FOREIGN KEY (`faculty_code`) REFERENCES `faculties` (`code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `recommendations` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `rec_type` enum('course','online_resource','skill_description') NOT NULL,
  `rec_title` varchar(255) NOT NULL,
  `rec_link` varchar(500) DEFAULT NULL,
  `rec_description` text DEFAULT NULL,
  `is_actioned` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`rec_id`),
  KEY `student_id` (`student_id`),
  KEY `skill_id` (`skill_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`,`permission`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `scope` enum('all','faculty') NOT NULL DEFAULT 'faculty',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `skill_evidence` (
  `evidence_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `level` int(11) NOT NULL CHECK (`level` between 0 and 100),
  `notes` text DEFAULT NULL,
  `recorded_on` date NOT NULL,
  PRIMARY KEY (`evidence_id`),
  UNIQUE KEY `uq_evidence` (`student_id`,`skill_id`,`lecturer_id`),
  KEY `skill_id` (`skill_id`),
  KEY `lecturer_id` (`lecturer_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `skills` (
  `skill_id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `default_weight` decimal(3,2) DEFAULT 0.50,
  PRIMARY KEY (`skill_id`),
  UNIQUE KEY `skill_name` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `student_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_code` varchar(25) NOT NULL,
  `course_name` varchar(100) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `mark` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_attempt` (`student_id`,`course_code`,`academic_year`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `student_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `skill_level` int(11) NOT NULL CHECK (`skill_level` >= 0 and `skill_level` <= 100),
  `assessed_by` int(11) DEFAULT NULL,
  `assessment_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `academic_level` int(11) DEFAULT NULL,
  `evidence_level` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_skill` (`student_id`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  KEY `assessed_by` (`assessed_by`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE,
  FOREIGN KEY (`assessed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `registration_no` varchar(20) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `programme_code` varchar(25) DEFAULT NULL,
  `cbc_level_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `registration_no` (`registration_no`),
  KEY `cbc_level_id` (`cbc_level_id`),
  KEY `fk_students_programme` (`programme_code`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`cbc_level_id`) REFERENCES `cbc_levels` (`level_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_programme` FOREIGN KEY (`programme_code`) REFERENCES `programmes` (`code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `sync_log` (
  `sync_id` int(11) NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `status` enum('running','ok','failed') NOT NULL DEFAULT 'running',
  `summary` text DEFAULT NULL,
  PRIMARY KEY (`sync_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(60) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','staff') NOT NULL DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `source` enum('local','university') NOT NULL DEFAULT 'local',
  `faculty_code` varchar(10) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

SET FOREIGN_KEY_CHECKS = 1;

-- Built-in staff roles (Super admin always has every permission and needs no rows)
INSERT IGNORE INTO roles (slug, name, description, scope, is_system) VALUES
('super_admin', 'Super admin', 'Full control of the system, including users, roles and university sync.', 'all', TRUE),
('career_officer', 'Career officer', 'Manages job postings and the skills framework; follows readiness across the university.', 'all', TRUE),
('dean', 'Dean', 'Follows readiness and curriculum mapping across their faculty.', 'faculty', TRUE),
('head_of_department', 'Head of department', 'Follows readiness and owns curriculum mapping for their faculty (departments are not in the university system yet).', 'faculty', TRUE),
('lecturer', 'Lecturer', 'Views students in their faculty and records practical evidence.', 'faculty', TRUE);

-- Super admin needs no rows: it always has every permission
INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.role_id, p.permission FROM roles r JOIN (
    SELECT 'career_officer' slug, 'overview.view' permission UNION ALL SELECT 'career_officer', 'analytics.view'
    UNION ALL SELECT 'career_officer', 'students.view' UNION ALL SELECT 'career_officer', 'jobs.manage'
    UNION ALL SELECT 'career_officer', 'skills.manage'
    UNION ALL SELECT 'dean', 'overview.view' UNION ALL SELECT 'dean', 'analytics.view'
    UNION ALL SELECT 'dean', 'students.view' UNION ALL SELECT 'dean', 'curriculum.manage'
    UNION ALL SELECT 'head_of_department', 'overview.view' UNION ALL SELECT 'head_of_department', 'analytics.view'
    UNION ALL SELECT 'head_of_department', 'students.view' UNION ALL SELECT 'head_of_department', 'curriculum.manage'
    UNION ALL SELECT 'head_of_department', 'evidence.record'
    UNION ALL SELECT 'lecturer', 'students.view' UNION ALL SELECT 'lecturer', 'evidence.record'
) p ON p.slug = r.slug;

-- Default CBC levels
INSERT IGNORE INTO cbc_levels (level_name, min_score, max_score, description) VALUES
('Novice', 0, 40, 'Needs supervision'),
('Intermediate', 41, 70, 'Can work with guidance'),
('Proficient', 71, 90, 'Works independently'),
('Expert', 91, 100, 'Mentors others');

-- Default skill categories
INSERT IGNORE INTO skills (skill_name, category, default_weight) VALUES
('Python', 'Programming', 0.80),
('Java', 'Programming', 0.80),
('C++', 'Programming', 0.70),
('JavaScript', 'Web Development', 0.75),
('HTML', 'Web Development', 0.60),
('CSS', 'Web Development', 0.60),
('PHP', 'Web Development', 0.70),
('MySQL', 'Database Management', 0.75),
('PostgreSQL', 'Database Management', 0.70),
('Linux', 'Server Configuration', 0.70),
('Apache', 'Server Configuration', 0.60),
('TCP/IP', 'Networking', 0.65),
('DNS', 'Networking', 0.60),
('Communication', 'Soft Skills', 0.50),
('Problem Solving', 'Soft Skills', 0.85);
