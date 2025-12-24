<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'users' => "CREATE TABLE IF NOT EXISTS `{prefix}users` (
        `uid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '用户 ID',
        `name` VARCHAR(255) NOT NULL UNIQUE COMMENT '用户名',
        `password` VARCHAR(255) NOT NULL COMMENT '密码哈希值',
        `email` VARCHAR(255) NOT NULL UNIQUE COMMENT '邮箱地址',
        `group` VARCHAR(255) NOT NULL DEFAULT 'member' COMMENT '用户组',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表'"
];

