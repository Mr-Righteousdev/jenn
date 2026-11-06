<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MatchingEngine.php';

// Turns university results into skill levels.
//
// academic level of a skill = average of the student's best mark in each course unit that builds
//                             that skill, weighted by (mapping weight × credit units)
// final level               = 75% academic + 25% lecturer evidence when both exist, otherwise whichever exists
class Competency {
    const EVIDENCE_SHARE = 0.25;

    /** Rebuilds student_skills, CBC levels and job matches. Pass student ids to limit the work, or null for everyone. */
    public static function recompute(?array $studentIds = null): int {
        $db = Database::getConnection();
        $ids = $studentIds ?? $db->query("SELECT student_id FROM students")->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return 0;
        $in = implode(',', array_map('intval', $ids));

        $academic = [];
        foreach ($db->query("
            SELECT b.student_id, cus.skill_id,
                   ROUND(SUM(b.mark * cus.weight * cu.credit_units) / SUM(cus.weight * cu.credit_units)) AS level
            FROM (SELECT student_id, course_code, MAX(mark) AS mark FROM student_results
                  WHERE student_id IN ($in) AND mark IS NOT NULL GROUP BY student_id, course_code) b
            JOIN course_unit_skills cus ON cus.course_code = b.course_code
            JOIN course_units cu ON cu.code = b.course_code
            GROUP BY b.student_id, cus.skill_id") as $r) {
            $academic[$r['student_id']][$r['skill_id']] = (int)$r['level'];
        }

        $evidence = [];
        foreach ($db->query("SELECT student_id, skill_id, ROUND(AVG(level)) AS level FROM skill_evidence WHERE student_id IN ($in) GROUP BY student_id, skill_id") as $r) {
            $evidence[$r['student_id']][$r['skill_id']] = (int)$r['level'];
        }

        $levels = $db->query("SELECT level_id, min_score, max_score FROM cbc_levels ORDER BY min_score")->fetchAll();
        $del = $db->prepare("DELETE FROM student_skills WHERE student_id = ?");
        $ins = $db->prepare("INSERT INTO student_skills (student_id, skill_id, skill_level, academic_level, evidence_level, assessment_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        $setLevel = $db->prepare("UPDATE students SET cbc_level_id = ? WHERE student_id = ?");

        $db->beginTransaction();
        try {
            foreach ($ids as $sid) {
                $del->execute([$sid]);
                $a = $academic[$sid] ?? [];
                $e = $evidence[$sid] ?? [];
                $finals = [];
                foreach (array_unique(array_merge(array_keys($a), array_keys($e))) as $skillId) {
                    $ac = $a[$skillId] ?? null;
                    $ev = $e[$skillId] ?? null;
                    $final = ($ac !== null && $ev !== null)
                        ? (int)round($ac * (1 - self::EVIDENCE_SHARE) + $ev * self::EVIDENCE_SHARE)
                        : ($ac ?? $ev);
                    $finals[] = $final;
                    $ins->execute([$sid, $skillId, $final, $ac, $ev]);
                }
                $avg = $finals ? (int)round(array_sum($finals) / count($finals)) : null;
                $levelId = null;
                foreach ($levels as $l) {
                    if ($avg !== null && $avg >= $l['min_score'] && $avg <= $l['max_score']) { $levelId = $l['level_id']; break; }
                }
                $setLevel->execute([$levelId, $sid]);
            }
            $db->commit();
        } catch (Throwable $t) {
            $db->rollBack();
            throw $t;
        }

        (new MatchingEngine())->scoreMany($studentIds === null ? null : $ids);
        return count($ids);
    }

    /** Scores every student against the given (new) jobs, so they show up straight away without a full sync. */
    public static function scoreJobs(array $jobIds): int {
        if (!$jobIds) return 0;
        (new MatchingEngine())->scoreMany(null, $jobIds);
        return (int)Database::getConnection()->query("SELECT COUNT(*) FROM students")->fetchColumn();
    }
}
