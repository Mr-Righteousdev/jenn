<?php

require_once __DIR__ . '/../config/database.php';

function importStudentResults(string $csvContent): array {
    $db = Database::getConnection();
    $lines = explode("\n", trim($csvContent));
    $errors = [];
    $imported = 0;

    $headers = str_getcsv(array_shift($lines));
    $headerMap = array_flip(array_map('strtolower', $headers));

    $required = ['registration_no', 'course_code', 'grade'];
    foreach ($required as $r) {
        if (!isset($headerMap[$r])) {
            return ['success' => false, 'error' => "Missing required column: $r. Found: " . implode(', ', array_keys($headerMap))];
        }
    }

    $db->beginTransaction();
    try {
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $fields = str_getcsv($line);
            if (count($fields) !== count($headers)) {
                $errors[] = "Line " . ($lineNum + 2) . ": column count mismatch";
                continue;
            }

            $row = array_combine(array_map('strtolower', $headers), $fields);

            $regNo = trim($row['registration_no']);
            $courseCode = trim($row['course_code']);
            $grade = strtoupper(trim($row['grade']));

            if (empty($regNo) || empty($courseCode) || empty($grade)) {
                $errors[] = "Line " . ($lineNum + 2) . ": empty required field";
                continue;
            }

            // Find or create student
            $stmt = $db->prepare("SELECT student_id, user_id FROM students WHERE registration_no = ?");
            $stmt->execute([$regNo]);
            $student = $stmt->fetch();

            if (!$student) {
                $name = trim($row['name'] ?? $regNo);
                $email = trim($row['email'] ?? $regNo . '@student.import');
                $course = trim($row['course'] ?? 'Unassigned');
                $year = (int)($row['year_of_study'] ?? 1);

                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
                $hash = password_hash('student123', PASSWORD_DEFAULT);
                $stmt->execute([$name, $email, $hash]);
                $userId = (int) $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO students (user_id, registration_no, course, year_of_study) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $regNo, $course, $year]);
                $studentId = (int) $db->lastInsertId();
            } else {
                $studentId = $student['student_id'];
            }

            $courseName = trim($row['course_name'] ?? $courseCode);
            $semester = (int)($row['semester'] ?? 1);
            $academicYear = trim($row['academic_year'] ?? date('Y'));

            $stmt = $db->prepare("INSERT INTO student_results (student_id, course_code, course_name, grade, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$studentId, $courseCode, $courseName, $grade, $semester, $academicYear]);

            // Auto-map grade to skill level
            $gradeMap = ['A' => 90, 'B' => 75, 'C' => 60, 'D' => 45, 'F' => 25];
            if (isset($gradeMap[$grade[0]])) {
                $skillLevel = $gradeMap[$grade[0]];
            } else {
                $skillLevel = 50;
            }

            // Match course name to skill category (e.g., "Database Management I" -> "Database Management")
            $skill = null;
            $keywords = [
                'Program' => 'Programming',
                'Database' => 'Database Management',
                'Web' => 'Web Development',
                'Server' => 'Server Configuration',
                'Network' => 'Networking',
                'Linux' => 'Server Configuration',
                'Soft Skill' => 'Soft Skills',
                'Communic' => 'Soft Skills',
            ];
            foreach ($keywords as $keyword => $category) {
                if (stripos($courseName, $keyword) !== false || stripos($courseCode, $keyword) !== false) {
                    $stmt = $db->prepare("SELECT skill_id FROM skills WHERE category = ? LIMIT 1");
                    $stmt->execute([$category]);
                    $skill = $stmt->fetch();
                    if ($skill) break;
                }
            }

            // Fallback: try matching skill_name directly
            if (!$skill) {
                $likeSkill = '%' . $courseCode . '%';
                $stmt = $db->prepare("SELECT skill_id FROM skills WHERE skill_name LIKE ? LIMIT 1");
                $stmt->execute([$likeSkill]);
                $skill = $stmt->fetch();
            }

            if ($skill) {
                $stmt = $db->prepare("INSERT INTO student_skills (student_id, skill_id, skill_level, assessed_by, assessment_date) VALUES (?, ?, ?, NULL, CURDATE()) ON DUPLICATE KEY UPDATE skill_level = GREATEST(skill_level, VALUES(skill_level))");
                $stmt->execute([$studentId, $skill['skill_id'], $skillLevel]);
            }

            $imported++;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }

    return ['success' => true, 'imported' => $imported, 'errors' => $errors];
}
