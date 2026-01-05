# 配置说明

一句话：系统配置在env.php，应用配置在useApp.php，SQL配置在useSQL.php，用Anon_Env获取。

## 系统配置 (env.php)

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

## 应用配置 (useApp.php)

```php
return [
    'app' => [
        'autoRouter' => true,  // 是否启用自动路由（推荐）
        'debug' => [
            'global' => false,  // 全局调试
            'router' => false,  // 路由调试
        ],
        'avatar' => 'https://www.cravatar.cn/avatar',  // 头像源URL
        'token' => [
            'enabled' => true,  // 是否启用Token验证
            'refresh' => false, // 是否在验证后自动刷新Token生成新Token
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],  // Token验证白名单路由
        ],
        'captcha' => [
            'enabled' => true,  // 是否启用验证码
        ],
        'cache' => [
            'enabled' => true,  // 是否启用全局缓存
            'time' => 3600,     // 全局缓存时间（秒）
            'exclude' => [
                '/auth/',           // 所有认证相关接口
                '/anon/debug/',     // Debug 接口
                '/anon/install',    // 安装接口
            ], // 自动排除缓存的路径模式
        ],
        'security' => [
            'csrf' => [
                'enabled' => true,      // 是否启用 CSRF 防护
                'stateless' => true,    // 是否使用无状态 Token（推荐）
            ],
            'xss' => [
                'enabled' => true,      // 是否启用 XSS 自动过滤
                'stripHtml' => true,    // 是否移除 HTML 标签
                'skipFields' => ['password', 'token', 'csrf_token'], // 跳过的字段
            ],
            'sql' => [
                'validateInDebug' => true, // 在调试模式下验证 SQL 查询安全性
            ],
        ],
        'rateLimit' => [
            'register' => [
                'ip' => [
                    'enabled' => true,        // 是否启用IP限制
                    'maxAttempts' => 5,        // 每小时最大注册次数
                    'windowSeconds' => 3600,   // 时间窗口（秒）
                ],
                'device' => [
                    'enabled' => true,         // 是否启用设备指纹限制
                    'maxAttempts' => 3,        // 每小时最大注册次数
                    'windowSeconds' => 3600,   // 时间窗口（秒）
                ],
            ],
        ],
    ],
];
```


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
        `group` VARCHAR(255) NOT NULL DEFAULT 'member' COMMENT '用户组',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表'"
];
```

使用 `{prefix}` 作为表前缀占位符，安装时会自动替换为 `env.php` 中配置的表前缀。
- 可以添加多个表的 SQL 语句，安装系统会自动执行所有 SQL
- 每个表的 SQL 语句必须是完整的 CREATE TABLE 语句

## 配置访问

### Anon_System_Env

```php
// 获取配置值（自动缓存，首次解析后存入内存）
$enabled = Anon_System_Env::get('app.token.enabled', false);
$host = Anon_System_Env::get('system.db.host', 'localhost');
$whitelist = Anon_System_Env::get('app.token.whitelist', []);

// 清除配置缓存
Anon_System_Env::clearCache();
```

**性能优化：** 配置值会自动缓存，首次解析后存入内存，后续调用直接读取缓存，提升性能。

### Anon_System_Config

```php
// 添加路由
Anon_System_Config::addRoute('/api/custom', function() {
    Anon_Http_Response::success(['message' => '自定义路由']);
});

// 添加错误处理器
Anon_System_Config::addErrorHandler(404, function() {
    Anon_Http_Response::notFound('页面不存在');
});

// 获取路由配置
$config = Anon_System_Config::getRouterConfig();
// 返回: ['routes' => [...], 'errorHandlers' => [...]]

// 检查是否已安装
$installed = Anon_System_Config::isInstalled();
// 返回: bool
```

