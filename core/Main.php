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
     * 初始化框架环境
     * @return void
     */
    public static function init()
    {
        require_once self::ROOT_DIR . '.env.php';
        
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
        require_once self::MODULES_DIR . 'System/Env.php';
        Anon_System_Env::init($envConfig);
        require_once self::MODULES_DIR . 'System/Config.php';
        require_once self::MODULES_DIR . 'Database/SqlConfig.php';
        require_once self::MODULES_DIR . 'Common.php';
        Anon_Common::defineConstantsFromEnv();
        
        require_once __DIR__ . '/Loader.php';
        Anon_Loader::loadAll();
        if (Anon_System_Config::isInstalled()) {
            $mode = Anon_System_Env::get('app.mode', 'api');
            if ($mode === 'cms') {
                Anon_Loader::loadCmsModules();
            }
        }
        if (Anon_System_Env::get('app.token.enabled', false)) {
            Anon_Loader::loadOptionalModules('token');
        }
        if (Anon_System_Env::get('app.captcha.enabled', false)) {
            Anon_Loader::loadOptionalModules('captcha');
        }
        if (Anon_System_Env::get('app.security.csrf.enabled', false)) {
            Anon_Loader::loadOptionalModules('csrf');
        }
        
        // Debug 已在 loadCoreModules 中加载，这里只需要初始化
        if (class_exists('Anon_Debug')) {
            Anon_Debug::init();
        }
        
        if (class_exists('Anon_System_Plugin')) {
            Anon_System_Plugin::init();
        }
        
        $codeFile = self::APP_DIR . 'useCode.php';
        if (file_exists($codeFile)) {
            require_once $codeFile;
        }
        
        if (class_exists('Anon_Auth_Capability')) {
            Anon_Auth_Capability::getInstance()->init();
        }
    }

    /**
     * 启动 FPM Web 应用
     * @return void
     */
    public static function runFpm()
    {
        self::init();
        Anon_System_Config::initSystemRoutes();
        Anon_System_Config::initAppRoutes();
        Anon_Http_Router::init();

        Anon_Debug::info('Application started (FPM)', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
    }

    /**
     * 启动 Swoole 服务
     * @param array $argv 命令行参数
     * @return void
     */
    public static function runSwoole($argv)
    {
        self::init();
        require_once self::MODULES_DIR . 'Server/Manager.php';
        $type = $argv[2] ?? 'http';
        $action = $argv[3] ?? 'start';
        $host = '0.0.0.0';
        $port = 9501;
        foreach ($argv as $arg) {
            if (strpos($arg, '--port=') === 0) {
                $port = (int) substr($arg, 7);
            }
            if (strpos($arg, '--host=') === 0) {
                $host = substr($arg, 7);
            }
        }
        if (!in_array('--port=', $argv)) {
            switch ($type) {
                case 'tcp': $port = 9502; break;
                case 'websocket': $port = 9503; break;
            }
        }

        $manager = new Anon_Server_Manager('swoole');

        try {
            $server = $manager->create($type, [
                'host' => $host,
                'port' => $port
            ]);

            switch ($action) {
                case 'start':
                    $server->start();
                    break;
                case 'reload':
                    $server->reload();
                    break;
                case 'stop':
                    $server->stop();
                    break;
                default:
                    echo "用法: php index.php swoole [http|tcp|websocket] [start|stop|reload] [--port=端口] [--host=主机]\n";
                    break;
            }
        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
        }
    }
}
