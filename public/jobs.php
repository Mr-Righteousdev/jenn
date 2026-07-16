<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';
require_once __DIR__ . '/../src/utils/MatchingEngine.php';
requireLogin();

$user = getCurrentUser();
$db = Database::getConnection();

if ($user['role'] !== 'student') {
    die('This page is for students only.');
}

$stmt = $db->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$student = $stmt->fetch();
if (!$student) {
    die('Student profile not found.');
}
$studentId = $student['student_id'];

$engine = new MatchingEngine();

// Calculate readiness for a specific job
$selectedJobId = $_GET['job_id'] ?? null;
$result = null;
if ($selectedJobId) {
    $result = $engine->calculateReadiness($studentId, (int)$selectedJobId);
    $cbcLevel = $engine->getCbcLevel($result['hybrid_score']);
    $engine->generateRecommendations($studentId, (int)$selectedJobId);
}

// Get all jobs with their match scores
$jobs = $db->query("
    SELECT j.*,
           jr.match_score, jr.weighted_score, jr.keyword_score, jr.report_generated
    FROM jobs j
    LEFT JOIN job_readiness jr ON j.job_id = jr.job_id AND jr.student_id = $studentId
    ORDER BY jr.match_score DESC, j.job_id DESC
")->fetchAll();

// Get student skills for keyword visualization
$stmt = $db->prepare("
    SELECT sk.skill_name, sk.category, ss.skill_level
    FROM student_skills ss
    JOIN skills sk ON ss.skill_id = sk.skill_id
    WHERE ss.student_id = ?
    ORDER BY sk.category
");
$stmt->execute([$studentId]);
$studentSkills = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<div class="row mb-4">
    <div class="col">
        <h2>Job Matching</h2>
        <p class="text-muted">See how your skills align with real job opportunities.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Your Skills</h5>
                <?php if (empty($studentSkills)): ?>
                    <p class="text-muted">No skills assessed yet. Ask your lecturer to assess you.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($studentSkills as $sk): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($sk['skill_name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($sk['category']) ?></small>
                                </div>
                                <span class="badge bg-<?= $sk['skill_level'] >= 70 ? 'success' : ($sk['skill_level'] >= 40 ? 'warning' : 'danger') ?> rounded-pill"><?= $sk['skill_level'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if ($result && $selectedJobId): ?>
            <?php
            $stmt = $db->prepare("SELECT * FROM jobs WHERE job_id = ?");
            $stmt->execute([$selectedJobId]);
            $selectedJob = $stmt->fetch();
            ?>
            <div class="card mb-3 border-<?= $result['hybrid_score'] >= 70 ? 'success' : ($result['hybrid_score'] >= 40 ? 'warning' : 'danger') ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="card-title"><?= htmlspecialchars($selectedJob['title']) ?></h4>
                            <h6 class="text-muted"><?= htmlspecialchars($selectedJob['company']) ?></h6>
                        </div>
                        <div class="text-center">
                            <div class="display-4 fw-bold text-<?= $result['hybrid_score'] >= 70 ? 'success' : ($result['hybrid_score'] >= 40 ? 'warning' : 'danger') ?>">
                                <?= $result['hybrid_score'] ?>%
                            </div>
                            <span class="badge bg-<?= $cbcLevel['level_name'] === 'Expert' ? 'dark' : 'primary' ?>"><?= $cbcLevel['level_name'] ?></span>
                        </div>
                    </div>

                    <div class="row mt-3 text-center">
                        <div class="col">
                            <small class="text-muted d-block">Weighted Skills</small>
                            <strong><?= $result['weighted_score'] ?>%</strong>
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-info" style="width:<?= $result['weighted_score'] ?>%"></div>
                            </div>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Keyword Match</small>
                            <strong><?= $result['keyword_score'] ?>%</strong>
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-secondary" style="width:<?= $result['keyword_score'] ?>%"></div>
                            </div>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Hybrid Score</small>
                            <strong><?= $result['hybrid_score'] ?>%</strong>
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-success" style="width:<?= $result['hybrid_score'] ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($result['gaps'])): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Skill Gaps</h5>
                        <p class="text-muted">Top missing skills to work on:</p>
                        <?php foreach ($result['gaps'] as $gap): ?>
                            <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($gap['skill_name']) ?></strong>
                                    <span class="badge bg-<?= $gap['severity'] === 'high' ? 'danger' : ($gap['severity'] === 'medium' ? 'warning' : 'info') ?> ms-2"><?= $gap['severity'] ?> gap</span>
                                    <br><small class="text-muted">Current: <?= $gap['current_level'] ?>/100 &rarr; Need: 60+</small>
                                </div>
                                <span class="text-muted">Weight: <?= $gap['weight'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php
                $stmt = $db->prepare("SELECT * FROM recommendations WHERE student_id = ? ORDER BY rec_type");
                $stmt->execute([$studentId]);
                $recs = $stmt->fetchAll();
                ?>
                <?php if (!empty($recs)): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Recommendations</h5>
                            <div class="list-group">
                                <?php foreach ($recs as $rec): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="badge bg-<?= $rec['rec_type'] === 'course' ? 'primary' : ($rec['rec_type'] === 'online_resource' ? 'success' : 'secondary') ?> me-2">
                                                    <?= str_replace('_', ' ', $rec['rec_type']) ?>
                                                </span>
                                                <strong><?= htmlspecialchars($rec['rec_title']) ?></strong>
                                            </div>
                                            <?php if ($rec['rec_link']): ?>
                                                <a href="<?= htmlspecialchars($rec['rec_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($rec['rec_description']): ?>
                                            <p class="mb-0 mt-1 text-muted small"><?= htmlspecialchars($rec['rec_description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card mb-3">
                    <div class="card-body text-center text-success py-4">
                        <h5>No critical skill gaps!</h5>
                        <p class="mb-0">You meet the minimum requirements for this role.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Available Jobs</h5>
                <?php if (empty($jobs)): ?>
                    <p class="text-muted mb-0">No jobs loaded yet. Ask your admin to import jobs.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($jobs as $job): ?>
                            <a href="?job_id=<?= $job['job_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selectedJobId == $job['job_id'] ? 'active' : '' ?>">
                                <div>
                                    <strong><?= htmlspecialchars($job['title']) ?></strong>
                                    <br><small><?= htmlspecialchars($job['company']) ?></small>
                                </div>
                                <div class="text-end">
                                    <?php if ($job['match_score'] !== null): ?>
                                        <span class="badge bg-<?= $job['match_score'] >= 70 ? 'success' : ($job['match_score'] >= 40 ? 'warning' : 'danger') ?> rounded-pill">
                                            <?= $job['match_score'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not scored</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
