<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/Competency.php';
requirePermission('evidence.record');

// Lecturers don't mark here; grades come from the university system. This page records
// practical evidence the results can't show (projects, internships, soft skills), which
// counts for 25% of a skill level next to the academic level.
$db = Database::getConnection();
$me = getCurrentUser();
$message = '';
$selectedId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

$students = $db->prepare("
    SELECT s.student_id, s.registration_no, s.year_of_study, p.name AS programme, u.name
    FROM students s JOIN users u ON u.user_id = s.user_id JOIN programmes p ON p.code = s.programme_code
    WHERE " . studentScope() . " ORDER BY s.year_of_study DESC, u.name");
$students->execute();
$students = $students->fetchAll();
$allowed = array_column($students, 'student_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($selectedId, $allowed, true)) {
    checkCsrf();
    $up = $db->prepare("INSERT INTO skill_evidence (student_id, skill_id, lecturer_id, level, notes, recorded_on) VALUES (?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE level = VALUES(level), notes = VALUES(notes), recorded_on = CURDATE()");
    $del = $db->prepare("DELETE FROM skill_evidence WHERE student_id = ? AND skill_id = ? AND lecturer_id = ?");
    $db->beginTransaction();
    foreach ($_POST['evidence'] ?? [] as $skillId => $e) {
        $level = trim($e['level'] ?? '');
        $level === ''
            ? $del->execute([$selectedId, (int)$skillId, $me['user_id']])
            : $up->execute([$selectedId, (int)$skillId, $me['user_id'], min(100, max(0, (int)$level)), trim($e['notes'] ?? '') ?: null]);
    }
    $db->commit();
    Competency::recompute([$selectedId]);
    $message = 'Evidence saved and the student\'s profile recalculated.';
}

$student = null;
$profile = [];
if (in_array($selectedId, $allowed, true)) {
    foreach ($students as $s) if ($s['student_id'] === $selectedId) $student = $s;
    $stmt = $db->prepare("
        SELECT sk.skill_id, sk.skill_name, sk.category, ss.skill_level, ss.academic_level, ss.evidence_level, e.level AS my_level, e.notes AS my_notes
        FROM skills sk
        LEFT JOIN student_skills ss ON ss.skill_id = sk.skill_id AND ss.student_id = ?
        LEFT JOIN skill_evidence e ON e.skill_id = sk.skill_id AND e.student_id = ? AND e.lecturer_id = ?
        ORDER BY sk.category, sk.skill_name");
    $stmt->execute([$selectedId, $selectedId, $me['user_id']]);
    $profile = $stmt->fetchAll();
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Practical evidence</h1>
        <p>Marks come from the university system. Use this to add what results can't show, such as project work, internship feedback or soft skills. Evidence counts for 25% of a skill level.</p>
    </div>
</header>
<?= flash($message) ?>

<div class="row g-3">
    <div class="col-lg-4">
        <section class="panel form-panel">
            <h2 class="panel-title"><?= count($students) ?> students</h2>
            <p class="small text-muted mt-n2">In <?= htmlspecialchars(scopeLabel()) ?></p>
            <label class="search mb-2 w-100" style="max-width: none">
                <?= icon('search') ?>
                <input type="search" class="form-control" placeholder="Search name or reg no" aria-label="Search students"
                       oninput="const q = this.value.toLowerCase(); document.querySelectorAll('.student-pick li').forEach(li => li.hidden = !li.dataset.q.includes(q))">
            </label>
            <ul class="student-pick">
                <?php foreach ($students as $s): ?>
                    <li data-q="<?= htmlspecialchars(strtolower($s['name'] . ' ' . $s['registration_no'])) ?>">
                        <a href="?student_id=<?= $s['student_id'] ?>" class="<?= $s['student_id'] === $selectedId ? 'is-current' : '' ?>">
                            <strong><?= htmlspecialchars(nice($s['name'])) ?></strong>
                            <span><?= htmlspecialchars($s['registration_no']) ?> · <?= htmlspecialchars(nice($s['programme'])) ?> year <?= $s['year_of_study'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$students): ?><div class="empty">No students synced for your area yet.</div><?php endif; ?>
        </section>
    </div>

    <div class="col-lg-8">
        <?php if (!$student): ?>
            <section class="panel"><div class="empty">Choose a student to see their competency profile and add evidence.</div></section>
        <?php else: ?>
            <section class="panel">
                <h2 class="panel-title mb-1"><?= htmlspecialchars(nice($student['name'])) ?></h2>
                <p class="small text-muted mb-3"><?= htmlspecialchars($student['registration_no']) ?> · <?= htmlspecialchars(nice($student['programme'])) ?> year <?= $student['year_of_study'] ?>. Leave a level blank to add no evidence for that skill.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                    <div class="table-responsive">
                        <table class="table data-table mb-0">
                            <thead><tr><th>Skill</th><th class="text-end">From results</th><th class="text-end">Current level</th><th style="width: 110px">Your evidence</th><th>Note</th></tr></thead>
                            <tbody>
                                <?php $cat = null; foreach ($profile as $p): ?>
                                    <?php if ($p['category'] !== $cat): $cat = $p['category']; ?>
                                        <tr class="group-row"><td colspan="5"><?= htmlspecialchars($cat) ?></td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['skill_name']) ?></strong></td>
                                        <td class="text-end num"><?= $p['academic_level'] ?? '<span class="text-muted" title="No mapped course unit results">—</span>' ?></td>
                                        <td class="text-end num"><?= $p['skill_level'] !== null ? '<strong>' . $p['skill_level'] . '</strong>' : '<span class="text-muted">—</span>' ?></td>
                                        <td><input type="number" min="0" max="100" name="evidence[<?= $p['skill_id'] ?>][level]" value="<?= $p['my_level'] ?>" class="form-control form-control-sm" aria-label="Evidence level for <?= htmlspecialchars($p['skill_name']) ?>" placeholder="0–100"></td>
                                        <td><input type="text" name="evidence[<?= $p['skill_id'] ?>][notes]" value="<?= htmlspecialchars($p['my_notes'] ?? '') ?>" class="form-control form-control-sm" aria-label="Note for <?= htmlspecialchars($p['skill_name']) ?>" placeholder="e.g. internship supervisor report"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Save evidence</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
