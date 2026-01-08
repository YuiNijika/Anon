<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

class Anon_Server_Driver_Swoole_Tcp implements Anon_Server_Contract_ServerInterface
{
    protected $server;
    protected $config;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'host' => '0.0.0.0',
            'port' => 9502,
            'worker_num' => 4,
            'daemonize' => false,
        ], $config);
    }

    public function start()
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

    public function stop() {}
    public function reload() 
    {
        if ($this->server) {
            $this->server->reload();
        }
    }
}
