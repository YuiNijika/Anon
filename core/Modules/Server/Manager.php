<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/Contract/ServerInterface.php';
require_once __DIR__ . '/Driver/Swoole/Http.php';
require_once __DIR__ . '/Driver/Swoole/Tcp.php';
require_once __DIR__ . '/Driver/Swoole/WebSocket.php';
require_once __DIR__ . '/Driver/Swoole/Process.php';
require_once __DIR__ . '/Driver/Swoole/Crontab.php';

/**
 * 服务器管理器
 * 负责根据命令行参数创建和运行不同类型的服务器实例
 */
class Anon_Server_Manager
{
    /**
     * @var string 驱动类型
     */
    protected $driver = 'swoole';

    /**
     * @var Anon_Server_Contract_ServerInterface 服务实例
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
     * @param string $type 服务类型 (http, tcp, websocket)
     * @param array $config 配置数组
     * @return Anon_Server_Contract_ServerInterface
     * @throws Exception
     */
    public function create($type = 'http', $config = [])
    {
        $class = 'Anon_Server_Driver_' . ucfirst($this->driver) . '_' . ucfirst($type);
        
        if (!class_exists($class)) {
            // 自动加载逻辑或假设文件路径约定
            $file = __DIR__ . '/Driver/' . ucfirst($this->driver) . '/' . ucfirst($type) . '.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                throw new Exception("未找到服务器驱动类: $class");
            }
        }

        if (class_exists($class)) {
            return new $class($config);
        }

        throw new Exception("未找到服务器驱动类: $class");
    }
}
