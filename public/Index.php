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
require_once $root . '/Controllers/LeaveController.php';

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

/** Reads a whole-number query parameter within a range, or fails with 422. */
function intParam(string $name, int $min, int $max): int
{
    $value = filter_var($_GET[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($value === false) {
        throw new HttpException(422, "$name must be a number between $min and $max");
    }
    return $value;
}

try {
    $database = new Database($dbConfig);
    $auth = new AuthController($database, new JwtHandler($jwtConfig), $basePath);
    $users = new UserController($database);
    $leave = new LeaveController($database);
    $admin = new AdminController($database);

    $router = new Router();

    // Login, token hand-over, logout
    $router->post('/api/login', function () use ($auth) {
        $auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    });
    $router->get('/api/authenticate', function () use ($auth) {
        $auth->takeSessionToken();
    });
    $router->post('/api/logout', function () use ($auth) {
        $auth->logout();
    });

    // Employee panel. Employees reach their own id, admins any id.
    $router->get('/api/user/today', function () use ($auth, $users) {
        $users->getUserDataHour($auth->requireUser($_GET['id'] ?? null));
    });
    $router->get('/api/user/summary', function () use ($auth, $users) {
        $users->summary($auth->requireUser($_GET['id'] ?? null));
    });
    $router->post('/api/user/clock', function () use ($auth, $users) {
        $body = HelperFunctions::jsonBody();
        $users->clock($auth->requireUser($body['id'] ?? null), (string) ($body['action'] ?? ''));
    });
    $router->get('/api/user/hours', function () use ($auth, $users) {
        $userId = $auth->requireUser($_GET['id'] ?? null);
        $year = intParam('year', 2000, 2100);
        if (isset($_GET['month'])) {
            $users->getUserHoursLogMonth($year, intParam('month', 1, 12), $userId);
        } else {
            $users->getUserHoursLogYear($year, $userId);
        }
    });
    $router->post('/api/user/hours', function () use ($auth, $users) {
        $body = HelperFunctions::jsonBody();
        $userId = $auth->requireUser($body['id'] ?? null);
        $users->editLog($userId, $body, $auth->callerId());
    });
    $router->get('/api/user/export', function () use ($auth, $users) {
        $users->exportMonth($auth->requireUser($_GET['id'] ?? null), intParam('year', 2000, 2100), intParam('month', 1, 12));
    });
    $router->post('/api/user/password', function () use ($auth) {
        $body = HelperFunctions::jsonBody();
        $auth->changePassword((int) ($body['id'] ?? 0), $body);
    });

    // Vacation requests
    $router->get('/api/user/leave', function () use ($auth, $leave) {
        $leave->listOwn($auth->requireUser($_GET['id'] ?? null));
    });
    $router->post('/api/user/leave', function () use ($auth, $leave) {
        $body = HelperFunctions::jsonBody();
        $leave->create($auth->requireUser($body['id'] ?? null), $body);
    });
    $router->post('/api/user/leave/{requestId}/cancel', function (array $params) use ($auth, $leave) {
        $body = HelperFunctions::jsonBody();
        $leave->cancelOwn($auth->requireUser($body['id'] ?? null), $params['requestId']);
    });

    // Admin panel
    $router->get('/api/admin/overview', function () use ($auth, $admin) {
        $auth->requireAdmin();
        $admin->overview();
    });
    $router->get('/api/admin/employees', function () use ($auth, $admin) {
        $auth->requireAdmin();
        $admin->listEmployees();
    });
    $router->post('/api/admin/employees', function () use ($auth, $admin) {
        $admin->createEmployee($auth->requireAdmin(), HelperFunctions::jsonBody());
    });
    $router->patch('/api/admin/employees/{id}', function (array $params) use ($auth, $admin) {
        $admin->updateEmployee($auth->requireAdmin(), $params['id'], HelperFunctions::jsonBody());
    });
    $router->get('/api/admin/leave', function () use ($auth, $leave) {
        $auth->requireAdmin();
        $group = $_GET['status'] ?? null;
        $leave->listAll(in_array($group, ['pending', 'decided'], true) ? $group : null);
    });
    foreach (['approve', 'reject', 'cancel'] as $decision) {
        $router->post("/api/admin/leave/{requestId}/$decision", function (array $params) use ($auth, $leave, $decision) {
            $leave->decide($auth->requireAdmin(), $params['requestId'], $decision, HelperFunctions::jsonBody());
        });
    }
    $router->get('/api/admin/audit', function () use ($auth, $admin) {
        $auth->requireAdmin();
        $admin->auditLog();
    });

    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (HttpException $e) {
    HelperFunctions::json(['error' => $e->getMessage()], $e->getStatus());
} catch (Throwable $e) {
    error_log((string) $e);
    HelperFunctions::json(['error' => 'Internal server error'], 500);
}
