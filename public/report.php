<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';
require_once __DIR__ . '/../src/utils/MatchingEngine.php';
requireLogin();

$user = getCurrentUser();
$db = Database::getConnection();

if ($user['role'] !== 'student') {
    die('Students only.');
}

$stmt = $db->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$student = $stmt->fetch();
if (!$student) die('Profile not found.');
$studentId = $student['student_id'];

$engine = new MatchingEngine();

// Get all readiness scores
$scores = $db->prepare("
    SELECT jr.*, j.title, j.company, j.source
    FROM job_readiness jr
    JOIN jobs j ON jr.job_id = j.job_id
    WHERE jr.student_id = ?
    ORDER BY jr.match_score DESC
");
$scores->execute([$studentId]);
$scores = $scores->fetchAll();

$print = isset($_GET['print']);
?>
<?php if (!$print): ?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>My Readiness Report</h2>
    <a href="?print=1" class="btn btn-outline-primary" target="_blank">Print PDF</a>
</div>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Readiness Report — <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 2rem; font-size: 14px; }
        @media print { .no-print { display: none !important; } }
        .badge { font-size: 11px; }
    </style>
</head>
<body>
<div class="no-print mb-3">
    <button onclick="window.print()" class="btn btn-primary">Print</button>
    <a href="?" class="btn btn-secondary">Back</a>
</div>
<?php endif; ?>

<?php
$stmt = $db->prepare("SELECT s.*, cl.level_name, u.name, u.email FROM students s JOIN users u ON s.user_id = u.user_id LEFT JOIN cbc_levels cl ON s.cbc_level_id = cl.level_id WHERE s.student_id = ?");
$stmt->execute([$studentId]);
$profile = $stmt->fetch();
?>

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col">
                <h3><?= htmlspecialchars($profile['name']) ?></h3>
                <p class="mb-1"><?= htmlspecialchars($profile['registration_no']) ?> | <?= htmlspecialchars($profile['course']) ?> Year <?= $profile['year_of_study'] ?></p>
                <p class="mb-0 text-muted small">Report generated: <?= date('F j, Y') ?></p>
            </div>
            <div class="col-auto text-end">
                <?php
                $avgScore = !empty($scores) ? round(array_sum(array_column($scores, 'match_score')) / count($scores), 1) : 0;
                $topScore = !empty($scores) ? round(max(array_column($scores, 'match_score')), 1) : 0;
                $cbc = $engine->getCbcLevel($avgScore);
                ?>
                <div class="display-3 fw-bold text-<?= $avgScore >= 70 ? 'success' : ($avgScore >= 40 ? 'warning' : 'danger') ?>"><?= $avgScore ?>%</div>
                <span class="badge bg-primary">Avg Readiness — <?= $cbc['level_name'] ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Summary</h5>
                <table class="table table-sm mb-0">
                    <tr><td>Jobs Available</td><td><strong><?= $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn() ?></strong></td></tr>
                    <tr><td>Jobs Scored</td><td><strong><?= count($scores) ?></strong></td></tr>
                    <tr><td>Top Match</td><td><strong><?= $topScore ?>%</strong></td></tr>
                    <?php $skillCount = $db->prepare("SELECT COUNT(*) FROM student_skills WHERE student_id = ?"); $skillCount->execute([$studentId]); ?>
                    <tr><td>Skills Assessed</td><td><strong><?= $skillCount->fetchColumn() ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Top Recommendations</h5>
                <?php
                $recs = $db->prepare("SELECT r.*, sk.skill_name FROM recommendations r JOIN skills sk ON r.skill_id = sk.skill_id WHERE r.student_id = ? AND r.is_actioned = 0 LIMIT 5");
                $recs->execute([$studentId]);
                $recs = $recs->fetchAll();
                ?>
                <?php if (empty($recs)): ?>
                    <p class="text-muted mb-0">No recommendations yet. Run a job match first.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($recs as $r): ?>
                            <li class="mb-1 small">
                                <span class="badge bg-<?= $r['rec_type'] === 'course' ? 'primary' : ($r['rec_type'] === 'online_resource' ? 'success' : 'secondary') ?> me-1"><?= str_replace('_', ' ', $r['rec_type']) ?></span>
                                <?= htmlspecialchars($r['rec_title']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Job Readiness Scores</h5>
        <?php if (empty($scores)): ?>
            <p class="text-muted mb-0">No jobs scored yet. Visit <a href="<?= BASE_URL ?>/jobs.php">Job Matching</a> to get started.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Weighted</th>
                            <th>Keyword</th>
                            <th>Hybrid</th>
                            <th>CBC Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scores as $s): ?>
                            <?php $lvl = $engine->getCbcLevel($s['match_score']); ?>
                            <tr>
                                <td><?= htmlspecialchars($s['title']) ?></td>
                                <td><?= htmlspecialchars($s['company']) ?></td>
                                <td><?= $s['weighted_score'] ?>%</td>
                                <td><?= $s['keyword_score'] ?>%</td>
                                <td><strong class="text-<?= $s['match_score'] >= 70 ? 'success' : ($s['match_score'] >= 40 ? 'warning' : 'danger') ?>"><?= $s['match_score'] ?>%</strong></td>
                                <td><span class="badge bg-primary"><?= $lvl['level_name'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($print): ?>
</body></html>
<?php else: ?>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
<?php endif; ?>
