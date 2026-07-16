<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requireRole('admin');

$db = Database::getConnection();

$stats = [
    'users' => $db->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role")->fetchAll(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'total_skills_assessed' => $db->query("SELECT COUNT(*) FROM student_skills")->fetchColumn(),
    'total_jobs' => $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'total_matches' => $db->query("SELECT COUNT(*) FROM job_readiness")->fetchColumn(),
    'total_recommendations' => $db->query("SELECT COUNT(*) FROM recommendations")->fetchColumn(),
    'results_imported' => $db->query("SELECT COUNT(*) FROM student_results")->fetchColumn(),
    'top_skills' => $db->query("
        SELECT sk.skill_name, sk.category, COUNT(ss.id) AS times_assessed, ROUND(AVG(ss.skill_level), 1) AS avg_level
        FROM student_skills ss JOIN skills sk ON ss.skill_id = sk.skill_id
        GROUP BY ss.skill_id ORDER BY times_assessed DESC LIMIT 10
    ")->fetchAll(),
    'avg_match' => $db->query("SELECT ROUND(AVG(match_score), 1) AS avg_score FROM job_readiness")->fetchColumn(),
    'jobs_by_source' => $db->query("SELECT source, COUNT(*) AS cnt FROM jobs GROUP BY source ORDER BY cnt DESC")->fetchAll(),
    'top_jobs' => $db->query("
        SELECT j.title, j.company, COUNT(jr.readiness_id) AS match_count, ROUND(AVG(jr.match_score), 1) AS avg_score
        FROM jobs j JOIN job_readiness jr ON j.job_id = jr.job_id
        GROUP BY j.job_id ORDER BY match_count DESC LIMIT 10
    ")->fetchAll(),
];

$print = isset($_GET['print']);
?>
<?php if (!$print): ?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>System Analytics</h2>
    <a href="?print=1" class="btn btn-outline-primary" target="_blank">Print</a>
</div>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>System Analytics</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{padding:2rem;font-size:14px}@media print{.no-print{display:none!important}}</style></head>
<body><div class="no-print mb-3"><button onclick="window.print()" class="btn btn-primary">Print</button><a href="?" class="btn btn-secondary">Back</a></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-4"><?= $stats['total_users'] ?></div><small class="text-muted">Total Users</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-4"><?= $stats['total_students'] ?></div><small class="text-muted">Students</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-4"><?= $stats['total_jobs'] ?></div><small class="text-muted">Jobs</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-4"><?= $stats['total_skills_assessed'] ?></div><small class="text-muted">Skill Assessments</small></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="display-5"><?= $stats['results_imported'] ?></div><small class="text-muted">Results Imported</small></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="display-5"><?= $stats['total_matches'] ?></div><small class="text-muted">Job Matches Run</small></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="display-5"><?= $stats['total_recommendations'] ?></div><small class="text-muted">Recommendations</small></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Top Skills Across Students</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Skill</th><th>Category</th><th>Assessments</th><th>Avg Level</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['top_skills'] as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['skill_name']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($s['category']) ?></span></td>
                                    <td><?= $s['times_assessed'] ?></td>
                                    <td><?= $s['avg_level'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Users by Role</h5>
                <canvas id="roleChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Jobs by Source</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Source</th><th>Count</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['jobs_by_source'] as $j): ?>
                                <tr><td><?= htmlspecialchars($j['source']) ?></td><td><?= $j['cnt'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Top Matched Jobs</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Job</th><th>Company</th><th>Matches</th><th>Avg Score</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['top_jobs'] as $j): ?>
                                <tr><td><?= htmlspecialchars($j['title']) ?></td><td><?= htmlspecialchars($j['company']) ?></td><td><?= $j['match_count'] ?></td><td><?= $j['avg_score'] ?>%</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">System Overview</h5>
        <p class="mb-0">
            Average match score across all students: <strong><?= $stats['avg_match'] ?: 'N/A' ?>%</strong>.
            <?php if ($stats['total_jobs'] > 0 && $stats['total_students'] > 0): ?>
                Each student has access to <strong><?= $stats['total_jobs'] ?></strong> job matches
                with <strong><?= $stats['total_recommendations'] ?></strong> total recommendations generated.
            <?php endif; ?>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('roleChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($stats['users'], 'role')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($stats['users'], 'cnt')) ?>,
            backgroundColor: ['#0d6efd', '#198754', '#ffc107'],
        }]
    }
});
</script>

<?php if ($print): ?>
</body></html>
<?php else: ?>
<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
<?php endif; ?>
