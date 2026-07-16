<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/JobApi.php';
requireRole('admin');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle fetch from API
if (isset($_POST['fetch'])) {
    $query = trim($_POST['query'] ?: 'software developer');
    $country = trim($_POST['country'] ?: 'ug');
    $api = new JobApi();
    $result = $api->fetchJobs($query, $country);

    if (!$result['success']) {
        $error = $result['error'];
    } else {
        $storeResult = JobApi::storeJobs($result['jobs']);
        if ($storeResult['success']) {
            $message = 'Fetched and stored ' . count($result['jobs']) . ' jobs, ' . $storeResult['stored'] . ' saved with skill mappings.';
            if (!$api->isConfigured()) {
                $message .= ' (Using sample data — set RAPIDAPI_KEY env for live data)';
            }
        } else {
            $error = $storeResult['error'];
        }
    }
}

// Handle manual add
if (isset($_POST['add_manual'])) {
    $job = [
        'title' => $_POST['title'],
        'company' => $_POST['company'],
        'description' => $_POST['description'],
        'url' => $_POST['url'] ?? '#',
        'source' => 'Manual',
    ];
    $result = JobApi::storeJobs([$job]);
    if ($result['success']) {
        $message = 'Job added manually.';
    } else {
        $error = $result['error'];
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM jobs WHERE job_id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Job deleted.';
}

$jobs = $db->query("
    SELECT j.*,
           COUNT(DISTINCT js.id) AS skill_count,
           GROUP_CONCAT(DISTINCT CONCAT(sk.skill_name, '|', js.weight) ORDER BY js.weight DESC SEPARATOR '||') AS matched_skills_raw
    FROM jobs j
    LEFT JOIN job_skills js ON j.job_id = js.job_id
    LEFT JOIN skills sk ON js.skill_id = sk.skill_id
    GROUP BY j.job_id
    ORDER BY j.scraped_date DESC, j.job_id DESC
")->fetchAll();

// Parse matched_skills into structured array
foreach ($jobs as &$job) {
    $job['skills_display'] = [];
    if ($job['matched_skills_raw']) {
        $parts = explode('||', $job['matched_skills_raw']);
        foreach ($parts as $p) {
            [$name, $weight] = explode('|', $p);
            $job['skills_display'][] = [
                'name' => $name,
                'weight' => (float) $weight,
                'relevance' => $weight >= 0.7 ? 'high' : ($weight >= 0.5 ? 'medium' : 'low'),
            ];
        }
    }
}
unset($job);
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<h2>Job Management</h2>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="jobTabs">
    <li class="nav-item"><a class="nav-link active" href="#browse" data-bs-toggle="tab">Browse Jobs (<?= count($jobs) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" href="#fetch" data-bs-toggle="tab">Fetch from API</a></li>
    <li class="nav-item"><a class="nav-link" href="#manual" data-bs-toggle="tab">Add Manual</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="browse">
        <div class="card">
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <p class="text-muted mb-0">No jobs yet. Use the Fetch tab to import jobs or Add Manual to create one.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Company</th>
                                    <th>Source</th>
                                    <th>Matched Skills</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($job['title']) ?></td>
                                        <td><?= htmlspecialchars($job['company']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($job['source']) ?></span></td>
                                        <td>
                                            <?php if (empty($job['skills_display'])): ?>
                                                <span class="text-muted small">None</span>
                                            <?php else: ?>
                                                <?php foreach ($job['skills_display'] as $sk): ?>
                                                    <span class="badge bg-<?= $sk['relevance'] === 'high' ? 'success' : ($sk['relevance'] === 'medium' ? 'warning text-dark' : 'light text-dark') ?> me-1" title="Relevance: <?= $sk['relevance'] ?>">
                                                        <?= htmlspecialchars($sk['name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $job['scraped_date'] ?></td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="collapse" data-bs-target="#desc-<?= $job['job_id'] ?>">Desc</button>
                                            <a href="?delete=<?= $job['job_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this job?')">Delete</a>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="desc-<?= $job['job_id'] ?>">
                                        <td colspan="6" class="bg-light">
                                            <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.875rem;"><?= htmlspecialchars($job['description']) ?></pre>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="fetch">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Fetch Jobs via JSearch API</h5>
                <p class="text-muted">
                    <?php if ((new JobApi())->isConfigured()): ?>
                        <span class="text-success">RAPIDAPI_KEY is set — using live API.</span>
                    <?php else: ?>
                        <span class="text-warning">No RAPIDAPI_KEY set. Using sample jobs. Set <code>RAPIDAPI_KEY</code> env var for live data.</span>
                    <?php endif; ?>
                </p>
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search Query</label>
                        <input type="text" name="query" class="form-control" value="software developer" placeholder="e.g. IT, developer, networking">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country Code</label>
                        <input type="text" name="country" class="form-control" value="ug" placeholder="ug, ke, us, etc.">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" name="fetch" class="btn btn-primary w-100">Fetch & Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="manual">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Add Job Manually</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Job Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Job Description</label>
                        <textarea name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Application URL (optional)</label>
                        <input type="url" name="url" class="form-control" placeholder="https://...">
                    </div>
                    <button type="submit" name="add_manual" class="btn btn-primary">Add Job</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
