<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/University.php';
require_once __DIR__ . '/Competency.php';

// Copies what we need from the university SIS into our database. One direction only:
// the SIS is never written to. Run it from the admin "University data" page or from cron:
//   php src/scripts/sync.php
//
// Structure (faculties, programmes, course units, curricula) comes in for the whole university.
// Students and their results come in only for programmes switched on here.
class Sync {
    public static function run(): array {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO sync_log (started_at) VALUES (NOW())")->execute();
        $logId = (int)$db->lastInsertId();

        try {
            $s = ['faculties' => 0, 'programmes' => 0, 'course_units' => 0, 'students' => 0, 'lecturers' => 0, 'results' => 0];
            $db->beginTransaction();

            $up = function (string $sql, array $rows, array $cols) use ($db): int {
                $stmt = $db->prepare($sql);
                foreach ($rows as $r) $stmt->execute(array_map(fn($c) => $r[$c], $cols));
                return count($rows);
            };

            $s['faculties'] = $up("INSERT INTO faculties (code, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)",
                University::faculties(), ['code', 'name']);
            $faculties = $db->query("SELECT code FROM faculties")->fetchAll(PDO::FETCH_COLUMN);
            $programmes = array_map(fn($p) => ['faculty_code' => in_array($p['faculty_code'], $faculties, true) ? $p['faculty_code'] : null] + $p, University::programmes());
            $s['programmes'] = $up("INSERT INTO programmes (code, faculty_code, name, duration_years) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE faculty_code = VALUES(faculty_code), name = VALUES(name), duration_years = VALUES(duration_years)",
                $programmes, ['code', 'faculty_code', 'name', 'duration_years']);
            $s['course_units'] = $up("INSERT INTO course_units (code, name, credit_units) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), credit_units = VALUES(credit_units)",
                University::courseUnits(), ['code', 'name', 'credit_units']);

            // Curricula: only rows whose programme and unit both exist
            $progSet = array_flip(array_column($programmes, 'code'));
            $unitSet = array_flip($db->query("SELECT code FROM course_units")->fetchAll(PDO::FETCH_COLUMN));
            $curricula = array_filter(University::programmeUnits(), fn($r) => isset($progSet[$r['programme_code']], $unitSet[$r['course_code']]));
            $up("INSERT INTO programme_units (programme_code, course_code, year_of_study, semester) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE year_of_study = VALUES(year_of_study), semester = VALUES(semester)",
                $curricula, ['programme_code', 'course_code', 'year_of_study', 'semester']);

            // People. Accounts are keyed by username (reg no / staff username); many students have no valid email.
            // ponytail: switching a programme off stops updates but keeps its already-synced students
            $active = $db->query("SELECT code FROM programmes WHERE is_active")->fetchAll(PDO::FETCH_COLUMN);
            $facultyOf = $db->query("SELECT code, faculty_code FROM programmes")->fetchAll(PDO::FETCH_KEY_PAIR);
            $noLocalPassword = '!university'; // university accounts never sign in with a local password
            $lecturerRole = $db->query("SELECT role_id FROM roles WHERE slug = 'lecturer'")->fetchColumn() ?: null;

            // New staff start as Lecturers; a role assigned later on the Users page is never overwritten
            $upUser = $db->prepare("INSERT INTO users (name, username, email, password_hash, role, source, faculty_code, role_id) VALUES (?, ?, ?, ?, ?, 'university', ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), role = VALUES(role), source = 'university', faculty_code = VALUES(faculty_code)");
            $userId = $db->prepare("SELECT user_id FROM users WHERE username = ?");
            $upStudent = $db->prepare("INSERT INTO students (user_id, registration_no, course, year_of_study, programme_code) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE registration_no = VALUES(registration_no), course = VALUES(course), year_of_study = VALUES(year_of_study), programme_code = VALUES(programme_code)");
            $progName = $db->query("SELECT code, name FROM programmes")->fetchAll(PDO::FETCH_KEY_PAIR);
            // Emails are unique per account. A shared or reused email would make the upsert land on someone
            // else's row, so an email already owned by another username is dropped.
            $emailOwner = $db->query("SELECT LOWER(email), COALESCE(username, CONCAT('#', user_id)) FROM users WHERE email IS NOT NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
            $ownEmail = function (?string $email, string $username) use (&$emailOwner): ?string {
                if ($email === null) return null;
                if (isset($emailOwner[$email]) && $emailOwner[$email] !== $username) return null;
                $emailOwner[$email] = $username;
                return $email;
            };

            $students = University::students($active);
            foreach ($students as $st) {
                $upUser->execute([$st['name'] ?: $st['reg_no'], $st['reg_no'], $ownEmail($st['email'], $st['reg_no']), $noLocalPassword, 'student', $facultyOf[$st['programme_code']] ?? null, null]);
                $userId->execute([$st['reg_no']]);
                $upStudent->execute([$userId->fetchColumn(), $st['reg_no'], $progName[$st['programme_code']] ?? null, $st['year_of_study'], $st['programme_code']]);
                $s['students']++;
            }
            foreach (University::staff() as $sf) {
                $upUser->execute([$sf['name'], $sf['username'], $ownEmail($sf['email'], $sf['username']), $noLocalPassword, 'staff',
                                  in_array($sf['faculty_code'], $faculties, true) ? $sf['faculty_code'] : null, $lecturerRole]);
                $s['lecturers']++;
            }

            $studentByReg = $db->query("SELECT registration_no, student_id FROM students")->fetchAll(PDO::FETCH_KEY_PAIR);
            $courseName = $db->query("SELECT code, name FROM course_units")->fetchAll(PDO::FETCH_KEY_PAIR);
            $upResult = $db->prepare("INSERT INTO student_results (student_id, course_code, course_name, academic_year, semester, mark, grade) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), semester = VALUES(semester), mark = VALUES(mark), grade = VALUES(grade)");
            foreach (University::results(array_column($students, 'reg_no')) as $r) {
                if (!isset($studentByReg[$r['reg_no']], $courseName[$r['course_code']])) continue;
                $upResult->execute([$studentByReg[$r['reg_no']], $r['course_code'], $courseName[$r['course_code']], $r['academic_year'], $r['semester'], $r['mark'], $r['grade']]);
                $s['results']++;
            }
            $db->commit();

            $s['profiles'] = Competency::recompute();
            $db->prepare("UPDATE sync_log SET finished_at = NOW(), status = 'ok', summary = ? WHERE sync_id = ?")->execute([json_encode($s), $logId]);
            return ['success' => true] + $s;
        } catch (Throwable $t) {
            if ($db->inTransaction()) $db->rollBack();
            $db->prepare("UPDATE sync_log SET finished_at = NOW(), status = 'failed', summary = ? WHERE sync_id = ?")->execute([$t->getMessage(), $logId]);
            return ['success' => false, 'error' => $t->getMessage()];
        }
    }
}
