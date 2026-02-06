# 自动化测试

统一的测试套件，整合安全功能测试和缓存功能测试。

## 概述

框架提供了统一的自动化测试工具，用于验证核心功能是否正常工作。测试套件位于 `server/tools/test/` 目录。

## 运行测试

### 命令行运行

```bash
php tools/test.php [baseUrl]
```

示例：
```bash
php tools/test.php http://anon.localhost:8081
```

### Web 访问

访问 `http://your-domain/tools/test.php?url=http://anon.localhost:8081`

## 测试模块

### 1. 安全功能测试 (SecurityTest)

测试以下安全功能：

- **CSRF 防护**
  - 获取 CSRF Token
  - 验证无 Token 的 POST 请求被拒绝
  - 验证有效 Token 的请求通过

- **XSS 过滤**
  - 验证 XSS 过滤中间件正常工作
  - 测试恶意脚本被过滤

- **SQL 注入防护**
  - 验证查询构建器的安全机制
  - 测试参数化查询

- **接口限流**
  - 验证 IP 限流功能
  - 验证用户限流功能
  - 测试限流阈值

### 2. 缓存功能测试 (CacheTest)

测试以下缓存功能：

- **全局缓存配置**
  - 验证全局缓存头设置
  - 验证缓存时间配置

- **路由特定缓存配置**
  - 验证路由级别的缓存覆盖
  - 验证缓存时间正确应用

- **动态内容排除**
  - 验证验证码接口不缓存
  - 验证 POST 请求不缓存
  - 验证需要登录的接口不缓存

## 测试结果

测试完成后会显示：

- 每个测试模块的通过率
- 总测试数和通过数
- 失败的测试详情

### 输出示例

```
========================================
Anon Framework 自动化测试套件
========================================
测试服务器: http://anon.localhost:8081
开始时间: 2026-01-04 09:39:27

[1/2] 运行安全功能测试...
✅ 获取 CSRF Token
✅ CSRF 验证 - 有效 Token 通过
✅ XSS 过滤功能

[2/2] 运行缓存功能测试...
✅ 全局缓存 - Cache-Control 头
✅ 全局缓存 - 缓存时间
✅ 路由缓存 - Cache-Control 头

========================================
测试总摘要
========================================
✓ Security: 10/10 通过 (100.0%)
✓ Cache: 7/7 通过 (100.0%)

总计: 17/17 通过 (100.0%)
结束时间: 2026-01-04 09:39:30
========================================
```

## 退出码

- `0`: 所有测试通过
- `1`: 有测试失败

## 测试类结构

### TestRunner

统一测试运行器，负责：

- 初始化测试环境
- 运行所有测试模块
- 汇总测试结果
- 输出测试报告

### SecurityTest

安全功能测试类，包含：

- `testCsrf()`: CSRF 防护测试
- `testXss()`: XSS 过滤测试
- `testSqlInjection()`: SQL 注入防护测试
- `testRateLimit()`: 接口限流测试

### CacheTest

缓存功能测试类，包含：

- `testGlobalCache()`: 全局缓存测试
- `testRouteSpecificCache()`: 路由缓存测试
- `testDynamicContentExclusion()`: 动态内容排除测试

## 自定义测试

### 添加新的测试类

1. 在 `server/tools/test/` 目录创建测试类文件
2. 实现测试方法
3. 在 `TestRunner.php` 中引入并运行

示例：

```php
<?php
class MyTest
{
    private $baseUrl;
    private $results = [];

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function run(): void
    {
        $this->testFeature1();
        $this->testFeature2();
    }

    private function testFeature1(): void
    {
        // 测试逻辑
        $this->recordResult('Feature 1', true, '测试通过');
    }

    private function recordResult(string $testName, bool $passed, string $message): void
    {
        $this->results[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message
        ];
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
```

### 集成到 TestRunner

```php
// 在 TestRunner.php 中
require_once __DIR__ . '/MyTest.php';

public function runAll(): void
{
    // ... 其他测试
    
    // 运行自定义测试
    $myTest = new MyTest($this->baseUrl);
    $myTest->run();
    $this->results['my'] = $myTest->getResults();
}
```

## 注意事项

1. **测试环境**：确保测试服务器正常运行
2. **网络连接**：测试需要能够访问目标服务器
3. **测试数据**：某些测试可能需要特定的测试数据
4. **调试模式**：建议在调试模式下运行测试，便于查看详细日志

## 持续集成

可以将测试集成到 CI/CD 流程中：

```yaml
# .github/workflows/test.yml
name: Test

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Run tests
        run: php tools/test.php http://localhost:8081
```

