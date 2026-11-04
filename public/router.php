<?php

// Router for PHP's built-in development server:
//   php -S localhost:8000 -t public public/router.php
// Existing files (views, JavaScript, CSS) are served directly.
// Everything else goes to the front controller.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/' || $path === '') {
    header('Location: /views/login.php');
    return true;
}

if ($path !== '/router.php' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/Index.php';
