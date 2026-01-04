# 安全功能

一句话：CSRF 防护、XSS 过滤、SQL 注入防护、接口限流等安全功能。

---

## CSRF 防护

### 启用 CSRF 防护

在 `server/app/useApp.php` 中配置：

```php
'security' => [
    'csrf' => [
        'enabled' => true,      // 是否启用 CSRF 防护
        'stateless' => true,     // 是否使用无状态 Token（推荐，减少 Session 锁竞争）
    ],
]
```

### 生成 CSRF Token

```php
// 生成并获取 CSRF Token
$token = Anon_Csrf::generateToken();

// 获取当前 Token（如果不存在则生成）
$token = Anon_Csrf::getToken();
```

**无状态 Token 说明：**

- 默认启用无状态 Token，基于 HMAC 签名，无需存储在 Session 中
- 减少 Session 锁竞争，提高并发性能
- Token 包含时间戳、随机数和 Session ID，有效期 2 小时
- 可通过配置 `stateless => false` 切换为传统 Session 存储模式

### 验证 CSRF Token

```php
// 自动从请求中获取并验证 Token
Anon_Csrf::verify();

// 验证指定的 Token
Anon_Csrf::verify($token);

// 验证失败时不抛出异常，返回 false
$isValid = Anon_Csrf::verify($token, false);
```

### 在路由中使用

```php
// 在路由文件中验证 CSRF
const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

// 验证 CSRF Token
Anon_Csrf::verify();
```

### 使用 CSRF 中间件

```php
// 在 useCode.php 中注册全局中间件
Anon_Middleware::global(Anon_CsrfMiddleware::make([
    '/api/public', // 排除的路由
]));
```

### 前端获取 Token

```javascript
// 从响应头或配置中获取 Token
fetch('/anon/common/config')
    .then(res => res.json())
    .then(data => {
        const csrfToken = data.data.csrfToken;
        // 在请求头中携带 Token
        fetch('/api/endpoint', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });
    });
```

## XSS 过滤

### 启用 XSS 过滤

在 `server/app/useApp.php` 中配置：

```php
'security' => [
    'xss' => [
        'enabled' => true, // 是否启用 XSS 自动过滤
        'stripHtml' => true, // 是否移除 HTML 标签
        'skipFields' => ['password', 'token', 'csrf_token'], // 跳过的字段
    ],
]
```

### 手动过滤数据

```php
// 过滤单个字符串
$clean = Anon_Utils_Sanitize::text('<script>alert("xss")</script>');

// 过滤 HTML（允许指定标签）
$html = Anon_Utils_Sanitize::html('<p>Hello</p><script>alert("xss")</script>', '<p><strong>');

// 过滤数组（递归）
$data = Anon_Utils_Sanitize::array([
    'name' => '<script>alert("xss")</script>',
    'content' => '<p>Safe content</p>'
]);

// 使用安全工具类过滤
$filtered = Anon_Security::filterInput($_POST, [
    'stripHtml' => true,
    'skipFields' => ['password']
]);
```

### 使用 XSS 过滤中间件

```php
// 在 useCode.php 中注册全局中间件
Anon_Middleware::global(Anon_XssFilterMiddleware::make(
    true, // 移除 HTML 标签
    ['password', 'token'] // 跳过的字段
));
```

### 检查 XSS 风险

```php
// 检查字符串是否包含潜在的 XSS 代码
if (Anon_Security::containsXss($userInput)) {
    // 处理风险
}
```

## SQL 注入防护

### 使用预处理语句

框架的查询构建器自动使用预处理语句，防止 SQL 注入：

```php
// 使用查询构建器（推荐）
$db = Anon_Database::getInstance();
$users = $db->db('users')
    ->where('name', '=', $username)
    ->where('email', '=', $email)
    ->get();

// 使用 prepare 方法
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [$userId]);
$result = $stmt->get_result();
```

### 验证 SQL 查询安全性

在调试模式下，框架会自动检查 SQL 查询：

```php
// 手动验证（仅在调试模式下有效）
Anon_Security::validateSqlQuery($sql, $params);

// 检查是否使用了预处理语句
$isSafe = Anon_Security::isUsingPreparedStatement($sql, $params);
```

### 转义 LIKE 查询

```php
// 转义 LIKE 查询中的特殊字符
$search = Anon_Security::escapeLike($userInput);
$users = $db->db('users')
    ->where('name', 'LIKE', "%{$search}%")
    ->get();
```

### 检查 SQL 注入风险

```php
// 检查字符串是否包含潜在的 SQL 注入代码
if (Anon_Security::containsSqlInjection($userInput)) {
    // 处理风险
}
```

## 接口限流

### 使用限流中间件

```php
// 在 useCode.php 中注册限流中间件
Anon_Middleware::global(
    Anon_RateLimitMiddleware::make(
        100, // 最大请求次数
        60,  // 时间窗口（秒）
        'api', // 限流键前缀
        [
            'useIp' => true,    // 基于 IP
            'useUserId' => true // 基于用户 ID
        ]
    )
);
```

### 手动检查限流

```php
// 检查是否超过限制
$limit = Anon_RateLimit::checkLimit(
    'api:login', // 限流键
    10,          // 最大尝试次数
    3600         // 时间窗口（秒）
);

if (!$limit['allowed']) {
    // 超过限制
    Anon_ResponseHelper::error('请求过于频繁', [], 429);
}

// 返回信息
// [
//     'allowed' => bool,
//     'remaining' => int,
//     'resetAt' => int,
//     'count' => int
// ]
```

### 在路由中使用

```php
// 在路由文件中检查限流
const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

$limit = Anon_RateLimit::checkLimit('login', 5, 3600);
if (!$limit['allowed']) {
    Anon_ResponseHelper::error('登录尝试次数过多，请稍后再试', [], 429);
}
```

## 最佳实践

### 1. 始终使用预处理语句

```php
// ✅ 正确
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [$userId]);

// ❌ 错误
$sql = "SELECT * FROM users WHERE id = {$userId}";
$result = $db->query($sql);
```

### 2. 过滤用户输入

```php
// ✅ 正确
$username = Anon_Utils_Sanitize::text($_POST['username']);
$email = Anon_Utils_Sanitize::email($_POST['email']);

// ❌ 错误
$username = $_POST['username'];
```

### 3. 验证 CSRF Token

```php
// ✅ 正确
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Anon_Csrf::verify();
}

// ❌ 错误
// 直接处理 POST 请求，不验证 CSRF
```

### 4. 使用限流保护敏感接口

```php
// ✅ 正确
Anon_Middleware::global(
    Anon_RateLimitMiddleware::make(10, 60, 'login')
);
```

### 5. 配置安全选项

在 `server/app/useApp.php` 中配置所有安全选项：

```php
'security' => [
    'csrf' => [
        'enabled' => true,      // 是否启用 CSRF 防护
        'stateless' => true,    // 是否使用无状态 Token（推荐，减少 Session 锁竞争）
    ],
    'xss' => [
        'enabled' => true,      // 是否启用 XSS 自动过滤
        'stripHtml' => true,   // 是否移除 HTML 标签
        'skipFields' => ['password', 'token', 'csrf_token'], // 跳过的字段
    ],
    'sql' => [
        'validateInDebug' => true, // 在调试模式下验证 SQL 查询安全性
    ],
],
```


