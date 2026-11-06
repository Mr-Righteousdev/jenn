<?php
// Sets up a working system on top of database/schema.sql, reading the university data
// (load it first with: php src/scripts/load_university_dump.php passbook.sql)
//
//   php src/scripts/seed.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../utils/Sync.php';
require_once __DIR__ . '/suggest_mapping.php';

$db = Database::getConnection();
echo "Seeding..." . PHP_EOL;

$stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute(['admin@jenn.edu']);
if (!$stmt->fetch()) {
    // The one local account: an emergency super admin that works even if the university system is down
    $db->prepare("INSERT INTO users (name, email, password_hash, role, role_id) SELECT ?, ?, ?, 'staff', role_id FROM roles WHERE slug = 'super_admin'")
       ->execute(['Administrator', 'admin@jenn.edu', password_hash('admin123', PASSWORD_DEFAULT)]);
    echo "  Admin created (admin@jenn.edu / admin123)" . PHP_EOL;
}

// First sync mirrors the university structure (all faculties and programmes)
$r = Sync::run();
if (!$r['success']) exit("  Sync failed: {$r['error']}" . PHP_EOL);
echo "  Structure: {$r['faculties']} faculties, {$r['programmes']} programmes, {$r['course_units']} course units" . PHP_EOL;

// The case study: IT programmes in the Faculty of Science and Technology
$itProgrammes = ['02305', '02307', '02306', '02311', '02221']; // BIT, BSc CS, BSc Computer Eng, BSc Telecom Eng, Diploma IT
$on = $db->prepare("UPDATE programmes SET is_active = TRUE WHERE code = ?");
foreach ($itProgrammes as $code) $on->execute([$code]);
echo "  IT programmes switched on" . PHP_EOL;

echo "  Drafted skills for " . suggestMapping($db) . " course units (review on the Curriculum page)" . PHP_EOL;

// Second sync brings in the IT students and their results, then builds profiles
$r = Sync::run();
if (!$r['success']) exit("  Sync failed: {$r['error']}" . PHP_EOL);
echo "  Synced {$r['students']} students, {$r['lecturers']} staff accounts, {$r['results']} results; {$r['profiles']} profiles computed" . PHP_EOL;
