<?php
// Loads the university's academic data from a full SIS dump into a local database that stands in
// for the live university system until the API is ready. Only the tables this app reads are copied;
// HR, payroll, admissions and other personal data in the dump are skipped.
//
//   php src/scripts/load_university_dump.php path/to/passbook.sql
//
// The target database is UNI_DB_NAME (default campus_dynamics). It is dropped and rebuilt.

require_once __DIR__ . '/../config/env.php';

$dump = $argv[1] ?? __DIR__ . '/../../passbook.sql';
if (!is_readable($dump)) exit("Can't read $dump" . PHP_EOL);

$host = getenv('UNI_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$name = getenv('UNI_DB_NAME') ?: 'campus_dynamics';
$user = getenv('UNI_DB_LOADER_USERNAME') ?: getenv('DB_USERNAME') ?: 'jm';
$pass = getenv('UNI_DB_LOADER_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'jm';

// [schema in the dump, table] => table name here. The admin-system and student-portal logins share
// table names, so they are renamed.
$keep = [
    'campus_dynamics' => [
        'acad_faculty' => 'acad_faculty', 'acad_programme' => 'acad_programme', 'acad_course' => 'acad_course',
        'acad_programmecourses' => 'acad_programmecourses', 'acad_student' => 'acad_student',
        'acad_registration' => 'acad_registration', 'acad_results' => 'acad_results',
        'my_aspnet_users' => 'staff_users', 'my_aspnet_membership' => 'staff_membership',
        'my_aspnet_user_faculties' => 'staff_faculties',
    ],
    'campus_dynamics_portal' => [
        'my_aspnet_users' => 'portal_users', 'my_aspnet_membership' => 'portal_membership',
    ],
];

$pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$name`");
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0, UNIQUE_CHECKS = 0, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

$fh = fopen($dump, 'r');
$db = null; $src = null; $dst = null; $done = []; $stmt = '';
while (($line = fgets($fh)) !== false) {
    if (str_starts_with($line, 'USE ')) { $db = trim(substr($line, 4), " ;\r\n`"); $dst = null; continue; }
    if (preg_match('/^-- (?:Definition of table|Dumping data for table) `([^`]+)`/', $line, $m)) {
        $src = $m[1];
        $dst = $keep[$db][$src] ?? null;
        continue;
    }
    if (!$dst) continue;
    // The dump repeats some tables; keep only the first copy of each
    if (str_starts_with($line, 'CREATE TABLE')) {
        if (isset($done[$dst])) { $dst = null; continue; }
        $done[$dst] = true;
    }
    if (str_starts_with($line, '/*!') || str_starts_with($line, '--') || trim($line) === '') continue;
    $stmt .= str_replace("`$src`", "`$dst`", $line);
    if (str_ends_with(rtrim($line), ';')) {
        $pdo->exec($stmt);
        $stmt = '';
    }
}
fclose($fh);

echo "Loaded into '$name':" . PHP_EOL;
foreach (array_keys($done) as $t) {
    printf("  %-24s %d rows%s", $t, $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(), PHP_EOL);
}
