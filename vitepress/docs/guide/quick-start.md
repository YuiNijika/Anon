# 快速开始

本节说明通过安装向导完成安装并创建路由立即开始，适用于通用场景。

一句话：通过安装向导完成安装，创建路由文件，立即开始。

## 1. 系统安装

首次使用需要通过安装向导完成系统安装。访问您的域名，系统会自动跳转到安装页面：

```
http://your-domain.com/anon/install
```

安装向导会引导您完成选择安装模式、配置数据库连接、创建管理员账号和初始化系统配置。

详细安装步骤请参考 [安装指南](./installation.md)。安装时需选择 **API 模式** 或 **CMS 模式**，选好后可查看 [模式对比](./mode-overview.md) 与「安装后下一步」。

## 2. 手动配置

如果您需要手动配置，可以编辑 `server/.env.php`：

```php
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'root');
define('ANON_DB_DATABASE', 'anon');
define('ANON_DB_CHARSET', 'utf8mb4');
define('ANON_INSTALLED', true);
define('ANON_APP_MODE', 'api');  // 或 'cms'
```

## 3. 应用配置

编辑 `server/app/useApp.php`：

```php
return [
    'app' => [
        'mode' => 'api',       // 'api' 或 'cms'，默认为 'api'
        'autoRouter' => true,  // 启用自动路由
        'debug' => [
            'global' => false, // 全局debug
            'router' => false, // 路由debug
            'logDetailedErrors' => false, // 是否记录详细错误信息
        ],
        'token' => [
            'enabled' => true, // 是否启用token
            'refresh' => false, // 是否在验证后自动刷新Token
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
        'security' => [
            'csrf' => [
                'enabled' => true,      // 是否启用 CSRF 防护
                'stateless' => true,    // 是否使用无状态 Token，推荐
            ],
        ],
    ],
];
```

## 4. 创建路由

创建 `server/app/Router/Test/Index.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    Anon_Http_Response::success(['message' => 'Anon Tokyo~!']);
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

访问：`GET /test/index` 或 `GET /test`，Index.php 会同时注册两个路由

## 路由规则

- 文件路径：`app/Router/Test/Index.php` → 路由路径：`/test/index` 和 `/test`
- 文件路径：`app/Router/User/Profile/Index.php` → 路由路径：`/user/profile/index` 和 `/user/profile`
- 所有路由路径自动转为小写，不区分文件大小写
- **特殊处理**：
  - 文件名和目录名中的下划线 `_` 会自动转换为连字符 `-`
  - 文件路径：`app/Router/Aa_Bb/Cc_Dd.php` → 路由路径：`/aa-bb/cc-dd`
  - 文件路径：`app/Router/User_Profile/Index.php` → 路由路径：`/user-profile/index` 和 `/user-profile`
  - 根目录下的 `Index.php` 会注册 `/` 和 `/index`

## CMS 模式快速开始

### 1. 切换到 CMS 模式

在 `server/app/useApp.php` 中设置：

```php
return [
    'app' => [
        'mode' => 'cms',  // 切换到 CMS 模式
        'cms' => [
            'theme' => 'default',
            'routes' => [
                '/' => 'index',  // 首页路由
            ],
        ],
    ],
];
```

### 2. 创建主题模板

创建 `server/app/Theme/default/index.php`：

```php
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>首页</title>
</head>
<body>
    <h1>欢迎使用 Anon CMS</h1>
    <p>这是首页内容</p>
</body>
</html>
```

### 3. 访问首页

访问 `http://your-domain/` 即可看到 HTML 页面。

更多信息请参考：

- [CMS 模式概述](/guide/cms/overview)
- [主题系统与开发](/guide/cms/theme-system)

## 安装后下一步

- **若选 API 模式**：建议先看 [API 模式概述](/guide/api/overview) → [路由系统](/guide/api/routing) → [API 参考](/api/reference)、[API 端点](/api/endpoints)。  
- **若选 CMS 模式**：建议先看 [CMS 模式概述](/guide/cms/overview) → [路由与页面](/guide/cms/routes) → [主题系统与开发](/guide/cms/theme-system) → [管理后台](/guide/cms/admin)。  

详见 [模式对比 API vs CMS](/guide/mode-overview#安装后下一步)。
