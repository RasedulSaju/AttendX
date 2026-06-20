<?php
declare(strict_types=1);

return [
    'driver'    => 'mysql',
    'host'      => env('DB_HOST',     '127.0.0.1'),
    'port'      => (int)env('DB_PORT', '3306'),
    'database'  => env('DB_NAME',     'attendx'),
    'username'  => env('DB_USER',     'root'),
    'password'  => env('DB_PASS',     ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];
