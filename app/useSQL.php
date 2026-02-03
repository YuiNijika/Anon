<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    /**
     * 全局表
     */
    // 用户表
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
    
    // 登录记录表
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户登录记录表'",
    
    /**
     * API 模式专用表
     */
    'api' => [
        // 在这里添加 API 模式专用的数据表
        // 'api_tokens' => "CREATE TABLE IF NOT EXISTS `{prefix}api_tokens` ...",
    ],
    
    /**
     * CMS 模式专用表
     */
    'cms' => [
        // 文章表
        'posts' => "CREATE TABLE IF NOT EXISTS `{prefix}posts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '内容 ID',
            `type` VARCHAR(20) NOT NULL DEFAULT 'post' COMMENT '类型：post=文章，page=页面',
            `title` VARCHAR(255) NOT NULL COMMENT '标题',
            `slug` VARCHAR(255) NOT NULL COMMENT '别名',
            `content` LONGTEXT NULL DEFAULT NULL COMMENT '内容',
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT '状态：draft=草稿，publish=已发布，private=私有',
            `author_id` INT UNSIGNED NOT NULL COMMENT '作者 ID',
            `category_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '分类 ID',
            `tag_ids` TEXT NULL DEFAULT NULL COMMENT '标签 ID 数组（JSON格式）',
            `views` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览量',
            `comment_status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT '评论状态：open=开放，closed=关闭',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            INDEX `idx_type` (`type`),
            INDEX `idx_status` (`status`),
            INDEX `idx_author_id` (`author_id`),
            INDEX `idx_category_id` (`category_id`),
            INDEX `idx_slug` (`slug`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_type_status_created` (`type`, `status`, `created_at`),
            UNIQUE KEY `uk_slug_type` (`slug`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容表（文章和页面）'",
        
        // 评论表
        'comments' => "CREATE TABLE IF NOT EXISTS `{prefix}comments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '评论 ID',
            `post_id` INT UNSIGNED NOT NULL COMMENT '内容 ID',
            `parent_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '父评论 ID',
            `uid` INT UNSIGNED NULL DEFAULT NULL COMMENT '用户 ID（登录用户）',
            `type` VARCHAR(20) NOT NULL DEFAULT 'guest' COMMENT 'user=登录用户，guest=未登录',
            `name` VARCHAR(255) NULL DEFAULT NULL COMMENT '评论者名称（仅 guest 写入）',
            `email` VARCHAR(255) NULL DEFAULT NULL COMMENT '评论者邮箱（仅 guest 写入）',
            `url` VARCHAR(500) NULL DEFAULT NULL COMMENT '评论者网址',
            `ip` VARCHAR(45) NOT NULL COMMENT 'IP 地址',
            `user_agent` TEXT NULL DEFAULT NULL COMMENT 'User-Agent',
            `content` TEXT NOT NULL COMMENT '评论内容',
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending=待审核，approved=已通过，spam=垃圾，trash=已删除',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            INDEX `idx_post_id` (`post_id`),
            INDEX `idx_parent_id` (`parent_id`),
            INDEX `idx_uid` (`uid`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表'",
        
        // 附件表
        'attachments' => "CREATE TABLE IF NOT EXISTS `{prefix}attachments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '附件 ID',
            `uid` INT UNSIGNED NOT NULL COMMENT '上传用户 ID',
            `filename` VARCHAR(255) NOT NULL COMMENT '文件名',
            `original_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
            `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
            INDEX `idx_uid` (`uid`),
            INDEX `idx_updated_at` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='附件表'",
        
        // 选项表
        'options' => "CREATE TABLE IF NOT EXISTS `{prefix}options` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '选项 ID',
            `name` VARCHAR(255) NOT NULL UNIQUE COMMENT '选项名称',
            `value` LONGTEXT NULL DEFAULT NULL COMMENT '选项值',
            INDEX `idx_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='选项表'",
        
        // 元数据表
        'metas' => "CREATE TABLE IF NOT EXISTS `{prefix}metas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '元数据 ID',
            `name` VARCHAR(255) NOT NULL COMMENT '名称',
            `slug` VARCHAR(255) NOT NULL COMMENT '别名',
            `description` TEXT NULL DEFAULT NULL COMMENT '描述',
            `type` VARCHAR(20) NOT NULL COMMENT '类型：category=分类，tag=标签',
            `parent_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '父级 ID',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            INDEX `idx_type` (`type`),
            INDEX `idx_parent_id` (`parent_id`),
            INDEX `idx_slug` (`slug`),
            UNIQUE KEY `uk_slug_type` (`slug`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='元数据表（分类和标签）'",
        
        // 访问日志表
        'access_logs' => "CREATE TABLE IF NOT EXISTS `{prefix}access_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '日志 ID',
            `url` VARCHAR(500) NOT NULL COMMENT '访问 URL',
            `path` VARCHAR(500) NOT NULL COMMENT '访问路径',
            `method` VARCHAR(10) NOT NULL DEFAULT 'GET' COMMENT '请求方法',
            `type` VARCHAR(20) NOT NULL DEFAULT 'page' COMMENT '请求类型：api=API请求，page=页面请求，static=静态资源',
            `ip` VARCHAR(45) NOT NULL COMMENT 'IP 地址',
            `user_agent` TEXT NULL DEFAULT NULL COMMENT 'User-Agent',
            `referer` VARCHAR(500) NULL DEFAULT NULL COMMENT '来源页面',
            `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 200 COMMENT 'HTTP 状态码',
            `response_time` INT UNSIGNED NULL DEFAULT NULL COMMENT '响应时间（毫秒）',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '访问时间',
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_path_created` (`path`(100), `created_at`),
            INDEX `idx_ip_created` (`ip`, `created_at`),
            INDEX `idx_status_created` (`status_code`, `created_at`),
            INDEX `idx_type_created` (`type`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问日志表'",
    ],
];

