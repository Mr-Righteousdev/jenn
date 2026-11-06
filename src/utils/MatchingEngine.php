<?php

require_once __DIR__ . '/../config/database.php';

class MatchingEngine {
    // A required skill below this level counts as a gap
    const GAP_THRESHOLD = 60;

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

    // Average student level across every skill the job's text mentions (missing skills count as 0).
    // Measured against the job's needs, not the student's skill count, so students with fewer
    // skills recorded aren't rewarded for it.
    private function keywordMatch(int $studentId, int $jobId): float {
        $stmt = $this->db->prepare("SELECT description, title FROM jobs WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job || empty($job['description'])) return 0;
        $text = strtolower($job['title'] . ' ' . strip_tags($job['description']));

        $stmt = $this->db->prepare("
            SELECT sk.skill_name, COALESCE(ss.skill_level, 0) AS level
            FROM skills sk LEFT JOIN student_skills ss ON ss.skill_id = sk.skill_id AND ss.student_id = ?
        ");
        $stmt->execute([$studentId]);

        $levels = [];
        foreach ($stmt->fetchAll() as $sk) {
            // Whole-word match so "C" or "Java" don't fire inside other words
            if (preg_match('/(?<![\w+#])' . preg_quote(strtolower($sk['skill_name']), '/') . '(?![\w+#])/', $text)) {
                $levels[] = (int)$sk['level'];
            }
        }
        return $levels ? round(array_sum($levels) / count($levels), 2) : 0;
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
            $threshold = self::GAP_THRESHOLD;
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
}
