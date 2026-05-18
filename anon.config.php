<?php

return [
    'app' => [
        'name' => 'Anon Framework Next',
        'env' => 'local',
        'debug' => true,
        'url' => 'http://127.0.0.1:8000',
    ],

    'database' => [
        'type' => getenv('DATABASE_TYPE') ?: 'mysql',
        'host' => getenv('DATABASE_URL') ?: '127.0.0.1',
        'port' => (int) (getenv('DATABASE_PORT') ?: 3306),
        'database' => getenv('DATABASE_NAME') ?: 'anon',
        'username' => getenv('DATABASE_USER') ?: 'root',
        'password' => getenv('DATABASE_PASSWORD') ?: '',
        'charset' => getenv('DATABASE_CHARSET') ?: 'utf8mb4',
        'prefix' => getenv('DATABASE_PREFIX') ?: '',
    ],

    'cache' => [
        'default' => 'file',
        'path' => __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache',
        'prefix' => 'anon:cache:',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
        ],
    ],

    'session' => [
        'driver' => 'file',
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
        'prefix' => 'anon:session:',
        'path_storage' => __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'session',
    ],

    'upload' => [
        'path' => __DIR__ . DIRECTORY_SEPARATOR . 'run' . DIRECTORY_SEPARATOR . 'storage',
    ],

    'auth' => [
        'jwt_secret' => getenv('JWT_SECRET') ?: 'anon_secret_key',
    ],
];
