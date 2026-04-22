# 配置说明

本节说明系统配置、应用配置与SQL配置的存放位置与常用项,适用于通用场景。

系统配置在 `app/useEnv.php`,应用配置在 `app/useApp.php`,SQL表结构在 `app/useSQL.php`。运行时可用 `Anon\Modules\System\Env` 读取配置。

## 系统配置 (useEnv.php)

系统配置文件位于 `app/useEnv.php`，通常通过安装向导自动生成：

```php
define('ANON_APP_MODE', 'api');  // 或 'cms'，安装时选择
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'password');
define('ANON_DB_DATABASE', 'database_name');
define('ANON_DB_CHARSET', 'utf8mb4');
define('ANON_INSTALLED', true);
define('ANON_APP_KEY', 'base64:随机生成的密钥');
```

**注意**：建议使用安装向导进行配置，手动编辑可能导致配置错误。

## 应用配置 (useApp.php)

```php
return [
    'app' => [
        'base' => [
            'router' => [
                'mode' => 'auto', // auto | manual
            ],
            'debug' => [
                'global' => false,
                'router' => false,
            ],
            'avatar' => 'https://www.cravatar.cn/avatar',
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
            'cache' => [
                'enabled' => true,
                'time' => 3600,
                'exclude' => [
                '/auth/',           // 所有认证相关接口
                '/anon/debug/',     // Debug 接口
                '/anon/install',    // 安装接口
                ],
            ],
            'security' => [
                'csrf' => [
                    'enabled' => true,
                    'stateless' => true,
                ],
                'xss' => [
                    'enabled' => true,
                    'stripHtml' => true,
                    'skipFields' => ['password', 'token', 'csrf_token'],
                ],
                'sql' => [
                    'validateInDebug' => true,
                ],
            ],
            'rateLimit' => [
                'register' => [
                    'ip' => [
                        'enabled' => true,
                        'maxAttempts' => 5,
                        'windowSeconds' => 3600,
                    ],
                    'device' => [
                        'enabled' => true,
                        'maxAttempts' => 3,
                        'windowSeconds' => 3600,
                    ],
                ],
            ],
        ],
    ],
];
```

### CMS 模式配置说明

CMS 模式的配置存储在数据库 `options` 表中，安装时会自动初始化。可以通过 `Anon\Modules\Cms\Options` 访问：

```php
use Anon\Modules\Cms\Options;

// 获取主题名称
$theme = Options::get('theme', 'Default');

// 获取 API 前缀
$apiPrefix = Options::get('apiPrefix', '/api');

// 获取路由配置
$routes = json_decode(Options::get('routes', '[]'), true);

// 设置配置
Options::set('theme', 'MyTheme');
```

**CMS 配置项：**

- `theme`: 主题名称，对应 `app/Theme/{themeName}/` 目录
- `apiPrefix`: API 路由前缀，默认为 `/api`
- `routes`: CMS 路由配置，JSON 格式
- `title`: 网站标题
- `description`: 网站描述
- `keywords`: 网站关键词

**路由配置格式：**

```json
{
  "/post/{id}": "post",
  "/page/{slug}": "page"
}
```

更多信息请参考 [CMS 模式文档](/guide/cms/overview) 和 [主题系统文档](/guide/cms/theme-system)。

## SQL 安装配置 (useSQL.php)

安装时使用的 SQL 语句配置，用于创建数据库表结构。

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'users' => "CREATE TABLE IF NOT EXISTS `{prefix}users` (
        `uid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '用户 ID',
        `name` VARCHAR(255) NOT NULL UNIQUE COMMENT '用户名',
        `password` VARCHAR(255) NOT NULL COMMENT '密码哈希值',
        `email` VARCHAR(255) NOT NULL UNIQUE COMMENT '邮箱地址',
        `group` VARCHAR(255) NOT NULL DEFAULT 'user' COMMENT '用户组',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表'"
];
```

使用 `{prefix}` 作为表前缀占位符，安装时会自动替换为 `app/useEnv.php` 中配置的表前缀。

- 可以添加多个表的 SQL 语句，安装系统会自动执行所有 SQL
- 每个表的 SQL 语句必须是完整的 CREATE TABLE 语句

### 用户组说明

- `admin`：管理员
- `editor`：编辑
- `user`：普通用户

说明：管理后台对外显示用户组为 `admin/editor/user`。数据库内部若存在历史值 `author`，会映射为 `editor` 展示。

## 配置访问

### Env

```php
use Anon\Modules\System\Env;

// 获取配置值，自动缓存，首次解析后存入内存
$enabled = Env::get('app.base.token.enabled', false);
$host = Env::get('system.db.host', 'localhost');
$whitelist = Env::get('app.base.token.whitelist', []);

// 清除配置缓存
Env::clearCache();
```

**性能优化：** 配置值会自动缓存，首次解析后存入内存，后续调用直接读取缓存，提升性能。

### Config

```php
use Anon\Modules\System\Config;
use Anon\Modules\Http\ResponseHelper;

// 添加路由
Config::addRoute('/api/custom', function () {
    ResponseHelper::success(['message' => '自定义路由']);
});

// 添加错误处理器
Config::addErrorHandler(404, function () {
    ResponseHelper::notFound('页面不存在');
});

// 获取路由配置
$config = Config::getRouterConfig();
// 返回: ['routes' => [...], 'errorHandlers' => [...]]

// 检查是否已安装
$installed = Config::isInstalled();
// 返回: bool
```
