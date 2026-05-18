<?php

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    $lower = strtolower(trim((string) $value));
    if ($lower === 'true') {
        return true;
    }
    if ($lower === 'false') {
        return false;
    }
    if ($lower === 'null') {
        return null;
    }

    return $value;
};

return [
    'cache' => [
        'default' => $env('CACHE_DRIVER', 'file'),
        'path' => __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache',
        'prefix' => $env('CACHE_PREFIX', $env('REDIS_PREFIX', 'anon:cache:')),
        'redis' => [
            'host' => $env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) $env('REDIS_PORT', 6379),
            'password' => $env('REDIS_PASSWORD', ''),
            'database' => (int) $env('REDIS_DB', 0),
        ],
    ],

    'session' => [
        'driver' => $env('SESSION_DRIVER', 'file'),
        'lifetime' => (int) $env('SESSION_LIFETIME', 86400),
        'path' => '/',
        'domain' => $env('SESSION_DOMAIN', ''),
        'secure' => $env('SESSION_SECURE', false),
        'httponly' => $env('SESSION_HTTPONLY', true),
        'samesite' => $env('SESSION_SAMESITE', 'Lax'),
        'prefix' => $env('SESSION_PREFIX', 'anon:session:'),
        'path_storage' => __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'session',
    ],

    'auth' => [
        'jwt_secret' => $env('JWT_SECRET', 'anon_secret_key'),
    ],
];
