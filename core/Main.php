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
        
        // 核心模块 - 必须立即加载
        require_once self::MODULES_DIR . 'Env.php';
        Anon_Env::init($envConfig);
        require_once self::MODULES_DIR . 'Config.php';
        require_once self::MODULES_DIR . 'Common.php';
        Anon_Common::defineConstantsFromEnv();
        
        // 工具类 - 按需加载
        require_once self::WIDGETS_DIR . 'Connection.php';
        require_once self::WIDGETS_DIR . 'Utils/Escape.php';
        require_once self::WIDGETS_DIR . 'Utils/Sanitize.php';
        require_once self::WIDGETS_DIR . 'Utils/Validate.php';
        require_once self::WIDGETS_DIR . 'Utils/Text.php';
        require_once self::WIDGETS_DIR . 'Utils/Format.php';
        require_once self::WIDGETS_DIR . 'Utils/Array.php';
        require_once self::WIDGETS_DIR . 'Utils/Random.php';
        
        // 核心功能模块 - 延迟加载
        require_once self::MODULES_DIR . 'Database.php';
        require_once self::MODULES_DIR . 'Install.php';
        require_once self::MODULES_DIR . 'ResponseHelper.php';
        require_once self::MODULES_DIR . 'RequestHelper.php';
        require_once self::MODULES_DIR . 'Hook.php';
        require_once self::MODULES_DIR . 'Helper.php';
        require_once self::MODULES_DIR . 'Widget.php';
        require_once self::MODULES_DIR . 'Container.php';
        require_once self::MODULES_DIR . 'Middleware.php';
        require_once self::MODULES_DIR . 'Cache.php';
        require_once self::MODULES_DIR . 'QueryBuilder.php';
        require_once self::MODULES_DIR . 'QueryOptimizer.php';
        require_once self::MODULES_DIR . 'Sharding.php';
        
        // 可选功能模块 - 按需加载
        require_once self::MODULES_DIR . 'Token.php';
        require_once self::MODULES_DIR . 'Captcha.php';
        require_once self::MODULES_DIR . 'RateLimit.php';
        require_once self::MODULES_DIR . 'Csrf.php';
        require_once self::MODULES_DIR . 'Security.php';
        require_once self::MODULES_DIR . 'Capability.php';
        require_once self::MODULES_DIR . 'Console.php';
        
        // Debug 模块 - 仅在启用时初始化
        require_once self::MODULES_DIR . 'Debug.php';
        if (defined('ANON_DEBUG') && Anon_Debug::isEnabled()) {
            Anon_Debug::init();
        }
        
        // 能力系统 - 延迟初始化
        Anon_Capability::getInstance()->init();
        
        // 路由初始化 - 延迟到 Router 模块加载时
        Anon_Config::initSystemRoutes();
        Anon_Config::initAppRoutes();
        
        // 先加载快捷方法封装类
        require_once self::MODULES_DIR . 'Anon.php';
        
        // 加载插件系统
        require_once self::MODULES_DIR . 'Plugin.php';
        Anon_Plugin::init();
        
        $codeFile = self::APP_DIR . 'useCode.php';
        if (file_exists($codeFile)) {
            require_once $codeFile;
        }
        
        require_once self::MODULES_DIR . 'Router.php';
        
        Anon_Debug::info('Application started', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
    }
}

Anon_Main::run();
