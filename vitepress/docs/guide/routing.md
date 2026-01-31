# 路由处理

一句话：文件路径自动映射为路由，用常量配置路由元数据。

## 自动路由

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

### API 模式路由配置

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

### CMS 模式路由配置

在 `server/app/useApp.php` 的 `app.cms.routes` 中配置：

```php
'app' => [
    'mode' => 'cms',
    'cms' => [
        'theme' => 'default',
        'routes' => [
            '/' => 'index',              // 首页，渲染 Theme/default/index.php
            '/about' => 'about',         // 关于页，渲染 Theme/default/about.php
            '/post/{id}' => 'post',      // 文章详情，渲染 Theme/default/post.php
        ],
    ],
],
```

**CMS 路由说明：**
- 路由路径映射到主题模板文件，不区分大小写
- 支持参数路由，如 `/post/{id}`，参数会传递给模板
- 模板文件位于 `app/Theme/{themeName}/` 目录
- 支持 `.php`、`.html`、`.htm` 扩展名

## 路由元数据配置

### 配置项说明

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `header` | bool | `true` | 是否设置响应头，包含CORS和Content-Type |
| `requireLogin` | bool\|string | `false` | 是否需要登录验证，`true` 使用默认消息或钩子，字符串则使用自定义消息 |
| `method` | string\|array | `null` | 允许的HTTP方法，如 `'GET'` 或 `['GET', 'POST']` |
| `cors` | bool | `true` | 是否设置CORS头，不设置时使用默认值true |
| `response` | bool | `true` | 是否设置JSON响应头，不设置时使用默认值true |
| `code` | int | `200` | HTTP状态码，不设置时使用默认值200 |
| `token` | bool | - | 是否启用Token验证，`true` 表示启用，`false` 表示禁用，不设置时使用全局配置 |
| `middleware` | array | `[]` | 中间件列表 |
| `cache` | array | `['enabled' => false, 'time' => 0]` | 缓存控制配置，`enabled` 为是否启用缓存，`time` 为缓存时间，单位秒，0 表示不缓存 |

### 示例：需要登录的GET接口

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => true,  // 使用默认消息或钩子自定义消息
    'method' => 'GET',
];

try {
    // 获取用户信息已自动通过登录检查
    $userInfo = Anon_Http_Request::requireAuth();
    
    Anon_Http_Response::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：自定义未登录消息

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => '请先登录以获取 Token',  // 直接指定自定义消息
    'method' => 'GET',
];

