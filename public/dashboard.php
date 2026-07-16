<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';

requireLogin();
$user = getCurrentUser();
$db = Database::getConnection();
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<div class="row mb-4">
    <div class="col">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
        <p class="text-muted">Role: <?= ucfirst($user['role']) ?></p>
    </div>
</div>

<?php if ($user['role'] === 'student'): ?>
    <?php
    $stmt = $db->prepare("SELECT s.*, cl.level_name FROM students s LEFT JOIN cbc_levels cl ON s.cbc_level_id = cl.level_id WHERE s.user_id = ?");
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();

    // Get student skills
    $stmt = $db->prepare("SELECT sk.skill_name, sk.category, ss.skill_level FROM student_skills ss JOIN skills sk ON ss.skill_id = sk.skill_id WHERE ss.student_id = ? ORDER BY sk.category");
    $stmt->execute([$profile['student_id'] ?? 0]);
    $studentSkills = $stmt->fetchAll();

    // Group skills by category
    $skillData = [];
    $categoryLevels = [];
    foreach ($studentSkills as $sk) {
        $cat = $sk['category'];
        if (!isset($categoryLevels[$cat])) {
            $categoryLevels[$cat] = ['total' => 0, 'count' => 0];
        }
        $categoryLevels[$cat]['total'] += $sk['skill_level'];
        $categoryLevels[$cat]['count']++;
    }

    // Get latest results
    $stmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY academic_year DESC, semester DESC LIMIT 10");
    $stmt->execute([$profile['student_id'] ?? 0]);
    $results = $stmt->fetchAll();
    ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">My Profile</h5>
                    <p class="mb-1"><strong>Reg No:</strong> <?= htmlspecialchars($profile['registration_no'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Course:</strong> <?= htmlspecialchars($profile['course'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Year:</strong> <?= htmlspecialchars($profile['year_of_study'] ?? 'N/A') ?></p>
                    <p class="mb-0"><strong>CBC Level:</strong> <?= htmlspecialchars($profile['level_name'] ?? 'Not set') ?></p>
                </div>
            </div>

            <?php if (!empty($results)): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Recent Results</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['course_code']) ?></td>
                                            <td><span class="badge bg-<?= in_array($r['grade'], ['A','B']) ? 'success' : ($r['grade'] === 'C' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($r['grade']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Skills Overview</h5>
                    <canvas id="skillsChart" height="200"></canvas>
                </div>
            </div>

            <?php if (!empty($studentSkills)): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Detailed Skill Levels</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Skill</th>
                                        <th>Category</th>
                                        <th>Level</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentSkills as $sk): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sk['skill_name']) ?></td>
                                            <td><span class="badge bg-info"><?= htmlspecialchars($sk['category']) ?></span></td>
                                            <td><?= $sk['skill_level'] ?>/100</td>
                                            <td>
                                                <div class="progress" style="height:8px">
                                                    <div class="progress-bar bg-<?= $sk['skill_level'] >= 70 ? 'success' : ($sk['skill_level'] >= 40 ? 'warning' : 'danger') ?>" style="width:<?= $sk['skill_level'] ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row mt-3">
                <?php
                $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM jobs");
                $stmt->execute();
                $jobCount = $stmt->fetch()['cnt'];
                $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM job_readiness WHERE student_id = ?");
                $stmt->execute([$profile['student_id'] ?? 0]);
                $matchedCount = $stmt->fetch()['cnt'];
                ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Available Jobs</h5>
                            <p class="display-6"><?= $jobCount ?></p>
                            <a href="<?= BASE_URL ?>/jobs.php" class="btn btn-primary">View Matches</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Jobs Scored</h5>
                            <p class="display-6"><?= $matchedCount ?></p>
                            <a href="<?= BASE_URL ?>/jobs.php" class="btn btn-outline-primary">Match All</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('skillsChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode(array_keys($categoryLevels)) ?>,
            datasets: [{
                label: 'Your Skills',
                data: <?= json_encode(array_map(fn($c) => round($c['total'] / $c['count']), array_values($categoryLevels))) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.2)',
                borderColor: 'rgba(13, 110, 253, 1)',
                pointBackgroundColor: 'rgba(13, 110, 253, 1)',
            }]
        },
        options: {
            scales: {
                r: {
                    min: 0,
                    max: 100,
                    ticks: { stepSize: 20 }
                }
            }
        }
    });
    </script>

<?php elseif ($user['role'] === 'lecturer'): ?>
    <?php
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM students");
    $studentCount = $stmt->fetch()['cnt'];
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM student_skills WHERE assessed_by = " . (int)$user['user_id']);
    $assessedCount = $stmt->fetch()['cnt'];
    ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Students</h5>
                    <p class="display-6"><?= $studentCount ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Assessments Given</h5>
                    <p class="display-6"><?= $assessedCount ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <a href="<?= BASE_URL ?>/lecturer/assess.php" class="btn btn-primary">Assess Students</a>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($user['role'] === 'admin'): ?>
    <?php
    $levelCount = $db->query("SELECT COUNT(*) AS cnt FROM cbc_levels")->fetch()['cnt'];
    $skillCount = $db->query("SELECT COUNT(*) AS cnt FROM skills")->fetch()['cnt'];
    $userCount = $db->query("SELECT COUNT(*) AS cnt FROM users")->fetch()['cnt'];
    $studentCount = $db->query("SELECT COUNT(*) AS cnt FROM students")->fetch()['cnt'];
    $resultCount = $db->query("SELECT COUNT(*) AS cnt FROM student_results")->fetch()['cnt'];
    ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">CBC Levels</h5>
                    <p class="display-6"><?= $levelCount ?></p>
                    <a href="<?= BASE_URL ?>/admin/cbc_levels.php" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Skills</h5>
                    <p class="display-6"><?= $skillCount ?></p>
                    <a href="<?= BASE_URL ?>/admin/skills.php" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Users</h5>
                    <p class="display-6"><?= $userCount ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Students</h5>
                    <p class="display-6"><?= $studentCount ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Results Imported</h5>
                    <p class="display-6"><?= $resultCount ?></p>
                    <a href="<?= BASE_URL ?>/admin/import.php" class="btn btn-sm btn-outline-primary">Import CSV</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Jobs</h5>
                    <?php
                    $jobCount = $db->query("SELECT COUNT(*) AS cnt FROM jobs")->fetch()['cnt'];
                    ?>
                    <p class="display-6"><?= $jobCount ?></p>
                    <a href="<?= BASE_URL ?>/admin/jobs.php" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
