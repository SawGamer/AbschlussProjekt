<?php

declare(strict_types=1);

// Front controller: every /api/* request ends up here.

$root = dirname(__DIR__);
require_once $root . '/Config/jwt.php';
require_once $root . '/Config/database.php';
require_once $root . '/utils/Router.php';
require_once $root . '/utils/HttpException.php';
require_once $root . '/utils/HelperFunctions.php';
require_once $root . '/Models/Database.php';
require_once $root . '/Models/JwtHandler.php';
require_once $root . '/Controllers/AuthController.php';
require_once $root . '/Controllers/UserController.php';
require_once $root . '/Controllers/AdminController.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Berlin');
ini_set('display_errors', getenv('APP_DEBUG') ? '1' : '0');
error_reporting(E_ALL);
header('X-Content-Type-Options: nosniff');

session_name('workforce');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// The app can live in a sub-folder (for example /AbschlussProjekt/public under XAMPP),
// so routes are matched relative to this folder's URL path.
$publicDir = str_replace('\\', '/', __DIR__);
$documentRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$basePath = ($documentRoot !== '' && strpos($publicDir, $documentRoot) === 0)
    ? substr($publicDir, strlen($documentRoot))
    : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

try {
    $database = new Database($dbConfig);
    $authController = new AuthController($database, new JwtHandler($jwtConfig), $basePath);
    $userController = new UserController($database);
    $adminController = new AdminController($database);

    $router = new Router();

    // Login form and token hand-over
    $router->post('/api/login', function () use ($authController) {
        $authController->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    });
    $router->get('/api/authenticate', function () use ($authController) {
        $authController->takeSessionToken();
    });

    // Employee dashboard
    $router->get('/api/user/today', function () use ($authController, $userController) {
        $userController->getUserDataHour($authController->requireUser($_GET['id'] ?? null));
    });
    $router->post('/api/user/clock', function () use ($authController, $userController) {
        $body = HelperFunctions::jsonBody();
        $userId = $authController->requireUser($body['id'] ?? null);
        $userController->logTime($userId, $body['pause'] ?? 0);
    });
    $router->get('/api/user/hours', function () use ($authController, $userController) {
        $userId = $authController->requireUser($_GET['id'] ?? null);
        $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        if ($year === false) {
            throw new HttpException(422, 'year must be a number between 2000 and 2100');
        }
        if (isset($_GET['month'])) {
            $month = filter_var($_GET['month'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
            if ($month === false) {
                throw new HttpException(422, 'month must be a number between 1 and 12');
            }
            $userController->getUserHoursLogMonth($year, $month, $userId);
        } else {
            $userController->getUserHoursLogYear($year, $userId);
        }
    });
    $router->post('/api/user/hours', function () use ($authController, $userController) {
        $body = HelperFunctions::jsonBody();
        $userId = $authController->requireUser($body['id'] ?? null);
        $userController->editLog($userId, $body);
    });

    // Admin dashboard
    $router->get('/api/admin/users', function () use ($authController, $adminController) {
        $authController->requireAdmin();
        $adminController->getAllUsers();
    });

    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (HttpException $e) {
    HelperFunctions::json(['error' => $e->getMessage()], $e->getStatus());
} catch (Throwable $e) {
    error_log((string) $e);
    HelperFunctions::json(['error' => 'Internal server error'], 500);
}
