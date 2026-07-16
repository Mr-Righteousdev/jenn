<?php

require_once __DIR__ . '/env.php';

session_start();

$baseUrl = $_SERVER['BASE_URL'] ?? getenv('BASE_URL') ?? '';
define('BASE_URL', $baseUrl);
define('APP_NAME', 'Competency Profiling System');
