<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/Sync.php';
requirePermission('university.sync');

$db = Database::getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    if (isset($_POST['toggle_programme'])) {
        $db->prepare("UPDATE programmes SET is_active = NOT is_active WHERE code = ?")->execute([(string)$_POST['toggle_programme']]);
        // A newly enabled programme needs a sync to bring in its students and results
        $message = 'Programme updated. Run a sync to bring in its students and results.';
    }
    if (isset($_POST['sync'])) {
        // A full sync takes seconds, but don't let PHP's default 30-second limit (or a closed tab) cut a large one off.
        // nginx's fastcgi_read_timeout must also allow it (see docs/production_upgrade.md).
        set_time_limit(0);
        ignore_user_abort(true);
        $r = Sync::run();
        $r['success']
            ? $message = "Synced {$r['students']} students, {$r['lecturers']} lecturers and {$r['results']} results. {$r['profiles']} competency profiles recalculated."
            : $error = 'Sync failed: ' . $r['error'];
    }
}

$lastSync = $db->query("SELECT * FROM sync_log ORDER BY sync_id DESC LIMIT 1")->fetch();
$history  = $db->query("SELECT * FROM sync_log ORDER BY sync_id DESC LIMIT 6")->fetchAll();

$programmes = $db->query("
    SELECT p.code, p.name, p.faculty_code, p.is_active,
           (SELECT COUNT(*) FROM programme_units pu WHERE pu.programme_code = p.code) AS units,
           (SELECT COUNT(*) FROM programme_units pu WHERE pu.programme_code = p.code
              AND NOT EXISTS (SELECT 1 FROM course_unit_skills c WHERE c.course_code = pu.course_code)) AS unmapped,
           (SELECT COUNT(*) FROM students s WHERE s.programme_code = p.code) AS students
    FROM programmes p ORDER BY p.is_active DESC, p.name")->fetchAll();
$faculties = $db->query("SELECT f.code, f.name, (SELECT COUNT(*) FROM users u WHERE u.role = 'staff' AND u.faculty_code = f.code) AS staff
                         FROM faculties f ORDER BY f.name")->fetchAll();
$byFaculty = [];
foreach ($programmes as $p) $byFaculty[$p['faculty_code'] ?? ''][] = $p;
$inUse = array_filter($programmes, fn($p) => $p['is_active']);

$summary = $lastSync && $lastSync['status'] === 'ok' ? json_decode($lastSync['summary'], true) : null;
$ago = function (?string $ts): string {
    if (!$ts) return 'never';
    $s = time() - strtotime($ts);
    return match (true) { $s < 60 => 'just now', $s < 3600 => floor($s / 60) . ' min ago', $s < 86400 => floor($s / 3600) . ' h ago', default => date('j M Y, H:i', strtotime($ts)) };
};
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>University data</h1>
        <p>Students, lecturers, course units and results come from the university system. This app only reads them; nothing here changes the university's records.</p>
    </div>
    <form method="POST" class="page-actions">
        <?= csrfField() ?>
        <input type="hidden" name="sync" value="1">
        <button type="submit" class="btn btn-primary btn-sm" onclick="this.disabled = true; this.textContent = 'Syncing…'; this.form.submit();">Sync now</button>
    </form>
</header>
<?= flash($message) ?>
<?= flash($error, 'err') ?>

<section class="sync-card panel mb-3">
    <div class="sync-status is-<?= $lastSync['status'] ?? 'never' ?>">
        <span class="sync-dot" aria-hidden="true"></span>
        <div>
            <strong><?= match ($lastSync['status'] ?? null) { 'ok' => 'Up to date', 'failed' => 'Last sync failed', 'running' => 'Sync in progress', default => 'Never synced' } ?></strong>
            <span>Last sync <?= $ago($lastSync['finished_at'] ?? $lastSync['started_at'] ?? null) ?> · source <code><?= htmlspecialchars(University::source()) ?></code> · read-only</span>
        </div>
    </div>
    <?php if ($summary): ?>
        <dl class="sync-figures">
            <div><dt>Students</dt><dd><?= $summary['students'] ?></dd></div>
            <div><dt>Lecturers</dt><dd><?= $summary['lecturers'] ?></dd></div>
            <div><dt>Results</dt><dd><?= $summary['results'] ?></dd></div>
            <div><dt>Course units</dt><dd><?= $summary['course_units'] ?></dd></div>
            <div><dt>Profiles</dt><dd><?= $summary['profiles'] ?></dd></div>
        </dl>
    <?php elseif (($lastSync['status'] ?? '') === 'failed'): ?>
        <p class="small mb-0 text-danger"><?= htmlspecialchars($lastSync['summary']) ?></p>
    <?php endif; ?>
</section>

<div class="row g-3">
    <div class="col-xl-8">
        <section class="panel">
            <header class="panel-head">
                <div>
                    <h3>Faculties and programmes</h3>
                    <p>Faculties, programmes and curricula always sync. Switch a programme on to bring in its current students and their results.
                       <?= count($inUse) ?> of <?= count($programmes) ?> programmes are in use.</p>
                </div>
            </header>
            <?php if (!$programmes): ?>
                <div class="empty">Nothing synced yet. Run a sync to load the university structure.</div>
            <?php endif; ?>
            <?php foreach ($faculties as $f): $list = $byFaculty[$f['code']] ?? []; if (!$list) continue; $on = count(array_filter($list, fn($p) => $p['is_active'])); ?>
                <details class="tree-dept<?= $on ? ' is-on' : '' ?>" <?= $on ? 'open' : '' ?>>
                    <summary class="tree-dept-head">
                        <div>
                            <strong><?= htmlspecialchars(nice($f['name'])) ?></strong>
                            <span class="small text-muted d-block"><?= count($list) ?> programmes · <?= $on ?> in use · <?= $f['staff'] ?> staff accounts</span>
                        </div>
                    </summary>
                    <ul class="tree-progs">
                        <?php foreach ($list as $p): ?>
                            <li class="prog-row">
                                <span class="prog-name"><span class="tag"><?= htmlspecialchars($p['code']) ?></span> <?= htmlspecialchars(nice($p['name'])) ?>
                                    <span class="text-muted small">· <?= $p['units'] ?> units<?= $p['is_active'] ? ' · ' . $p['students'] . ' students' : '' ?></span>
                                    <?php if ($p['is_active'] && $p['unmapped']): ?>
                                        <a class="tag tag-warn text-decoration-none" href="<?= BASE_URL ?>/admin/curriculum.php?programme=<?= urlencode($p['code']) ?>"><?= $p['unmapped'] ?> unmapped</a>
                                    <?php endif; ?>
                                </span>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <button type="submit" name="toggle_programme" value="<?= htmlspecialchars($p['code']) ?>" class="dept-toggle" role="switch" aria-checked="<?= $p['is_active'] ? 'true' : 'false' ?>"
                                            aria-label="<?= $p['is_active'] ? 'Stop using' : 'Use' ?> <?= htmlspecialchars(nice($p['name'])) ?>"><span></span><?= $p['is_active'] ? 'In use' : 'Off' ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endforeach; ?>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel mb-3">
            <h3 class="panel-title">How data flows</h3>
            <ol class="flow">
                <li><strong>University system</strong><span>Owns students, staff, course units and results</span></li>
                <li><strong>Sync (read-only)</strong><span>Copies them here on demand or nightly</span></li>
                <li><strong>Curriculum mapping</strong><span>Course units → skills, set by faculty staff</span></li>
                <li><strong>Competency profiles</strong><span>Marks become skill levels and CBC levels</span></li>
                <li><strong>Job matching</strong><span>Profiles scored against job requirements</span></li>
            </ol>
        </section>
        <section class="panel">
            <h3 class="panel-title">Recent syncs</h3>
            <?php if (!$history): ?><div class="empty py-3">No syncs yet.</div><?php endif; ?>
            <ul class="sync-history">
                <?php foreach ($history as $h): ?>
                    <li class="is-<?= $h['status'] ?>">
                        <span class="sync-dot" aria-hidden="true"></span>
                        <span><?= date('j M, H:i', strtotime($h['started_at'])) ?></span>
                        <span class="text-muted ms-auto"><?= $h['status'] === 'ok' ? (json_decode($h['summary'], true)['results'] ?? 0) . ' results' : ucfirst($h['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="small text-muted mb-0 mt-2">Schedule it nightly with <code>php src/scripts/sync.php</code> in cron.</p>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
