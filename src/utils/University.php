<?php

require_once __DIR__ . '/../config/env.php';

// Read-only connector to the university's Student Information System (campus_dynamics schema).
// This is the only place that knows the SIS tables and their quirks. When the university's API is
// ready, these methods call it instead; nothing else in the app changes.
//
// What it cleans up on the way in:
//   * codes are trimmed (some have stray spaces)
//   * marks outside 0–100 and malformed academic years (e.g. "\t 2018/2019", "Rese/1") are skipped
//   * a unit's year and semester come from when students actually took it, because the curriculum
//     table files most units under year 1 semester 1
//   * emails are kept only if they look valid (most student emails don't)
class University {
    private static ?PDO $pdo = null;

    private static function db(): PDO {
        if (self::$pdo === null) {
            $host = getenv('UNI_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
            $name = getenv('UNI_DB_NAME') ?: 'campus_dynamics';
            $user = getenv('UNI_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'jm';
            $pass = getenv('UNI_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'jm';
            self::$pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Enforced by the database, not just by convention: any write attempt fails
            self::$pdo->exec('SET SESSION TRANSACTION READ ONLY');
        }
        return self::$pdo;
    }

    public static function source(): string {
        return (getenv('UNI_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost') . '/' . (getenv('UNI_DB_NAME') ?: 'campus_dynamics');
    }

    private static function in(array $values): string {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }

    private static function cleanEmail(?string $email): ?string {
        $email = trim((string)$email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    /** Valid academic year labels, newest first, e.g. ['2025/2026', '2024/2025', ...] */
    public static function academicYears(): array {
        $years = self::db()->query("SELECT DISTINCT TRIM(acad_year) y FROM acad_registration")->fetchAll(PDO::FETCH_COLUMN);
        $years = array_filter($years, fn($y) => preg_match('#^\d{4}/\d{4}$#', $y));
        rsort($years);
        return array_values($years);
    }

    public static function faculties(): array {
        // '00' is the SIS's "All" placeholder, not a real faculty
        return self::db()->query("SELECT TRIM(faculty_code) code, TRIM(faculty_name) name FROM acad_faculty WHERE TRIM(faculty_code) <> '00'")->fetchAll();
    }

    public static function programmes(): array {
        return self::db()->query("SELECT TRIM(progcode) code, NULLIF(TRIM(faculty_code), '00') faculty_code, TRIM(progname) name, couselength duration_years
                                  FROM acad_programme WHERE progname IS NOT NULL AND TRIM(progname) <> ''")->fetchAll();
    }

    public static function courseUnits(): array {
        $rows = self::db()->query("SELECT TRIM(courseID) code, TRIM(courseName) name, CreditUnit credit_units FROM acad_course
                                   WHERE courseName IS NOT NULL AND TRIM(courseName) <> ''")->fetchAll();
        $units = [];
        foreach ($rows as $r) {
            $r['credit_units'] = $r['credit_units'] > 0 ? (float)$r['credit_units'] : 3.0;
            $units[$r['code']] ??= $r; // a few codes repeat once trimmed; keep the first
        }
        return array_values($units);
    }

    /**
     * Each programme's curriculum with the year and semester students actually take each unit:
     * the most common (year, semester) in results, falling back to the curriculum table's own value.
     * Units that appear only in results are included too.
     */
    public static function programmeUnits(): array {
        $place = [];
        $q = self::db()->query("SELECT TRIM(progid) p, TRIM(courseid) c, studyyear y, semester s, COUNT(*) n FROM acad_results
                                WHERE studyyear BETWEEN 1 AND 6 AND semester BETWEEN 1 AND 3 AND progid IS NOT NULL
                                GROUP BY 1, 2, 3, 4");
        foreach ($q as $r) {
            $k = $r['p'] . '|' . $r['c'];
            if (!isset($place[$k]) || $r['n'] > $place[$k]['n']) $place[$k] = $r;
        }
        foreach (self::db()->query("SELECT TRIM(progcode) p, TRIM(course_code) c, study_year y, semester s FROM acad_programmecourses") as $r) {
            $place[$r['p'] . '|' . $r['c']] ??= $r + ['n' => 0];
        }
        return array_map(fn($r) => ['programme_code' => $r['p'], 'course_code' => $r['c'], 'year_of_study' => (int)$r['y'], 'semester' => (int)$r['s']], array_values($place));
    }

    /** Students of the given programmes registered in either of the two most recent academic years. */
    public static function students(array $programmeCodes): array {
        if (!$programmeCodes) return [];
        $recent = array_slice(self::academicYears(), 0, 2);
        $stmt = self::db()->prepare("
            SELECT TRIM(s.regno) reg_no, TRIM(CONCAT_WS(' ', s.firstname, s.othername)) name, s.email, TRIM(s.progid) programme_code,
                   (SELECT r.studyyear FROM acad_registration r WHERE r.regno = s.regno ORDER BY TRIM(r.acad_year) DESC, r.semester DESC LIMIT 1) year_of_study
            FROM acad_student s
            WHERE TRIM(s.progid) IN (" . self::in($programmeCodes) . ")
              AND s.regno IN (SELECT regno FROM acad_registration WHERE TRIM(acad_year) IN (" . self::in($recent) . "))");
        $stmt->execute([...$programmeCodes, ...$recent]);
        return array_map(fn($s) => ['email' => self::cleanEmail($s['email']), 'year_of_study' => (int)$s['year_of_study'] ?: 1] + $s, $stmt->fetchAll());
    }

    /** Results for the given students, cleaned. */
    public static function results(array $regNos): array {
        $out = [];
        foreach (array_chunk($regNos, 500) as $chunk) {
            $stmt = self::db()->prepare("SELECT TRIM(regno) reg_no, TRIM(courseid) course_code, TRIM(acad) academic_year, semester, score mark, TRIM(grade) grade
                                         FROM acad_results WHERE regno IN (" . self::in($chunk) . ") AND score BETWEEN 0 AND 100");
            $stmt->execute($chunk);
            foreach ($stmt as $r) {
                if (preg_match('#^\d{4}/\d{4}$#', $r['academic_year'])) $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Staff who use the university's admin system, with the faculty they're attached to.
     * The SIS holds usernames, not full names, for these accounts.
     */
    public static function staff(): array {
        $rows = self::db()->query("
            SELECT TRIM(u.name) username, m.Email email,
                   (SELECT TRIM(f.fax_code) FROM staff_faculties f WHERE f.user_name = u.name AND TRIM(f.fax_code) <> '00' ORDER BY f.ID LIMIT 1) faculty_code
            FROM staff_users u JOIN staff_membership m ON m.userId = u.id
            WHERE COALESCE(m.IsApproved, 1) = 1")->fetchAll();
        return array_map(fn($r) => ['email' => self::cleanEmail($r['email']), 'name' => $r['username']] + $r, $rows);
    }

    /**
     * Checks portal credentials (students: reg no; staff: username or email).
     * Returns ['username' => ...] for a valid login, or null.
     *
     * UNI_AUTH_MODE=dev accepts DEV_LOGIN_PASSWORD for any university account. It exists only
     * because the portal's hash scheme hasn't been confirmed by the university yet, and must never
     * be set in production.
     */
    public static function authenticate(string $identifier, string $password): ?array {
        $identifier = trim($identifier);
        // Usernames in the SIS sometimes carry stray whitespace, and a reg no can have several portal
        // accounts (re-created over the years), so match trimmed names and try every account found.
        foreach ([
            "SELECT TRIM(u.name) username, m.Password hash, m.PasswordKey salt FROM portal_users u JOIN portal_membership m ON m.userId = u.id WHERE TRIM(u.name) = ?",
            "SELECT TRIM(u.name) username, m.Password hash, m.PasswordKey salt FROM staff_users u JOIN staff_membership m ON m.userId = u.id WHERE TRIM(u.name) = ? OR TRIM(m.Email) = ?",
        ] as $sql) {
            $stmt = self::db()->prepare($sql);
            $stmt->execute(str_contains($sql, ' OR ') ? [$identifier, $identifier] : [$identifier]);
            foreach ($stmt->fetchAll() as $row) {
                $ok = getenv('UNI_AUTH_MODE') === 'dev'
                    ? (string)getenv('DEV_LOGIN_PASSWORD') !== '' && hash_equals((string)getenv('DEV_LOGIN_PASSWORD'), $password)
                    : self::aspNetPasswordMatches($password, (string)$row['hash'], (string)$row['salt']);
                if ($ok) return ['username' => $row['username']];
            }
        }
        return null;
    }

    /**
     * ASP.NET membership stores base64(hash(salt + UTF-16LE password)). The hash function depends on the
     * server's configuration; these are the standard ones. To be confirmed with the university's IT team.
     */
    private static function aspNetPasswordMatches(string $password, string $hash, string $salt): bool {
        $saltBytes = base64_decode($salt, true);
        if ($saltBytes === false || $hash === '') return false;
        $pw = mb_convert_encoding($password, 'UTF-16LE', 'UTF-8');
        foreach ([
            fn() => hash('sha256', $saltBytes . $pw, true),
            fn() => hash_hmac('sha256', $pw, $saltBytes, true),
            fn() => hash('sha1', $saltBytes . $pw, true),
        ] as $candidate) {
            if (hash_equals($hash, base64_encode($candidate()))) return true;
        }
        return false;
    }
}
