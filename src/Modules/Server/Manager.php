<?php
namespace Anon\Modules\Server;







use Exception;
use Server;
use ServerInterface;
use Crontab;
use Http;
use Process;
use Tcp;
use WebSocket;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 服务器管理器
 * 负责根据命令行参数创建和运行不同类型的服务器实例
 */
class Manager
{
    /**
     * @var string 驱动类型
     */
    protected $driver = 'swoole';

    /**
     * @var ServerInterface 服务实例
     */
    protected $instance;

    /**
     * 构造函数
     * @param string $driver 驱动类型
     */
    public function __construct($driver = 'swoole')
    {
        $this->driver = $driver;
    }

    /**
     * 创建服务实例
     * @param string $type 服务类型，可选值为 http、tcp 或 websocket
     * @param array $config 配置数组
     * @return ServerInterface
     * @throws Exception
     */
    public function create($type = 'http', $config = []): ServerInterface
    {
        $driver = strtolower((string) $this->driver);
        $type = strtolower((string) $type);

        if ($driver !== 'swoole') {
            throw new Exception("不支持的服务器驱动: {$driver}");
        }

        return match ($type) {
            'http' => new Http($config),
            'tcp' => new Tcp($config),
            'websocket' => new WebSocket($config),
            'process' => new Process($config),
            'crontab' => new Crontab($config),
            default => throw new Exception("不支持的服务器类型: {$type}"),
        };
    }
}
