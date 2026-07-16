<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/MatchingEngine.php';
requireRole('lecturer');

$db = Database::getConnection();
$engine = new MatchingEngine();

$selectedYear = $_GET['year'] ?? null;

// Get all students with their skill stats
$students = $db->query("
    SELECT s.student_id, s.registration_no, s.course, s.year_of_study, u.name, u.email,
           COUNT(DISTINCT ss.id) AS skill_count,
           ROUND(AVG(ss.skill_level), 1) AS avg_skill_level,
           (SELECT cl.level_name FROM cbc_levels cl WHERE cl.min_score <= ROUND(AVG(ss.skill_level), 0) AND cl.max_score >= ROUND(AVG(ss.skill_level), 0) LIMIT 1) AS cbc_level
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN student_skills ss ON s.student_id = ss.student_id
    GROUP BY s.student_id
    ORDER BY u.name
")->fetchAll();

// Class-level stats
$classStats = [
    'total' => count($students),
    'assessed' => count(array_filter($students, fn($s) => $s['skill_count'] > 0)),
    'avg_level' => $students ? round(array_sum(array_column($students, 'avg_skill_level')) / count($students), 1) : 0,
];

// Category distribution
$catStats = $db->query("
    SELECT sk.category, COUNT(DISTINCT ss.student_id) AS student_count, ROUND(AVG(ss.skill_level), 1) AS avg_level
    FROM student_skills ss
    JOIN skills sk ON ss.skill_id = sk.skill_id
    GROUP BY sk.category
    ORDER BY avg_level DESC
")->fetchAll();

$print = isset($_GET['print']);
?>
<?php if (!$print): ?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Class Competency Report</h2>
    <a href="?print=1" class="btn btn-outline-primary" target="_blank">Print PDF</a>
</div>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Competency Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { padding: 2rem; font-size: 14px; } @media print { .no-print { display: none !important; } }</style>
</head>
<body>
<div class="no-print mb-3">
    <button onclick="window.print()" class="btn btn-primary">Print</button>
    <a href="?" class="btn btn-secondary">Back</a>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-4"><?= $classStats['total'] ?></div>
                <small class="text-muted">Total Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-4"><?= $classStats['assessed'] ?></div>
                <small class="text-muted">Students Assessed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-4 text-<?= $classStats['avg_level'] >= 70 ? 'success' : 'warning' ?>"><?= $classStats['avg_level'] ?>%</div>
                <small class="text-muted">Avg Skill Level</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-4"><?= count($catStats) ?></div>
                <small class="text-muted">Skill Categories</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Competency by Category</h5>
                <?php if (empty($catStats)): ?>
                    <p class="text-muted mb-0">No assessments yet.</p>
                <?php else: ?>
                    <canvas id="catChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">CBC Level Distribution</h5>
                <?php
                $levels = $db->query("SELECT level_name, COUNT(*) AS cnt FROM (SELECT (SELECT cl.level_name FROM cbc_levels cl WHERE cl.min_score <= ROUND(AVG(ss.skill_level), 0) AND cl.max_score >= ROUND(AVG(ss.skill_level), 0) LIMIT 1) AS level_name FROM student_skills ss GROUP BY ss.student_id) t GROUP BY level_name ORDER BY cnt DESC")->fetchAll();
                ?>
                <?php if (empty($levels)): ?>
                    <p class="text-muted mb-0">No data yet.</p>
                <?php else: ?>
                    <?php foreach ($levels as $l): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span><?= $l['level_name'] ?></span>
                            <span class="badge bg-primary rounded-pill"><?= $l['cnt'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Student Detail</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Reg No</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Skills</th>
                        <th>Avg Level</th>
                        <th>CBC Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['registration_no'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['course']) ?></td>
                            <td><?= $s['year_of_study'] ?></td>
                            <td><span class="badge bg-info"><?= $s['skill_count'] ?></span></td>
                            <td>
                                <span class="text-<?= $s['avg_skill_level'] >= 70 ? 'success' : ($s['avg_skill_level'] >= 40 ? 'warning' : 'danger') ?>">
                                    <?= $s['avg_skill_level'] ?>%
                                </span>
                            </td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($s['cbc_level'] ?? '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($catStats)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($catStats, 'category')) ?>,
        datasets: [{
            label: 'Avg Level',
            data: <?= json_encode(array_column($catStats, 'avg_level')) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.6)',
        }]
    },
    options: {
        scales: { y: { min: 0, max: 100 } },
        plugins: { legend: { display: false } }
    }
});
</script>
<?php endif; ?>

<?php if ($print): ?>
</body></html>
<?php else: ?>
<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
<?php endif; ?>
