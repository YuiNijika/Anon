# 生产环境安全配置指南

本指南说明如何将 Anon Framework 从开发环境配置为安全可靠的生产环境。

## 快速开始

### 一键切换生产模式

在 `app/useApp.php` 中设置：

```php
'productionMode' => true,  // 自动应用所有安全加固配置
```

启用后自动：
- ✅ 关闭所有 Debug 输出
- ✅ 强制开启全局限流
- ✅ 强制 SQL 使用 prepared statements
- ✅ 启用 CSP 内容安全策略
- ✅ 隐藏 PHP 版本信息

## 安全配置清单

### 1. 限流配置（防暴力破解）

```php
'rateLimit' => [
    // 全局限流（生产环境必须开启）
    'global' => [
        'enabled' => true,      // ✅ 生产强制 true
        'maxAttempts' => 60,    // 每分钟最多 60 次写请求
        'windowSeconds' => 60,
        'methods' => ['POST', 'PUT', 'DELETE'],  // 仅限写操作
    ],
    // 注册场景限流
    'register' => [
        'ip' => [
            'enabled' => true,   // ✅ 建议开启
            'maxAttempts' => 5,  // 每小时最多 5 次注册
            'windowSeconds' => 3600,
        ],
        'device' => [
            'enabled' => true,   // ✅ 建议开启
            'maxAttempts' => 3,  // 每台设备每小时最多 3 次
            'windowSeconds' => 3600,
        ],
    ],
],
```

**为什么重要：**
- 防止暴力破解密码
- 防止短信/邮件接口被刷
- 防止恶意注册

### 2. SQL 注入防护

```php
'sql' => [
    'validateInDebug' => true,
    'forcePrepareAlways' => true,     // ✅ 生产强制 true，所有查询必须使用 prepared statements
    'logRawSql' => true,              // ✅ 记录潜在风险的 raw SQL
    'forbiddenKeywords' => [          // ✅ Debug 模式拦截危险操作
        'DROP', 
        'TRUNCATE', 
        'ALTER TABLE', 
        'CREATE TABLE'
    ],
],
```

**最佳实践：**

```php
<?php
use Anon\Modules\Database\Database;

// ✅ 正确：使用 QueryBuilder（自动 prepared）
$db = Database::getInstance();
$users = $db->db('users')
    ->where('id', '=', $id)
    ->first();

// ❌ 错误：直接拼接 SQL（生产环境会被拦截）
$sql = "SELECT * FROM users WHERE id = " . $id;
```

### 3. XSS 防护升级

```php
'xss' => [
    'enabled' => true,
    'mode' => 'escape',           // ✅ escape / strip / purify
    'autoApplyToResponse' => true, // ✅ 生产强制 true，自动转义输出
    'stripHtml' => true,
    'skipFields' => ['password', 'token', 'csrf_token'],
],
```

**输出转义示例：**

```php
// ✅ 正确：框架自动转义（当 autoApplyToResponse=true）
echo $user['username'];  // 自动调用 htmlspecialchars

// ❌ 错误：直接输出未信任数据
echo $_GET['input'];  // 可能被注入 XSS
```

### 4. CSP 内容安全策略

```php
'csp' => [
    'enabled' => true,  // ✅ 生产强制 true
    'policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
    'reportOnly' => false,  // true 仅报告不拦截
    'reportUri' => '/anon/security/csp-report',
],
```

**CSP 作用：**
- 防止加载外部恶意脚本
- 防止内联脚本注入
- 防止样式表劫持

### 5. CSRF 防护

```php
'csrf' => [
    'enabled' => true,
    'stateless' => true,  // ✅ 无状态模式，适合 API
],
```

**前端使用示例：**

```javascript
// 获取 CSRF token
const response = await fetch('/anon/auth/csrf');
const data = await response.json();
const csrfToken = data.data.token;

// 发送请求时携带 token
await fetch('/api/users', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': csrfToken,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username: 'test' }),
});
```

### 6. 密码策略加强

```php
'password' => [
    'minLength' => 12,              // ✅ 生产建议 12+
    'maxLength' => 128,
    'requireUppercase' => true,     // ✅ 要求大写字母
    'requireLowercase' => true,     // ✅ 要求小写字母
    'requireDigit' => true,         // ✅ 要求数字
    'requireSpecial' => true,       // ✅ 要求特殊字符
],
```

**弱密码示例（会被拒绝）：**
- ❌ `password123` （太简单）
- ❌ `12345678` （纯数字）
- ❌ `abcdefgh` （纯字母）

**强密码示例：**
- ✅ `An0n@2024!Secure`
- ✅ `P@ssw0rd#Strong123`

### 7. CORS 跨域配置

```php
'cors' => [
    'enabled' => true,
    'origins' => ['https://example.com'],  // ✅ 生产必须指定域名，禁止 *
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposedHeaders' => [],
    'maxAge' => 3600,
    'supportsCredentials' => true,
],
```

**⚠️ 危险配置：**

```php
// ❌ 禁止在生产环境使用
'origins' => ['*']  // 允许所有域名跨域
```

## 路由系统优化

### 混合路由模式（推荐）

