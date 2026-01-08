<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

class Anon_Server_Driver_Swoole_Http implements Anon_Server_Contract_ServerInterface
{
    protected $server;
    protected $config;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'host' => '0.0.0.0',
            'port' => 9501,
            'worker_num' => 4,
            'daemonize' => false,
        ], $config);
    }

    public function start()
    {
        if (!class_exists('Swoole\Http\Server')) {
            die("未安装 Swoole 扩展。\n");
        }

        $this->server = new Swoole\Http\Server($this->config['host'], $this->config['port']);

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'daemonize' => $this->config['daemonize'],
            'enable_static_handler' => true,
            'document_root' => dirname(__DIR__, 6) . '/public', // 调整到 public 根目录
        ]);

        $this->server->on('Start', function ($server) {
            echo "Swoole HTTP 服务已启动: http://{$this->config['host']}:{$this->config['port']}\n";
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
            
            // 开启输出缓冲以捕获框架输出
            ob_start();
            
            try {
                // 这里调用框架的请求处理器
                // 理想情况: $app->handle($request);
                // 为了完全集成，我们需要将 Router::dispatch 和 exit() 分离
                
                // 临时测试:
                echo "来自 Anon Swoole HTTP Server 的问候!";
                
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            $content = ob_get_clean();
            
            $response->header('Content-Type', 'text/html');
            $response->end($content);
        });

        $this->server->start();
    }

    public function stop()
    {
        // Swoole 主要通过信号处理停止
    }

    public function reload()
    {
        if ($this->server) {
            $this->server->reload();
        }
    }
}
