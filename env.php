<?php
/**
 * Anon环境配置
 */
return [
    'system' => [
        'db' => [
            'host' => 'localhost', // 数据库主机    
            'port' => 3306, // 数据库端口
            'prefix' => 'puxt_', // 数据库表前缀
            'user' => 'root', // 数据库用户
            'password' => 'root', // 数据库密码
            'database' => 'puxt', // 数据库名称
            'charset' => 'utf8mb4', // 数据库字符集
        ],
        'installed' => true, // 是否安装程序
    ],
    'app' => [
        'debug' => [
            'global' => false, // 全局调试
            'router' => true, // 路由调试
        ],
    ],
];