<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Main
{
    /**
     * 启动应用
     */
    public static function run()
    {
        $App  = __DIR__ . '/../app/';
        $Widget = __DIR__ . '/Widget/';
        $Modules = __DIR__ . '/Modules/';
        $Root = __DIR__ . '/../';
        
        require_once $Root . 'env.php';
        $appConfigFile = $App . 'useApp.php';
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
        
        require_once $Modules . 'Env.php';
        Anon_Env::init($envConfig);
        require_once $Modules . 'Config.php';
        require_once $Modules  . 'Common.php';
        Anon_Common::defineConstantsFromEnv();
        
        require_once $Widget  . 'Connection.php';
        require_once $Widget  . 'Utils/Escape.php';
        require_once $Widget  . 'Utils/Sanitize.php';
        require_once $Widget  . 'Utils/Validate.php';
        require_once $Widget  . 'Utils/Text.php';
        require_once $Widget  . 'Utils/Format.php';
        require_once $Widget  . 'Utils/Array.php';
        require_once $Widget  . 'Utils/Random.php';
        require_once $Modules  . 'Database.php';
        require_once $Modules  . '../Install/Install.php';
        
        require_once $Modules  . 'ResponseHelper.php';
        require_once $Modules  . 'RequestHelper.php';
        require_once $Modules . 'Token.php';
        require_once $Modules . 'Captcha.php';
        require_once $Modules . 'Debug.php';
        require_once $Modules . 'Hook.php';
        require_once $Modules . 'Helper.php';
        require_once $Modules . 'Widget.php';
        require_once $Modules . 'Capability.php';
        
        Anon_Debug::init();
        Anon_Capability::getInstance()->init();
        Anon_Config::initSystemRoutes();
        Anon_Config::initAppRoutes();
        
        $codeFile = $App . 'useCode.php';
        if (file_exists($codeFile)) {
            require_once $codeFile;
        }
        
        require_once $Modules . 'Router.php';
        
        Anon_Debug::info('Application started', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
    }
}

// 启动应用
Anon_Main::run();
