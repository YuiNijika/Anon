<?php
/**
 * Anon环境配置
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 数据库配置
define('ANON_DB_HOST', 'localhost'); // 数据库主机
define('ANON_DB_PORT', 3306); // 数据库端口
define('ANON_DB_PREFIX', 'anon_'); // 数据库表前缀
define('ANON_DB_USER', 'root'); // 数据库用户
define('ANON_DB_PASSWORD', 'root'); // 数据库密码
define('ANON_DB_DATABASE', 'anon'); // 数据库名称
define('ANON_DB_CHARSET', 'utf8mb4'); // 数据库字符集

// 安装状态
define('ANON_INSTALLED', true);

return [
    'app' => [
        'debug' => [
            'global' => false, // 全局调试
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