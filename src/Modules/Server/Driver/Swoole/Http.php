<?php
namespace Anon\Modules\Server\Driver\Swoole;
use Anon\Main;


use Throwable;
use HttpServer;
use Swoole;

use Anon\Modules\Http\Router;
use Anon\Modules\Http\RequestHelper;
use ServerInterface;
use Anon\Modules\System\Config;
use Server;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Swoole HTTP 服务器驱动
 * 负责启动、停止和管理 Swoole HTTP 服务器
 */
class Http implements ServerInterface
{
    /**
     * @var SwooleServer Swoole HTTP 服务器实例
     */
    protected $server;

    /**
     * @var array 配置选项
     */
    protected $config;

    /**
     * 构造函数
     * @param array $config 配置数组
     */
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'host' => '0.0.0.0',
            'port' => 9501,
            'worker_num' => 4,
            'daemonize' => false,
        ], $config);
    }

    /**
     * 启动服务
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        if (!class_exists('SwooleServer')) {
            die("未安装 Swoole 扩展。\n");
        }

        $this->server = new SwooleServer($this->config['host'], $this->config['port']);

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'daemonize' => $this->config['daemonize'],
            'enable_static_handler' => true,
            'document_root' => dirname(__DIR__, 6) . '/public', // 调整到 public 根目录
        ]);

        $this->server->on('Start', function ($server) {
            echo "Swoole HTTP 服务已启动: http://{$this->config['host']}:{$this->config['port']}\n";
        });

        // 在 WorkerStart 中初始化框架，避免重复加载
        $this->server->on('WorkerStart', function ($server, $workerId) {
            try {
                // 初始化核心
                Main::init();
                
                // 初始化路由配置
                Config::initSystemRoutes();
                Config::initAppRoutes();
                
                Router::loadRoutes();
                
                echo "[Worker #{$workerId}] Framework initialized.\n";
            } catch (Throwable $e) {
                echo "[Worker #{$workerId}] Initialization failed: " . $e->getMessage() . "\n";
            }
        });

        $this->server->on('Request', function ($request, $response) {
            // 为 Anon 框架设置上下文
            $_SERVER = [];
            if (isset($request->server)) {
                foreach ($request->server as $k => $v) {
                    $_SERVER[strtoupper($k)] = $v;
                }
            }
            if (isset($request->header)) {
                foreach ($request->header as $k => $v) {
                    $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
                }
            }
            $_GET = $request->get ?? [];
            $_POST = $request->post ?? [];
            $_COOKIE = $request->cookie ?? [];
            $_FILES = $request->files ?? [];
            
            // 设置原始输入
            RequestHelper::resetInput();
            RequestHelper::setRawInput($request->rawContent());

            // 开启输出缓冲以捕获框架输出
            ob_start();
            
            try {
                // 分发请求
                Router::dispatchRequest();
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            $content = ob_get_clean();
            
            $response->header('Content-Type', 'text/html');
            $response->end($content);
        });

        $this->server->start();
    }

    /**
     * 停止服务
     * @return void
     */
    public function stop(): void
    {
        // Swoole 主要通过信号处理停止
    }

    /**
     * 重载服务
     * @return void
     */
    public function reload(): void
    {
        if ($this->server) {
            $this->server->reload();
        }
    }
}
