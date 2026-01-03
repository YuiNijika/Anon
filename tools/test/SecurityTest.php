<?php
/**
 * 安全功能自动化测试类
 * 测试 CSRF、XSS、SQL 注入防护和接口限流功能
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class SecurityTest
{

    const CORE_DIR = __DIR__ . '/../../core/';

    /**
     * @var string 测试基础 URL
     */
    private $baseUrl;
    
    /**
     * @var bool 是否为 Web 访问
     */
    private $isWeb;
    
    /**
     * @var array 测试结果
     */
    private $results = [];
    
    /**
     * @var int 通过的测试数
     */
    private $passed = 0;
    
    /**
     * @var int 失败的测试数
     */
    private $failed = 0;
    
    /**
     * 构造函数
     * @param string $baseUrl 测试基础 URL
     */
    public function __construct(string $baseUrl = 'http://anon.localhost:8081')
    {
        $this->baseUrl = $baseUrl;
        $this->isWeb = php_sapi_name() !== 'cli';
    }
    
    /**
     * 执行 HTTP 请求
     * @param string $url 请求 URL
     * @param string $method HTTP 方法
     * @param mixed $data 请求数据
     * @param array $headers 请求头
     * @return array 响应数据
     */
    private function httpRequest(string $url, string $method = 'GET', $data = null, array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                if (is_array($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $headers[] = 'Content-Type: application/json';
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'code' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'json' => json_decode($body, true)
        ];
    }
    
    /**
     * 记录测试结果
     * @param string $name 测试名称
     * @param bool $passed 是否通过
     * @param string $message 消息
     */
    private function testResult(string $name, bool $passed, string $message = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message
        ];
        
        if ($passed) {
            $this->passed++;
            if ($this->isWeb) {
                echo "<div class='pass'>✅ {$name}";
                if ($message) {
                    echo " - {$message}";
                }
                echo "</div>";
            } else {
                echo "✅ {$name}\n";
                if ($message) {
                    echo "   {$message}\n";
                }
            }
        } else {
            $this->failed++;
            if ($this->isWeb) {
                echo "<div class='fail'>❌ {$name}";
                if ($message) {
                    echo " - {$message}";
                }
                echo "</div>";
            } else {
                echo "❌ {$name}\n";
                if ($message) {
                    echo "   {$message}\n";
                }
            }
        }
    }
    
    /**
     * 输出标题
     * @param string $title 标题
     */
    private function outputTitle(string $title): void
    {
        if ($this->isWeb) {
            echo "<h2>{$title}</h2>";
        } else {
            echo "\n{$title}\n";
        }
    }
    
    /**
     * 测试 CSRF Token 获取
     * @return string|null CSRF Token
     */
    private function testCsrfToken(): ?string
    {
        $this->outputTitle('测试 1: 获取 CSRF Token');
        $response = $this->httpRequest($this->baseUrl . '/anon/common/config');
        $csrfToken = null;
        
        if ($response['code'] === 200) {
            if (isset($response['json']['data']['csrfToken']) && !empty($response['json']['data']['csrfToken'])) {
                $csrfToken = $response['json']['data']['csrfToken'];
                $this->testResult('获取 CSRF Token', true, "Token: " . substr($csrfToken, 0, 20) . '...');
            } else {
                // 尝试从框架生成 Token
                if (file_exists(self::CORE_DIR . 'Modules/Csrf.php')) {
                    require_once self::CORE_DIR . 'Modules/Csrf.php';
                    if (class_exists('Anon_Csrf')) {
                        $csrfToken = Anon_Csrf::generateToken();
                        $this->testResult('获取 CSRF Token', true, "从框架生成 Token: " . substr($csrfToken, 0, 20) . '...');
                    } else {
                        $csrfToken = bin2hex(random_bytes(32));
                        $this->testResult('获取 CSRF Token', false, '配置接口未返回 CSRF Token，使用随机 Token 继续测试');
                    }
                } else {
                    $csrfToken = bin2hex(random_bytes(32));
                    $this->testResult('获取 CSRF Token', false, '配置接口未返回 CSRF Token，使用随机 Token 继续测试');
                }
            }
        } else {
            $csrfToken = bin2hex(random_bytes(32));
            $this->testResult('获取 CSRF Token', false, "HTTP {$response['code']}，使用随机 Token 继续测试");
        }
        
        return $csrfToken;
    }
    
    /**
     * 测试 CSRF 验证 - 无 Token
     */
    private function testCsrfVerificationNoToken(): void
    {
        $this->outputTitle('测试 2: CSRF 验证 - 无 Token 的 POST 请求');
        $response = $this->httpRequest($this->baseUrl . '/auth/login', 'POST', [
            'username' => 'test',
            'password' => 'test'
        ]);
        $message = $response['json']['message'] ?? '';
        
        if ($response['code'] === 403 || (isset($response['json']['success']) && $response['json']['success'] === false && (stripos($message, 'CSRF') !== false || stripos($message, 'Token') !== false))) {
            $this->testResult('CSRF 验证 - 无 Token 被拒绝', true, "HTTP {$response['code']}, {$message}");
        } else {
            $this->testResult('CSRF 验证 - 无 Token 被拒绝', false, "HTTP {$response['code']}，预期 403 或包含 CSRF/Token 错误信息。注意：如果未启用 CSRF 中间件，此测试可能失败");
        }
    }
    
    /**
     * 测试 CSRF 验证 - 有效 Token
     * @param string $csrfToken CSRF Token
     */
    private function testCsrfVerificationWithToken(string $csrfToken): void
    {
        $this->outputTitle('测试 3: CSRF 验证 - 有效 Token 的请求');
        $response = $this->httpRequest($this->baseUrl . '/auth/login', 'POST', [
            'username' => 'test',
            'password' => 'test',
            '_csrf_token' => $csrfToken
        ], [
            'X-CSRF-Token: ' . $csrfToken
        ]);
        $message = $response['json']['message'] ?? '';
        
        if ($response['code'] !== 403 && stripos($message, 'CSRF') === false && stripos($message, 'Token') === false) {
            $this->testResult('CSRF 验证 - 有效 Token 通过', true, "HTTP {$response['code']}，请求通过 CSRF 验证");
        } else {
            $this->testResult('CSRF 验证 - 有效 Token 通过', false, "HTTP {$response['code']}，{$message}");
        }
    }
    
    /**
     * 测试 XSS 过滤功能
     */
    private function testXssFilter(): void
    {
        $this->outputTitle('测试 4: XSS 过滤功能');
        $testXss = '<script>alert("xss")</script>';
        $response = $this->httpRequest($this->baseUrl . '/anon/common/config');
        
        if ($response['code'] === 200) {
            $this->testResult('XSS 过滤 - 接口正常', true, '接口可访问，XSS 过滤中间件未阻止正常请求');
        } else {
            $this->testResult('XSS 过滤 - 接口正常', false, "HTTP {$response['code']}");
        }
        
        // 测试 XSS 检测功能
        if (file_exists(self::CORE_DIR . 'Modules/Security.php')) {
            require_once self::CORE_DIR . 'Modules/Security.php';
            if (class_exists('Anon_Security')) {
                $hasXss = Anon_Security::containsXss($testXss);
                $this->testResult('XSS 检测 - 检测危险代码', $hasXss, $hasXss ? '成功检测到 XSS 代码' : '未检测到 XSS 代码');
            }
        }
    }
    
    /**
     * 测试 SQL 注入防护
     */
    private function testSqlInjectionProtection(): void
    {
        $this->outputTitle('测试 5: SQL 注入防护检查');
        
        if (file_exists(self::CORE_DIR . 'Modules/Security.php')) {
            require_once self::CORE_DIR . 'Modules/Security.php';
            if (class_exists('Anon_Security')) {
                $this->testResult('SQL 注入防护 - Security 类存在', true);
                
                if (method_exists('Anon_Security', 'containsSqlInjection')) {
                    $testSql = "SELECT * FROM users WHERE id = 1 OR 1=1";
                    $hasRisk = Anon_Security::containsSqlInjection($testSql);
                    $this->testResult('SQL 注入检测 - 检测危险 SQL', $hasRisk, $hasRisk ? '成功检测到 SQL 注入风险' : '未检测到 SQL 注入风险');
                }
            } else {
                $this->testResult('SQL 注入防护 - Security 类存在', false, 'Anon_Security 类未找到');
            }
        } else {
            $this->testResult('SQL 注入防护 - Security 类存在', false, 'Security.php 文件未找到');
        }
    }
    
    /**
     * 测试接口限流功能
     */
    private function testRateLimit(): void
    {
        $this->outputTitle('测试 6: 接口限流功能');
        $rateLimitHeaders = [];
        
        for ($i = 0; $i < 5; $i++) {
            $response = $this->httpRequest($this->baseUrl . '/anon/common/config');
            if (preg_match('/X-RateLimit-Limit:\s*(\d+)/i', $response['headers'], $matches)) {
                $rateLimitHeaders['limit'] = $matches[1];
            }
            if (preg_match('/X-RateLimit-Remaining:\s*(\d+)/i', $response['headers'], $matches)) {
                $rateLimitHeaders['remaining'] = $matches[1];
            }
            if (preg_match('/X-RateLimit-Reset:\s*(\d+)/i', $response['headers'], $matches)) {
                $rateLimitHeaders['reset'] = $matches[1];
            }
            usleep(100000); // 0.1 秒延迟
        }
        
        if (!empty($rateLimitHeaders)) {
            $this->testResult('接口限流 - 响应头存在', true, "Limit: {$rateLimitHeaders['limit']}, Remaining: {$rateLimitHeaders['remaining']}");
        } else {
            $this->testResult('接口限流 - 响应头存在', false, '未找到限流响应头。注意：需要在 useCode.php 中注册限流中间件才能生效');
        }
    }
    
    /**
     * 测试验证码功能
     */
    private function testCaptcha(): void
    {
        $this->outputTitle('测试 7: 验证码功能');
        $response = $this->httpRequest($this->baseUrl . '/auth/captcha');
        
        if ($response['code'] === 200 && isset($response['json']['data']['image'])) {
            $imageData = $response['json']['data']['image'];
            $this->testResult('验证码生成', true, '验证码图片已生成，长度: ' . strlen($imageData) . ' 字符');
        } else {
            $this->testResult('验证码生成', false, "HTTP {$response['code']}");
        }
    }
    
    /**
     * 测试 Token 验证功能
     */
    private function testTokenVerification(): void
    {
        $this->outputTitle('测试 8: Token 验证功能');
        $response = $this->httpRequest($this->baseUrl . '/user/info');
        
        if ($response['code'] === 401 || $response['code'] === 403) {
            $message = $response['json']['message'] ?? '';
            $this->testResult('Token 验证 - 未登录被拒绝', true, "HTTP {$response['code']}，{$message}");
        } else {
            $this->testResult('Token 验证 - 未登录被拒绝', false, "HTTP {$response['code']}，预期 401 或 403");
        }
    }
    
    /**
     * 运行所有测试
     */
    public function run(): void
    {
        if ($this->isWeb) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>安全功能测试</title><style>body{font-family:monospace;padding:20px;} .pass{color:green;} .fail{color:red;} .summary{margin-top:20px;padding:10px;background:#f0f0f0;}</style></head><body>';
            echo '<h1>安全功能自动化测试</h1>';
        } else {
            echo "========================================\n";
            echo "安全功能自动化测试\n";
            echo "========================================\n\n";
        }
        
        // 执行所有测试
        $csrfToken = $this->testCsrfToken();
        $this->testCsrfVerificationNoToken();
        if ($csrfToken) {
            $this->testCsrfVerificationWithToken($csrfToken);
        }
        $this->testXssFilter();
        $this->testSqlInjectionProtection();
        $this->testRateLimit();
        $this->testCaptcha();
        $this->testTokenVerification();
        
        // 输出测试总结
        $this->outputSummary();
    }
    
    /**
     * 输出测试总结
     */
    private function outputSummary(): void
    {
        if ($this->isWeb) {
            echo "<div class='summary'>";
            echo "<h2>测试总结</h2>";
            echo "<p>通过: <strong>{$this->passed}</strong></p>";
            echo "<p>失败: <strong>{$this->failed}</strong></p>";
            echo "<p>总计: <strong>" . ($this->passed + $this->failed) . "</strong></p>";
            echo "</div>";
            echo "</body></html>";
        } else {
            echo "\n========================================\n";
            echo "测试总结\n";
            echo "========================================\n";
            echo "通过: {$this->passed}\n";
            echo "失败: {$this->failed}\n";
            echo "总计: " . ($this->passed + $this->failed) . "\n";
            echo "========================================\n";
        }
    }
    
    /**
     * 获取测试结果
     * @return array 测试结果数组
     */
    public function getResults(): array
    {
        return [
            'results' => $this->results,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'total' => $this->passed + $this->failed
        ];
    }
    
    /**
     * 获取退出码
     * @return int 退出码（0=成功，1=失败）
     */
    public function getExitCode(): int
    {
        return $this->failed > 0 ? 1 : 0;
    }
}

