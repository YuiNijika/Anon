<?php
/**
 * 统一测试运行器
 * 整合所有自动化测试
 */

// 移除安全检查，允许测试文件直接运行
if (file_exists(__DIR__ . '/SecurityTest.php')) {
    require_once __DIR__ . '/SecurityTest.php';
}
require_once __DIR__ . '/CacheTest.php';

class TestRunner
{
    private $baseUrl;
    private $results = [];
    
    public function __construct($baseUrl = 'http://anon.localhost:8081')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * 运行所有测试
     */
    public function runAll(): void
    {
        echo "========================================\n";
        echo "Anon Framework 自动化测试套件\n";
        echo "========================================\n";
        echo "测试服务器: {$this->baseUrl}\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";
        
        // 运行安全功能测试
        echo "\n[1/2] 运行安全功能测试...\n";
        $securityTest = new SecurityTest($this->baseUrl);
        $securityTest->run();
        $securityResults = $securityTest->getResults();
        $this->results['security'] = $securityResults['results'] ?? [];
        
        // 运行缓存功能测试
        echo "\n[2/2] 运行缓存功能测试...\n";
        $cacheTest = new CacheTest($this->baseUrl);
        $cacheTest->runAll();
        $this->results['cache'] = $cacheTest->getResults();
        
        // 打印总摘要
        $this->printSummary();
    }
    
    /**
     * 打印测试摘要
     */
    private function printSummary(): void
    {
        echo "\n========================================\n";
        echo "测试总摘要\n";
        echo "========================================\n";
        
        $totalTests = 0;
        $totalPassed = 0;
        
        foreach ($this->results as $category => $categoryResults) {
            $categoryTotal = count($categoryResults);
            $categoryPassed = count(array_filter($categoryResults, fn($r) => $r['passed']));
            $categoryFailed = $categoryTotal - $categoryPassed;
            
            $totalTests += $categoryTotal;
            $totalPassed += $categoryPassed;
            
            $status = $categoryFailed === 0 ? '✓' : '✗';
            echo sprintf(
                "%s %s: %d/%d 通过 (%.1f%%)\n",
                $status,
                ucfirst($category),
                $categoryPassed,
                $categoryTotal,
                ($categoryPassed / $categoryTotal) * 100
            );
        }
        
        echo sprintf("\n总计: %d/%d 通过 (%.1f%%)\n", $totalPassed, $totalTests, ($totalPassed / $totalTests) * 100);
        echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
        echo "========================================\n";
    }
    
    /**
     * 获取测试结果
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * 获取退出码
     */
    public function getExitCode(): int
    {
        $allPassed = true;
        foreach ($this->results as $categoryResults) {
            foreach ($categoryResults as $result) {
                if (!$result['passed']) {
                    $allPassed = false;
                    break 2;
                }
            }
        }
        return $allPassed ? 0 : 1;
    }
}

