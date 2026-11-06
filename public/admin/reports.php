<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requirePermission('analytics.view');

$db = Database::getConnection();
// Student figures cover only the students this user's role can see
$inScope = fn(string $col) => studentScope($col);

$stats = [
    'users' => $db->query("SELECT IF(u.role = 'student', 'Student', COALESCE(r.name, 'No role')) AS role, COUNT(*) AS cnt
        FROM users u LEFT JOIN roles r ON r.role_id = u.role_id WHERE u.is_active GROUP BY 1 ORDER BY cnt DESC")->fetchAll(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_students' => $db->query("SELECT COUNT(*) FROM students s WHERE " . $inScope('s.student_id'))->fetchColumn(),
    'total_skills_assessed' => $db->query("SELECT COUNT(*) FROM student_skills ss WHERE " . $inScope('ss.student_id'))->fetchColumn(),
    'total_jobs' => $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'total_matches' => $db->query("SELECT COUNT(*) FROM job_readiness jr WHERE " . $inScope('jr.student_id'))->fetchColumn(),
    'units_mapped' => $db->query("SELECT COUNT(DISTINCT course_code) FROM course_unit_skills")->fetchColumn(),
    'results_imported' => $db->query("SELECT COUNT(*) FROM student_results r WHERE " . $inScope('r.student_id'))->fetchColumn(),
    'top_skills' => $db->query("
        SELECT sk.skill_name, sk.category, COUNT(ss.id) AS times_assessed, ROUND(AVG(ss.skill_level), 1) AS avg_level
        FROM student_skills ss JOIN skills sk ON ss.skill_id = sk.skill_id
        WHERE " . $inScope('ss.student_id') . "
        GROUP BY ss.skill_id ORDER BY times_assessed DESC LIMIT 10
    ")->fetchAll(),
    'avg_match' => $db->query("SELECT ROUND(AVG(match_score), 1) FROM job_readiness jr WHERE jr.job_id IN (SELECT job_id FROM job_skills) AND " . $inScope('jr.student_id'))->fetchColumn(),
    'jobs_by_source' => $db->query("SELECT source, COUNT(*) AS cnt FROM jobs GROUP BY source ORDER BY cnt DESC")->fetchAll(),
    // Rank jobs by how well students fit them; unmapped jobs always score 0 so leave them out
    'top_jobs' => $db->query("
        SELECT j.title, j.company, ROUND(AVG(jr.match_score), 1) AS avg_score, ROUND(MAX(jr.match_score), 1) AS best,
               SUM(jr.match_score >= 70) AS ready
        FROM jobs j JOIN job_readiness jr ON j.job_id = jr.job_id
        WHERE j.job_id IN (SELECT job_id FROM job_skills) AND " . $inScope('jr.student_id') . "
        GROUP BY j.job_id ORDER BY avg_score DESC LIMIT 8
    ")->fetchAll(),
    'grades' => $db->query("SELECT grade, COUNT(*) AS cnt FROM student_results r WHERE " . $inScope('r.student_id') . " GROUP BY grade ORDER BY FIELD(grade, 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F'), grade")->fetchAll(),
    'by_year' => $db->query("
        SELECT s.year_of_study AS yr, COUNT(DISTINCT s.student_id) AS students, ROUND(AVG(b.m)) AS avg_best
        FROM students s LEFT JOIN (SELECT student_id, MAX(match_score) m FROM job_readiness GROUP BY student_id) b USING(student_id)
        WHERE " . $inScope('s.student_id') . "
        GROUP BY s.year_of_study ORDER BY s.year_of_study
    ")->fetchAll(),
];

$print = isset($_GET['print']);
$roles = array_column($stats['users'], 'cnt', 'role');
// Fold the long tail of job sources into "Other" so the chart stays readable
$srcTop = array_slice($stats['jobs_by_source'], 0, 7);
$srcOther = array_sum(array_column(array_slice($stats['jobs_by_source'], 7), 'cnt'));
if ($srcOther) $srcTop[] = ['source' => 'Other', 'cnt' => $srcOther];
?>
<?php if (!$print): ?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>
<?php else: ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head><meta charset="UTF-8"><title>System Analytics</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('/css/style.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>body{padding:2rem} .page-actions{display:none}</style></head>
<body><div class="container">
<div class="no-print d-flex gap-2 mb-3"><button onclick="window.print()" class="btn btn-primary btn-sm">Print</button><a href="?" class="btn btn-outline-secondary btn-sm">Back</a></div>
<?php require_once __DIR__ . '/../../src/utils/ui.php'; ?>
<?php endif; ?>

<header class="page-head">
    <div>
        <h1>Analytics</h1>
        <p>Figures for <?= htmlspecialchars(scopeLabel()) ?> on <?= date('j F Y') ?>. Averages leave out jobs with no skills mapped.</p>
    </div>
    <div class="page-actions">
        <a href="?print=1" class="btn btn-outline-primary btn-sm" target="_blank"><?= icon('print', 14) ?> Print report</a>
    </div>
</header>

<section class="kpis" aria-label="Key figures">
    <div class="kpi"><span class="kpi-label">Users</span><span class="kpi-value"><?= $stats['total_users'] ?></span><span class="kpi-sub"><?= $stats['total_students'] ?> students in scope</span></div>
    <div class="kpi"><span class="kpi-label">Skill levels</span><span class="kpi-value"><?= $stats['total_skills_assessed'] ?></span><span class="kpi-sub">computed from results</span></div>
    <div class="kpi"><span class="kpi-label">Match scores</span><span class="kpi-value"><?= $stats['total_matches'] ?></span><span class="kpi-sub">student–job pairs</span></div>
    <div class="kpi"><span class="kpi-label">Avg match</span><span class="kpi-value"><?= $stats['avg_match'] !== null ? round($stats['avg_match']) : '—' ?><small>%</small></span><span class="kpi-sub">mapped jobs only</span></div>
    <div class="kpi"><span class="kpi-label">Results synced</span><span class="kpi-value"><?= $stats['results_imported'] ?></span><span class="kpi-sub">from the university</span></div>
    <div class="kpi"><span class="kpi-label">Course units mapped</span><span class="kpi-value"><?= $stats['units_mapped'] ?></span><span class="kpi-sub">to skills</span></div>
</section>

<div class="dash-grid">
    <section class="panel span-4">
        <header class="panel-head"><div><h3>Users by role</h3><p>Accounts in the system.</p></div></header>
        <div class="chart" style="height: 200px"><canvas id="roleChart" aria-label="Bar chart of users by role"></canvas></div>
    </section>
    <section class="panel span-4">
        <header class="panel-head"><div><h3>Grade distribution</h3><p>Across all synced results.</p></div></header>
        <div class="chart" style="height: 200px"><canvas id="gradeChart" aria-label="Bar chart of grades"></canvas></div>
    </section>
    <section class="panel span-4">
        <header class="panel-head"><div><h3>Readiness by year of study</h3><p>Average best match per year.</p></div></header>
        <div class="chart" style="height: 200px"><canvas id="yearChart" aria-label="Bar chart of readiness by year of study"></canvas></div>
    </section>

    <section class="panel span-6">
        <header class="panel-head"><div><h3>Jobs by source</h3><p>Where tracked postings come from.</p></div></header>
        <div class="chart" style="height: <?= max(200, count($srcTop) * 34) ?>px"><canvas id="sourceChart" aria-label="Bar chart of jobs by source"></canvas></div>
    </section>
    <section class="panel span-6">
        <header class="panel-head"><div><h3>Skill levels across students</h3><p>Students with a level, and their average.</p></div></header>
        <div class="table-responsive">
            <table class="table data-table mb-0">
                <thead><tr><th>Skill</th><th class="text-end">Students</th><th style="width: 40%">Average level</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($stats['top_skills'], 0, 7) as $sk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sk['skill_name']) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($sk['category']) ?></span></td>
                            <td class="text-end num"><?= $sk['times_assessed'] ?></td>
                            <td><div class="meter"><span class="<?= $sk['avg_level'] >= 70 ? 'is-ready' : '' ?>" style="width: <?= $sk['avg_level'] ?>%"></span></div><span class="meter-val"><?= round($sk['avg_level']) ?>/100</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel span-12">
        <header class="panel-head"><div><h3>Jobs students fit best</h3><p>Ranked by average match across all students.</p></div></header>
        <div class="table-responsive">
            <table class="table data-table mb-0">
                <thead><tr><th>Job</th><th class="text-end">Best student</th><th class="text-end">Job-ready students</th><th style="width: 30%">Average match</th></tr></thead>
                <tbody>
                    <?php foreach ($stats['top_jobs'] as $j): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($j['title']) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($j['company']) ?></span></td>
                            <td class="text-end num"><?= round($j['best']) ?>%</td>
                            <td class="text-end num"><?= (int)$j['ready'] ?></td>
                            <td><div class="meter"><span class="<?= $j['avg_score'] >= 70 ? 'is-ready' : '' ?>" style="width: <?= $j['avg_score'] ?>%"></span></div><span class="meter-val"><?= round($j['avg_score']) ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$stats['top_jobs']): ?><tr><td colspan="4" class="empty">No scored jobs yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.fonts.ready.then(() => {
    const css = getComputedStyle(document.documentElement), v = n => css.getPropertyValue(n).trim();
    const C = { student: v('--c-student'), market: v('--c-market'), ink: v('--ink'), muted: v('--muted'), grid: v('--chart-grid') };
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.color = C.muted;
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.layout.padding = { left: 6, right: 8 };
    Object.assign(Chart.defaults.plugins.tooltip, { backgroundColor: '#13201F', padding: 10, cornerRadius: 8, displayColors: false });
    const bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 40, categoryPercentage: 0.8, barPercentage: 0.9 };
    const val = { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } };
    const cat = { grid: { display: false }, border: { display: false }, ticks: { color: C.ink } };
    const count = unit => ({ callbacks: { label: c => `${c.parsed[c.chart.options.indexAxis === 'y' ? 'x' : 'y']} ${unit}` } });

    new Chart(roleChart, { type: 'bar',
        data: { labels: <?= json_encode(array_keys($roles)) ?>, datasets: [{ data: <?= json_encode(array_map('intval', array_values($roles))) ?>, backgroundColor: C.student, ...bar }] },
        options: { maintainAspectRatio: false, scales: { y: val, x: cat }, plugins: { tooltip: count('users') } } });

    new Chart(gradeChart, { type: 'bar',
        data: { labels: <?= json_encode(array_column($stats['grades'], 'grade')) ?>, datasets: [{ data: <?= json_encode(array_map('intval', array_column($stats['grades'], 'cnt'))) ?>, backgroundColor: C.student, ...bar }] },
        options: { maintainAspectRatio: false, scales: { y: val, x: cat }, plugins: { tooltip: count('results') } } });

    const years = <?= json_encode(array_map(fn($r) => ['y' => 'Year ' . $r['yr'], 'v' => (int)$r['avg_best'], 'n' => (int)$r['students']], $stats['by_year'])) ?>;
    new Chart(yearChart, { type: 'bar',
        data: { labels: years.map(y => y.y), datasets: [{ data: years.map(y => y.v), backgroundColor: C.student, ...bar }] },
        options: { maintainAspectRatio: false, scales: { y: { ...val, max: 100, ticks: { callback: t => t + '%', stepSize: 25 } }, x: cat },
            plugins: { tooltip: { callbacks: { label: c => `${c.parsed.y}% avg best match · ${years[c.dataIndex].n} students` } } } } });

    new Chart(sourceChart, { type: 'bar',
        data: { labels: <?= json_encode(array_column($srcTop, 'source')) ?>, datasets: [{ data: <?= json_encode(array_map('intval', array_column($srcTop, 'cnt'))) ?>, backgroundColor: C.market, ...bar, maxBarThickness: 22 }] },
        options: { indexAxis: 'y', maintainAspectRatio: false, scales: { x: val, y: cat }, plugins: { tooltip: count('jobs') } } });
});
</script>

<?php if ($print): ?>
</div></body></html>
<?php else: ?>
<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
<?php endif; ?>
