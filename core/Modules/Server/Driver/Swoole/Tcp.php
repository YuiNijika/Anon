<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

/**
 * Swoole TCP 服务器驱动
 * 负责启动、停止和管理 Swoole TCP 服务器
 */
class Anon_Server_Driver_Swoole_Tcp implements Anon_Server_Contract_ServerInterface
{
    /**
     * @var Swoole\Server Swoole 服务器实例
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
            'port' => 9502,
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
        if (!class_exists('Swoole\Server')) {
            die("未安装 Swoole 扩展。\n");
        }

        $this->server = new Swoole\Server($this->config['host'], $this->config['port']);

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'daemonize' => $this->config['daemonize'],
        ]);

        $this->server->on('Connect', function ($server, $fd) {
            echo "客户端: 连接成功。\n";
        });

        $this->server->on('Receive', function ($server, $fd, $reactor_id, $data) {
            $server->send($fd, "服务端: " . $data);
        });

        $this->server->on('Close', function ($server, $fd) {
            echo "客户端: 连接关闭。\n";
        });

        echo "Swoole TCP 服务已启动: tcp://{$this->config['host']}:{$this->config['port']}\n";
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
