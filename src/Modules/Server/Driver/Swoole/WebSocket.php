<?php
namespace Anon\Modules\Server\Driver\Swoole;


use WebSocketServer;
use Swoole;
use ServerInterface;
use Server;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Swoole WebSocket 服务器驱动
 * 负责启动、停止和管理 Swoole WebSocket 服务器
 */
class WebSocket implements ServerInterface
{
    /**
     * @var SwooleServer WebSocket 服务器实例
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
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '0.0.0.0',
            'port' => 9503,
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
        ]);

        $this->server->on('Open', function (SwooleServer $server, $request) {
            echo "WebSocket 连接开启: {$request->fd}\n";
        });

        $this->server->on('Message', function (SwooleServer $server, $frame) {
            echo "收到消息: {$frame->data}\n";
            $server->push($frame->fd, "服务端已收到: {$frame->data}");
        });

        $this->server->on('Close', function ($server, $fd) {
            echo "WebSocket 连接关闭: {$fd}\n";
        });

        echo "Swoole WebSocket 服务已启动: ws://{$this->config['host']}:{$this->config['port']}\n";
        $this->server->start();
    }

    /**
     * 停止服务
     * @return void
     */
    public function stop(): void
    {
        // Swoole 服务停止逻辑
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
