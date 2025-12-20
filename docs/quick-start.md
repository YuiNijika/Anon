# 快速开始

一句话：配置数据库，创建路由文件，立即开始。

## 1. 配置数据库

编辑 `server/env.php`：

```php
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'root');
define('ANON_DB_DATABASE', 'anon');
define('ANON_DB_CHARSET', 'utf8mb4');
define('ANON_INSTALLED', true);
```

## 2. 应用配置

编辑 `server/app/useApp.php`：

```php
return [
    'app' => [
        'autoRouter' => true,  // 启用自动路由
        'debug' => [
            'global' => false, // 全局debug
            'router' => false, // 路由debug
        ],
        'token' => [
            'enabled' => true, // 是否启用token
            'whitelist' => [   // 白名单路由
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],
        ],
        'captcha' => [
            'enabled' => true, // 是否启用验证码
        ],
    ],
];
```

## 3. 创建路由

创建 `server/app/Router/Test/Index.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    Anon_ResponseHelper::success(['message' => 'Anon Tokyo~!']);
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

访问：`GET /test/index` 或 `GET /test`（Index.php会同时注册两个路由）

## 路由规则

- 文件路径：`app/Router/Test/Index.php` → 路由路径：`/test/index` 和 `/test`
- 文件路径：`app/Router/User/Profile/Index.php` → 路由路径：`/user/profile/index` 和 `/user/profile`
- 所有路由路径自动转为小写，不区分文件大小写
- **特殊处理**：
  - 文件名和目录名中的下划线（`_`）会自动转换为连字符（`-`）
  - 文件路径：`app/Router/Aa_Bb/Cc_Dd.php` → 路由路径：`/aa-bb/cc-dd`
  - 文件路径：`app/Router/User_Profile/Index.php` → 路由路径：`/user-profile/index` 和 `/user-profile`
  - 根目录下的 `Index.php` 会注册 `/` 和 `/index`

---

[← 返回文档首页](../README.md)
