<?php

// JWT settings. Set JWT_SECRET in the environment for any shared or public deployment.
$jwtConfig = [
    'header' => [
        'alg' => 'HS256', // HMAC with SHA-256
        'typ' => 'JWT',
    ],
    'issuer' => 'workforce',
    'audience' => 'workforce-app',
    'ttl' => 8 * 60 * 60, // valid for one working day
    'secret' => getenv('JWT_SECRET') ?: 'local-development-secret-change-me',
];
