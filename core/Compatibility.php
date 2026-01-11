<?php
/**
 * 类别名兼容层
 * 为了向后兼容，将旧类名映射到新类名
 * 
 * 注意：这是临时方案，建议逐步迁移到新类名
 * 未来版本可能会移除此文件
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 自动类别名映射
class_alias('Anon_Auth_Capability', 'Anon_Capability');
class_alias('Anon_Auth_Captcha', 'Anon_Captcha');
class_alias('Anon_Auth_Csrf', 'Anon_Csrf');
class_alias('Anon_Auth_RateLimit', 'Anon_RateLimit');
class_alias('Anon_Auth_Token', 'Anon_Token');
class_alias('Anon_Database_QueryBuilder', 'Anon_QueryBuilder');
class_alias('Anon_Database_QueryOptimizer', 'Anon_QueryOptimizer');
class_alias('Anon_Database_Sharding', 'Anon_Sharding');
class_alias('Anon_Database_SqlConfig', 'Anon_SqlConfig');
class_alias('Anon_Http_Middleware', 'Anon_Middleware');
class_alias('Anon_Http_Request', 'Anon_RequestHelper');
class_alias('Anon_Http_Response', 'Anon_ResponseHelper');
class_alias('Anon_Http_Router', 'Anon_Router');
class_alias('Anon_Security_Security', 'Anon_Security');
class_alias('Anon_System_Cache', 'Anon_FileCache');
class_alias('Anon_System_Config', 'Anon_Config');
class_alias('Anon_System_Console', 'Anon_Console');
class_alias('Anon_System_Container', 'Anon_Container');
class_alias('Anon_System_Env', 'Anon_Env');
class_alias('Anon_System_Exception', 'Anon_Exception');
class_alias('Anon_System_Hook', 'Anon_Hook');
class_alias('Anon_System_Install', 'Anon_Install');
class_alias('Anon_System_Plugin', 'Anon_Plugin');
class_alias('Anon_System_Widget', 'Anon_Widget');
class_alias('Anon_Utils_Sanitize', 'Anon_Security_Sanitize');
