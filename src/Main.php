<?php
namespace Anon;

use Anon\Modules\Common;
use Anon\Modules\System\Env;
use Anon\Modules\Database\Sharding;
use Anon\Modules\Debug;
use Anon\Modules\System\Cache\Cache;
use Anon\Modules\System\AccessLog;
use Anon\Modules\System\Plugin;
use Anon\Modules\System\Extension;
use Anon\Modules\Auth\Capability;
use Anon\Modules\System\Config;
use Anon\Modules\Http\Router;
use Anon\Modules\Server\Manager;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

if (version_compare(phpversion(), '7.4.0', '<')) {
    die('哎呀~ Anon Framework 需要 PHP 7.4.0及以上版本！');
    exit;
}

class Main
{
    const APP_DIR = __DIR__ . '/../app/';
    const WIDGETS_DIR = __DIR__ . '/Widgets/';
    const MODULES_DIR = __DIR__ . '/Modules/';
    const ROOT_DIR = __DIR__ . '/../';
    private static bool $bootstrapped = false;

    /**
     * 统一启动入口
     * @param array|null $argv
     * @return void
     */
    public static function boot(?array $argv = null): void
    {
        self::bootstrapDiagnostics();
        $argv = $argv ?? ($_SERVER['argv'] ?? []);
        if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'swoole') {
            self::runSwoole($argv);
            return;
        }
        self::runFpm();
    }

    private static function bootstrapDiagnostics(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        error_reporting(E_ALL);
        ini_set('log_errors', '1');
        ini_set('display_errors', '0');

        $logDir = self::ROOT_DIR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $phpErrorLog = $logDir . DIRECTORY_SEPARATOR . 'php_error.log';
        ini_set('error_log', $phpErrorLog);

        register_shutdown_function([self::class, 'handleShutdown']);

        set_exception_handler(function ($e) {
            self::handleError($e);
        });

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'] ?? 0, $fatalTypes, true)) {
            return;
        }
        $id = bin2hex(random_bytes(8));
        self::writeFatalLog([
            'id' => $id,
            'type' => 'shutdown',
            'error' => $err,
            'sapi' => php_sapi_name(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'time' => date('c'),
        ]);
    }

    private static function writeFatalLog(array $payload): void
    {
        $logDir = self::ROOT_DIR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $file = $logDir . DIRECTORY_SEPARATOR . 'fatal.log';
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"time":"' . date('c') . '","error":"json_encode_failed"}';
        }
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }

    /**
     * 初始化框架环境
     * @return void
     */
    public static function init()
    {
        if (!defined('ANON_ROOT')) {
            define('ANON_ROOT', self::ROOT_DIR);
        }
        
        $envFile = self::APP_DIR . 'useEnv.php';
        if (is_file($envFile)) {
            require_once $envFile;
        }
        
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
        Env::init($envConfig);

        if (class_exists(Sharding::class)) {
            $shardingConfig = Env::get('system.db.sharding', Env::get('app.db.sharding', []));
            if (is_array($shardingConfig) && !empty($shardingConfig)) {
                Sharding::init($shardingConfig);
            }
        }
        Common::defineConstantsFromEnv();
        Common::enforceInstallRedirect();
        
        // Debug 已在 loadCoreModules 中加载，这里只需要初始化
        if (class_exists(Debug::class)) {
            Debug::init();
        }
        
        // 初始化缓存系统
        if (class_exists(Cache::class)) {
            $driver = Env::get('app.base.cache.driver', 'file');
            $config = Env::get('app.base.cache', []);
            Cache::init($driver, $config);
        }
        
        // 初始化访问日志
        if (class_exists(AccessLog::class)) {
            AccessLog::log();
        }
        
        if (class_exists(Plugin::class)) {
            Plugin::init();
        }
        
        // 初始化扩展系统
        if (class_exists(Extension::class)) {
            Extension::init();
        }
        
        $codeFile = self::APP_DIR . 'useCode.php';
        if (file_exists($codeFile)) {
            require_once $codeFile;
        }
        
        if (class_exists(Capability::class)) {
            Capability::getInstance()->init();
        }
    }

    /**
     * 启动 FPM Web 应用
     * @return void
     */
    public static function runFpm()
    {
        try {
            self::init();
            Config::initSystemRoutes();
            Config::initAppRoutes();
            Router::init();

            Debug::info('Application started (FPM)', [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]);
        } catch (\Exception $e) {
            self::handleError($e);
        } catch (\Error $e) {
            self::handleError($e);
        }
    }

    /**
     * 处理错误
     * @param \Throwable $e 异常对象
     * @return void
     */
    private static function handleError($e)
    {
        $id = bin2hex(random_bytes(8));
        $isDebug = (defined('ANON_DEBUG') && ANON_DEBUG);
        if (!$isDebug && class_exists(Env::class) && Env::isInitialized()) {
            $isDebug = (bool) Env::get('app.debug.global', false);
        }

        $payload = [
            'id' => $id,
            'type' => 'exception',
            'message' => method_exists($e, 'getMessage') ? $e->getMessage() : (string) $e,
            'file' => method_exists($e, 'getFile') ? $e->getFile() : null,
            'line' => method_exists($e, 'getLine') ? $e->getLine() : null,
            'trace' => method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : null,
            'sapi' => php_sapi_name(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'time' => date('c'),
        ];
        self::writeFatalLog($payload);

        if ($isDebug) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo "<pre>";
            echo "ErrorId: " . $id . "\n\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            echo "</pre>";
        } else {
            // 生产环境只显示简单错误
            http_response_code(500);
            echo "Internal Server Error (ErrorId: {$id})";
        }
        exit(1);
    }

    /**
     * 启动 Swoole 服务
     * @param array $argv 命令行参数
     * @return void
     */
    public static function runSwoole($argv)
    {
        try {
            self::init();
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

            $manager = new Manager('swoole');

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
                    echo "用法: php run/index.php swoole [http|tcp|websocket] [start|stop|reload] [--port=端口] [--host=主机]\n";
                    break;
            }
        } catch (\Exception $e) {
            self::handleError($e);
        } catch (\Error $e) {
            self::handleError($e);
        }
    }
}


