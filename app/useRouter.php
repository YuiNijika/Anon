<?php

/**
 * 手动配置路由
 * 如果 autoRouter 为 true，则不会加载此配置
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'auth' => [
        'login' => [
            'view' => 'Auth/Login',
        ],
        'logout' => [
            'view' => 'Auth/Logout',
        ],
        'check-login' => [
            'view' => 'Auth/CheckLogin',
        ],
        'token' => [
            'view' => 'Auth/Token',
        ],
        'captcha' => [
            'view' => 'Auth/Captcha',
        ],
    ],
    'user' => [
        'info' => [
            'view' => 'User/Info',
        ],
    ],
];
