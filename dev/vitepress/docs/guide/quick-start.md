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

如果您需要手动配置，可以编辑 `app/useEnv.php`：

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

编辑 `app/useApp.php`：

```php
return [
    // 生产环境模式开关
    'productionMode' => false,
    
    'app' => [
        'base' => [
            'gravatar' => 'https://www.cravatar.cn/avatar',
            'router' => [
                'mode' => 'auto', // auto or manual
                'prefix' => '/',
                'path' => 'Router', // app/Router
            ],
            'cache' => [
                'enabled' => true,
                'driver' => 'redis', // file, redis, memory
                'time' => 3600,
            ],
            'token' => [
                'enabled' => true,
                'refresh' => false,
                'whitelist' => [
                    '/auth/login',
                    '/auth/logout',
                    '/auth/check-login',
                    '/auth/token',
                    '/auth/captcha'
                ],
            ],
            'captcha' => [
                'enabled' => true,
            ],
            'security' => [
                'csrf' => [
                    'enabled' => true,
                    'stateless' => true,
                ],
            ],
        ],
        'plugins' => [
            'enabled' => false,
            'active' => [],
        ],
        'debug' => [
            'global' => true,
            'router' => true,
            'logDetailedErrors' => false,
        ],
    ]
];
```

## 4. 创建路由

创建 `app/Router/Test/Index.php`：

```php
<?php
use Anon\Modules\Http\ResponseHelper;
use Exception;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    ResponseHelper::success(['message' => 'Anon Tokyo~!']);
} catch (Exception $e) {
    ResponseHelper::handleException($e);
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

在 `app/useEnv.php` 中设置：

```php
define('ANON_APP_MODE', 'cms'); // 将 'api' 改为 'cms'
```

**注意**: CMS 模式需要安装数据库,路由配置存储在数据库 `options` 表中。

### 2. 创建主题模板

创建 `app/Theme/default/index.php`：

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
