<?php
/**
 * Anon Framework Next
 * @author YuiNijika
 * @license MIT
 * @copyright 2025 YuiNijika
 * @link https://github.com/YuiNijika/Anon
 * @link https://github.com/YuiNijika/AnonClient
 */
define('ANON_ALLOWED_ACCESS', true);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'Anon\\';
        $baseDir = realpath(__DIR__ . '/../src') . DIRECTORY_SEPARATOR;
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
\Anon\Main::boot($_SERVER['argv'] ?? []);
