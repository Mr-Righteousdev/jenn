<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';

requireLogin();
$user = getCurrentUser();
$db = Database::getConnection();
$staffOverview = can('overview.view');
$lastSyncAt = $staffOverview ? $db->query("SELECT finished_at FROM sync_log WHERE status = 'ok' ORDER BY sync_id DESC LIMIT 1")->fetchColumn() : null;
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1><?= $staffOverview ? 'Overview' : 'Welcome back, ' . htmlspecialchars(explode(' ', nice($user['name']))[0]) ?></h1>
        <p><?= $staffOverview ? 'How students in ' . htmlspecialchars(scopeLabel()) . ' are tracking against the job market, and where the data needs work.' . ($lastSyncAt ? ' University data synced ' . date('j M, H:i', strtotime($lastSyncAt)) . '.' : '') : ($user['role'] === 'student' ? 'Here is how your skills line up with the job market today.' : date('l, j F Y')) ?></p>
    </div>
    <?php if ($staffOverview): ?>
        <div class="page-actions">
            <?php if (can('analytics.view')): ?><a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-primary btn-sm">Analytics</a><?php endif; ?>
            <?php if (can('jobs.manage')): ?><a href="<?= BASE_URL ?>/admin/jobs.php" class="btn btn-primary btn-sm">Manage jobs</a><?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<?php if (isStudent()): ?>
    <?php
    require_once __DIR__ . '/../src/utils/StudentProfile.php';
    $profile = StudentProfile::forUser($user['user_id']);
    if (!$profile): ?>
        <section class="panel"><div class="empty">Your student profile hasn't been synced from the university yet. Contact the career office.</div></section>
    <?php else:
    $st = $profile->student;
    $skills = $profile->skills();
    $withLevel = array_filter($skills, fn($s) => $s['level'] !== null);
    $avgSkill = $withLevel ? (int)round(array_sum(array_column($withLevel, 'level')) / count($withLevel)) : null;
    $scorable = array_values(array_filter($profile->matches(), fn($m) => $m['required'] > 0));
    $ready = count(array_filter($scorable, fn($m) => StudentProfile::band($m['match_score']) === 'ready'));
    $best = $scorable[0] ?? null;
    $growth = $profile->growthAreas();
    $cats = $profile->categoryLevels();
    arsort($cats);
    $unitsUsed = count(array_unique(array_merge([], ...array_map(fn($s) => array_column($s['sources'], 'code'), array_values($skills)))));
    $results = $db->prepare("SELECT course_code, course_name, mark, grade, academic_year, semester FROM student_results WHERE student_id = ? ORDER BY academic_year DESC, semester DESC, course_code LIMIT 6");
    $results->execute([$profile->id()]);
    $results = $results->fetchAll();
    ?>
    <div class="dash">
        <section class="kpis" aria-label="Your summary">
            <div class="kpi">
                <span class="kpi-label">CBC level</span>
                <span class="kpi-value kpi-word"><?= htmlspecialchars($st['level_name'] ?? 'Not set') ?></span>
                <span class="kpi-sub">Year <?= $st['year_of_study'] ?> · <?= htmlspecialchars(nice($st['programme_name'] ?? '')) ?></span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Average skill level</span>
                <span class="kpi-value"><?= $avgSkill ?? '—' ?><small>/100</small></span>
                <span class="kpi-sub"><?= count($withLevel) ?> of <?= count($skills) ?> skills have a level</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Ready to apply</span>
                <span class="kpi-value"><?= $ready ?><small>/<?= count($scorable) ?></small></span>
                <span class="kpi-sub">jobs at 70% or more</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Best match</span>
                <span class="kpi-value"><?= $best ? round($best['match_score']) : '—' ?><small>%</small></span>
                <span class="kpi-sub"><?= $best ? htmlspecialchars(mb_strimwidth($best['title'], 0, 34, '…')) : 'no scored jobs' ?></span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Skills to grow</span>
                <span class="kpi-value"><?= count($growth) ?></span>
                <span class="kpi-sub">holding back your top matches</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Results on record</span>
                <span class="kpi-value"><?= $unitsUsed ?></span>
                <span class="kpi-sub">course units feeding your skills</span>
            </div>
        </section>

        <div class="dash-grid">
            <section class="panel span-6">
                <header class="panel-head">
                    <div><h3>Your skills by area</h3><p>Average level in each area. 60 meets most job requirements.</p></div>
                    <a href="<?= BASE_URL ?>/profile.php" class="panel-link">See every skill</a>
                </header>
                <div class="chart" style="height: 260px"><canvas id="catChart" aria-label="Bar chart of your average skill level by area"></canvas></div>
            </section>

            <section class="panel span-6">
                <header class="panel-head">
                    <div><h3>Your top matches</h3><p>Jobs your profile fits best right now.</p></div>
                    <a href="<?= BASE_URL ?>/jobs.php" class="panel-link">All matches</a>
                </header>
                <ul class="top-matches">
                    <?php foreach (array_slice($scorable, 0, 5) as $m): $band = StudentProfile::band($m['match_score']); ?>
                        <li>
                            <a href="<?= BASE_URL ?>/jobs.php?job_id=<?= $m['job_id'] ?>">
                                <span class="job-pick-text"><strong><?= htmlspecialchars($m['title']) ?></strong><span><?= htmlspecialchars($m['company']) ?></span></span>
                                <span class="score-pill is-<?= $band ?>"><?= round($m['match_score']) ?>%</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$scorable): ?><li class="empty">No scored jobs yet.</li><?php endif; ?>
                </ul>
            </section>

            <section class="panel span-7">
                <header class="panel-head"><div><h3>Skills to grow next</h3><p>The gaps that cost you most across your 10 closest matches.</p></div></header>
                <?php if (!$growth): ?>
                    <div class="empty"><strong class="d-block text-body mb-1">No gaps in your closest matches.</strong>You meet what they ask for. Look at stretch roles on the Job matches page.</div>
                <?php else: ?>
                    <ul class="growth">
                        <?php foreach ($growth as $g): $next = StudentProfile::learnLinks($g)[0]; ?>
                            <li>
                                <div class="growth-main">
                                    <strong><?= htmlspecialchars($g['skill_name']) ?></strong>
                                    <span class="small text-muted">Needed by <?= $g['jobs'] ?> of your top matches · you're at <?= $g['level'] ?? 0 ?></span>
                                </div>
                                <div class="meter meter-target"><span style="width: <?= (int)$g['level'] ?>%"></span><i style="left: <?= MatchingEngine::GAP_THRESHOLD ?>%"></i></div>
                                <div class="growth-next small">
                                    <?php if ($next['type'] === 'course'): ?>
                                        <span class="tag tag-brand">Coming up</span> <?= htmlspecialchars($next['title']) ?>
                                    <?php else: ?>
                                        <span class="tag">Self-study</span> <a href="<?= htmlspecialchars($next['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($next['title']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="panel span-5">
                <header class="panel-head"><div><h3>Latest results</h3><p>From the university system.</p></div></header>
                <?php if (!$results): ?>
                    <div class="empty">No results synced yet.</div>
                <?php else: ?>
                    <table class="table data-table mb-0">
                        <tbody>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['course_code']) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($r['course_name']) ?></span></td>
                                    <td class="text-end num"><?= rtrim(rtrim((string)$r['mark'], '0'), '.') ?></td>
                                    <td class="text-end"><span class="tag <?= in_array($r['grade'], ['A', 'B+', 'B']) ? 'tag-brand' : (in_array($r['grade'], ['D', 'F']) ? 'tag-warn' : '') ?>"><?= htmlspecialchars($r['grade']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
    document.fonts.ready.then(() => {
        const css = getComputedStyle(document.documentElement), v = n => css.getPropertyValue(n).trim();
        const C = { student: v('--c-student'), light: '#7FC4BF', amber: v('--c-amber'), ink: v('--ink'), muted: v('--muted'), grid: v('--chart-grid') };
        Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
        Chart.defaults.color = C.muted;
        Chart.defaults.plugins.legend.display = false;
        Object.assign(Chart.defaults.plugins.tooltip, { backgroundColor: '#13201F', padding: 10, cornerRadius: 8, displayColors: false });
        const target = { id: 'target', afterDatasetsDraw(chart) {
            const { ctx, chartArea: a, scales: { x } } = chart, px = x.getPixelForValue(<?= MatchingEngine::GAP_THRESHOLD ?>);
            ctx.save(); ctx.strokeStyle = C.amber; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
            ctx.beginPath(); ctx.moveTo(px, a.top); ctx.lineTo(px, a.bottom); ctx.stroke(); ctx.restore();
        }};
        const vals = <?= json_encode(array_values($cats)) ?>;
        new Chart(catChart, { type: 'bar', plugins: [target],
            data: { labels: <?= json_encode(array_keys($cats)) ?>, datasets: [{ data: vals, backgroundColor: vals.map(x => x >= <?= MatchingEngine::GAP_THRESHOLD ?> ? C.student : C.light), borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, layout: { padding: { left: 6, right: 8 } },
                scales: { x: { min: 0, max: 100, grid: { color: C.grid }, border: { display: false }, ticks: { stepSize: 20 } },
                          y: { grid: { display: false }, border: { display: false }, ticks: { color: C.ink } } },
                plugins: { tooltip: { callbacks: { label: c => `Average level ${c.parsed.x} / 100` } } } } });
    });
    </script>
    <?php endif; ?>

<?php elseif (!$staffOverview): ?>
    <?php
    $studentCount = $db->query("SELECT COUNT(*) FROM students s WHERE " . studentScope())->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM skill_evidence WHERE lecturer_id = ?");
    $stmt->execute([$user['user_id']]);
    $assessedCount = $stmt->fetchColumn();
    ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Students in <?= htmlspecialchars(scopeLabel()) ?></h5>
                    <p class="display-6"><?= $studentCount ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Evidence records you've added</h5>
                    <p class="display-6"><?= $assessedCount ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <a href="<?= BASE_URL ?>/lecturer/assess.php" class="btn btn-primary">Record practical evidence</a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <?php
    $q = fn(string $sql) => $db->query($sql)->fetchAll();
    $one = fn(string $sql) => $db->query($sql)->fetchColumn();

    $studentCount = (int)$one("SELECT COUNT(*) FROM students s WHERE " . studentScope());
    $skillCount   = (int)$one("SELECT COUNT(*) FROM skills");
    $jobCount     = (int)$one("SELECT COUNT(*) FROM jobs");
    $levelCount   = (int)$one("SELECT COUNT(*) FROM cbc_levels");
    $resultCount  = (int)$one("SELECT COUNT(*) FROM student_results r WHERE " . studentScope('r.student_id'));
    $assessed     = (int)$one("SELECT COUNT(*) FROM student_skills ss WHERE " . studentScope('ss.student_id'));
    $coverage     = ($studentCount && $skillCount) ? round($assessed / ($studentCount * $skillCount) * 100) : 0;
    $staffCount   = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_active");

    // Jobs with no mapped skills always score 0 — exclude them from readiness stats and flag them instead
    $unmappedJobs = (int)$one("SELECT COUNT(*) FROM jobs j WHERE NOT EXISTS (SELECT 1 FROM job_skills js WHERE js.job_id = j.job_id)");
    $scored = "jr.job_id IN (SELECT job_id FROM job_skills)";

    // Best match per student (with the job it came from)
    $best = $q("SELECT u.name, s.registration_no, s.course, jr.match_score, j.title
                FROM job_readiness jr
                JOIN (SELECT student_id, MAX(match_score) m FROM job_readiness GROUP BY student_id) b
                  ON b.student_id = jr.student_id AND b.m = jr.match_score
                JOIN students s ON s.student_id = jr.student_id
                JOIN users u ON u.user_id = s.user_id
                JOIN jobs j ON j.job_id = jr.job_id
                WHERE $scored AND " . studentScope('jr.student_id') . "
                  AND EXISTS (SELECT 1 FROM student_skills ss WHERE ss.student_id = jr.student_id) -- students with no results yet have nothing to score
                GROUP BY jr.student_id
                ORDER BY jr.match_score DESC");
    $jobReady  = count(array_filter($best, fn($r) => round($r['match_score']) >= 70));
    // Charts and the leaderboard show the top students; the full list is on the Students page
    $topBest   = array_slice($best, 0, 15);
    $avgBest   = $best ? round(array_sum(array_column($best, 'match_score')) / count($best)) : 0;

    $byCategory = $q("SELECT sk.category, ROUND(AVG(ss.skill_level)) lvl FROM student_skills ss JOIN skills sk USING(skill_id) WHERE " . studentScope('ss.student_id') . " GROUP BY sk.category ORDER BY lvl DESC");
    $demand     = $q("SELECT sk.skill_name, COUNT(*) n, (SELECT ROUND(AVG(ss2.skill_level)) FROM student_skills ss2 WHERE ss2.skill_id = sk.skill_id AND " . studentScope('ss2.student_id') . ") lvl
                      FROM job_skills js JOIN skills sk USING(skill_id) GROUP BY sk.skill_id ORDER BY n DESC, sk.skill_name LIMIT 8");
    $levels     = $q("SELECT cl.level_name, COUNT(s.student_id) n FROM cbc_levels cl LEFT JOIN students s ON s.cbc_level_id = cl.level_id AND " . studentScope() . " GROUP BY cl.level_id ORDER BY cl.min_score");
    $noLevel    = (int)$one("SELECT COUNT(*) FROM students s WHERE s.cbc_level_id IS NULL AND " . studentScope());
    $histRows   = array_column($q("SELECT LEAST(FLOOR(match_score/10), 9) b, COUNT(*) n FROM job_readiness jr WHERE $scored AND " . studentScope('jr.student_id') . " GROUP BY b"), 'n', 'b');
    $hist       = array_map(fn($b) => (int)($histRows[$b] ?? 0), range(0, 9));
    $byCourse   = $q("SELECT s.course, ROUND(AVG(b.m)) avg_best, COUNT(*) n
                      FROM (SELECT student_id, MAX(match_score) m FROM job_readiness jr WHERE $scored AND " . studentScope('jr.student_id') . " GROUP BY student_id) b
                      JOIN students s USING(student_id) GROUP BY s.course ORDER BY avg_best DESC");
    $unassessed = (int)$one("SELECT COUNT(*) FROM students s WHERE NOT EXISTS (SELECT 1 FROM student_skills ss WHERE ss.student_id = s.student_id) AND " . studentScope());
    // Units in the curricula of programmes in use (and in this user's faculty) that no skill is mapped to
    $unmappedUnits = (int)$one("SELECT COUNT(DISTINCT pu.course_code) FROM programme_units pu JOIN programmes p ON p.code = pu.programme_code
                                WHERE p.is_active AND " . programmeScope() . "
                                AND EXISTS (SELECT 1 FROM student_results r WHERE r.course_code = pu.course_code)
                                AND NOT EXISTS (SELECT 1 FROM course_unit_skills c WHERE c.course_code = pu.course_code)");
    $lastSync = $db->query("SELECT status, finished_at FROM sync_log ORDER BY sync_id DESC LIMIT 1")->fetch();
    $staleSync = !$lastSync || $lastSync['status'] !== 'ok' || strtotime($lastSync['finished_at']) < time() - 7 * 86400;

    $health = [
        ['n' => $unmappedJobs, 'label' => ($unmappedJobs === 1 ? 'job has' : 'jobs have') . ' no skills mapped, so every student scores 0 against them', 'href' => '/admin/jobs.php?filter=unmapped', 'cta' => 'Review jobs', 'perm' => 'jobs.manage'],
        ['n' => $unmappedUnits, 'label' => ($unmappedUnits === 1 ? 'course unit students have taken isn\'t' : 'course units students have taken aren\'t') . ' mapped to skills, so their marks don\'t count', 'href' => '/admin/curriculum.php', 'cta' => 'Map units', 'perm' => 'curriculum.manage'],
        ['n' => $unassessed,   'label' => ($unassessed === 1 ? 'student has' : 'students have') . ' no competency profile yet (mostly new students with no results)', 'href' => '/admin/university.php', 'cta' => 'Check sync', 'perm' => 'university.sync'],
        ['n' => $staleSync ? 1 : 0, 'label' => $staleSync ? 'University data hasn\'t synced successfully in the last 7 days' : 'University data synced in the last 7 days', 'href' => '/admin/university.php', 'cta' => 'Sync now', 'flag' => true, 'perm' => 'university.sync'],
        ['n' => $noLevel,      'label' => ($noLevel === 1 ? 'student has' : 'students have') . ' no CBC level assigned', 'href' => '/admin/cbc_levels.php', 'cta' => 'Review levels', 'perm' => 'cbc.manage'],
    ];
    $health = array_filter($health, fn($h) => can($h['perm']));
    ?>
    <div class="dash">
        <section class="kpis" aria-label="Key figures">
            <div class="kpi">
                <span class="kpi-label">Students</span>
                <span class="kpi-value"><?= $studentCount ?></span>
                <span class="kpi-sub">in <?= htmlspecialchars(scopeLabel()) ?></span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Job-ready</span>
                <span class="kpi-value"><?= $jobReady ?><small>/<?= count($best) ?></small></span>
                <span class="kpi-sub">best match ≥ 70%</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Avg best match</span>
                <span class="kpi-value"><?= $avgBest ?><small>%</small></span>
                <span class="kpi-sub">across <?= count($best) ?> students with a profile</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Profile coverage</span>
                <span class="kpi-value"><?= $coverage ?><small>%</small></span>
                <span class="kpi-sub"><?= $assessed ?> of <?= $studentCount * $skillCount ?> student-skills have a level</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Jobs tracked</span>
                <span class="kpi-value"><?= $jobCount ?></span>
                <span class="kpi-sub"><?= $jobCount - $unmappedJobs ?> with skills mapped</span>
            </div>
            <div class="kpi">
                <span class="kpi-label">Framework</span>
                <span class="kpi-value"><?= $skillCount ?></span>
                <span class="kpi-sub">skills · <?= $levelCount ?> CBC levels · <?= $resultCount ?> results synced</span>
            </div>
        </section>

        <div class="dash-grid">
            <section class="panel span-8">
                <header class="panel-head">
                    <div>
                        <h3>Top 15 students by best job match</h3>
                        <p>Highest readiness score each student reaches against any job. The line marks job-ready (70%).</p>
                    </div>
                    <?php if (can('analytics.view')): ?><a href="<?= BASE_URL ?>/admin/reports.php" class="panel-link">Full analytics</a><?php endif; ?>
                </header>
                <div class="chart" style="height: <?= max(220, count($topBest) * 30) ?>px"><canvas id="bestChart" aria-label="Bar chart of best job match per student"></canvas></div>
            </section>

            <section class="panel span-4">
                <header class="panel-head"><div><h3>Needs attention</h3><p>Data gaps that weaken matching.</p></div></header>
                <ul class="health">
                    <?php foreach ($health as $h): ?>
                        <li class="<?= $h['n'] > 0 ? 'is-warn' : 'is-ok' ?>">
                            <span class="health-icon" aria-hidden="true">
                                <?php if ($h['n'] > 0): ?>
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="health-text"><?php if (empty($h['flag'])): ?><strong><?= $h['n'] ?></strong> <?php endif; ?><?= $h['label'] ?></span>
                            <?php if ($h['n'] > 0): ?><a href="<?= BASE_URL . $h['href'] ?>"><?= $h['cta'] ?></a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel span-6">
                <header class="panel-head"><div><h3>Average competency by category</h3><p>Mean computed level (0–100) across all students.</p></div></header>
                <div class="chart" style="height: 260px"><canvas id="categoryChart" aria-label="Bar chart of average competency by category"></canvas></div>
            </section>

            <section class="panel span-6">
                <header class="panel-head"><div><h3>Most in-demand skills</h3><p>How many tracked jobs ask for each skill. Hover for the students' average level.</p></div></header>
                <div class="chart" style="height: 260px"><canvas id="demandChart" aria-label="Bar chart of skills most requested by jobs"></canvas></div>
            </section>

            <section class="panel span-4">
                <header class="panel-head"><div><h3>Students by CBC level</h3><p><?= $noLevel ? "$noLevel not yet assigned." : 'All students assigned.' ?></p></div></header>
                <div class="chart" style="height: 220px"><canvas id="levelChart" aria-label="Bar chart of students per CBC level"></canvas></div>
            </section>

            <section class="panel span-4">
                <header class="panel-head"><div><h3>Match score distribution</h3><p>All student–job scores, jobs with mapped skills only.</p></div></header>
                <div class="chart" style="height: 220px"><canvas id="histChart" aria-label="Histogram of match scores"></canvas></div>
            </section>

            <section class="panel span-4">
                <header class="panel-head"><div><h3>Readiness by programme</h3><p>Average of each student's best match.</p></div></header>
                <div class="chart" style="height: 220px"><canvas id="courseChart" aria-label="Bar chart of readiness by programme"></canvas></div>
            </section>

            <section class="panel span-12">
                <header class="panel-head"><div><h3>Student leaderboard</h3><p>Top 15 of <?= count($best) ?> students, ranked by best job match.</p></div>
                    <?php if (can('students.view')): ?><a href="<?= BASE_URL ?>/lecturer/reports.php" class="panel-link">All students</a><?php endif; ?></header>
                <div class="table-responsive">
                    <table class="table data-table mb-0">
                        <thead><tr><th>#</th><th>Student</th><th>Programme</th><th>Best-matching job</th><th class="w-25">Match</th></tr></thead>
                        <tbody>
                            <?php foreach ($topBest as $i => $r): $pct = round($r['match_score']); ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><strong><?= htmlspecialchars(nice($r['name'])) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($r['registration_no']) ?></span></td>
                                    <td><?= htmlspecialchars(nice($r['course'])) ?></td>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td>
                                        <div class="meter"><span style="width: <?= $pct ?>%" class="<?= $pct >= 70 ? 'is-ready' : '' ?>"></span></div>
                                        <span class="meter-val"><?= $pct ?>%<?= $pct >= 70 ? ' · ready' : '' ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$best): ?><tr><td colspan="5" class="text-center text-muted py-4">No match scores yet. Run matching from the Jobs page.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>

    <script>
    // Charts measure label widths on creation, so wait for the web font to avoid clipped labels
    document.fonts.ready.then(() => {
        const css = getComputedStyle(document.documentElement);
        const v = n => css.getPropertyValue(n).trim();
        const C = { student: v('--c-student'), market: v('--c-market'), amber: v('--c-amber'), ink: v('--ink'), muted: v('--muted'), grid: v('--chart-grid') };
        Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = C.muted;
        Chart.defaults.plugins.legend.display = false;
        Chart.defaults.layout.padding = { left: 6, right: 8 };
        Object.assign(Chart.defaults.plugins.tooltip, { backgroundColor: '#13201F', padding: 10, cornerRadius: 8, titleFont: { weight: '600' }, displayColors: false });

        const bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22, categoryPercentage: 0.8, barPercentage: 0.9 };
        const pctAxis = { min: 0, max: 100, grid: { color: C.grid }, border: { display: false }, ticks: { callback: v => v + '%', stepSize: 20 } };
        const catAxis = { grid: { display: false }, border: { display: false }, ticks: { color: C.ink } };

        // Draws the 70% "job-ready" reference line on the x axis
        const readyLine = { id: 'readyLine', afterDatasetsDraw(chart) {
            const { ctx, chartArea: a, scales: { x } } = chart, px = x.getPixelForValue(70);
            ctx.save(); ctx.strokeStyle = C.amber; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
            ctx.beginPath(); ctx.moveTo(px, a.top); ctx.lineTo(px, a.bottom); ctx.stroke();
            ctx.setLineDash([]); ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--ink').trim(); ctx.font = "600 11px 'Plus Jakarta Sans'"; ctx.textAlign = 'center';
            ctx.fillText('job-ready 70%', px, a.top - 6); ctx.restore();
        }};

        const best = <?= json_encode(array_map(fn($r) => ['name' => nice($r['name']), 'reg' => $r['registration_no'], 'score' => round($r['match_score'], 1), 'job' => $r['title']], $topBest)) ?>;
        new Chart(bestChart, {
            type: 'bar', plugins: [readyLine],
            data: { labels: best.map(r => `${r.name} · ${r.reg}`), datasets: [{ data: best.map(r => r.score), backgroundColor: C.student, ...bar }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, layout: { padding: { top: 18, left: 6, right: 8 } },
                scales: { x: pctAxis, y: catAxis },
                plugins: { tooltip: { callbacks: { label: c => `${c.parsed.x}% · ${best[c.dataIndex].job}` } } } }
        });

        new Chart(categoryChart, {
            type: 'bar',
            data: { labels: <?= json_encode(array_column($byCategory, 'category')) ?>,
                    datasets: [{ data: <?= json_encode(array_map('intval', array_column($byCategory, 'lvl'))) ?>, backgroundColor: C.student, ...bar }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, scales: { x: { ...pctAxis, ticks: { stepSize: 20 } }, y: catAxis },
                plugins: { tooltip: { callbacks: { label: c => `Average level ${c.parsed.x} / 100` } } } }
        });

        const demand = <?= json_encode(array_map(fn($r) => ['skill' => $r['skill_name'], 'n' => (int)$r['n'], 'lvl' => $r['lvl'] === null ? null : (int)$r['lvl']], $demand)) ?>;
        new Chart(demandChart, {
            type: 'bar',
            data: { labels: demand.map(d => d.skill), datasets: [{ data: demand.map(d => d.n), backgroundColor: C.market, ...bar }] },
            options: { indexAxis: 'y', maintainAspectRatio: false,
                scales: { x: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } }, y: catAxis },
                plugins: { tooltip: { callbacks: { label: c => `${c.parsed.x} job${c.parsed.x === 1 ? '' : 's'}`,
                    afterLabel: c => demand[c.dataIndex].lvl === null ? 'Not yet assessed in students' : `Students' average: ${demand[c.dataIndex].lvl}/100` } } } }
        });

        // Ordinal levels → one-hue sequential ramp, light to dark
        const ramp = ['#9FD6D2', '#4DB3AC', '#008C85', '#0A5F5A'];
        new Chart(levelChart, {
            type: 'bar',
            data: { labels: <?= json_encode(array_column($levels, 'level_name')) ?>,
                    datasets: [{ data: <?= json_encode(array_map('intval', array_column($levels, 'n'))) ?>, backgroundColor: ramp, ...bar, maxBarThickness: 44 }] },
            options: { maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } }, x: catAxis },
                plugins: { tooltip: { callbacks: { label: c => `${c.parsed.y} student${c.parsed.y === 1 ? '' : 's'}` } } } }
        });

        new Chart(histChart, {
            type: 'bar',
            data: { labels: <?= json_encode(array_map(fn($b) => ($b * 10) . '–' . ($b * 10 + 10), range(0, 9))) ?>,
                    datasets: [{ data: <?= json_encode($hist) ?>, backgroundColor: ctx => ctx.dataIndex >= 7 ? C.student : '#9FD6D2', borderRadius: 3, borderSkipped: 'start', categoryPercentage: 1, barPercentage: 0.94 }] },
            options: { maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } },
                          x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, callback: (v, i) => i % 2 ? '' : i * 10 } } },
                plugins: { tooltip: { callbacks: { title: c => `${c[0].label}%`, label: c => `${c.parsed.y} student–job pairs` } } } }
        });

        const courses = <?= json_encode(array_map(fn($r) => ['c' => nice($r['course']), 'v' => (int)$r['avg_best'], 'n' => (int)$r['n']], $byCourse)) ?>;
        new Chart(courseChart, {
            type: 'bar',
            data: { labels: courses.map(c => c.c.replace('Bachelor of Science in ', 'BSc ').replace('Bachelor of ', 'B. ').replace('Diploma in ', 'Dip. ')), datasets: [{ data: courses.map(c => c.v), backgroundColor: C.student, ...bar, maxBarThickness: 44 }] },
            options: { maintainAspectRatio: false, scales: { y: { ...pctAxis }, x: catAxis },
                plugins: { tooltip: { callbacks: { title: c => courses[c[0].dataIndex].c, label: c => `${c.parsed.y}% avg best match · ${courses[c.dataIndex].n} students` } } } }
        });
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
