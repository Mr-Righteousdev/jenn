<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MatchingEngine.php';

// Read-side view of one student: skills with where they came from, job matches and gaps.
// Everything here is derived from synced results, curriculum mapping and lecturer evidence.
class StudentProfile {
    public array $student;
    private PDO $db;
    private ?array $skills = null;

    private function __construct(array $student) {
        $this->student = $student;
        $this->db = Database::getConnection();
    }

    public static function forUser(int $userId): ?self {
        $stmt = Database::getConnection()->prepare("
            SELECT s.*, u.name, u.email, p.name AS programme_name, f.name AS faculty,
                   cl.level_name, cl.description AS level_description
            FROM students s
            JOIN users u ON u.user_id = s.user_id
            LEFT JOIN programmes p ON p.code = s.programme_code
            LEFT JOIN faculties f ON f.code = p.faculty_code
            LEFT JOIN cbc_levels cl ON cl.level_id = s.cbc_level_id
            WHERE s.user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public function id(): int { return (int)$this->student['student_id']; }

    /**
     * Every skill in the framework, keyed by skill_id, with:
     *   level / academic / evidence  (null when there is nothing yet)
     *   sources   course units already taken that build it (with the student's best mark)
     *   upcoming  course units still ahead in the curriculum that build it
     *   evidence_items  lecturer practical evidence
     */
    public function skills(): array {
        if ($this->skills !== null) return $this->skills;
        $sid = $this->id();

        $skills = [];
        $stmt = $this->db->prepare("
            SELECT sk.skill_id, sk.skill_name, sk.category, ss.skill_level AS level, ss.academic_level AS academic, ss.evidence_level AS evidence
            FROM skills sk LEFT JOIN student_skills ss ON ss.skill_id = sk.skill_id AND ss.student_id = ?
            ORDER BY sk.category, sk.skill_name");
        $stmt->execute([$sid]);
        foreach ($stmt->fetchAll() as $r) {
            $skills[$r['skill_id']] = $r + ['sources' => [], 'upcoming' => [], 'evidence_items' => []];
        }

        // Units in the student's programme curriculum that build each skill, with their best mark if taken.
        // Units they took outside the curriculum (electives, retakes elsewhere) count too.
        $stmt = $this->db->prepare("
            SELECT c.skill_id, c.weight, cu.code, cu.name, cu.credit_units,
                   COALESCE(pu.year_of_study, 0) AS year_of_study, COALESCE(pu.semester, 0) AS semester,
                   best.mark, best.grade, best.academic_year
            FROM course_unit_skills c
            JOIN course_units cu ON cu.code = c.course_code
            LEFT JOIN programme_units pu ON pu.course_code = cu.code AND pu.programme_code = ?
            LEFT JOIN (
                SELECT r.course_code, r.mark, r.grade, r.academic_year FROM student_results r
                WHERE r.student_id = ? AND r.mark = (SELECT MAX(r2.mark) FROM student_results r2 WHERE r2.student_id = r.student_id AND r2.course_code = r.course_code)
            ) best ON best.course_code = cu.code
            WHERE pu.course_code IS NOT NULL OR best.course_code IS NOT NULL
            ORDER BY year_of_study, semester, c.weight DESC");
        $stmt->execute([$this->student['programme_code'] ?? '', $sid]);
        foreach ($stmt->fetchAll() as $u) {
            if (!isset($skills[$u['skill_id']])) continue;
            // Units not yet taken that are behind the student's current year aren't "still ahead"
            if ($u['mark'] === null && $u['year_of_study'] < (int)$this->student['year_of_study']) continue;
            $skills[$u['skill_id']][$u['mark'] !== null ? 'sources' : 'upcoming'][] = $u;
        }

        $stmt = $this->db->prepare("
            SELECT e.skill_id, e.level, e.notes, e.recorded_on, u.name AS lecturer
            FROM skill_evidence e JOIN users u ON u.user_id = e.lecturer_id WHERE e.student_id = ? ORDER BY e.recorded_on DESC");
        $stmt->execute([$sid]);
        foreach ($stmt->fetchAll() as $e) $skills[$e['skill_id']]['evidence_items'][] = $e;

        return $this->skills = $skills;
    }

    public function categoryLevels(): array {
        $cats = [];
        foreach ($this->skills() as $s) {
            if ($s['level'] === null) continue;
            $cats[$s['category']][] = (int)$s['level'];
        }
        return array_map(fn($l) => (int)round(array_sum($l) / count($l)), $cats);
    }

    /** All jobs with this student's scores, best first. Jobs with no mapped skills are flagged. */
    public function matches(): array {
        $stmt = $this->db->prepare("
            SELECT j.job_id, j.title, j.company, j.source, j.url, j.scraped_date,
                   jr.match_score, jr.weighted_score, jr.keyword_score,
                   (SELECT COUNT(*) FROM job_skills js WHERE js.job_id = j.job_id) AS required
            FROM jobs j LEFT JOIN job_readiness jr ON jr.job_id = j.job_id AND jr.student_id = ?
            ORDER BY (SELECT COUNT(*) FROM job_skills js WHERE js.job_id = j.job_id) > 0 DESC, jr.match_score DESC, j.job_id DESC");
        $stmt->execute([$this->id()]);
        return $stmt->fetchAll();
    }

    /** What a job asks for, against this student's levels. */
    public function requirements(int $jobId): array {
        $stmt = $this->db->prepare("SELECT js.skill_id, MAX(js.weight) AS weight FROM job_skills js WHERE js.job_id = ? GROUP BY js.skill_id ORDER BY weight DESC");
        $stmt->execute([$jobId]);
        $skills = $this->skills();
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $s = $skills[$r['skill_id']] ?? null;
            if (!$s) continue;
            $level = (int)($s['level'] ?? 0);
            $out[] = $s + ['weight' => (float)$r['weight'], 'meets' => $level >= MatchingEngine::GAP_THRESHOLD];
        }
        return $out;
    }

    /**
     * Skills holding the student back across their closest matches: required skills below the gap
     * threshold, ranked by how many of those jobs need them and how important they are there.
     */
    public function growthAreas(int $topJobs = 10, int $limit = 5): array {
        $jobs = array_slice(array_filter($this->matches(), fn($m) => $m['required'] > 0), 0, $topJobs);
        $areas = [];
        foreach ($jobs as $j) {
            foreach ($this->requirements((int)$j['job_id']) as $r) {
                if ($r['meets']) continue;
                $a = &$areas[$r['skill_id']];
                $a ??= $r + ['jobs' => 0, 'impact' => 0.0, 'job_titles' => []];
                $a['jobs']++;
                $a['impact'] += $r['weight'] * (MatchingEngine::GAP_THRESHOLD - (int)($r['level'] ?? 0));
                $a['job_titles'][] = $j['title'];
                unset($a);
            }
        }
        usort($areas, fn($x, $y) => $y['impact'] <=> $x['impact']);
        return array_slice($areas, 0, $limit);
    }

    /** Where to build a skill: units still ahead in the curriculum first, then a self-study search. */
    public static function learnLinks(array $skill): array {
        $links = [];
        foreach (array_slice($skill['upcoming'], 0, 2) as $u) {
            $links[] = ['type' => 'course', 'title' => "{$u['code']} {$u['name']}", 'detail' => "Year {$u['year_of_study']}, semester {$u['semester']} of your programme"];
        }
        $q = urlencode($skill['skill_name']);
        $links[] = ['type' => 'online', 'title' => "{$skill['skill_name']} courses on Coursera", 'url' => "https://www.coursera.org/search?query=$q"];
        $links[] = ['type' => 'online', 'title' => "{$skill['skill_name']} tutorials on YouTube", 'url' => "https://www.youtube.com/results?search_query=$q+tutorial"];
        return $links;
    }

    // Bands use the rounded score so a job shown as "70%" is never labelled "close"
    public static function band(?float $score): string {
        if ($score === null) return 'none';
        $score = round($score);
        return $score >= 70 ? 'ready' : ($score >= 50 ? 'close' : 'far');
    }
}
