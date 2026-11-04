<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/JwtHandler.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

class AuthController
{
    private const SESSION_TOKEN_KEY = 'jwt';

    private Database $database;
    private JwtHandler $jwtHandler;
    private string $basePath;

    public function __construct(Database $database, JwtHandler $jwtHandler, string $basePath)
    {
        $this->database = $database;
        $this->jwtHandler = $jwtHandler;
        $this->basePath = $basePath;
    }

    /**
     * Checks the login form. On success the token is parked in the session
     * and the browser is sent to the matching dashboard, which picks it up
     * through /api/authenticate.
     */
    public function login(string $username, string $password): void
    {
        $username = trim($username);
        $employee = $username === '' ? [] : $this->database->findEmployeeByUsername($username);

        if (!$employee || !password_verify($password, $employee['Password'])) {
            HelperFunctions::redirect($this->basePath . '/views/login.php?error=1');
            return;
        }

        session_regenerate_id(true);
        $isAdmin = (bool) $employee['is_admin'];
        $_SESSION[self::SESSION_TOKEN_KEY] = $this->jwtHandler->generate([
            'id' => (int) $employee['id'],
            'name' => $employee['first_name'],
            'admin' => $isAdmin,
        ]);

        HelperFunctions::redirect($isAdmin
            ? $this->basePath . '/views/admin_view.php'
            : $this->basePath . '/views/user_view.php?id=' . (int) $employee['id']);
    }

    /**
     * Hands the token from the login redirect to the page's JavaScript, once.
     * Answers 204 when there is none (for example after a page reload).
     */
    public function takeSessionToken(): void
    {
        $token = $_SESSION[self::SESSION_TOKEN_KEY] ?? null;
        unset($_SESSION[self::SESSION_TOKEN_KEY]);

        if ($token === null) {
            http_response_code(204);
            return;
        }
        HelperFunctions::json(['jwt' => $token]);
    }

    /**
     * Returns the requested employee id if the caller may access it:
     * employees may read and write only their own data, admins any employee's.
     */
    public function requireUser($requestedId): int
    {
        $claims = $this->requireClaims();

        if (!is_numeric($requestedId)) {
            throw new HttpException(400, 'Missing or invalid id');
        }
        $requestedId = (int) $requestedId;

        if ($claims['id'] !== $requestedId && empty($claims['admin'])) {
            throw new HttpException(403, 'Forbidden');
        }
        return $requestedId;
    }

    public function requireAdmin(): void
    {
        $claims = $this->requireClaims();
        if (empty($claims['admin'])) {
            throw new HttpException(403, 'Forbidden');
        }
    }

    private function requireClaims(): array
    {
        $claims = $this->jwtHandler->decode($this->bearerToken());
        if ($claims === null || !is_int($claims['id'] ?? null)) {
            throw new HttpException(401, 'Unauthorized');
        }
        return $claims;
    }

    private function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                }
            }
        }
        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : '';
    }
}
