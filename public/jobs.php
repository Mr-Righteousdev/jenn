<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';
require_once __DIR__ . '/../src/utils/StudentProfile.php';
requireStudent();

$profile = StudentProfile::forUser($_SESSION['user_id']);
if (!$profile) die('Your student profile has not been synced yet. Contact the career office.');

// Scores are recalculated whenever results sync or evidence changes, so this page only reads them
$matches = $profile->matches();
$scorable = array_values(array_filter($matches, fn($m) => $m['required'] > 0));
$selectedId = (int)($_GET['job_id'] ?? ($scorable[0]['job_id'] ?? 0));
$job = null;
foreach ($matches as $m) if ((int)$m['job_id'] === $selectedId) $job = $m;
$reqs = $job ? $profile->requirements($selectedId) : [];
$gaps = array_filter($reqs, fn($r) => !$r['meets']);
$counts = ['ready' => 0, 'close' => 0, 'far' => 0];
foreach ($scorable as $m) $counts[StudentProfile::band($m['match_score'])]++;
$weightLabel = fn(float $w) => $w >= 0.9 ? 'Essential' : ($w >= 0.65 ? 'Important' : ($w >= 0.4 ? 'Useful' : 'Nice to have'));
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Job matches</h1>
        <p>How your skill profile fits real job postings. 70% or more means you're ready to apply.</p>
    </div>
