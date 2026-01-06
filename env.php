<?php
/**
 * Anon 环境配置
 * 仅包含数据库配置和安装状态
 * 其他配置请在 app/useApp.php 中设置
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 数据库配置
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'root');
define('ANON_DB_DATABASE', 'anon');
define('ANON_DB_CHARSET', 'utf8mb4');

// APP Key
define('ANON_APP_KEY', 'base64:cC/sE72DUlNLqUIFt1SnXNOQ/AG9Kt20JIjBLYh4THM=');

// 安装状态
define('ANON_INSTALLED', true);
