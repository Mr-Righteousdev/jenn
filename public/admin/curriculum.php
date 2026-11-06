<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/Competency.php';
requirePermission('curriculum.manage');

$db = Database::getConnection();
$message = '';

// Only programmes in use and inside the user's area (their faculty, or everything)
$programmes = $db->query("SELECT p.code, p.name FROM programmes p WHERE p.is_active AND " . programmeScope() . " ORDER BY p.name")->fetchAll();
$progCode = (string)($_GET['programme'] ?? ($programmes[0]['code'] ?? ''));
if ($programmes && !in_array($progCode, array_column($programmes, 'code'), true)) forbid('That programme is outside your area or not in use.');
$unitCode = (string)($_GET['unit'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_code'])) {
    checkCsrf();
    $cu = (string)$_POST['course_code'];
    $own = $db->prepare("SELECT 1 FROM programme_units WHERE course_code = ? AND programme_code = ?");
    $own->execute([$cu, $progCode]);
    if (!$own->fetchColumn()) forbid('That course unit isn\'t in this programme.');
    $db->beginTransaction();
    $db->prepare("DELETE FROM course_unit_skills WHERE course_code = ?")->execute([$cu]);
    $ins = $db->prepare("INSERT INTO course_unit_skills (course_code, skill_id, weight) VALUES (?, ?, ?)");
    foreach ($_POST['weight'] ?? [] as $skillId => $w) {
        $w = round(min(1, max(0, (float)$w)), 2);
        if ($w > 0) $ins->execute([$cu, (int)$skillId, $w]);
    }
    $db->commit();
    // A unit's mapping counts for everyone who took it, in any programme, so recompute exactly those students
    $ids = $db->prepare("SELECT DISTINCT student_id FROM student_results WHERE course_code = ?");
    $ids->execute([$cu]);
    $n = Competency::recompute($ids->fetchAll(PDO::FETCH_COLUMN));
    $message = "Mapping saved. $n student profiles recalculated.";
    $unitCode = $cu;
}

$units = $db->prepare("
    SELECT cu.code, cu.name, cu.credit_units, pu.year_of_study, pu.semester,
           (SELECT COUNT(DISTINCT r.student_id) FROM student_results r JOIN students s USING(student_id)
             WHERE r.course_code = cu.code AND s.programme_code = pu.programme_code) AS students,
           (SELECT COUNT(*) FROM programme_units o WHERE o.course_code = cu.code) AS programmes
    FROM programme_units pu JOIN course_units cu ON cu.code = pu.course_code
    WHERE pu.programme_code = ? ORDER BY pu.year_of_study, pu.semester, cu.code");
$units->execute([$progCode]);
$units = $units->fetchAll();
// Units nobody in this programme has results for are usually old or optional; list them last
$taken = array_filter($units, fn($u) => $u['students'] > 0);
$untaken = array_filter($units, fn($u) => $u['students'] == 0);

$links = [];
foreach ($db->query("SELECT c.course_code, c.skill_id, c.weight, sk.skill_name FROM course_unit_skills c JOIN skills sk USING(skill_id) ORDER BY c.weight DESC") as $l) {
    $links[$l['course_code']][$l['skill_id']] = $l;
}
$skills = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();

// Which skills does this programme's curriculum build, and which do employers want that it doesn't?
$taught = [];
foreach ($taken as $u) foreach ($links[$u['code']] ?? [] as $sid => $l) $taught[$sid] = ($taught[$sid] ?? 0) + 1;
$demand = $db->query("SELECT skill_id, COUNT(*) AS n FROM job_skills GROUP BY skill_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$notTaught = array_filter($skills, fn($s) => !isset($taught[$s['skill_id']]));
$mappedUnits = count(array_filter($taken, fn($u) => !empty($links[$u['code']])));

$editing = null;
foreach ($units as $u) if ($u['code'] === $unitCode) $editing = $u;
$byTerm = [];
foreach ($taken as $u) $byTerm["Year {$u['year_of_study']} · Semester {$u['semester']}"][] = $u;
if ($untaken) $byTerm['Listed in the curriculum but no current student has taken them'] = array_values($untaken);
$weights = ['0' => 'None', '0.25' => 'Light', '0.5' => 'Moderate', '0.75' => 'Strong', '1' => 'Core'];
$weightLabel = fn(float $w) => $w >= 0.9 ? 'Core' : ($w >= 0.65 ? 'Strong' : ($w >= 0.4 ? 'Moderate' : 'Light'));
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Curriculum mapping</h1>
        <p>Say which skills each course unit in a programme builds. Students' marks in a unit then count towards those skills, weighted by how central the skill is to it.</p>
    </div>
    <?php if ($programmes): ?>
        <form class="page-actions" method="GET">
            <select name="programme" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Programme">
                <?php foreach ($programmes as $pr): ?>
                    <option value="<?= htmlspecialchars($pr['code']) ?>" <?= $pr['code'] === $progCode ? 'selected' : '' ?>><?= htmlspecialchars(nice($pr['name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</header>
<?= flash($message) ?>

<?php if (!$programmes): ?>
    <section class="panel"><div class="empty">No programmes in your area are in use yet. Switch one on from the <a href="<?= BASE_URL ?>/admin/university.php">University data</a> page.</div></section>
<?php else: ?>

<section class="kpis kpis-3" aria-label="Mapping coverage">
    <div class="kpi"><span class="kpi-label">Units students take, mapped</span><span class="kpi-value"><?= $mappedUnits ?><small>/<?= count($taken) ?></small></span></div>
    <div class="kpi"><span class="kpi-label">Skills the curriculum builds</span><span class="kpi-value"><?= count($taught) ?><small>/<?= count($skills) ?></small></span></div>
    <div class="kpi"><span class="kpi-label">Skills not taught</span><span class="kpi-value<?= $notTaught ? ' is-warn' : '' ?>"><?= count($notTaught) ?></span></div>
</section>

<?php if ($notTaught): ?>
    <div class="flash flash-warn alert-static align-items-start">
        <?= icon('alert') ?>
        <div>
            <strong>No course unit builds these skills,</strong> so students can only show them through lecturer evidence:
            <?php foreach ($notTaught as $s): ?>
                <span class="tag ms-1"><?= htmlspecialchars($s['skill_name']) ?><?= !empty($demand[$s['skill_id']]) ? ' · ' . $demand[$s['skill_id']] . ' jobs ask' : '' ?></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="<?= $editing ? 'col-lg-7' : 'col-12' ?>">
        <section class="panel">
            <?php foreach ($byTerm as $term => $list): ?>
                <h3 class="term-head"><?= $term ?></h3>
                <ul class="unit-list">
                    <?php foreach ($list as $u): $l = $links[$u['code']] ?? []; ?>
                        <li class="<?= $u['code'] === $unitCode ? 'is-current' : '' ?>">
                            <a href="?programme=<?= urlencode($progCode) ?>&unit=<?= urlencode($u['code']) ?>#editor" class="unit-row">
                                <span class="unit-code"><?= htmlspecialchars($u['code']) ?></span>
                                <span class="unit-name"><?= htmlspecialchars(nice($u['name'])) ?><span><?= (float)$u['credit_units'] ?> CU · <?= $u['students'] ?> students with results<?= $u['programmes'] > 1 ? ' · in ' . $u['programmes'] . ' programmes' : '' ?></span></span>
                                <span class="unit-skills">
                                    <?php if (!$l): ?>
                                        <span class="tag tag-warn"><?= icon('alert', 12) ?> Not mapped</span>
                                    <?php else: foreach ($l as $s): ?>
                                        <span class="tag <?= $s['weight'] >= 0.9 ? 'tag-brand' : '' ?>" title="<?= $weightLabel((float)$s['weight']) ?> (<?= $s['weight'] ?>)"><?= htmlspecialchars($s['skill_name']) ?></span>
                                    <?php endforeach; endif; ?>
                                </span>
                                <span class="unit-edit"><?= icon('edit', 14) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </section>
    </div>

    <?php if ($editing): $cur = $links[$editing['code']] ?? []; ?>
        <div class="col-lg-5" id="editor">
            <section class="panel form-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <h2 class="panel-title mb-0"><?= htmlspecialchars($editing['code']) ?> · <?= htmlspecialchars(nice($editing['name'])) ?></h2>
                    <a href="?programme=<?= urlencode($progCode) ?>" class="icon-btn" aria-label="Close editor" title="Close">×</a>
                </div>
                <p class="small text-muted">How strongly does this unit build each skill? Leave a skill at None if it doesn't.<?php if ($editing['programmes'] > 1): ?>
                    <strong>This unit is in <?= $editing['programmes'] ?> programmes; the mapping applies to all of them.</strong><?php endif; ?></p>
                <form method="POST" action="?programme=<?= urlencode($progCode) ?>&unit=<?= urlencode($editing['code']) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="course_code" value="<?= htmlspecialchars($editing['code']) ?>">
                    <div class="map-editor">
                        <?php $cat = null; foreach ($skills as $s):
                            $w = isset($cur[$s['skill_id']]) ? (float)$cur[$s['skill_id']]['weight'] : 0;
                            // Pre-select the closest named weight
                            $near = array_reduce(array_keys($weights), fn($best, $v) => abs($w - (float)$v) < abs($w - (float)$best) ? $v : $best, '0'); ?>
                            <?php if ($s['category'] !== $cat): $cat = $s['category']; ?><div class="map-cat"><?= htmlspecialchars($cat) ?></div><?php endif; ?>
                            <label class="map-row <?= $w > 0 ? 'is-on' : '' ?>">
                                <span><?= htmlspecialchars($s['skill_name']) ?></span>
                                <select name="weight[<?= $s['skill_id'] ?>]" class="form-select form-select-sm" onchange="this.closest('.map-row').classList.toggle('is-on', this.value !== '0')">
                                    <?php foreach ($weights as $v => $label): ?>
                                        <option value="<?= $v ?>" <?= (string)$v === (string)$near ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Save and recalculate profiles</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
