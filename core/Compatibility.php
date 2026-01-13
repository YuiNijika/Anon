<?php
/**
 * 类别名兼容层
 * 为了向后兼容，将旧类名映射到新类名
 * 
 * 注意：这是临时方案，建议逐步迁移到新类名
 * 未来版本可能会移除此文件
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

if (class_exists('Anon_Auth_Capability')) {
    class_alias('Anon_Auth_Capability', 'Anon_Capability');
}
if (class_exists('Anon_Auth_Captcha')) {
    class_alias('Anon_Auth_Captcha', 'Anon_Captcha');
}
if (class_exists('Anon_Auth_Csrf')) {
    class_alias('Anon_Auth_Csrf', 'Anon_Csrf');
}
if (class_exists('Anon_Auth_RateLimit')) {
    class_alias('Anon_Auth_RateLimit', 'Anon_RateLimit');
}
if (class_exists('Anon_Auth_Token')) {
    class_alias('Anon_Auth_Token', 'Anon_Token');
}
if (class_exists('Anon_Database_QueryBuilder')) {
    class_alias('Anon_Database_QueryBuilder', 'Anon_QueryBuilder');
}
if (class_exists('Anon_Database_QueryOptimizer')) {
    class_alias('Anon_Database_QueryOptimizer', 'Anon_QueryOptimizer');
}
if (class_exists('Anon_Database_Sharding')) {
    class_alias('Anon_Database_Sharding', 'Anon_Sharding');
}
if (class_exists('Anon_Database_SqlConfig')) {
    class_alias('Anon_Database_SqlConfig', 'Anon_SqlConfig');
}
if (class_exists('Anon_Http_Middleware')) {
    class_alias('Anon_Http_Middleware', 'Anon_Middleware');
}
if (class_exists('Anon_Http_Request')) {
    class_alias('Anon_Http_Request', 'Anon_RequestHelper');
}
if (class_exists('Anon_Http_Response')) {
    class_alias('Anon_Http_Response', 'Anon_ResponseHelper');
}
if (class_exists('Anon_Http_Router')) {
    class_alias('Anon_Http_Router', 'Anon_Router');
}
if (class_exists('Anon_Security_Security')) {
    class_alias('Anon_Security_Security', 'Anon_Security');
}
if (class_exists('Anon_System_Cache')) {
    class_alias('Anon_System_Cache', 'Anon_FileCache');
}
if (class_exists('Anon_System_Config')) {
    class_alias('Anon_System_Config', 'Anon_Config');
}
if (class_exists('Anon_System_Console')) {
    class_alias('Anon_System_Console', 'Anon_Console');
}
if (class_exists('Anon_System_Container')) {
    class_alias('Anon_System_Container', 'Anon_Container');
}
if (class_exists('Anon_System_Env')) {
    class_alias('Anon_System_Env', 'Anon_Env');
}
if (class_exists('Anon_System_Exception')) {
    class_alias('Anon_System_Exception', 'Anon_Exception');
}
if (class_exists('Anon_System_Hook')) {
    class_alias('Anon_System_Hook', 'Anon_Hook');
}
if (class_exists('Anon_System_Install')) {
    class_alias('Anon_System_Install', 'Anon_Install');
}
if (class_exists('Anon_System_Plugin')) {
    class_alias('Anon_System_Plugin', 'Anon_Plugin');
}
if (class_exists('Anon_System_Widget')) {
    class_alias('Anon_System_Widget', 'Anon_Widget');
}
if (class_exists('Anon_Utils_Sanitize')) {
    class_alias('Anon_Utils_Sanitize', 'Anon_Security_Sanitize');
}
if (class_exists('Anon_Cms_Theme')) {
    class_alias('Anon_Cms_Theme', 'Anon_View_Theme');
}
