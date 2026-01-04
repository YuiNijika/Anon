<?php
/**
 * 缓存功能自动化测试
 */

class CacheTest
{
    private $baseUrl;
    private $results = [];
    
    public function __construct($baseUrl = 'http://anon.localhost:8081')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * 发送 HTTP 请求
     */
    private function request(string $url, array $options = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $options['headers'] ?? [],
        ]);
        
        if (isset($options['method']) && $options['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($options['data'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($options['data']) ? http_build_query($options['data']) : $options['data']);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'code' => $httpCode,
            'headers' => $this->parseHeaders($headers),
            'body' => $body
        ];
    }
    
    /**
     * 解析响应头
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        return $headers;
    }
    
    /**
     * 记录测试结果
     */
    private function recordResult(string $testName, bool $passed, string $message = '', array $data = []): void
    {
        $this->results[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message,
            'data' => $data
        ];
        
        $status = $passed ? '✓' : '✗';
        echo sprintf("[%s] %s: %s\n", $status, $testName, $message);
        if (!empty($data)) {
            echo "  数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    /**
     * 测试全局缓存配置
     */
    public function testGlobalCache(): void
    {
        echo "\n=== 测试全局缓存配置 ===\n";
        
        $response = $this->request($this->baseUrl . '/anon/common/system');
        
        $cacheControl = $response['headers']['Cache-Control'] ?? '';
        $expires = $response['headers']['Expires'] ?? '';
        
        if (!empty($cacheControl)) {
            if (preg_match('/max-age=(\d+)/', $cacheControl, $matches)) {
                $maxAge = (int)$matches[1];
                $this->recordResult(
                    '全局缓存 - Cache-Control 头',
                    $maxAge > 0,
                    "找到 max-age={$maxAge}",
                    ['cache_control' => $cacheControl, 'max_age' => $maxAge]
                );
                
                $this->recordResult(
                    '全局缓存 - 缓存时间',
                    $maxAge === 3600,
                    "缓存时间: {$maxAge} 秒（期望: 3600 秒）",
                    ['expected' => 3600, 'actual' => $maxAge]
                );
            } else {
                $this->recordResult(
                    '全局缓存 - Cache-Control 头',
                    false,
                    "Cache-Control 头存在但未找到 max-age",
                    ['cache_control' => $cacheControl]
                );
            }
        } else {
            $this->recordResult(
                '全局缓存 - Cache-Control 头',
                false,
                "未找到 Cache-Control 头",
                ['headers' => array_keys($response['headers'])]
            );
        }
        
        if (!empty($expires)) {
            $this->recordResult(
                '全局缓存 - Expires 头',
                true,
                "找到 Expires 头: {$expires}",
                ['expires' => $expires]
            );
        } else {
            $this->recordResult(
                '全局缓存 - Expires 头',
                false,
                "未找到 Expires 头"
            );
        }
    }
    
    /**
     * 测试路由特定缓存配置
     */
    public function testRouteSpecificCache(): void
    {
        echo "\n=== 测试路由特定缓存配置 ===\n";
        
        $response = $this->request($this->baseUrl . '/hello');
        
        $cacheControl = $response['headers']['Cache-Control'] ?? '';
        $expires = $response['headers']['Expires'] ?? '';
        
        if (!empty($cacheControl)) {
            if (preg_match('/max-age=(\d+)/', $cacheControl, $matches)) {
                $maxAge = (int)$matches[1];
                $this->recordResult(
                    '路由缓存 - Cache-Control 头',
                    $maxAge > 0,
                    "找到 max-age={$maxAge}",
                    ['cache_control' => $cacheControl, 'max_age' => $maxAge]
                );
                
                $this->recordResult(
                    '路由缓存 - 缓存时间',
                    $maxAge === 3600,
                    "缓存时间: {$maxAge} 秒（期望: 3600 秒）",
                    ['expected' => 3600, 'actual' => $maxAge]
                );
            } else {
                $this->recordResult(
                    '路由缓存 - Cache-Control 头',
                    false,
                    "Cache-Control 头存在但未找到 max-age",
                    ['cache_control' => $cacheControl]
                );
            }
        } else {
            $this->recordResult(
                '路由缓存 - Cache-Control 头',
                false,
                "未找到 Cache-Control 头",
                ['headers' => array_keys($response['headers'])]
            );
        }
        
        if (!empty($expires)) {
            $this->recordResult(
                '路由缓存 - Expires 头',
                true,
                "找到 Expires 头: {$expires}",
                ['expires' => $expires]
            );
        } else {
            $this->recordResult(
                '路由缓存 - Expires 头',
                false,
                "未找到 Expires 头"
            );
        }
    }
    
    /**
     * 测试动态内容接口不会被缓存
     */
    public function testDynamicContentExclusion(): void
    {
        echo "\n=== 测试动态内容接口缓存排除 ===\n";
        
        // 测试验证码接口
        $response = $this->request($this->baseUrl . '/auth/captcha');
        $cacheControl = $response['headers']['Cache-Control'] ?? '';
        
        if (strpos($cacheControl, 'no-cache') !== false || strpos($cacheControl, 'no-store') !== false) {
            $this->recordResult(
                '动态内容排除 - 验证码接口',
                true,
                "验证码接口正确禁用缓存: {$cacheControl}",
                ['cache_control' => $cacheControl]
            );
        } else {
            $this->recordResult(
                '动态内容排除 - 验证码接口',
                false,
                "验证码接口未禁用缓存（危险！）",
                ['cache_control' => $cacheControl]
            );
        }
        
        // 测试 POST 请求
        $response = $this->request($this->baseUrl . '/auth/login', [
            'method' => 'POST',
            'data' => ['username' => 'test', 'password' => 'test']
        ]);
        $cacheControl = $response['headers']['Cache-Control'] ?? '';
        
        if (strpos($cacheControl, 'no-cache') !== false || strpos($cacheControl, 'no-store') !== false) {
            $this->recordResult(
                '动态内容排除 - POST 请求',
                true,
                "POST 请求正确禁用缓存: {$cacheControl}",
                ['cache_control' => $cacheControl]
            );
        } else {
            $this->recordResult(
                '动态内容排除 - POST 请求',
                false,
                "POST 请求未禁用缓存",
                ['cache_control' => $cacheControl]
            );
        }
    }
    
    /**
     * 运行所有测试
     */
    public function runAll(): void
    {
        echo "开始缓存功能自动化测试...\n";
        
        $this->testGlobalCache();
        $this->testRouteSpecificCache();
        $this->testDynamicContentExclusion();
        
        $this->printSummary();
    }
    
    /**
     * 打印测试摘要
     */
    private function printSummary(): void
    {
        echo "\n=== 测试摘要 ===\n";
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $failed = $total - $passed;
        
        echo "总测试数: {$total}\n";
        echo "通过: {$passed}\n";
        echo "失败: {$failed}\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n";
        
        if ($failed > 0) {
            echo "\n失败的测试:\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['test']}: {$result['message']}\n";
                }
            }
        }
    }
    
    /**
     * 获取测试结果
     */
    public function getResults(): array
    {
        return $this->results;
    }
}

