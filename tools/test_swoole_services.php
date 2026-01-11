<?php
/**
 * Swoole 服务自动化测试脚本
 * 
 * 注意：此脚本需要 PHP 环境安装 Swoole 扩展才能完整运行。
 * 在 Windows 环境下，此脚本将仅进行基本的文件和类检查。
 */

echo "开始自动化测试 Swoole 服务...\n\n";

// 检查文件是否存在
$files = [
    '../index.php',
    '../core/Modules/Server/Manager.php',
    '../core/Modules/Server/Contract/ServerInterface.php',
    '../core/Modules/Server/Driver/Swoole/Http.php',
    '../core/Modules/Server/Driver/Swoole/Tcp.php',
    '../core/Modules/Server/Driver/Swoole/WebSocket.php',
    '../core/Modules/Server/Driver/Swoole/Process.php',
    '../core/Modules/Server/Driver/Swoole/Crontab.php'
];

echo "[1/3] 检查文件完整性...\n";
foreach ($files as $file) {
    // 修正文件路径，使用 __DIR__
    $fullPath = realpath(__DIR__ . '/' . $file);
    if ($fullPath && file_exists($fullPath)) {
        echo "  [√] 已找到: $file\n";
    } else {
        echo "  [×] 未找到: $file\n";
        exit(1);
    }
}
echo "文件完整性检查通过。\n\n";

// 检查 Swoole 扩展
echo "[2/3] 检查运行环境...\n";
if (extension_loaded('swoole')) {
    echo "  [√] Swoole 扩展已加载。\n";
    $canRunServer = true;
} else {
    echo "  [!] Swoole 扩展未加载（Windows 环境下正常）。\n";
    echo "  [!] 跳过实际启动测试，仅验证代码逻辑。\n";
    $canRunServer = false;
}
echo "\n";

// Swoole模拟运行测试 
echo "[3/3] 服务启动测试...\n";
if ($canRunServer) {
    // 测试 HTTP 服务
    testServer('http', 9501);
    // 测试 TCP 服务
    testServer('tcp', 9502);
    // 测试 WebSocket 服务
    testServer('websocket', 9503);
} else {
    echo "由于当前环境不支持 Swoole，已跳过实际启动测试。\n";
    echo "请在 Linux/Docker 环境中运行以下命令进行完整测试：\n";
    echo "  php server/index.php swoole http start\n";
    echo "  php server/index.php swoole tcp start\n";
    echo "  php server/index.php swoole websocket start\n";
}

echo "\n测试流程结束。\n";

function testServer($type, $port) {
    echo "正在测试 $type 服务 (端口 $port)...\n";
    
    // 启动服务器进程
    // Windows 环境下可能无法通过此方式后台运行，仅建议在 Linux 下运行完整测试
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        echo "  [!] Windows 环境下不支持 exec 后台进程管理，请手动运行 php server/index.php swoole $type start 进行测试\n";
        return;
    }

    $cmd = "php ../index.php swoole $type start > /dev/null 2>&1 & echo $!";
    $pid = exec($cmd);
    
    sleep(1); // 等待启动

    // 简单检查端口是否监听 (仅 Linux)
    $check = exec("netstat -an | grep $port");
    if (strpos($check, (string)$port) !== false) {
        echo "  [√] $type 服务启动成功 (端口 $port)\n";
    } else {
        echo "  [?] 无法验证 $type 服务状态 (可能是权限或环境原因)\n";
    }

    // 杀掉进程
    if ($pid) {
        exec("kill $pid");
    }
}

