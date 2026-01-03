# 安全功能测试指南

## 测试脚本

已创建自动化测试脚本，用于测试所有安全功能。

### 文件结构

```
server/
├── test/
│   └── SecurityTest.php    # 测试类（面向对象）
└── test_security.php        # 测试脚本入口
```

### 使用方法

#### 命令行测试

```bash
cd server
php test_security.php
```

#### Web 访问测试

访问：`http://localhost:98/test_security.php`

#### 自定义测试 URL

```bash
# 命令行指定 URL
php test_security.php

# Web 访问指定 URL
http://localhost:98/test_security.php?url=http://example.com
```

### 测试类使用

```php
require_once __DIR__ . '/test/SecurityTest.php';

// 创建测试实例
$test = new SecurityTest('http://localhost:98');

// 运行测试
$test->run();

// 获取测试结果
$results = $test->getResults();
// 返回: ['results' => [...], 'passed' => int, 'failed' => int, 'total' => int]

// 获取退出码
$exitCode = $test->getExitCode();
// 返回: 0=成功，1=失败
```

### 测试内容

1. **CSRF Token 获取** - 测试能否从配置接口获取 CSRF Token
2. **CSRF 验证** - 测试无 Token 的 POST 请求是否被拒绝
3. **CSRF 验证通过** - 测试有效 Token 的请求是否通过
4. **XSS 过滤** - 测试 XSS 过滤功能是否正常
5. **SQL 注入防护** - 测试 SQL 注入检测功能
6. **接口限流** - 测试限流响应头是否存在
7. **验证码功能** - 测试验证码生成
8. **Token 验证** - 测试 Token 验证功能

## 启用安全功能

### 1. 启用 CSRF 防护中间件

在 `server/app/useCode.php` 中添加：

```php
// 加载中间件类
require_once __DIR__ . '/../core/Middleware/CsrfMiddleware.php';

// 注册 CSRF 防护中间件
Anon_Middleware::global(
    Anon_CsrfMiddleware::make([
        '/auth/login',      // 排除登录接口（如果需要）
        '/auth/register',   // 排除注册接口（如果需要）
        '/anon/common',     // 排除公共接口
    ])
);
```

### 2. 启用 XSS 过滤中间件

在 `server/app/useCode.php` 中添加：

```php
// 加载中间件类
require_once __DIR__ . '/../core/Middleware/XssFilterMiddleware.php';

// 注册 XSS 过滤中间件
Anon_Middleware::global(
    Anon_XssFilterMiddleware::make(
        true,                              // 移除 HTML 标签
        ['password', 'token', 'csrf_token'] // 跳过的字段
    )
);
```

### 3. 启用接口限流中间件

在 `server/app/useCode.php` 中添加：

```php
// 加载中间件类
require_once __DIR__ . '/../core/Middleware/RateLimitMiddleware.php';

// 注册接口限流中间件
Anon_Middleware::global(
    Anon_RateLimitMiddleware::make(
        100,    // 最大请求次数
        60,     // 时间窗口（秒）
        'api',  // 限流键前缀
        [
            'useIp' => true,     // 基于 IP
            'useUserId' => false // 基于用户 ID（可选）
        ]
    )
);
```

## 测试结果说明

### 预期结果

- ✅ **CSRF Token 获取** - 应该成功从配置接口获取 Token
- ✅ **CSRF 验证** - 无 Token 的 POST 请求应该返回 403 或包含 CSRF 错误信息
- ✅ **CSRF 验证通过** - 有效 Token 的请求应该通过验证（即使登录失败）
- ✅ **XSS 过滤** - 接口应该正常访问，XSS 检测功能应该能检测到危险代码
- ✅ **SQL 注入防护** - Security 类应该存在，SQL 注入检测应该能检测到危险 SQL
- ✅ **接口限流** - 如果启用了限流中间件，响应头应该包含限流信息
- ✅ **验证码功能** - 应该能成功生成验证码图片
- ✅ **Token 验证** - 未登录的请求应该返回 401 或 403

### 注意事项

1. **中间件未注册**：如果 CSRF、XSS 过滤或限流测试失败，可能是因为中间件未在 `useCode.php` 中注册
2. **配置未启用**：检查 `useApp.php` 中的安全配置是否启用
3. **会话问题**：CSRF Token 需要会话支持，确保会话正常工作

## 手动测试

### 测试 CSRF 防护

```bash
# 1. 获取 CSRF Token
curl http://localhost:98/anon/common/config

# 2. 无 Token 的 POST 请求（应该被拒绝）
curl -X POST http://localhost:98/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test","password":"test"}'

# 3. 带 Token 的 POST 请求（应该通过）
curl -X POST http://localhost:98/auth/login \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN_HERE" \
  -d '{"username":"test","password":"test","_csrf_token":"YOUR_TOKEN_HERE"}'
```

### 测试 XSS 过滤

```bash
# 测试 XSS 代码检测
curl http://localhost:98/anon/common/config \
  -G --data-urlencode "test=<script>alert('xss')</script>"
```

### 测试接口限流

```bash
# 连续发送多个请求，观察响应头
for i in {1..10}; do
  curl -I http://localhost:98/anon/common/config
  sleep 0.1
done
```

## 故障排除

### CSRF 验证失败

1. 检查 `useApp.php` 中 `app.security.csrf.enabled` 是否为 `true`
2. 检查是否在 `useCode.php` 中注册了 CSRF 中间件
3. 检查会话是否正常工作

### XSS 过滤未生效

1. 检查 `useApp.php` 中 `app.security.xss.enabled` 是否为 `true`
2. 检查是否在 `useCode.php` 中注册了 XSS 过滤中间件

### 限流响应头不存在

1. 检查是否在 `useCode.php` 中注册了限流中间件
2. 检查缓存系统是否正常工作

