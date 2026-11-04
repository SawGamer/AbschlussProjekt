<?php

declare(strict_types=1);

/**
 * Creates and verifies HS256 JSON Web Tokens.
 */
class JwtHandler
{
    private array $header;
    private string $secret;
    private string $issuer;
    private string $audience;
    private int $ttl;

    public function __construct(array $jwtConfig)
    {
        $this->header = $jwtConfig['header'];
        $this->secret = $jwtConfig['secret'];
        $this->issuer = $jwtConfig['issuer'];
        $this->audience = $jwtConfig['audience'];
        $this->ttl = $jwtConfig['ttl'];
    }

    /**
     * Signs the given claims. Adds issuer, audience, issued-at and expiry.
     */
    public function generate(array $claims): string
    {
        $now = time();
        $claims += [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        $header = self::base64UrlEncode(json_encode($this->header));
        $payload = self::base64UrlEncode(json_encode($claims));
        $signature = self::base64UrlEncode($this->sign("$header.$payload"));

        return "$header.$payload.$signature";
    }

    /**
     * Returns the token's claims, or null if the token is malformed,
     * badly signed, expired, or issued for another issuer or audience.
     */
    public function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode(self::base64UrlDecode($header64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = $this->sign("$header64.$payload64");
        if (!hash_equals($expected, self::base64UrlDecode($signature64))) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($payload64), true);
        if (!is_array($claims)) {
            return null;
        }
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] < time()) {
            return null;
        }
        if (($claims['iss'] ?? null) !== $this->issuer || ($claims['aud'] ?? null) !== $this->audience) {
            return null;
        }

        return $claims;
    }

    public function is_valid(string $jwt): bool
    {
        return $this->decode($jwt) !== null;
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
