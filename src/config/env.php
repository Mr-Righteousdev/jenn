<?php

$envFile = __DIR__ . '/../../.env';
if (!file_exists($envFile)) return;

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) continue;

    $key = trim($parts[0]);
    $value = trim($parts[1]);

    // Remove surrounding quotes if present
    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        $value = substr($value, 1, -1);
    }

    putenv("$key=$value");
    $_ENV[$key] = $value;
}
