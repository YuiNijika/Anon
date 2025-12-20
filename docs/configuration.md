# 配置说明

一句话：系统配置在env.php，应用配置在useApp.php，用Anon_Env获取。

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
    ],
];
```

**配置说明：**
- `autoRouter`: 是否启用自动路由模式
  - `true`: 自动扫描 `app/Router` 目录，根据文件结构自动注册路由
  - `false`: 使用 `useRouter.php` 手动配置路由
- `debug.global`: 全局调试模式，启用后会在控制台输出调试信息
- `debug.router`: 路由调试模式，启用后会记录路由注册和执行日志
- `token.enabled`: 是否启用Token验证
- `token.refresh`: 是否在验证后自动刷新Token生成新Token，新Token通过响应头`X-New-Token`返回
- `token.whitelist`: Token验证白名单，这些路由不需要Token验证
- `captcha.enabled`: 是否启用验证码功能
- `public.enabled`: 是否启用public目录静态文件处理
- `public.cache`: 静态文件缓存时间（秒），默认31536000（1年）
- `public.compress`: 是否启用压缩，支持gzip和deflate，默认true
- `public.types`: 自定义MIME类型映射，用于扩展文件类型支持

## 配置访问

### Anon_Env

```php
// 获取配置值
$enabled = Anon_Env::get('app.token.enabled', false);
$host = Anon_Env::get('system.db.host', 'localhost');
$whitelist = Anon_Env::get('app.token.whitelist', []);
```

### Anon_Config

```php
// 添加路由
Anon_Config::addRoute('/api/custom', function() {
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 添加错误处理器
Anon_Config::addErrorHandler(404, function() {
    Anon_ResponseHelper::notFound('页面不存在');
});

// 获取路由配置
$config = Anon_Config::getRouterConfig();
// 返回: ['routes' => [...], 'errorHandlers' => [...]]

// 检查是否已安装
$installed = Anon_Config::isInstalled();
// 返回: bool
```

---

[← 返回文档首页](../README.md)
