<?php
// Seed script: populates sample data for demonstration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "Seeding database..." . PHP_EOL;

// Create admin user if not exists
$stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute(['admin@jenn.edu']);
if (!$stmt->fetch()) {
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")->execute(['Administrator', 'admin@jenn.edu', password_hash('admin123', PASSWORD_DEFAULT)]);
    echo "  Admin created (admin@jenn.edu / admin123)" . PHP_EOL;
}

// Create lecturer
$stmt->execute(['lecturer@jenn.edu']);
if (!$stmt->fetch()) {
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'lecturer')")->execute(['Dr. Okello', 'lecturer@jenn.edu', password_hash('lecturer123', PASSWORD_DEFAULT)]);
    echo "  Lecturer created (lecturer@jenn.edu / lecturer123)" . PHP_EOL;
}

// Create sample students
$students = [
    ['S100', 'Alice Nakato', 'Bachelor of IT', 3],
    ['S101', 'Bob Mugisha', 'Bachelor of CS', 2],
    ['S102', 'Carol Achieng', 'Bachelor of IT', 4],
    ['S103', 'David Ssempijja', 'Bachelor of SE', 3],
    ['S104', 'Eve Nabatanzi', 'Bachelor of IT', 2],
];

foreach ($students as $s) {
    $stmt->execute([$s[1] . '@student.jenn']);
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')")->execute([$s[1], $s[1] . '@student.jenn', password_hash('student123', PASSWORD_DEFAULT)]);
        $uid = $db->lastInsertId();
        $db->prepare("INSERT INTO students (user_id, registration_no, course, year_of_study) VALUES (?, ?, ?, ?)")->execute([$uid, $s[0], $s[2], $s[3]]);
        echo "  Student {$s[1]} ({$s[0]}) created" . PHP_EOL;
    }
}

// Assign skill levels to students
$allStudents = $db->query("SELECT student_id FROM students ORDER BY student_id")->fetchAll();
$allSkills = $db->query("SELECT skill_id, skill_name FROM skills")->fetchAll();

$skillLevels = [
    ['S100', 'Python', 85], ['S100', 'JavaScript', 78], ['S100', 'MySQL', 72], ['S100', 'HTML', 90], ['S100', 'CSS', 85], ['S100', 'PHP', 65], ['S100', 'Communication', 80], ['S100', 'Problem Solving', 88],
    ['S101', 'Python', 92], ['S101', 'Java', 88], ['S101', 'C++', 75], ['S101', 'PostgreSQL', 70], ['S101', 'Linux', 60], ['S101', 'TCP/IP', 55], ['S101', 'Problem Solving', 95],
    ['S102', 'JavaScript', 70], ['S102', 'HTML', 80], ['S102', 'CSS', 75], ['S102', 'PHP', 85], ['S102', 'MySQL', 80], ['S102', 'Apache', 70], ['S102', 'Communication', 90], ['S102', 'Leadership', 75],
    ['S103', 'Python', 75], ['S103', 'Java', 80], ['S103', 'MySQL', 65], ['S103', 'Linux', 70], ['S103', 'DNS', 50], ['S103', 'Problem Solving', 85], ['S103', 'Communication', 60],
    ['S104', 'JavaScript', 60], ['S104', 'HTML', 75], ['S104', 'CSS', 60], ['S104', 'PHP', 55], ['S104', 'MySQL', 50], ['S104', 'Communication', 85], ['S104', 'Problem Solving', 70],
];

foreach ($skillLevels as $sl) {
    $reg = $sl[0];
    $skillName = $sl[1];
    $level = $sl[2];

    $st = $db->prepare("SELECT s.student_id FROM students s WHERE s.registration_no = ?");
    $st->execute([$reg]);
    $sid = $st->fetchColumn();
    if (!$sid) continue;

    $st = $db->prepare("SELECT skill_id FROM skills WHERE skill_name = ?");
    $st->execute([$skillName]);
    $skid = $st->fetchColumn();
    if (!$skid) continue;

    $db->prepare("INSERT INTO student_skills (student_id, skill_id, skill_level, assessed_by, assessment_date) VALUES (?, ?, ?, 2, CURDATE()) ON DUPLICATE KEY UPDATE skill_level = VALUES(skill_level)")
        ->execute([$sid, $skid, $level]);
}

echo "  Skill assessments seeded" . PHP_EOL;

// Add CBC level mappings based on avg skill
foreach ($allStudents as $s) {
    $avg = $db->prepare("SELECT AVG(skill_level) FROM student_skills WHERE student_id = ?");
    $avg->execute([$s['student_id']]);
    $score = round($avg->fetchColumn());
    if ($score) {
        $lvl = $db->prepare("SELECT level_id FROM cbc_levels WHERE ? BETWEEN min_score AND max_score");
        $lvl->execute([$score]);
        $lid = $lvl->fetchColumn();
        if ($lid) {
            $db->prepare("UPDATE students SET cbc_level_id = ? WHERE student_id = ?")->execute([$lid, $s['student_id']]);
        }
    }
}
echo "  CBC levels assigned" . PHP_EOL;

echo PHP_EOL . "Seed complete!" . PHP_EOL;
echo "Logins:" . PHP_EOL;
echo "  Admin:    admin@jenn.edu / admin123" . PHP_EOL;
echo "  Lecturer: lecturer@jenn.edu / lecturer123" . PHP_EOL;
echo "  Students: e.g. S100@student.jenn / student123" . PHP_EOL;
