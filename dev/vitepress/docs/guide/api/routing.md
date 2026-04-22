# 路由处理

一句话：路由有两种来源（`app/useRouter.php` 手动路由、`app/Router/` 自动扫描），每个路由文件可通过常量 `RouterMeta` 配置元数据。

## 自动路由（扫描 app/Router）

### 启用方式

在 `app/useApp.php` 中设置路由模式：

```php
return [
  'app' => [
    'base' => [
      'router' => [
        'mode' => 'auto',
      ],
    ],
  ],
];
```

当项目根目录不存在 `app/useRouter.php` 时，会启用自动扫描（并按上述 `mode` 决定是否扫描）。

### 路径映射规则

```
文件路径                           → 路由路径
app/Router/Auth/Login.php          → /auth/login
app/Router/User/Info.php           → /user/info
app/Router/User_Profile/Index.php  → /user-profile  和 /user-profile/index
app/Router/Index.php               → /  和 /index
```

- 文件名与目录名：转小写；下划线 `_` 会转换为连字符 `-`
- `Index.php`：根目录注册 `/` 与 `/index`；子目录注册父级路径与 `/index`

## 手动路由（useRouter.php）

### 启用方式

将路由模式切换为 `manual`，并创建 `app/useRouter.php`：

```php
// app/useApp.php
return [
  'app' => [
    'base' => [
      'router' => [
        'mode' => 'manual',
      ],
    ],
  ],
];
```

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

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

## CMS 路由来源（options 表）

CMS 模式下，主题路由存储在数据库 `options` 表的 `routes` 字段中，通过 `Options::get('routes')` 读取并 JSON 解码。建议通过 CMS 管理端维护；如需代码层初始化，可直接写入数据库。

## 路由元数据配置（RouterMeta）

### 配置项说明

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `header` | bool | `true` | 是否设置响应头，包含CORS和Content-Type |
| `requireLogin` | bool\|string | `false` | 是否需要登录验证，`true` 使用默认消息或钩子，字符串则使用自定义消息 |
| `requireAdmin` | bool\|string | `false` | 是否需要管理员权限，`true` 使用默认消息，字符串则使用自定义消息 |
| `method` | string\|array | `null` | 允许的HTTP方法，如 `'GET'` 或 `['GET', 'POST']` |
| `cors` | bool | `true` | 是否设置CORS头，不设置时使用默认值true |
| `response` | bool | `true` | 是否设置JSON响应头，不设置时使用默认值true |
| `code` | int | `200` | HTTP状态码，不设置时使用默认值200 |
| `token` | bool | - | 是否启用Token验证，`true` 表示启用，`false` 表示禁用，不设置时使用全局配置 |
| `middleware` | array | `[]` | 中间件列表 |
| `cache` | array | `['enabled' => false, 'time' => 0]` | 缓存控制配置，`enabled` 为是否启用缓存，`time` 为缓存时间，单位秒，0 表示不缓存 |

### 示例：需要登录的 GET 接口

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Exception;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => true,
  'method' => 'GET',
];

try {
  $userInfo = RequestHelper::requireAuth();
  ResponseHelper::success($userInfo, '获取用户信息成功');
} catch (Exception $e) {
  ResponseHelper::handleException($e);
}
```


### 示例：自定义未登录消息

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => '请先登录以获取 Token',
  'method' => 'GET',
];

try {
    // 未登录时会返回自定义消息
    $isLoggedIn = \Anon\Modules\Check::isLoggedIn();
    // ...
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 使用钩子全局自定义消息

在 `app/useApp.php`（通过自定义代码加载点）或插件中注册钩子：

```php
// 全局修改所有使用 requireLogin: true 的路由的消息
use Anon\Modules\System\Hook;

Hook::add_filter('require_login_message', function ($defaultMessage) {
  $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
  if (strpos($requestPath, '/auth/token') !== false) {
    return '请先登录以获取 Token';
  }
  if (strpos($requestPath, '/user/') !== false) {
    return '您需要登录后才能访问用户功能';
  }
  return $defaultMessage;
});
```

**优先级说明：**
1. 如果 `requireLogin` 是字符串，直接使用该字符串
2. 如果 `requireLogin` 是 `true`，先尝试钩子 `require_login_message`
3. 如果钩子没有返回，使用默认消息 "请先登录"

### 示例：不需要登录的POST接口

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'POST',
];

try {
    // 验证输入数据
    $data = RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    // 业务逻辑
    $db = \Anon\Modules\Database\Database::getInstance();
    $user = $db->getUserInfoByName($data['username']);
    
    ResponseHelper::success($user, '操作成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：支持多种HTTP方法

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => true,
  'method' => ['GET', 'POST'],
];

try {
    // 根据请求方法执行不同逻辑
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        // GET 逻辑
    } else {
        // POST 逻辑
    }
    
    ResponseHelper::success(null, '操作成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：使用中间件

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'middleware' => ['auth', 'throttle'],
];

// 路由处理代码...
```

### 示例：启用 Token 验证

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'token' => true,
];

try {
    // 需要提供有效的 Token 才能访问
    ResponseHelper::success(['message' => '需要 Token 验证的接口']);
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：禁用 Token 验证

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'token' => false,
];

try {
    // 不需要 Token 即可访问
    ResponseHelper::success(['message' => '不需要 Token 验证的接口']);
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：自定义 HTTP 状态码

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'code' => 201,
];

try {
    ResponseHelper::success(['message' => '创建成功'], '资源已创建', 201);
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：启用缓存控制

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'cache' => ['enabled' => true, 'time' => 3600],
];

try {
    // 这个接口的响应会被缓存 1 小时
    ResponseHelper::success(['data' => '缓存的数据'], '获取成功');
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

### 示例：禁用缓存

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
  'requireLogin' => false,
  'method' => 'GET',
  'cache' => ['enabled' => false, 'time' => 0],
];

try {
    // 这个接口的响应不会被缓存
    ResponseHelper::success(['data' => '实时数据'], '获取成功');
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

## 动态注册路由

```php
use Anon\Modules\System\Config;
use Anon\Modules\Http\ResponseHelper;

Config::addRoute('/api/custom', function () {
  ResponseHelper::success(['message' => '自定义路由']);
});
```

## 静态文件路由

系统提供了 `Anon\Modules\System\Config::addStaticRoute()` 方法来注册静态文件路由，支持缓存与压缩。

### 使用方法（推荐在 app/useCode.php 或插件中注册）

```php
use Anon\Modules\System\Config;

$staticDir = ANON_ROOT . 'app/assets/';
Config::addStaticRoute('/assets/logo.png', $staticDir . 'logo.png', 'image/png', 86400, false);
```

### 方法签名

```php
Config::addStaticRoute(
    string $route,        // 路由路径
    string $filePath,     // 文件完整路径
    string $mimeType,     // MIME类型
    int $cacheTime = 31536000,
    bool $compress = true       // 是否启用压缩，默认true
)
```

### 示例：自定义静态文件路由

```php
use Anon\Modules\System\Config;

$customDir = ANON_ROOT . 'app/assets/';
Config::addStaticRoute('/assets/style.css', $customDir . 'style.css', 'text/css', 31536000, true);
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

