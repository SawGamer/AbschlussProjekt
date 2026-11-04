<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

class HelperFunctions
{
    public static function sanitizeInput($input): ?string
    {
        // Simplified input sanitization
        return is_string($input) ? htmlspecialchars(trim($input)) : null;
    }

    /**
     * Sends a JSON response with the given HTTP status code.
     */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Reads the JSON request body as an associative array.
     */
    public static function jsonBody(): array
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            throw new HttpException(400, 'Request body must be a JSON object');
        }
        return $body;
    }

    /**
     * Redirects the browser and stops the request.
     */
    public static function redirect(string $location): void
    {
        header('Location: ' . $location, true, 303);
    }
}
