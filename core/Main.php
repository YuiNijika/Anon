<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

if (version_compare(phpversion(), '7.4.0', '<')) {
    die('杂鱼~ Anon Framework 需要 PHP 7.4.0及以上版本~');
    exit;
}

class Anon_Main
{
    const APP_DIR = __DIR__ . '/../app/';
    const WIDGETS_DIR = __DIR__ . '/Widgets/';
    const MODULES_DIR = __DIR__ . '/Modules/';
    const ROOT_DIR = __DIR__ . '/../';

    /**
     * 启动应用
     */
    public static function run()
    {
        
        require_once self::ROOT_DIR . 'env.php';
        
        $appConfigFile = self::APP_DIR . 'useApp.php';
        $appConfig = file_exists($appConfigFile) ? require $appConfigFile : [];
        
        $envConfig = [
            'system' => [
                'db' => [
                    'host' => defined('ANON_DB_HOST') ? ANON_DB_HOST : 'localhost',
                    'port' => defined('ANON_DB_PORT') ? ANON_DB_PORT : 3306,
                    'prefix' => defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '',
                    'user' => defined('ANON_DB_USER') ? ANON_DB_USER : 'root',
                    'password' => defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : '',
                    'database' => defined('ANON_DB_DATABASE') ? ANON_DB_DATABASE : '',
                    'charset' => defined('ANON_DB_CHARSET') ? ANON_DB_CHARSET : 'utf8mb4',
                ],
                'installed' => defined('ANON_INSTALLED') ? ANON_INSTALLED : false,
            ],
        ];
        $envConfig = array_merge_recursive($envConfig, $appConfig);
        
        // 立即加载核心模块
        require_once self::MODULES_DIR . 'System/Env.php';
        Anon_System_Env::init($envConfig);
        require_once self::MODULES_DIR . 'System/Config.php';
        require_once self::MODULES_DIR . 'Database/SqlConfig.php';
        require_once self::MODULES_DIR . 'Common.php';
        Anon_Common::defineConstantsFromEnv();
        
        // 按需加载工具类
        require_once self::WIDGETS_DIR . 'Connection.php';
        require_once self::WIDGETS_DIR . 'Utils/Escape.php';
        require_once self::WIDGETS_DIR . 'Utils/Sanitize.php';
        require_once self::WIDGETS_DIR . 'Utils/Validate.php';
        require_once self::WIDGETS_DIR . 'Utils/Text.php';
        require_once self::WIDGETS_DIR . 'Utils/Format.php';
        require_once self::WIDGETS_DIR . 'Utils/Array.php';
        require_once self::WIDGETS_DIR . 'Utils/Random.php';
        
        // 按需加载核心功能模块
        require_once self::MODULES_DIR . 'System/Exception.php';
        require_once self::MODULES_DIR . 'Database.php';
        require_once self::MODULES_DIR . 'System/Install.php';
        require_once self::MODULES_DIR . 'Http/ResponseHelper.php';
        require_once self::MODULES_DIR . 'Http/RequestHelper.php';
        require_once self::MODULES_DIR . 'System/Hook.php';
        require_once self::MODULES_DIR . 'Helper.php';
        require_once self::MODULES_DIR . 'System/Widget.php';
        require_once self::MODULES_DIR . 'System/Container.php';
        require_once self::MODULES_DIR . 'Http/Middleware.php';
        require_once self::MODULES_DIR . 'System/Cache.php';
        require_once self::MODULES_DIR . 'Database/QueryBuilder.php';
        require_once self::MODULES_DIR . 'Database/QueryOptimizer.php';
        require_once self::MODULES_DIR . 'Database/Sharding.php';
        
        // 按需加载可选功能模块
        require_once self::MODULES_DIR . 'Auth/Token.php';
        require_once self::MODULES_DIR . 'Auth/Captcha.php';
        require_once self::MODULES_DIR . 'Auth/RateLimit.php';
        require_once self::MODULES_DIR . 'Auth/Csrf.php';
        require_once self::MODULES_DIR . 'Security/Security.php';
        require_once self::MODULES_DIR . 'Auth/Capability.php';
        require_once self::MODULES_DIR . 'System/Console.php';
        
        // 按需加载 Debug 模块
        require_once self::MODULES_DIR . 'Debug.php';
        if (defined('ANON_DEBUG') && Anon_Debug::isEnabled()) {
            Anon_Debug::init();
        }
        
        // 按需加载能力系统
        Anon_Auth_Capability::getInstance()->init();
        
        // 按需加载路由
        Anon_System_Config::initSystemRoutes();
        Anon_System_Config::initAppRoutes();
        
        // 按需加载快捷方法封装类
        require_once self::MODULES_DIR . 'Anon.php';
        
        // 按需加载插件系统
        require_once self::MODULES_DIR . 'System/Plugin.php';
        Anon_System_Plugin::init();
        
        $codeFile = self::APP_DIR . 'useCode.php';
        if (file_exists($codeFile)) {
            require_once $codeFile;
        }
        
        require_once self::MODULES_DIR . 'Http/Router.php';
        
        // 加载兼容层
        require_once self::MODULES_DIR . '/../Compatibility.php';
        
        Anon_Debug::info('Application started', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
    }
}

Anon_Main::run();