try {
    // 未登录时会返回自定义消息
    $isLoggedIn = Anon_Check::isLoggedIn();
    // ...
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 使用钩子全局自定义消息

在 `server/app/useApp.php` 或插件中注册钩子：

```php
// 全局修改所有使用 requireLogin: true 的路由的消息
if (class_exists('Anon_System_Hook')) {
    Anon_System_Hook::add_filter('require_login_message', function($defaultMessage) {
        // 可以根据路由、用户状态等动态返回消息
        $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
        if (strpos($requestPath, '/auth/token') !== false) {
            return '请先登录以获取 Token';
        }
        if (strpos($requestPath, '/user/') !== false) {
            return '您需要登录后才能访问用户功能';
        }
        return '您需要登录后才能访问此功能';
    });
}
```

**优先级说明：**
1. 如果 `requireLogin` 是字符串，直接使用该字符串
2. 如果 `requireLogin` 是 `true`，先尝试钩子 `require_login_message`
3. 如果钩子没有返回，使用默认消息 "请先登录"

### 示例：不需要登录的POST接口

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    // 验证输入数据
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    // 业务逻辑
    $db = Anon_Database::getInstance();
    $user = $db->getUserInfoByName($data['username']);
    
    Anon_Http_Response::success($user, '操作成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：支持多种HTTP方法

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
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
    
    Anon_Http_Response::success(null, '操作成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：使用中间件

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
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

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => true,  // 启用 Token 验证
];

try {
    // 需要提供有效的 Token 才能访问
    Anon_Http_Response::success(['message' => '需要 Token 验证的接口']);
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：禁用 Token 验证

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => false,
];

try {
    // 不需要 Token 即可访问
    Anon_Http_Response::success(['message' => '不需要 Token 验证的接口']);
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：自定义 HTTP 状态码

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'code' => 201,  // 自定义 HTTP 状态码为 201
];

try {
    Anon_Http_Response::success(['message' => '创建成功'], '资源已创建', 201);
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：启用缓存控制

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => [
        'enabled' => true,  // 启用缓存
        'time' => 3600,
    ],
];

try {
    // 这个接口的响应会被缓存 1 小时
    Anon_Http_Response::success(['data' => '缓存的数据'], '获取成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 示例：禁用缓存

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => [
        'enabled' => false,
        'time' => 0,
    ],
];

try {
    // 这个接口的响应不会被缓存
    Anon_Http_Response::success(['data' => '实时数据'], '获取成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

## 动态注册路由

```php
// server/app/useCode.php
Anon_System_Config::addRoute('/api/custom', function() {
    Anon_Common::Header();
    Anon_Http_Response::success(['message' => '自定义路由']);
});
```

## 静态文件路由

系统提供了 `Anon_System_Config::addStaticRoute()` 方法来注册静态文件路由，支持自动缓存和压缩。

### 使用方法

在 `server/core/Modules/Config.php` 的 `initSystemRoutes()` 方法中注册：

```php
// 注册静态文件路由
$staticDir = __DIR__ . '/../Static/';

Anon_System_Config::addStaticRoute('/anon/static/debug/css', $staticDir . 'debug.css', 'text/css');
Anon_System_Config::addStaticRoute('/anon/static/debug/js', $staticDir . 'debug.js', 'application/javascript');
Anon_System_Config::addStaticRoute('/anon/static/vue', $staticDir . 'vue.global.prod.js', 'application/javascript');
```

### 方法签名

```php
Anon_System_Config::addStaticRoute(
    string $route,        // 路由路径
    string $filePath,     // 文件完整路径
    string $mimeType,     // MIME类型
    int $cacheTime = 31536000,
    bool $compress = true       // 是否启用压缩，默认true
)
```

### 示例：自定义静态文件路由

```php
// 在 useCode.php 中注册自定义静态文件路由
$customDir = __DIR__ . '/../assets/';

Anon_System_Config::addStaticRoute('/assets/logo.png', $customDir . 'logo.png', 'image/png', 86400, false);
Anon_System_Config::addStaticRoute('/assets/style.css', $customDir . 'style.css', 'text/css', 31536000, true);
```

### 缓存刷新功能

静态文件路由支持通过 URL 参数控制缓存行为：

#### `ver` 参数 - 强制刷新缓存

在 URL 后添加 `?ver=版本号` 参数可以强制刷新缓存，返回最新文件内容：

```
/anon/static/admin/js        → 使用缓存
/anon/static/admin/js?ver=1  → 强制刷新，返回最新数据
/anon/static/admin/js?ver=2  → 更新版本号即可强制刷新
```

**使用场景：**
- 前端资源更新后，通过更新版本号强制浏览器重新加载
- 开发调试时快速查看最新文件内容

#### `nocache` 参数 - 禁用缓存

在 URL 后添加 `?nocache=1` 或 `?nocache=true` 参数可以禁用缓存：

```
/anon/static/admin/css              → 使用缓存
/anon/static/admin/css?nocache=1   → 禁用缓存，每次请求最新数据
```

**使用场景：**
- 开发环境需要实时查看文件变化
- 某些特殊文件需要始终获取最新版本

#### 参数优先级

1. `nocache=1` 或 `ver` 参数存在时强制不缓存，返回最新数据
2. 无参数时使用注册时设置的缓存策略

#### 组合使用示例

```html
<!-- 正常使用缓存 -->
<link rel="stylesheet" href="/anon/static/admin/css">

<!-- 强制刷新缓存 -->
<link rel="stylesheet" href="/anon/static/admin/css?ver=2">

<!-- 禁用缓存 -->
<link rel="stylesheet" href="/anon/static/admin/css?nocache=1">

<!-- 组合使用 -->
<script src="/anon/static/admin/js?ver=1&nocache=1"></script>
```

