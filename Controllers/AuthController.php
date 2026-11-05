<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/JwtHandler.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

class AuthController
{
    private const SESSION_TOKEN_KEY = 'jwt';
    public const MIN_PASSWORD_LENGTH = 8;

    private Database $database;
    private JwtHandler $jwtHandler;
    private string $basePath;

    /** The authenticated caller, filled by requireClaims(). */
    private ?array $caller = null;

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
        if (!$employee['active']) {
            HelperFunctions::redirect($this->basePath . '/views/login.php?inactive=1');
            return;
        }

        session_regenerate_id(true);
        $isAdmin = (bool) $employee['is_admin'];
        $_SESSION[self::SESSION_TOKEN_KEY] = $this->issueToken($employee['id'], $employee['first_name'], $isAdmin, (int) $employee['token_version']);

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
     * Logs out everywhere: every token issued so far stops working.
     */
    public function logout(): void
    {
        $claims = $this->requireClaims();
        $this->database->bumpTokenVersion($claims['id']);
        $_SESSION = [];
        http_response_code(204);
    }

    /**
     * Changes the caller's own password. Other sessions are logged out;
     * the caller receives a fresh token.
     */
    public function changePassword(int $userId, array $body): void
    {
        $claims = $this->requireClaims();
        if ($claims['id'] !== $userId) {
            throw new HttpException(403, 'You can only change your own password');
        }

        $state = $this->database->getAuthState($userId);
        if (!password_verify((string) ($body['current_password'] ?? ''), $state['Password'])) {
            throw new HttpException(422, 'Current password is wrong');
        }
        $new = (string) ($body['new_password'] ?? '');
        self::assertPasswordStrength($new);

        $this->database->setPassword($userId, password_hash($new, PASSWORD_DEFAULT));
        $state = $this->database->getAuthState($userId);
        $this->database->audit($userId, $userId, 'password_changed');

        HelperFunctions::json([
            'message' => 'Password changed',
            'jwt' => $this->issueToken($userId, $state['first_name'], (bool) $state['is_admin'], (int) $state['token_version']),
        ]);
    }

    public static function assertPasswordStrength(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new HttpException(422, 'Password must have at least ' . self::MIN_PASSWORD_LENGTH . ' characters');
        }
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

        if ($claims['id'] !== $requestedId && !$claims['admin']) {
            throw new HttpException(403, 'Forbidden');
        }
        return $requestedId;
    }

    /** Returns the admin's own id. */
    public function requireAdmin(): int
    {
        $claims = $this->requireClaims();
        if (!$claims['admin']) {
            throw new HttpException(403, 'Forbidden');
        }
        return $claims['id'];
    }

    /** Id of the caller (after requireUser or requireAdmin). */
    public function callerId(): int
    {
        return $this->requireClaims()['id'];
    }

    /**
     * Verifies the token and checks it against the database, so logout,
     * password changes, deactivation and role changes take effect immediately.
     */
    private function requireClaims(): array
    {
        if ($this->caller !== null) {
            return $this->caller;
        }

        $claims = $this->jwtHandler->decode($this->bearerToken());
        if ($claims === null || !is_int($claims['id'] ?? null)) {
            throw new HttpException(401, 'Unauthorized');
        }

        $state = $this->database->getAuthState($claims['id']);
        if (!$state || !$state['active'] || (int) $state['token_version'] !== ($claims['ver'] ?? -1)) {
            throw new HttpException(401, 'Unauthorized');
        }

        return $this->caller = ['id' => $claims['id'], 'admin' => (bool) $state['is_admin']];
    }

    private function issueToken($id, string $name, bool $isAdmin, int $version): string
    {
        return $this->jwtHandler->generate([
            'id' => (int) $id,
            'name' => $name,
            'admin' => $isAdmin,
            'ver' => $version,
        ]);
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
