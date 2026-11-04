<?php

// Database connection. The defaults match a local XAMPP/MAMP MySQL or MariaDB install.
$dbConfig = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'name' => getenv('DB_NAME') ?: 'workforce',
];
