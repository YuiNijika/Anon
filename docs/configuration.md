# 配置说明

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
            'enabled' => true,  // 是否启用 Token 验证
            'refresh' => false, // 是否在验证后自动刷新 Token（生成新 Token）
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],  // Token 验证白名单路由
        ],
        'captcha' => [
            'enabled' => true,  // 是否启用验证码
        ],
    ],
];
```

**配置说明**：

- `autoRouter`: 是否启用自动路由模式
  - `true`: 自动扫描 `app/Router` 目录，根据文件结构自动注册路由
  - `false`: 使用 `useRouter.php` 手动配置路由
- `debug.global`: 全局调试模式，启用后会在控制台输出调试信息
- `debug.router`: 路由调试模式，启用后会记录路由注册和执行日志
- `token.enabled`: 是否启用 Token 验证
- `token.refresh`: 是否在验证后自动刷新 Token（生成新 Token），新 Token 通过响应头 `X-New-Token` 返回
- `token.whitelist`: Token 验证白名单，这些路由不需要 Token 验证
- `captcha.enabled`: 是否启用验证码功能

## 配置访问

```php
// 通过 Anon_Env 获取配置
Anon_Env::get('app.token.enabled', false);
Anon_Env::get('app.captcha.enabled', false);
Anon_Env::get('system.db.host', 'localhost');
```

---

[← 返回文档首页](../README.md)

