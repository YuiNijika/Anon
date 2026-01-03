# 路由处理

一句话：文件路径自动映射为路由，用常量配置路由元数据。

## 自动路由（推荐）

### 启用方式

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => true,  // 启用自动路由
],
```

### 路径映射规则

```
文件路径                          → 路由路径
app/Router/Auth/Login.php        → /auth/login
app/Router/User/Info.php         → /user/info
app/Router/User_Profile/Index.php → /user-profile/index 和 /user-profile
app/Router/Index.php              → /index 和 /
```

**命名转换规则：**

- 文件名和目录名中的下划线 `_` 会自动转换为连字符 `-`
- 所有路径自动转为小写
- 例如：`User_Profile/Update_Avatar.php` → `/user-profile/update-avatar`

**Index.php 特殊处理：**

- 当文件名为 `Index.php` 时，会同时注册两个路由
- 根目录下的 `Index.php` 会注册 `/` 和 `/index`
- 子目录下的 `Index.php` 会注册父级路径和带 `/index` 的路径
- 例如：`app/Router/User/Index.php` 会注册 `/user` 和 `/user/index`

## 手动路由

### 启用方式

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => false,  // 禁用自动路由
],
```

### 配置路由

编辑 `server/app/useRouter.php`：

```php
return [
    'auth' => [
        'login' => 'Auth/Login',
        'logout' => 'Auth/Logout',
    ],
    'user' => [
        'info' => 'User/Info',
    ],
];
```

## 路由元数据配置

### 配置项说明

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `header` | bool | `true` | 是否设置响应头（包含CORS、Content-Type） |
| `requireLogin` | bool | `false` | 是否需要登录验证 |
| `method` | string\|array | `null` | 允许的HTTP方法，如 `'GET'` 或 `['GET', 'POST']` |
| `cors` | bool | `true` | 是否设置CORS头（可选，不设置时使用默认值true） |
| `response` | bool | `true` | 是否设置JSON响应头（可选，不设置时使用默认值true） |
| `code` | int | `200` | HTTP状态码（可选，不设置时使用默认值200） |
| `token` | bool | - | 是否启用Token验证（可选），`true` 表示启用，`false` 表示禁用，不设置时使用全局配置 |
| `middleware` | array | `[]` | 中间件列表 |
| `cache` | array | `['enabled' => false, 'time' => 0]` | 缓存控制配置，`enabled` 为是否启用缓存，`time` 为缓存时间（秒），0 表示不缓存 |

### 示例：需要登录的GET接口

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    // 获取用户信息已自动通过登录检查
    $userInfo = Anon_RequestHelper::requireAuth();
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：不需要登录的POST接口

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    // 验证输入数据
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    // 业务逻辑
    $db = new Anon_Database();
    $user = $db->getUserInfoByName($data['username']);
    
    Anon_ResponseHelper::success($user, '操作成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：支持多种HTTP方法

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => ['GET', 'POST'],  // 支持GET和POST
];

try {
    // 根据请求方法执行不同逻辑
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        // GET 逻辑
    } else {
        // POST 逻辑
    }
    
    Anon_ResponseHelper::success(null, '操作成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：使用中间件

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'middleware' => ['auth', 'throttle'], // 使用中间件
];

// 路由处理代码...
```

### 示例：启用 Token 验证

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => true,  // 启用 Token 验证
];

try {
    // 需要提供有效的 Token 才能访问
    Anon_ResponseHelper::success(['message' => '需要 Token 验证的接口']);
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：禁用 Token 验证

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => false,  // 禁用 Token 验证（即使全局启用了 Token）
];

try {
    // 不需要 Token 即可访问
    Anon_ResponseHelper::success(['message' => '不需要 Token 验证的接口']);
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：自定义 HTTP 状态码

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'code' => 201,  // 自定义 HTTP 状态码为 201
];

try {
    Anon_ResponseHelper::success(['message' => '创建成功'], '资源已创建', 201);
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：启用缓存控制

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => [
        'enabled' => true,  // 启用缓存
        'time' => 3600,     // 缓存 1 小时（3600 秒）
    ],
];

try {
    // 这个接口的响应会被缓存 1 小时
    Anon_ResponseHelper::success(['data' => '缓存的数据'], '获取成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 示例：禁用缓存

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => [
        'enabled' => false, // 禁用缓存（默认值）
        'time' => 0,        // 缓存时间为 0（默认值）
    ],
];

try {
    // 这个接口的响应不会被缓存
    Anon_ResponseHelper::success(['data' => '实时数据'], '获取成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

## 动态注册路由

```php
// server/app/useCode.php
Anon_Config::addRoute('/api/custom', function() {
    Anon_Common::Header();
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});
```

## 静态文件路由

系统提供了 `Anon_Config::addStaticRoute()` 方法来注册静态文件路由，支持自动缓存和压缩。

### 使用方法

在 `server/core/Modules/Config.php` 的 `initSystemRoutes()` 方法中注册：

```php
// 注册静态文件路由
$staticDir = __DIR__ . '/../Static/';

Anon_Config::addStaticRoute('/anon/static/debug/css', $staticDir . 'debug.css', 'text/css');
Anon_Config::addStaticRoute('/anon/static/debug/js', $staticDir . 'debug.js', 'application/javascript');
Anon_Config::addStaticRoute('/anon/static/vue', $staticDir . 'vue.global.prod.js', 'application/javascript');
```

### 方法签名

```php
Anon_Config::addStaticRoute(
    string $route,        // 路由路径
    string $filePath,     // 文件完整路径
    string $mimeType,     // MIME类型
    int $cacheTime = 31536000,  // 缓存时间（秒），0表示不缓存，默认1年
    bool $compress = true       // 是否启用压缩，默认true
)
```

### 示例：自定义静态文件路由

```php
// 在 useCode.php 中注册自定义静态文件路由
$customDir = __DIR__ . '/../assets/';

Anon_Config::addStaticRoute('/assets/logo.png', $customDir . 'logo.png', 'image/png', 86400, false);
Anon_Config::addStaticRoute('/assets/style.css', $customDir . 'style.css', 'text/css', 31536000, true);
```

