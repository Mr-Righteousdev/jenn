<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/University.php';
require_once __DIR__ . '/../../src/utils/JobApi.php';
requirePermission('system.view');

// One page that answers "is everything working?": settings, connections, the job API and recent errors.
$db = Database::getConnection();
$apiTest = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api'])) {
    checkCsrf();
    $apiTest = (new JobApi())->testConnection();
    if ($apiTest['success']) app_log('info', 'Job API test succeeded', ['jobs' => count($apiTest['jobs']), 'ms' => $apiTest['ms']]);
}

$checks = [];
$add = function (string $group, string $name, string $state, string $detail) use (&$checks) { $checks[$group][] = compact('name', 'state', 'detail'); };

// Settings (.env). Values are never shown, only whether they're set.
$envFile = realpath(__DIR__ . '/../../.env');
$add('Settings', '.env file', $envFile && is_readable($envFile) ? 'ok' : 'bad',
     $envFile ? (is_readable($envFile) ? 'Found in the project folder and readable by PHP.' : 'Found but not readable by the web server user.') : 'Not found in the project folder.');
// Each setting with the default the app falls back to when it's missing
foreach (['DB_NAME' => 'jenn_db', 'DB_USERNAME' => 'jm', 'DB_PASSWORD' => '(default)', 'RAPIDAPI_KEY' => 'none: sample jobs', 'UNI_DB_NAME' => 'campus_dynamics'] as $key => $default) {
    $set = getenv($key) !== false && getenv($key) !== '';
    $shown = in_array($key, ['DB_NAME', 'UNI_DB_NAME'], true) ? ' (' . htmlspecialchars((string)getenv($key)) . ')' : '';
    $add('Settings', $key, $set ? 'ok' : 'warn', $set ? "Set$shown." : 'Not set; using the default (' . htmlspecialchars($default) . '). Set it in .env for production.');
}
$devAuth = getenv('UNI_AUTH_MODE') === 'dev';
$add('Settings', 'Sign-in mode', $devAuth ? 'warn' : 'ok', $devAuth
    ? 'DEVELOPMENT: every university account signs in with DEV_LOGIN_PASSWORD. Remove UNI_AUTH_MODE from .env for real use.'
    : 'University accounts are checked against their portal passwords.');

// Server
$add('Server', 'PHP version', version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'bad', PHP_VERSION . ' (8.1 or newer needed)');
foreach (['pdo_mysql', 'mbstring', 'curl'] as $ext) {
    $add('Server', "PHP extension $ext", extension_loaded($ext) ? 'ok' : 'bad', extension_loaded($ext) ? 'Loaded.' : 'Missing: install it and restart PHP-FPM.');
}
$add('Server', 'Error log', app_log_writable() ? 'ok' : 'bad', app_log_writable()
    ? 'storage/logs/app.log is writable.'
    : 'storage/logs is not writable by the web server, so errors go to PHP\'s own log instead. Fix: sudo chown -R www-data storage/logs');

// Databases
try {
    $students = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $add('Databases', 'App database', 'ok', 'Connected to ' . htmlspecialchars($db->query("SELECT DATABASE()")->fetchColumn()) . " · $students students.");
} catch (Throwable $e) {
    $add('Databases', 'App database', 'bad', htmlspecialchars($e->getMessage()));
}
try {
    $n = count(University::faculties());
    $add('Databases', 'University data', $n ? 'ok' : 'warn', 'Connected to ' . htmlspecialchars(University::source()) . " (read-only) · $n faculties.");
} catch (Throwable $e) {
    $add('Databases', 'University data', 'bad', 'Can\'t connect: ' . htmlspecialchars($e->getMessage()));
}
$last = $db->query("SELECT status, finished_at, started_at, summary FROM sync_log ORDER BY sync_id DESC LIMIT 1")->fetch();
$add('Databases', 'Last sync', !$last ? 'warn' : ($last['status'] === 'ok' ? 'ok' : 'bad'), !$last ? 'Never synced.'
    : ($last['status'] === 'ok' ? 'Succeeded ' . date('j M Y, H:i', strtotime($last['finished_at'])) . '.' : ucfirst($last['status']) . ': ' . htmlspecialchars((string)$last['summary'])));

