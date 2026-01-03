<?php
$minVersion = '7.4.0';
$maxVersion = '8.4.99';

if (version_compare(PHP_VERSION, $minVersion, '<')) {
    die("错误: Anon Framework 需要 PHP {$minVersion} 或更高版本，当前版本: " . PHP_VERSION . "\n");
}

if (version_compare(PHP_VERSION, '8.5.0', '>=')) {
    die("警告: Anon Framework 尚未在 PHP 8.5+ 上测试，当前版本: " . PHP_VERSION . "\n");
}

echo "✓ PHP 版本检查通过: " . PHP_VERSION . "\n";
echo "✓ 兼容 PHP 7.4 - 8.4\n";

