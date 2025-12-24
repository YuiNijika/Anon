<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'app' => [
        'autoRouter' => true, // 是否启用自动路由
        'avatar' => 'https://www.cravatar.cn/avatar', // 头像源URL
        'debug' => [
            'global' => true, // 全局调试
            'router' => true, // 路由调试
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
        'public' => [
            'enabled' => true, // 是否启用静态文件服务
            'cache' => 31536000, // 缓存时间（秒），0 表示不缓存
            'compress' => true, // 是否启用压缩
            'types' => [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
            ], // MIME 类型配置
        ]
    ]
];