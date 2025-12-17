# 路由处理

## 自动路由

**启用方式**：

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => true,  // 启用自动路由
],
```

**工作原理**：

- 自动扫描 `app/Router` 目录下的所有 PHP 文件
- 根据文件结构自动生成路由路径
- 所有路由路径自动转为小写
- 通过 `Anon_RouterMeta` 常量配置路由元数据 Header、登录检查、HTTP 方法等

**示例**：

```text
app/Router/
  ├── Auth/
  │   ├── Login.php           → /auth/login
  │   ├── Logout.php          → /auth/logout
  │   └── Token.php           → /auth/token
  ├── User/
  │   └── Info.php             → /user/info
  └── User_Profile/
      ├── Index.php            → /user-profile/index
      └── Update_Avatar.php    → /user-profile/update-avatar
```

**命名转换规则**：

- 文件名和目录名中的下划线 `_` 会自动转换为连字符 `-`
- 所有路径自动转为小写
- 例如：`User_Profile/Update_Avatar.php` → `/user-profile/update-avatar`

## 手动路由

**启用方式**：

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => false,  // 禁用自动路由
],
```

**配置路由**：

编辑 `server/app/useRouter.php`：

```php
return [
    'auth' => [
        'login' => [
            'view' => 'Auth/Login',
        ],
        'logout' => [
            'view' => 'Auth/Logout',
        ],
    ],
    'user' => [
        'info' => [
            'view' => 'User/Info',
        ],
    ],
];
```

## 路由元数据配置（Anon_RouterMeta）

**推荐方式**：使用 `Anon_RouterMeta` 常量统一配置路由元数据，系统会自动应用这些配置。

**配置项说明**：

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `header` | bool | `true` | 是否设置响应头包括 CORS、Content-Type 等 |
| `requireLogin` | bool | `false` | 是否需要登录验证 |
| `method` | string\|array | `null` | 允许的 HTTP 方法，如 `'GET'` 或 `['GET', 'POST']` |
| `cors` | bool | `true` | 是否设置 CORS 头 |
| `response` | bool | `true` | 是否设置 JSON 响应头 |
| `code` | int | `200` | HTTP 状态码 |
| `middleware` | array | `[]` | 中间件列表 |

**示例：需要登录的 GET 接口**：

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

**示例：不需要登录的 POST 接口**：

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

**示例：支持多种 HTTP 方法**：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => ['GET', 'POST'],  // 支持 GET 和 POST
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

**示例：使用中间件**：

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

**注意**：

- `Anon_RouterMeta` 必须在路由文件顶部定义
- 如果未定义 `Anon_RouterMeta`，系统会使用默认配置 `header: true`, `requireLogin: false`, `method: null`
- HTTP 方法检查会在登录检查之前执行
- 登录检查失败会自动返回 401 错误

## 动态注册路由

```php
// server/app/useCode.php
Anon_Config::addRoute('/api/custom', function () {
    Anon_Common::Header();
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});
```

---

[← 返回文档首页](../README.md)

