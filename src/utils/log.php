<?php

// Application log: one JSON object per line in storage/logs/app.log (outside the web root).
// If that file isn't writable, entries go to PHP's own error log instead, so nothing is lost silently.
const APP_LOG_FILE = __DIR__ . '/../../storage/logs/app.log';

/** Writes a log entry and returns a short reference id that can be shown to the user. */
function app_log(string $level, string $message, array $context = []): string {
    $ref = bin2hex(random_bytes(4));
    $entry = json_encode([
        'time' => date('Y-m-d H:i:s'), 'ref' => $ref, 'level' => $level, 'message' => $message,
        'context' => $context, 'url' => $_SERVER['REQUEST_URI'] ?? 'cli', 'user' => $_SESSION['user_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";

    // ponytail: single file rotated at 5 MB into app.log.1; use logrotate if more history is needed
    if (is_file(APP_LOG_FILE) && filesize(APP_LOG_FILE) > 5 * 1024 * 1024) @rename(APP_LOG_FILE, APP_LOG_FILE . '.1');
    if (@file_put_contents(APP_LOG_FILE, $entry, FILE_APPEND | LOCK_EX) === false) error_log('[jenn] ' . trim($entry));
    return $ref;
}

/** The newest entries first. */
function app_log_recent(int $limit = 50): array {
    if (!is_readable(APP_LOG_FILE)) return [];
    $lines = array_slice(file(APP_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$limit);
    return array_reverse(array_filter(array_map(fn($l) => json_decode($l, true), $lines)));
}

function app_log_writable(): bool {
    return is_file(APP_LOG_FILE) ? is_writable(APP_LOG_FILE) : is_writable(dirname(APP_LOG_FILE));
}

// Uncaught errors: log them and show a friendly page with a reference, instead of a blank screen
set_exception_handler(function (Throwable $e): void {
    $ref = app_log('error', get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Error (ref $ref): {$e->getMessage()}\n"); exit(1); }
    if (!headers_sent()) http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
       . '<div class="container py-5" style="max-width:560px"><h1 class="h3">Something went wrong</h1>'
       . '<p class="text-muted">The error has been recorded. If it keeps happening, give the administrator this reference: <strong>'
       . htmlspecialchars($ref) . '</strong></p><a href="/dashboard.php" class="btn btn-outline-secondary btn-sm">Back to overview</a></div>';
});

// Fatal errors (out of memory, syntax errors in included files) bypass the exception handler
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('fatal', $e['message'], ['file' => $e['file'], 'line' => $e['line']]);
    }
});

// Warnings and notices are logged too, then PHP carries on as normal
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) return false;     // silenced with @
    app_log($no & (E_WARNING | E_USER_WARNING) ? 'warning' : 'notice', $msg, ['file' => $file, 'line' => $line]);
    return false;
});
