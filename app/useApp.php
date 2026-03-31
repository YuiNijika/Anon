<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    // 生产环境模式开关
    'productionMode' => false,
    
    'app' => [
        // API 基础路径
        'baseUrl' => '/',
        // 自动路由开关
        'autoRouter' => true,
        // 默认头像地址
        'avatar' => 'https://www.cravatar.cn/avatar',
        // 缓存配置
        'cache' => [
            'enabled' => true,
            // 驱动类型：file, redis, memory
            'driver' => 'redis',
            // 默认缓存时间（秒）
            'time' => 3600,
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => '',
                'database' => 1,
                'prefix' => 'anon:',
                'timeout' => 2.5,
            ],
            'file' => [
                'path' => __DIR__ . '/../../cache',
            ],
            'exclude' => [
                '/auth/',
                '/anon/debug/',
                '/anon/install',
            ],
        ],
        // 调试配置
        'debug' => [
            'global' => true,
            'router' => true,
            'logDetailedErrors' => false,
        ],
        // Token 配置
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
        // 验证码配置
        'captcha' => [
            'enabled' => true,
        ],
        // 限流配置
        'rateLimit' => [
            // 全局限流配置
            'global' => [
                'enabled' => false,
                // 最大请求次数
                'maxAttempts' => 60,
                // 时间窗口（秒）
                'windowSeconds' => 60,
                // 限制的 HTTP 方法
                'methods' => ['POST', 'PUT', 'DELETE'],
            ],
            // 注册场景限流
            'register' => [
                'ip' => [
                    'enabled' => false,
                    'maxAttempts' => 5,
                    'windowSeconds' => 3600,
                ],
                'device' => [
                    'enabled' => false,
                    'maxAttempts' => 3,
                    'windowSeconds' => 3600,
                ],
            ],
        ],
        // 安全配置
        'security' => [
            // CSRF 防护
            'csrf' => [
                'enabled' => true,
                'stateless' => true,
            ],
            // XSS 防护
            'xss' => [
                'enabled' => true,
                // 防护模式：escape（转义）, strip（过滤 HTML）, purify（净化，需 HTMLPurifier）
                'mode' => 'escape',
                // 自动转义响应输出
                'autoApplyToResponse' => false,
                // 是否移除 HTML 标签
                'stripHtml' => false,
                // 跳过验证的字段
                'skipFields' => ['password', 'token', 'csrf_token'],
            ],
            // CSP 内容安全策略
            'csp' => [
                'enabled' => false,
                // 策略规则
                'policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
                // 是否仅报告不拦截
                'reportOnly' => false,
                // 报告地址
                'reportUri' => '/anon/security/csp-report',
            ],
            // SQL 注入防护
            'sql' => [
                'validateInDebug' => true,
                // 强制使用预处理语句
                'forcePrepareAlways' => false,
                // 记录原始 SQL
                'logRawSql' => true,
                // 禁止的 SQL 关键字
                'forbiddenKeywords' => ['DROP', 'TRUNCATE', 'ALTER TABLE', 'CREATE TABLE'],
            ],
            // 密码策略
            'password' => [
                // 最小长度
                'minLength' => 8,
                // 最大长度
                'maxLength' => 128,
                // 要求大写字母
                'requireUppercase' => false,
                // 要求小写字母
                'requireLowercase' => false,
                // 要求数字
                'requireDigit' => false,
                // 要求特殊字符
                'requireSpecial' => false,
            ],
            // CORS 跨域配置
            'cors' => [
                'enabled' => true,
                // 允许的域名列表
                'origins' => [],
                // 允许的 HTTP 方法
                'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                // 允许的请求头
                'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                // 暴露的响应头
                'exposedHeaders' => [],
                // 预检请求缓存时间（秒）
                'maxAge' => 3600,
                // 是否支持凭证
                'supportsCredentials' => true,
            ],
        ],
        'plugins' => [
            'enabled' => false,
            'active' => [],
        ],
    ]
];
