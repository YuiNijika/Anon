<?php
/**
 * 统一测试入口
 * 运行所有自动化测试
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/test/TestRunner.php';

$baseUrl = $argv[1] ?? ($_GET['url'] ?? 'http://anon.localhost:8081');

$runner = new TestRunner($baseUrl);
$runner->runAll();

if (php_sapi_name() === 'cli') {
    exit($runner->getExitCode());
}