// Job API (configuration only; the live test is the button)
$api = new JobApi();
$add('Job API', 'RapidAPI key', $api->isConfigured() ? 'ok' : 'warn', $api->isConfigured()
    ? 'Set (ends in …' . htmlspecialchars(substr((string)getenv('RAPIDAPI_KEY'), -4)) . '). Use "Test the job API" below to check it works.'
    : 'Not set: "Fetch from API" will use sample jobs.');

$log = app_log_recent(80);
$levelFilter = $_GET['level'] ?? '';
if ($levelFilter) $log = array_values(array_filter($log, fn($e) => ($e['level'] ?? '') === $levelFilter));
$badCount = array_sum(array_map(fn($g) => count(array_filter($g, fn($c) => $c['state'] === 'bad')), $checks));
$icons = ['ok' => 'check', 'warn' => 'alert', 'bad' => 'alert'];
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>System health</h1>
        <p>Whether everything the app depends on is working, and the most recent errors. Problems are logged instead of failing silently.</p>
    </div>
    <form method="POST" class="page-actions">
        <?= csrfField() ?>
        <button type="submit" name="test_api" value="1" class="btn btn-primary btn-sm">Test the job API</button>
    </form>
</header>

<?php if ($apiTest): ?>
    <?php if ($apiTest['success']): ?>
        <?= flash('Job API works: ' . count($apiTest['jobs']) . ' jobs returned in ' . $apiTest['ms'] . ' ms.') ?>
    <?php else: ?>
        <div class="flash flash-err alert-static align-items-start" role="alert"><?= icon('alert') ?><div><strong>Job API test failed.</strong> <?= htmlspecialchars($apiTest['error']) ?></div></div>
    <?php endif; ?>
<?php endif; ?>
<?= $badCount ? flash("$badCount check" . ($badCount === 1 ? '' : 's') . ' failed. See the red items below.', 'err') : flash('All required checks pass.') ?>

<div class="row g-3">
    <div class="col-xl-5">
        <?php foreach ($checks as $group => $items): ?>
            <section class="panel mb-3">
                <h2 class="panel-title"><?= $group ?></h2>
                <ul class="health">
                    <?php foreach ($items as $c): ?>
                        <li class="is-<?= $c['state'] === 'ok' ? 'ok' : 'warn' ?><?= $c['state'] === 'bad' ? ' is-bad' : '' ?>">
                            <span class="health-icon" aria-hidden="true"><?= icon($icons[$c['state']]) ?></span>
                            <span class="health-text"><strong><?= $c['name'] ?></strong><span class="d-block small text-muted"><?= $c['detail'] ?></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="col-xl-7">
        <section class="panel">
            <header class="panel-head">
                <div><h3>Recent log entries</h3><p>Newest first, from <code>storage/logs/app.log</code>. Quote the reference when reporting a problem.</p></div>
                <div class="chips">
                    <?php foreach (['' => 'All', 'error' => 'Errors', 'warning' => 'Warnings', 'info' => 'Info'] as $k => $label): ?>
                        <a class="chip<?= $levelFilter === $k ? ' is-on' : '' ?>" href="?level=<?= $k ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </header>
            <?php if (!$log): ?>
                <div class="empty">Nothing logged yet<?= $levelFilter ? ' at this level' : '' ?>.</div>
            <?php else: ?>
                <ul class="log-list">
                    <?php foreach ($log as $e): ?>
                        <li class="log-<?= htmlspecialchars($e['level'] ?? 'info') ?>">
                            <div class="log-head">
                                <span class="tag <?= in_array($e['level'] ?? '', ['error', 'fatal']) ? 'tag-err' : (($e['level'] ?? '') === 'warning' ? 'tag-warn' : '') ?>"><?= htmlspecialchars($e['level'] ?? '') ?></span>
                                <strong><?= htmlspecialchars($e['message'] ?? '') ?></strong>
                            </div>
                            <div class="small text-muted"><?= htmlspecialchars($e['time'] ?? '') ?> · ref <?= htmlspecialchars($e['ref'] ?? '') ?> · <?= htmlspecialchars($e['url'] ?? '') ?></div>
                            <?php if (!empty($e['context'])): ?>
                                <pre class="log-context"><?= htmlspecialchars(json_encode($e['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
