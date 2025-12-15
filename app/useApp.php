<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'app' => [
        'debug' => [
            'global' => true, // 全局调试
            'router' => false, // 路由调试
        ],
        'avatar' => 'https://www.cravatar.cn/avatar', // 头像源URL
        'token' => [
            'enabled' => true, // 是否启用 Token 验证
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
    ],
];