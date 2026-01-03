<?php
/**
 * 安全功能自动化测试脚本入口
 * 
 * 使用方法：
 * php test_security.php
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载测试类
require_once __DIR__ . '/test/SecurityTest.php';

// 获取测试基础 URL
$baseUrl = isset($_GET['url']) ? $_GET['url'] : 'http://anon.localhost:8081';

// 创建测试实例并运行
$test = new SecurityTest($baseUrl);
$test->run();

// 如果通过命令行运行，返回退出码
if (php_sapi_name() === 'cli') {
    exit($test->getExitCode());
}