</header>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="panel form-panel">
            <div class="chips mb-2" role="group" aria-label="Filter jobs">
                <button type="button" class="chip is-on" data-band="">All <span><?= count($scorable) ?></span></button>
                <button type="button" class="chip" data-band="ready">Ready <span><?= $counts['ready'] ?></span></button>
                <button type="button" class="chip" data-band="close">Close <span><?= $counts['close'] ?></span></button>
                <button type="button" class="chip" data-band="far">Stretch <span><?= $counts['far'] ?></span></button>
            </div>
            <label class="search w-100 mb-2" style="max-width: none">
                <?= icon('search') ?>
                <input type="search" id="jobSearch" class="form-control" placeholder="Search jobs" aria-label="Search jobs">
            </label>
            <ul class="job-pick" id="jobList">
                <?php foreach ($scorable as $m): $band = StudentProfile::band($m['match_score']); $sc = (int)round($m['match_score']); ?>
                    <li data-band="<?= $band ?>" data-q="<?= htmlspecialchars(strtolower($m['title'] . ' ' . $m['company'])) ?>">
                        <a href="?job_id=<?= $m['job_id'] ?>" class="<?= (int)$m['job_id'] === $selectedId ? 'is-current' : '' ?>">
                            <span class="job-pick-text"><strong><?= htmlspecialchars($m['title']) ?></strong><span><?= htmlspecialchars($m['company']) ?></span></span>
                            <span class="score-pill is-<?= $band ?>"><?= $sc ?>%</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="empty py-3" id="noJobs" hidden>No jobs match.</div>
            <?php if (count($matches) > count($scorable)): ?>
                <p class="small text-muted mt-2 mb-0"><?= count($matches) - count($scorable) ?> more postings can't be scored yet because their required skills haven't been set.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-lg-7">
        <?php if (!$job): ?>
            <section class="panel"><div class="empty">No scored jobs yet. Check back after the career office adds postings.</div></section>
        <?php else: $sc = (int)round($job['match_score']); $band = StudentProfile::band($job['match_score']); ?>
            <section class="panel mb-3">
                <div class="match-head">
                    <div>
                        <h2 class="match-title"><?= htmlspecialchars($job['title']) ?></h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($job['company']) ?> · <?= htmlspecialchars($job['source']) ?></p>
                    </div>
                    <div class="match-score is-<?= $band ?>">
                        <strong><?= $sc ?><small>%</small></strong>
                        <span><?= ['ready' => 'Ready to apply', 'close' => 'Close to ready', 'far' => 'Stretch role'][$band] ?></span>
                    </div>
                </div>
                <div class="score-split">
                    <div><span>Skills fit <em>60% of score</em></span><div class="meter"><span class="is-ready" style="width: <?= $job['weighted_score'] ?>%"></span></div><b><?= round($job['weighted_score']) ?>%</b></div>
                    <div><span>Description fit <em>40% of score</em></span><div class="meter"><span class="is-ready" style="width: <?= $job['keyword_score'] ?>%"></span></div><b><?= round($job['keyword_score']) ?>%</b></div>
                </div>
                <?php if ($job['url'] && $job['url'] !== '#'): ?>
                    <a href="<?= htmlspecialchars($job['url']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-3"><?= icon('link', 14) ?> View posting</a>
                <?php endif; ?>
            </section>

            <section class="panel mb-3">
                <header class="panel-head"><div><h3>What this job asks for</h3><p>Your level against each required skill. The marker shows the 60 you need.</p></div></header>
                <table class="table data-table mb-0">
                    <thead><tr><th>Skill</th><th>Importance</th><th style="width: 42%">Your level</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($reqs as $r): $lvl = $r['level']; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['skill_name']) ?></strong></td>
                                <td class="small text-muted"><?= $weightLabel($r['weight']) ?></td>
                                <td>
                                    <div class="meter meter-target"><span class="<?= $r['meets'] ? 'is-ready' : '' ?>" style="width: <?= (int)$lvl ?>%"></span><i style="left: <?= MatchingEngine::GAP_THRESHOLD ?>%"></i></div>
                                    <span class="meter-val"><?= $lvl ?? '—' ?></span>
                                </td>
                                <td class="text-end"><?= $r['meets'] ? '<span class="tag tag-brand">' . icon('check', 12) . ' Meets</span>' : '<span class="tag tag-warn">Gap of ' . (MatchingEngine::GAP_THRESHOLD - (int)$lvl) . '</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="panel">
                <?php if (!$gaps): ?>
                    <div class="empty"><strong class="d-block text-body mb-1">No gaps for this role.</strong>You meet every skill it asks for.</div>
                <?php else: ?>
                    <header class="panel-head"><div><h3>How to close the gaps</h3><p>Course units still ahead in your programme come first, then self-study.</p></div></header>
                    <div class="gap-list">
                        <?php foreach ($gaps as $g): ?>
                            <div class="gap-item">
                                <div class="gap-head"><strong><?= htmlspecialchars($g['skill_name']) ?></strong><span class="text-muted small">you're at <?= $g['level'] ?? 0 ?>, need <?= MatchingEngine::GAP_THRESHOLD ?></span></div>
                                <ul>
                                    <?php foreach (StudentProfile::learnLinks($g) as $l): ?>
                                        <li class="learn-<?= $l['type'] ?>">
                                            <?php if ($l['type'] === 'course'): ?>
                                                <span class="tag tag-brand">Course unit</span> <?= htmlspecialchars($l['title']) ?> <span class="text-muted small">· <?= htmlspecialchars($l['detail']) ?></span>
                                            <?php else: ?>
                                                <span class="tag">Self-study</span> <a href="<?= htmlspecialchars($l['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['title']) ?></a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
(() => {
    let band = '';
    const search = document.getElementById('jobSearch');
    const apply = () => {
        const q = search.value.trim().toLowerCase(); let shown = 0;
        document.querySelectorAll('#jobList li').forEach(li => {
            const ok = (!band || li.dataset.band === band) && (!q || li.dataset.q.includes(q));
            li.hidden = !ok; shown += ok;
        });
        document.getElementById('noJobs').hidden = shown > 0;
    };
    document.querySelectorAll('.chip[data-band]').forEach(c => c.addEventListener('click', () => {
        band = c.dataset.band;
        document.querySelectorAll('.chip[data-band]').forEach(x => x.classList.toggle('is-on', x === c));
        apply();
    }));
    search.addEventListener('input', apply);
})();
</script>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
