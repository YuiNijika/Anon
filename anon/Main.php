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
        
        $envConfig = require $Root . 'env.php';
        require_once $Modules . 'Env.php';
        Anon_Env::init($envConfig);
        require_once $Modules . 'Config.php';
        require_once $Modules  . 'Common.php';
        Anon_Common::defineConstantsFromEnv();
        
        require_once $Widget  . 'Connection.php';
        require_once $Modules  . 'Database.php';
        require_once $Modules  . '../Install/Install.php';
        
        require_once $Modules  . 'ResponseHelper.php';
        require_once $Modules  . 'RequestHelper.php';
        require_once $Modules . 'Token.php';
        require_once $Modules . 'Debug.php';
        require_once $Modules . 'Hook.php';
        
        Anon_Debug::init();
        Anon_Config::initSystemRoutes();
        Anon_Config::initAppRoutes();
        
        $codeFile = $App . 'Code.php';
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
