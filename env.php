<?php
/**
 * Anon环境配置
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

// 安装状态
define('ANON_INSTALLED', true);
