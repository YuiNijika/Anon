<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

class Anon_Server_Driver_Swoole_WebSocket implements Anon_Server_Contract_ServerInterface
{
    protected $server;
    protected $config;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'host' => '0.0.0.0',
            'port' => 9503,
            'worker_num' => 4,
            'daemonize' => false,
        ], $config);
    }

    public function start()
    {
        if (!class_exists('Swoole\WebSocket\Server')) {
            die("未安装 Swoole 扩展。\n");
        }

        $this->server = new Swoole\WebSocket\Server($this->config['host'], $this->config['port']);

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'daemonize' => $this->config['daemonize'],
        ]);

        $this->server->on('Open', function (Swoole\WebSocket\Server $server, $request) {
            echo "WebSocket 连接开启: {$request->fd}\n";
        });

        $this->server->on('Message', function (Swoole\WebSocket\Server $server, $frame) {
            echo "收到消息: {$frame->data}\n";
            $server->push($frame->fd, "服务端已收到: {$frame->data}");
        });

        $this->server->on('Close', function ($server, $fd) {
            echo "WebSocket 连接关闭: {$fd}\n";
        });

        echo "Swoole WebSocket 服务已启动: ws://{$this->config['host']}:{$this->config['port']}\n";
        $this->server->start();
    }

    public function stop() {}
    public function reload() 
    {
        if ($this->server) {
            $this->server->reload();
        }
    }
}
