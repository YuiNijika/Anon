<?php
/**
 * 缓存系统测试脚本
 * 用于验证 Redis 缓存是否正常工作
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义常量允许访问
define('ANON_ALLOWED_ACCESS', true);

// 加载框架
require_once __DIR__ . '/../core/Main.php';

echo "=== Anon 缓存系统测试 ===\n\n";

try {
    // 测试 1: 获取缓存实例
    echo "1. 测试获取缓存实例...\n";
    $cache = Anon_Cache::getInstance();
    echo "   ✓ 缓存实例类型：" . get_class($cache) . "\n\n";
    
    // 测试 2: 测试基本缓存操作
    echo "2. 测试基本缓存操作...\n";
    $testKey = 'test:key';
    $testValue = ['name' => 'Anon', 'version' => '4.0', 'timestamp' => time()];
    
    // 设置缓存
    $result = Anon_Cache::set($testKey, $testValue, 60);
    echo "   设置缓存：" . ($result ? "✓ 成功" : "✗ 失败") . "\n";
    
    // 获取缓存
    $cached = Anon_Cache::get($testKey);
    if ($cached === $testValue) {
        echo "   获取缓存：✓ 成功，数据一致\n";
        echo "   缓存数据：" . json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "   获取缓存：✗ 失败，数据不一致\n";
        var_dump($cached);
    }
    
    // 检查缓存是否存在
    $exists = Anon_Cache::has($testKey);
    echo "   检查缓存：", $exists ? "✓ 存在" : "✗ 不存在", "\n\n";
    
    // 测试 3: 使用全局 cache() 函数
    echo "3. 测试全局 cache() 函数...\n";
    cache('global:test', '这是一个全局函数测试', 120);
    $globalTest = cache('global:test');
    echo "   设置缓存：✓ 成功\n";
    echo "   获取缓存：", $globalTest === '这是一个全局函数测试' ? "✓ 成功" : "✗ 失败", "\n";
    echo "   缓存值：{$globalTest}\n\n";
    
    // 测试 4: 测试 remember 方法
    echo "4. 测试 remember 方法...\n";
    $rememberResult = Anon_Cache::remember('remember:test', function() {
        echo "   [回调执行] 这是耗时操作...\n";
        return ['data' => 'from callback', 'time' => time()];
    }, 300);
    echo "   第一次调用（应执行回调）:\n";
    echo "   结果：" . json_encode($rememberResult, JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "   第二次调用（应从缓存读取）:\n";
    $rememberCached = Anon_Cache::remember('remember:test', function() {
        echo "   [回调执行] 这不应该被看到...\n";
        return ['data' => 'should not see this', 'time' => time()];
    }, 300);
    echo "   结果：" . json_encode($rememberCached, JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 测试 5: 删除缓存
    echo "5. 测试删除缓存...\n";
    $deleted = Anon_Cache::delete($testKey);
    echo "   删除缓存：" . ($deleted ? "✓ 成功" : "✗ 失败") . "\n";
    echo "   再次检查：", Anon_Cache::has($testKey) ? "✗ 还存在" : "✓ 已删除", "\n\n";
    
    // 测试 6: 如果是 Redis，显示连接信息
    if ($cache instanceof Anon_RedisCache) {
        echo "6. Redis 连接信息...\n";
        try {
            $reflection = new ReflectionClass($cache);
            $property = $reflection->getProperty('redis');
            $property->setAccessible(true);
            $redis = $property->getValue($cache);
            
            $info = $redis->info();
            echo "   ✓ Redis 版本：" . ($info['redis_version'] ?? 'unknown') . "\n";
            echo "   ✓ 内存使用：" . ($info['used_memory_human'] ?? 'unknown') . "\n";
            echo "   ✓ 键数量：" . $redis->dbSize() . "\n";
            echo "   ✓ 连接状态：正常\n\n";
        } catch (Exception $e) {
            echo "   ✗ 无法获取 Redis 信息：" . $e->getMessage() . "\n\n";
        }
    }
    
    // 清理测试数据
    echo "7. 清理测试数据...\n";
    Anon_Cache::delete('global:test');
    Anon_Cache::delete('remember:test');
    echo "   ✓ 清理完成\n\n";
    
    echo "=== 测试完成 ===\n";
    echo "提示：访问 /anon/cache/status 查看详细缓存状态（需开启调试模式）\n";
    
} catch (Throwable $e) {
    echo "\n✗ 测试失败!\n";
    echo "错误类型：" . get_class($e) . "\n";
    echo "错误信息：" . $e->getMessage() . "\n";
    echo "文件位置：" . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
