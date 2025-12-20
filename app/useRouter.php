<?php

/**
 * 手动配置路由
 * 如果 autoRouter 为 true，则不会加载此配置
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'auth' => [
        'login' => 'Auth/Login',
        'logout' => 'Auth/Logout',
        'check-login' => 'Auth/CheckLogin',
        'token' => 'Auth/Token',
        'captcha' => 'Auth/Captcha',
    ],
    'user' => [
        'info' => 'User/Info',
    ],
];
