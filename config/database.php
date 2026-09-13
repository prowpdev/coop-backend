<?php

declare(strict_types=1);

return [
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
    'database'  => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'cooperative_db',
    'username'  => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
