<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/JobApi.php';
requirePermission('jobs.manage');

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
$unmapped = count(array_filter($jobs, fn($j) => !$j['skill_count']));
$sources = count(array_unique(array_column($jobs, 'source')));
$apiLive = (new JobApi())->isConfigured();
$onlyUnmapped = ($_GET['filter'] ?? '') === 'unmapped';
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Jobs</h1>
        <p>Postings that students are matched against. A job only produces useful scores once skills are mapped to it.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" data-bs-target="#manual" type="button"><?= icon('plus', 14) ?> Add manually</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="tab" data-bs-target="#fetch" type="button">Fetch from API</button>
    </div>
</header>
<?= flash($message) ?>
<?= flash($error, 'err') ?>

<section class="kpis kpis-4" aria-label="Job figures">
    <div class="kpi"><span class="kpi-label">Jobs tracked</span><span class="kpi-value"><?= count($jobs) ?></span></div>
    <div class="kpi"><span class="kpi-label">With skills mapped</span><span class="kpi-value"><?= count($jobs) - $unmapped ?></span></div>
    <div class="kpi"><span class="kpi-label">Missing skills</span><span class="kpi-value<?= $unmapped ? ' is-warn' : '' ?>"><?= $unmapped ?></span></div>
    <div class="kpi"><span class="kpi-label">Sources</span><span class="kpi-value"><?= $sources ?></span></div>
</section>

<ul class="nav seg-tabs mb-3" role="tablist">
    <li><button class="active" data-bs-toggle="tab" data-bs-target="#browse" type="button" role="tab">All jobs <span><?= count($jobs) ?></span></button></li>
    <li><button data-bs-toggle="tab" data-bs-target="#fetch" type="button" role="tab">Fetch from API</button></li>
    <li><button data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">Add manually</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="browse" role="tabpanel">
        <section class="panel">
            <?php if (empty($jobs)): ?>
                <div class="empty">No jobs yet. Fetch some from the API or add one manually.</div>
            <?php else: ?>
                <div class="toolbar">
                    <label class="search">
                        <?= icon('search') ?>
                        <input type="search" id="jobSearch" class="form-control" placeholder="Search title, company or skill" aria-label="Search jobs">
                    </label>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="onlyUnmapped" <?= $onlyUnmapped ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold" for="onlyUnmapped">Missing skills only</label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table data-table mb-0" id="jobsTable">
                        <thead><tr><th>Job</th><th>Source</th><th>Mapped skills</th><th>Added</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr data-unmapped="<?= $job['skill_count'] ? 0 : 1 ?>" data-text="<?= htmlspecialchars(strtolower($job['title'] . ' ' . $job['company'] . ' ' . implode(' ', array_column($job['skills_display'], 'name')))) ?>">
                                    <td><strong><?= htmlspecialchars($job['title']) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($job['company']) ?></span></td>
                                    <td><span class="tag"><?= htmlspecialchars($job['source']) ?></span></td>
                                    <td>
                                        <?php if (empty($job['skills_display'])): ?>
                                            <span class="tag tag-warn"><?= icon('alert', 12) ?> None mapped</span>
                                        <?php else: ?>
                                            <div class="skill-tags">
                                                <?php foreach ($job['skills_display'] as $sk): ?>
                                                    <span class="tag <?= $sk['relevance'] === 'high' ? 'tag-brand' : '' ?>" title="Weight <?= number_format($sk['weight'], 2) ?> · <?= $sk['relevance'] ?> relevance"><?= htmlspecialchars($sk['name']) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num text-nowrap"><?= $job['scraped_date'] ? date('j M Y', strtotime($job['scraped_date'])) : '—' ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <button class="icon-btn" type="button" data-bs-toggle="collapse" data-bs-target="#desc-<?= $job['job_id'] ?>" title="Description" aria-label="Show description"><?= icon('eye') ?></button>
                                            <?php if ($job['url'] && $job['url'] !== '#'): ?>
                                                <a class="icon-btn" href="<?= htmlspecialchars($job['url']) ?>" target="_blank" rel="noopener" title="Open posting" aria-label="Open posting"><?= icon('link') ?></a>
                                            <?php endif; ?>
                                            <a href="?delete=<?= $job['job_id'] ?>" class="icon-btn is-danger" title="Delete" aria-label="Delete job" onclick="return confirm('Delete this job?')"><?= icon('trash') ?></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="collapse desc-row" id="desc-<?= $job['job_id'] ?>">
                                    <td colspan="5"><div class="job-desc"><?= nl2br(htmlspecialchars($job['description'])) ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty" id="noJobs" hidden>No jobs match these filters.</div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="tab-pane fade" id="fetch" role="tabpanel">
        <section class="panel">
            <h2 class="panel-title">Fetch jobs via the JSearch API</h2>
            <div class="flash <?= $apiLive ? 'flash-ok' : 'flash-warn' ?> alert-static">
                <?= icon($apiLive ? 'check' : 'alert') ?>
                <?= $apiLive ? 'API key found. Jobs come from the live API.' : 'No RAPIDAPI_KEY set, so sample jobs are used. Add the key to .env for live data.' ?>
            </div>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="query">Search query</label>
                    <input type="text" id="query" name="query" class="form-control" value="software developer" placeholder="e.g. IT, developer, networking">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="country">Country code</label>
                    <input type="text" id="country" name="country" class="form-control" value="ug" placeholder="ug, ke, us">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" name="fetch" class="btn btn-primary w-100">Fetch and store</button>
                </div>
            </form>
        </section>
    </div>

    <div class="tab-pane fade" id="manual" role="tabpanel">
        <section class="panel">
            <h2 class="panel-title">Add a job manually</h2>
            <p class="small text-muted">Skills are detected from the description automatically when you save.</p>
            <form method="POST">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="title">Job title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="company">Company</label>
                        <input type="text" id="company" name="company" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Job description</label>
                    <textarea id="description" name="description" class="form-control" rows="6" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="url">Application URL <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="url" id="url" name="url" class="form-control" placeholder="https://">
                </div>
                <button type="submit" name="add_manual" class="btn btn-primary">Add job</button>
            </form>
        </section>
    </div>
</div>

<script>
(() => {
    const search = document.getElementById('jobSearch'), only = document.getElementById('onlyUnmapped');
    if (!search) return;
    const apply = () => {
        const q = search.value.trim().toLowerCase(); let shown = 0;
        document.querySelectorAll('#jobsTable tbody tr[data-text]').forEach(tr => {
            const ok = (!q || tr.dataset.text.includes(q)) && (!only.checked || tr.dataset.unmapped === '1');
            tr.hidden = !ok; shown += ok;
            if (!ok) tr.nextElementSibling.classList.remove('show');
        });
        document.getElementById('noJobs').hidden = shown > 0;
    };
    search.addEventListener('input', apply); only.addEventListener('change', apply); apply();
    // Keep the segmented tabs in sync when a header button switches panes
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(b => b.addEventListener('shown.bs.tab', () => {
        document.querySelectorAll('.seg-tabs button').forEach(t => t.classList.toggle('active', t.dataset.bsTarget === b.dataset.bsTarget));
    }));
})();
</script>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
