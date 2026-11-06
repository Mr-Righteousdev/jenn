<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';
require_once __DIR__ . '/../src/utils/StudentProfile.php';
requireStudent();

$profile = StudentProfile::forUser($_SESSION['user_id']);
if (!$profile) die('Your student profile has not been synced yet. Contact the career office.');
$st = $profile->student;
$skills = $profile->skills();
$withLevel = array_filter($skills, fn($s) => $s['level'] !== null);
$avg = $withLevel ? (int)round(array_sum(array_column($withLevel, 'level')) / count($withLevel)) : null;
$evidenceCount = array_sum(array_map(fn($s) => count($s['evidence_items']), $skills));
$levels = Database::getConnection()->query("SELECT level_name, min_score, max_score FROM cbc_levels ORDER BY min_score")->fetchAll();
$byCat = [];
foreach ($skills as $s) $byCat[$s['category']][] = $s;
$weightLabel = fn(float $w) => $w >= 0.9 ? 'core' : ($w >= 0.65 ? 'strong' : ($w >= 0.4 ? 'moderate' : 'light'));
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>My skills</h1>
        <p><?= htmlspecialchars(nice($st['programme_name'] ?? $st['course'])) ?> · Year <?= $st['year_of_study'] ?> · <?= htmlspecialchars($st['registration_no']) ?>. Levels come from your university results, plus any practical evidence from your lecturers.</p>
    </div>
    <div class="page-actions"><a href="<?= BASE_URL ?>/report.php" class="btn btn-outline-primary btn-sm"><?= icon('print', 14) ?> Readiness report</a></div>
</header>

<section class="kpis kpis-4" aria-label="Profile summary">
    <div class="kpi"><span class="kpi-label">CBC level</span><span class="kpi-value kpi-word"><?= htmlspecialchars($st['level_name'] ?? 'Not set') ?></span><span class="kpi-sub"><?= htmlspecialchars($st['level_description'] ?? 'No results mapped yet') ?></span></div>
    <div class="kpi"><span class="kpi-label">Average skill level</span><span class="kpi-value"><?= $avg ?? '—' ?><small>/100</small></span><span class="kpi-sub">across skills with a level</span></div>
    <div class="kpi"><span class="kpi-label">Skills with a level</span><span class="kpi-value"><?= count($withLevel) ?><small>/<?= count($skills) ?></small></span><span class="kpi-sub"><?= count($skills) - count($withLevel) ?> still ahead in your programme</span></div>
    <div class="kpi"><span class="kpi-label">Practical evidence</span><span class="kpi-value"><?= $evidenceCount ?></span><span class="kpi-sub">from lecturers</span></div>
</section>

<?php if ($avg !== null): ?>
<section class="panel mb-3">
    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-3">
        <h2 class="panel-title mb-0">Where you are on the CBC scale</h2>
        <span class="small text-muted">Average level <?= $avg ?></span>
    </div>
    <div class="cbc-track">
        <?php foreach ($levels as $l): $on = $avg >= $l['min_score'] && $avg <= $l['max_score']; ?>
            <div class="cbc-step<?= $on ? ' is-on' : '' ?>" style="flex: <?= max(1, $l['max_score'] - $l['min_score'] + 1) ?>">
                <span><?= htmlspecialchars($l['level_name']) ?></span><small><?= $l['min_score'] ?>–<?= $l['max_score'] ?></small>
            </div>
        <?php endforeach; ?>
        <span class="cbc-pin" style="left: <?= $avg ?>%" aria-hidden="true"></span>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <header class="panel-head">
        <div><h3>Skill by skill</h3><p>Open a skill to see the course units and evidence behind it.</p></div>
        <div class="legend-inline" aria-hidden="true"><span><i class="lg lg-ok"></i>60+ meets most jobs</span><span><i class="lg"></i>below 60</span></div>
    </header>
    <?php foreach ($byCat as $cat => $list): ?>
        <h4 class="term-head"><?= htmlspecialchars($cat) ?></h4>
        <div class="skill-list">
            <?php foreach ($list as $s): $lvl = $s['level']; ?>
                <details class="skill-item">
                    <summary>
                        <span class="skill-name"><?= htmlspecialchars($s['skill_name']) ?></span>
                        <span class="skill-meter">
                            <?php if ($lvl !== null): ?>
                                <span class="meter"><span class="<?= $lvl >= MatchingEngine::GAP_THRESHOLD ? 'is-ready' : '' ?>" style="width: <?= $lvl ?>%"></span></span>
                                <span class="meter-val"><?= $lvl ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Not yet: <?= $s['upcoming'] ? 'taught in year ' . $s['upcoming'][0]['year_of_study'] : 'not in your curriculum' ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="skill-source small text-muted">
                            <?php if ($s['academic'] !== null): ?><?= count($s['sources']) ?> unit<?= count($s['sources']) === 1 ? '' : 's' ?><?php endif; ?>
                            <?php if ($s['evidence'] !== null): ?><?= $s['academic'] !== null ? ' + ' : '' ?>evidence<?php endif; ?>
                        </span>
                        <span class="chev-sm" aria-hidden="true"></span>
                    </summary>
                    <div class="skill-body">
                        <?php if ($s['academic'] !== null && $s['evidence'] !== null): ?>
                            <p class="small mb-2"><strong><?= $lvl ?></strong> = 75% of <?= $s['academic'] ?> from results + 25% of <?= $s['evidence'] ?> from practical evidence.</p>
                        <?php endif; ?>
                        <?php if ($s['sources']): ?>
                            <h5>From your results</h5>
                            <table class="table data-table mb-2">
                                <thead><tr><th>Course unit</th><th>Taken</th><th>How much it builds this</th><th class="text-end">Your mark</th></tr></thead>
                                <tbody>
                                    <?php foreach ($s['sources'] as $u): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($u['code']) ?></strong> <?= htmlspecialchars($u['name']) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars($u['academic_year']) ?>, sem <?= $u['semester'] ?></td>
                                            <td><span class="tag <?= $u['weight'] >= 0.9 ? 'tag-brand' : '' ?>"><?= $weightLabel((float)$u['weight']) ?></span></td>
                                            <td class="text-end num"><strong><?= rtrim(rtrim($u['mark'], '0'), '.') ?></strong> <span class="text-muted">(<?= htmlspecialchars($u['grade']) ?>)</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if ($s['evidence_items']): ?>
                            <h5>Practical evidence</h5>
                            <ul class="evidence-list">
                                <?php foreach ($s['evidence_items'] as $e): ?>
                                    <li><strong><?= $e['level'] ?></strong> from <?= htmlspecialchars($e['lecturer']) ?><?= $e['notes'] ? ': ' . htmlspecialchars($e['notes']) : '' ?> <span class="text-muted small">· <?= date('j M Y', strtotime($e['recorded_on'])) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($s['upcoming']): ?>
                            <h5>Still ahead</h5>
                            <ul class="evidence-list">
                                <?php foreach ($s['upcoming'] as $u): ?>
                                    <li><strong><?= htmlspecialchars($u['code']) ?></strong> <?= htmlspecialchars($u['name']) ?> <span class="text-muted small">· year <?= $u['year_of_study'] ?>, semester <?= $u['semester'] ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!$s['sources'] && !$s['evidence_items'] && !$s['upcoming']): ?>
                            <p class="small text-muted mb-0">No course unit in your programme builds this skill. Practical work your lecturers record, or self-study, is how to show it.</p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
