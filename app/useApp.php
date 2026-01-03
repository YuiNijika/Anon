<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'app' => [
        'autoRouter' => true, // 是否启用自动路由
        'avatar' => 'https://www.cravatar.cn/avatar', // 头像源URL
        'debug' => [
            'global' => true, // 全局调试
            'router' => true, // 路由调试
            'cache' => [
                'enabled' => false, // 是否启用缓存
                'time' => 0, // 缓存时间（秒），0 表示不缓存，默认 0
            ],
        ],
        'token' => [
            'enabled' => true, // 是否启用 Token 验证
            'refresh' => false, // 是否在验证后自动刷新生成新 Token
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ], // Token 验证白名单路由
        ],
        'captcha' => [
            'enabled' => true, // 是否启用验证码
        ],
        'rateLimit' => [
            'register' => [
                'ip' => [
                    'enabled' => true, // 是否启用IP限制
                    'maxAttempts' => 5, // 每小时最大注册次数
                    'windowSeconds' => 3600, // 时间窗口秒数
                ],
                'device' => [
                    'enabled' => true, // 是否启用设备指纹限制
                    'maxAttempts' => 3, // 每小时最大注册次数
                    'windowSeconds' => 3600, // 时间窗口秒数
                ],
            ],
        ],
        'security' => [
            'csrf' => [
                'enabled' => true, // 是否启用 CSRF 防护
            ],
            'xss' => [
                'enabled' => true, // 是否启用 XSS 自动过滤
                'stripHtml' => true, // 是否移除 HTML 标签
                'skipFields' => ['password', 'token', 'csrf_token'], // 跳过的字段（不进行过滤）
            ],
            'sql' => [
                'validateInDebug' => true, // 在调试模式下验证 SQL 查询安全性
            ],
        ]
    ]
];