```php
'router' => [
    'enableAnnotation' => true,      // ✅ 启用注解路由
    'enableConfigFile' => true,      // ✅ 启用配置文件路由
    'configFile' => 'app/Routes/config.php',
    'annotationPaths' => ['app/Controllers'],
],
```

**优先级：** 注解路由 > 配置文件 > 文件扫描

### 注解路由示例

```php
/**
 * @Route(prefix="/api/v1/users")
 */
class UserController
{
    /**
     * @Route("/", methods=["GET"])
     * @Middleware({"auth", "rateLimit"})
     * @Permission("user.list")
     */
    public function list()
    {
        // ...
    }
}
```

### 配置文件路由示例

```php
// app/Routes/config.php
return [
    '/api/v1/users' => [
        'GET' => [
            'handler' => [UserController::class, 'list'],
            'middleware' => ['auth', 'rateLimit'],
            'permission' => 'user.list',
        ],
    ],
];
```

## 分层架构（Repository + Service）

### 目录结构

```
app/
├── Controllers/        # 控制器层（接收请求）
│   └── UserController.php
├── Services/          # 服务层（业务逻辑）
│   └── UserService.php
└── Repository/        # 仓库层（数据访问）
    └── UserRepository.php
```

### 使用示例

**Repository 层：**

```php
namespace App\Repository;

class UserRepository
{
    public function find(int $id): ?array
    {
        return $this->db->db('users')
            ->where('id', '=', $id)
            ->first();
    }
}
```

**Service 层：**

```php
namespace App\Service;

use App\Repository\UserRepository;

class UserService
{
    private $userRepository;
    
    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }
    
    public function getUser(int $id): array
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            throw new \Exception('用户不存在');
        }
        
        return $user;
    }
}
```

**Controller 层：**

```php
namespace App\Controllers;

use App\Service\UserService;

class UserController
{
    private $userService;
    
    public function __construct()
    {
        $this->userService = new UserService();
    }
    
    public function get($id)
    {
        try {
            $user = $this->userService->getUser((int)$id);
            Anon::success($user);
        } catch (\Exception $e) {
            Anon::error($e->getMessage(), 404);
        }
    }
}
```

## Nginx 生产配置

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    
    # SSL 配置
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    root /path/to/anon;
    index index.php;
    
    # 隐藏 PHP 版本
    fastcgi_hide_header X-Powered-By;
    
    # 限流配置（每秒 10 个请求）
    limit_req_zone $binary_remote_addr zone=one:10m rate=10r/s;
    limit_req zone=one burst=20 nodelay;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
    }
}
```

## 安全检查清单

部署前逐项检查：

- [ ] **productionMode** 设为 `true`
- [ ] **debug.global** 设为 `false`
- [ ] **rateLimit.global.enabled** 设为 `true`
- [ ] **sql.forcePrepareAlways** 设为 `true`
- [ ] **xss.autoApplyToResponse** 设为 `true`
- [ ] **csp.enabled** 设为 `true`
- [ ] **cors.origins** 填写具体域名（非 `*`）
- [ ] **password.minLength** ≥ 12
- [ ] 所有密码字段使用 `password_hash()`
- [ ] 所有输出自动转义或使用 `htmlspecialchars()`
- [ ] 所有 AJAX 请求携带 CSRF token
- [ ] Nginx 配置限流规则
- [ ] 禁用目录浏览
- [ ] 设置正确的文件权限（755/644）

## 性能优化建议

### 1. 启用 Redis 缓存

```php
'cache' => [
    'enabled' => true,
    'driver' => 'redis',
    'time' => 3600,
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1,
    ],
],
```

### 2. 启用 OPcache

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; 生产环境设为 0
```

### 3. 数据库连接池

使用持久连接：

```php
'db' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
],
```

## 监控与日志

### 1. 错误日志

```php
'debug' => [
    'global' => false,  // 生产关闭
    'logDetailedErrors' => true,  // 但记录详细错误
],
```

### 2. 访问日志

```php
'access_log_enabled' => true,  // 启用访问日志
'access_log_retention_days' => 30,  // 保留 30 天
```

### 3. 性能监控

```php
<?php
use Anon\Modules\Debug;

// 在 Middleware 中记录响应时间
$start = microtime(true);
// ... 处理请求 ...
$duration = microtime(true) - $start;

if ($duration > 1.0) {  // 超过 1 秒记录
    Debug::warning('Slow request', ['duration' => $duration]);
}
```

## 故障排查

### 常见问题

#### 1. 生产环境仍然显示详细错误

**解决：**
```php
'productionMode' => true,
'debug' => [
    'global' => false,
    'logDetailedErrors' => true,  // 只记录不显示
],
```

#### 2. 限流导致正常用户被限制

**解决：** 调整限流阈值：
```php
'rateLimit' => [
    'global' => [
        'maxAttempts' => 120,  // 提高到 120 次/分钟
    ],
],
```

#### 3. CSP 阻止了正常脚本

**解决：** 调整 CSP 策略：
```php
'csp' => [
    'policy' => "default-src 'self'; script-src 'self' https://cdn.example.com;",
],
```

## 相关文档

- [API 参考](/api/reference) - 核心 API 文档
- [路由系统](/guide/api/routing) - 路由配置详解
- [中间件扩展](/guide/extension-system) - 中间件开发
- [插件系统](/guide/plugin-system) - 插件开发指南
