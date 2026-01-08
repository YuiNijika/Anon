<?php
/**
 * Anon Framework
 * @author YuiNijika
 * @license MIT
 * @copyright 2025 YuiNijika
 * @link https://github.com/YuiNijika/Anon
 * @link https://github.com/YuiNijika/AnonClient
 */
define('ANON_ALLOWED_ACCESS', true);

require_once __DIR__ . '/core/Main.php';

// CLI
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'swoole') {
    Anon_Main::runSwoole($argv);
} else {
    Anon_Main::run();
}
