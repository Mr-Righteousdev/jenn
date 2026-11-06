<?php
// Pull the latest data from the university SIS. Suitable for cron, e.g. nightly:
//   0 2 * * * php /var/www/jenn/src/scripts/sync.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../utils/Sync.php';

$r = Sync::run();
echo $r['success'] ? "Sync ok: " . json_encode(array_diff_key($r, ['success' => 1])) . PHP_EOL : "Sync failed: {$r['error']}" . PHP_EOL;
exit($r['success'] ? 0 : 1);
