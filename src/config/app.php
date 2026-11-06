<?php

require_once __DIR__ . '/env.php';
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Kampala');
require_once __DIR__ . '/../utils/log.php';
require_once __DIR__ . '/../utils/ui.php';

session_start();

$baseUrl = $_SERVER['BASE_URL'] ?? getenv('BASE_URL') ?? '';
define('BASE_URL', $baseUrl);
define('APP_NAME', 'Competency Profiling System');
