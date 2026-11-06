-- Editable staff roles with permissions and a data scope.
-- users.role becomes the account type (student | staff); what a staff member may do comes from users.role_id.
USE jenn_db;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255),
    scope ENUM('all','faculty','department') NOT NULL DEFAULT 'department', -- whose students the role can see
    is_system BOOLEAN NOT NULL DEFAULT FALSE,                              -- built-in: can't be deleted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission VARCHAR(50) NOT NULL,
    PRIMARY KEY (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

INSERT IGNORE INTO roles (slug, name, description, scope, is_system) VALUES
('super_admin', 'Super admin', 'Full control of the system, including users, roles and university sync.', 'all', TRUE),
('career_officer', 'Career officer', 'Manages job postings and the skills framework; follows readiness across the university.', 'all', TRUE),
('dean', 'Dean', 'Follows readiness and curriculum mapping across their faculty.', 'faculty', TRUE),
('head_of_department', 'Head of department', 'Follows readiness and owns curriculum mapping for their department.', 'department', TRUE),
('lecturer', 'Lecturer', 'Views students in their department and records practical evidence.', 'department', TRUE);

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

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role_id INT NULL,
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE,
    ADD CONSTRAINT fk_users_role FOREIGN KEY IF NOT EXISTS (role_id) REFERENCES roles(role_id) ON DELETE SET NULL,
    MODIFY role ENUM('student','lecturer','admin','staff') NOT NULL DEFAULT 'student';

UPDATE users SET role_id = (SELECT role_id FROM roles WHERE slug = 'super_admin') WHERE role = 'admin' AND role_id IS NULL;
UPDATE users SET role_id = (SELECT role_id FROM roles WHERE slug = 'lecturer') WHERE role = 'lecturer' AND role_id IS NULL;
UPDATE users SET role = 'staff' WHERE role IN ('admin', 'lecturer');
ALTER TABLE users MODIFY role ENUM('student','staff') NOT NULL DEFAULT 'student';
