<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = '/jenn';

if (str_starts_with($uri, $prefix)) {
    $path = substr($uri, strlen($prefix)) ?: '/';
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['SCRIPT_NAME'] = $path;
    putenv('BASE_URL=/jenn');

    // Serve the correct file from document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $filePath = $docRoot . $path;

    if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        return true;
    }
}

// Let the built-in server handle static files or 404 fallback
return false;
