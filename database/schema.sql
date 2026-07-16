CREATE DATABASE IF NOT EXISTS jenn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jenn_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','lecturer','admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cbc_levels (
    level_id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(50) NOT NULL,
    min_score INT NOT NULL,
    max_score INT NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    registration_no VARCHAR(20) UNIQUE,
    course VARCHAR(100),
    year_of_study INT,
    cbc_level_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (cbc_level_id) REFERENCES cbc_levels(level_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS skills (
    skill_id INT PRIMARY KEY AUTO_INCREMENT,
    skill_name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50),
    default_weight DECIMAL(3,2) DEFAULT 0.50
);

CREATE TABLE IF NOT EXISTS student_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    skill_id INT NOT NULL,
    skill_level INT NOT NULL CHECK (skill_level >= 0 AND skill_level <= 100),
    assessed_by INT,
    assessment_date DATE,
    notes TEXT,
    UNIQUE KEY uq_student_skill (student_id, skill_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS jobs (
    job_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    company VARCHAR(100),
    description TEXT,
    url VARCHAR(500),
    source VARCHAR(50),
    scraped_date DATE
);

CREATE TABLE IF NOT EXISTS job_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    skill_id INT NOT NULL,
    weight DECIMAL(3,2) DEFAULT 0.50,
    keyword_match_pattern VARCHAR(255),
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_readiness (
    readiness_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    job_id INT NOT NULL,
    match_score DECIMAL(5,2),
    weighted_score DECIMAL(5,2),
    keyword_score DECIMAL(5,2),
    gap_analysis JSON,
    report_generated DATE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recommendations (
    rec_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    skill_id INT NOT NULL,
    rec_type ENUM('course','online_resource','skill_description') NOT NULL,
    rec_title VARCHAR(255) NOT NULL,
    rec_link VARCHAR(500),
    rec_description TEXT,
    is_actioned BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_results (
    result_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    grade VARCHAR(2),
    semester INT,
    academic_year VARCHAR(10),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

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
