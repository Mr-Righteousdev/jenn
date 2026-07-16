<?php

require_once __DIR__ . '/env.php';

session_start();

$baseUrl = getenv('BASE_URL') ?: '/jenn';
define('BASE_URL', $baseUrl);
define('APP_NAME', 'Competency Profiling System');
