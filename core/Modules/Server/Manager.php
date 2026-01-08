<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

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

    public function __construct($driver = 'swoole')
    {
        $this->driver = $driver;
    }

    /**
     * 创建服务实例
     * @param string $type 服务类型 (http, tcp, websocket)
     * @param array $config 配置数组
     * @return object
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
