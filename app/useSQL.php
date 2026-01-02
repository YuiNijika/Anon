<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'users' => "CREATE TABLE IF NOT EXISTS `{prefix}users` (
        `uid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '用户 ID',
        `name` VARCHAR(255) NOT NULL UNIQUE COMMENT '用户名',
        `display_name` VARCHAR(255) NULL DEFAULT NULL COMMENT '显示名字',
        `password` VARCHAR(255) NOT NULL COMMENT '密码哈希值',
        `email` VARCHAR(255) NOT NULL UNIQUE COMMENT '邮箱地址',
        `avatar` VARCHAR(500) NULL DEFAULT NULL COMMENT '头像URL',
        `group` VARCHAR(255) NOT NULL DEFAULT 'member' COMMENT '用户组',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表'",
    'login_logs' => "CREATE TABLE IF NOT EXISTS `{prefix}login_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '记录 ID',
        `uid` INT UNSIGNED NULL DEFAULT NULL COMMENT '用户 ID',
        `username` VARCHAR(255) NULL DEFAULT NULL COMMENT '用户名',
        `ip` VARCHAR(45) NOT NULL COMMENT '登录 IP',
        `domain` VARCHAR(255) NULL DEFAULT NULL COMMENT '登录域名',
        `user_agent` TEXT NULL DEFAULT NULL COMMENT 'User-Agent',
        `status` TINYINT NOT NULL DEFAULT 1 COMMENT '登录状态：1=成功，0=失败',
        `message` VARCHAR(255) NULL DEFAULT NULL COMMENT '登录信息',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
        INDEX `idx_uid` (`uid`),
        INDEX `idx_username` (`username`),
        INDEX `idx_ip` (`ip`),
        INDEX `idx_domain` (`domain`),
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户登录记录表'"
];

