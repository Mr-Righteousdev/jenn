<?php

require_once __DIR__ . '/../config/database.php';

class MatchingEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function calculateReadiness(int $studentId, int $jobId): array {
        $weightedScore = $this->weightedScore($studentId, $jobId);
        $keywordScore = $this->keywordMatch($studentId, $jobId);
        $hybridScore = round(($weightedScore * 0.6) + ($keywordScore * 0.4), 2);
        $gaps = $this->gapAnalysis($studentId, $jobId);

        // Store result
        $stmt = $this->db->prepare("
            INSERT INTO job_readiness (student_id, job_id, match_score, weighted_score, keyword_score, gap_analysis, report_generated)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE match_score = VALUES(match_score), weighted_score = VALUES(weighted_score),
            keyword_score = VALUES(keyword_score), gap_analysis = VALUES(gap_analysis), report_generated = CURDATE()
        ");
        $stmt->execute([$studentId, $jobId, $hybridScore, round($weightedScore, 2), round($keywordScore, 2), json_encode($gaps)]);

        return [
            'student_id' => $studentId,
            'job_id' => $jobId,
            'weighted_score' => round($weightedScore, 2),
            'keyword_score' => round($keywordScore, 2),
            'hybrid_score' => $hybridScore,
            'gaps' => $gaps,
        ];
    }

    private function weightedScore(int $studentId, int $jobId): float {
        // Get job skill requirements with weights
        $stmt = $this->db->prepare("
            SELECT js.skill_id, js.weight, sk.skill_name
            FROM job_skills js
            JOIN skills sk ON js.skill_id = sk.skill_id
            WHERE js.job_id = ?
        ");
        $stmt->execute([$jobId]);
        $requirements = $stmt->fetchAll();

        if (empty($requirements)) return 0;

        // Get student's skill levels
        $stmt = $this->db->prepare("
            SELECT skill_id, skill_level FROM student_skills WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $studentSkills = [];
        foreach ($stmt->fetchAll() as $s) {
            $studentSkills[$s['skill_id']] = $s['skill_level'];
        }

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($requirements as $req) {
            $weight = (float) ($req['weight'] ?: 0.5);
            $studentLevel = $studentSkills[$req['skill_id']] ?? 0;
            $weightedSum += $studentLevel * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight == 0) return 0;

        return ($weightedSum / $totalWeight);
    }

    private function keywordMatch(int $studentId, int $jobId): float {
        $stmt = $this->db->prepare("SELECT description, title FROM jobs WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job || empty($job['description'])) return 0;

        $stmt = $this->db->prepare("
            SELECT sk.skill_name, ss.skill_level
            FROM student_skills ss
            JOIN skills sk ON ss.skill_id = sk.skill_id
            WHERE ss.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $studentSkills = $stmt->fetchAll();
        if (empty($studentSkills)) return 0;

        $description = strtolower(strip_tags($job['description']));

        // Build a combined text from job title + description
        $combined = strtolower($job['title']) . ' ' . $description;

        $matchedCount = 0;
        $totalSkills = count($studentSkills);
        $weightedSum = 0;

        foreach ($studentSkills as $sk) {
            $skillName = strtolower($sk['skill_name']);
            $level = $sk['skill_level'];

            // Check if skill name appears as a whole phrase in the description
            $pattern = '/' . preg_quote($skillName, '/') . '/i';
            if (preg_match($pattern, $combined)) {
                $matchedCount++;
                $weightedSum += $level;
            }
        }

        if ($totalSkills === 0) return 0;

        // Score = (matched skills / total skills) * (average level of matched skills / 100) * 100
        $coverage = $matchedCount / $totalSkills;
        $avgLevel = $matchedCount > 0 ? $weightedSum / $matchedCount : 0;

        return round(($coverage * 0.5 + ($avgLevel / 100) * 0.5) * 100, 2);
    }

    public function gapAnalysis(int $studentId, int $jobId): array {
        // Get required skills for the job
        $stmt = $this->db->prepare("
            SELECT js.skill_id, js.weight, sk.skill_name, sk.category
            FROM job_skills js
            JOIN skills sk ON js.skill_id = sk.skill_id
            WHERE js.job_id = ?
            ORDER BY js.weight DESC
        ");
        $stmt->execute([$jobId]);
        $requirements = $stmt->fetchAll();

        // Get student's skills
        $stmt = $this->db->prepare("
            SELECT skill_id, skill_level FROM student_skills WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $studentSkills = [];
        foreach ($stmt->fetchAll() as $s) {
            $studentSkills[$s['skill_id']] = $s['skill_level'];
        }

        $gaps = [];
        foreach ($requirements as $req) {
            $level = $studentSkills[$req['skill_id']] ?? 0;
            $threshold = 60; // Below 60 is considered a gap
            if ($level < $threshold) {
                $gaps[] = [
                    'skill_id' => $req['skill_id'],
                    'skill_name' => $req['skill_name'],
                    'category' => $req['category'],
                    'weight' => (float) $req['weight'],
                    'current_level' => $level,
                    'gap' => $threshold - $level,
                    'severity' => $level < 30 ? 'high' : ($level < 50 ? 'medium' : 'low'),
                ];
            }
        }

        // Sort by weight descending (most important gaps first)
        usort($gaps, fn($a, $b) => $b['weight'] <=> $a['weight']);

        return array_slice($gaps, 0, 5);
    }

    public function getCbcLevel(int $score): array {
        $stmt = $this->db->query("SELECT * FROM cbc_levels ORDER BY min_score");
        foreach ($stmt->fetchAll() as $level) {
            if ($score >= $level['min_score'] && $score <= $level['max_score']) {
                return $level;
            }
        }
        return ['level_name' => 'Unknown', 'description' => ''];
    }

    public function generateRecommendations(int $studentId, int $jobId): void {
        $gaps = $this->gapAnalysis($studentId, $jobId);

        // Clear old recommendations for this student/skill combo
        $this->db->prepare("DELETE FROM recommendations WHERE student_id = ?")->execute([$studentId]);

        $insertRec = $this->db->prepare("
            INSERT INTO recommendations (student_id, skill_id, rec_type, rec_title, rec_link, rec_description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($gaps as $gap) {
            $skill = $gap['skill_name'];
            $skillId = $gap['skill_id'];

            // Course recommendation
            $insertRec->execute([
                $studentId, $skillId, 'course',
                "Course: $skill Fundamentals",
                null,
                "Enroll in a course covering $skill basics and advanced topics to close this gap.",
            ]);

            // Online resource
            $searchQuery = urlencode("learn $skill programming tutorial");
            $insertRec->execute([
                $studentId, $skillId, 'online_resource',
                "YouTube: $skill Tutorial",
                "https://www.youtube.com/results?search_query=$searchQuery",
                "Watch tutorials on $skill to build practical skills.",
            ]);

            // Description
            $insertRec->execute([
                $studentId, $skillId, 'skill_description',
                "About $skill",
                null,
                "$skill is a key competency for this role. Focus on building hands-on projects to strengthen this area.",
            ]);
        }
    }
}
