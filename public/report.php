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
$avgSkill = $withLevel ? (int)round(array_sum(array_column($withLevel, 'level')) / count($withLevel)) : null;
$scorable = array_values(array_filter($profile->matches(), fn($m) => $m['required'] > 0));
$ready = count(array_filter($scorable, fn($m) => StudentProfile::band($m['match_score']) === 'ready'));
$growth = $profile->growthAreas();
$print = isset($_GET['print']);
$status = ['ready' => 'Ready', 'close' => 'Close', 'far' => 'Stretch'];
?>
<?php if (!$print): ?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>
<?php else: ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Readiness report · <?= htmlspecialchars($st['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('/css/style.css') ?>">
    <style>body { padding: 2rem 0; } .page-actions { display: none; }</style>
</head>
<body>
<div class="container">
<?php require_once __DIR__ . '/../src/utils/ui.php'; ?>
<div class="no-print d-flex gap-2 mb-3"><button onclick="window.print()" class="btn btn-primary btn-sm">Print or save as PDF</button><a href="?" class="btn btn-outline-secondary btn-sm">Back</a></div>
<?php endif; ?>

<header class="page-head report-head">
    <div>
        <h1>Job readiness report</h1>
        <p><strong><?= htmlspecialchars(nice($st['name'])) ?></strong> · <?= htmlspecialchars($st['registration_no']) ?> · <?= htmlspecialchars(nice($st['programme_name'] ?? $st['course'])) ?>, year <?= $st['year_of_study'] ?><br>
           <?= htmlspecialchars(nice($st['faculty'] ?? '')) ?> · generated <?= date('j F Y') ?></p>
    </div>
    <div class="page-actions"><a href="?print=1" target="_blank" class="btn btn-primary btn-sm"><?= icon('print', 14) ?> Print or save as PDF</a></div>
</header>

<section class="kpis kpis-4" aria-label="Summary">
    <div class="kpi"><span class="kpi-label">CBC level</span><span class="kpi-value kpi-word"><?= htmlspecialchars($st['level_name'] ?? 'Not set') ?></span></div>
    <div class="kpi"><span class="kpi-label">Average skill level</span><span class="kpi-value"><?= $avgSkill ?? '—' ?><small>/100</small></span></div>
    <div class="kpi"><span class="kpi-label">Ready to apply</span><span class="kpi-value"><?= $ready ?><small>/<?= count($scorable) ?> jobs</small></span></div>
    <div class="kpi"><span class="kpi-label">Best match</span><span class="kpi-value"><?= $scorable ? round($scorable[0]['match_score']) : '—' ?><small>%</small></span></div>
</section>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="panel h-100">
            <h2 class="panel-title">Competency profile</h2>
            <table class="table data-table mb-0">
                <thead><tr><th>Skill</th><th class="text-end">Level</th><th>Based on</th></tr></thead>
                <tbody>
                    <?php $cat = null; foreach ($skills as $s): ?>
                        <?php if ($s['category'] !== $cat): $cat = $s['category']; ?><tr class="group-row"><td colspan="3"><?= htmlspecialchars($cat) ?></td></tr><?php endif; ?>
                        <tr>
                            <td><?= htmlspecialchars($s['skill_name']) ?></td>
                            <td class="text-end num"><strong><?= $s['level'] ?? '—' ?></strong></td>
                            <td class="small text-muted">
                                <?= $s['sources'] ? implode(', ', array_map(fn($u) => $u['code'], $s['sources'])) : '' ?>
                                <?= $s['evidence_items'] ? ($s['sources'] ? ' + ' : '') . 'lecturer evidence' : '' ?>
                                <?= !$s['sources'] && !$s['evidence_items'] ? ($s['upcoming'] ? 'not yet taught' : 'not in curriculum') : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="panel mb-3">
            <h2 class="panel-title">Top job matches</h2>
            <table class="table data-table mb-0">
                <thead><tr><th>Job</th><th class="text-end">Match</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($scorable, 0, 10) as $m): $band = StudentProfile::band($m['match_score']); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['title']) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($m['company']) ?></span></td>
                            <td class="text-end num"><strong><?= round($m['match_score']) ?>%</strong></td>
                            <td><span class="tag <?= $band === 'ready' ? 'tag-brand' : ($band === 'far' ? 'tag-warn' : '') ?>"><?= $status[$band] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$scorable): ?><tr><td colspan="3" class="empty">No scored jobs yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
        <section class="panel">
            <h2 class="panel-title">Development plan</h2>
            <?php if (!$growth): ?>
                <p class="mb-0 text-muted">No gaps across the closest matches.</p>
            <?php else: ?>
                <ol class="plan">
                    <?php foreach ($growth as $g): ?>
                        <li>
                            <strong><?= htmlspecialchars($g['skill_name']) ?></strong> <span class="text-muted small">from <?= $g['level'] ?? 0 ?> to <?= MatchingEngine::GAP_THRESHOLD ?>+ · needed by <?= $g['jobs'] ?> top matches</span>
                            <ul>
                                <?php foreach (StudentProfile::learnLinks($g) as $l): ?>
                                    <li><?= $l['type'] === 'course' ? htmlspecialchars($l['title']) . ' (' . htmlspecialchars($l['detail']) . ')' : '<a href="' . htmlspecialchars($l['url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($l['title']) . '</a>' ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</div>
<p class="small text-muted mt-3">Skill levels are computed from university results using the curriculum mapping, with lecturer practical evidence counting for 25% where recorded. Match scores combine skills fit (60%) and job description fit (40%).</p>

<?php if ($print): ?>
</div></body></html>
<?php else: ?>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
<?php endif; ?>
