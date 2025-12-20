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
        ]
    ],
];