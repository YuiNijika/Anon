<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

/**
 * Swoole 定时任务 (Crontab) 驱动
 * 负责启动、停止和管理基于 Swoole Timer 的定时任务
 */
class Anon_Server_Driver_Swoole_Crontab implements Anon_Server_Contract_ServerInterface
{
    /**
     * @var array 任务列表
     */
    protected $tasks = [];

    /**
     * @var array 配置选项
     */
    protected $config = [];

    /**
     * @var array 运行中的定时器ID
     */
    protected $timerIds = [];

    /**
     * 构造函数
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 添加定时任务
     * @param int $intervalMs 间隔时间（毫秒）
     * @param callable $callback 回调函数
     * @return void
     */
    public function add(int $intervalMs, callable $callback): void
    {
        $this->tasks[] = [
            'interval' => $intervalMs,
            'callback' => $callback
        ];
    }

    /**
     * 启动服务
     * @param array $options 启动选项
     * @return void
     * @throws Exception 如果未安装 Swoole 扩展
     */
    public function start(array $options = []): void
    {
        if (!class_exists('Swoole\Timer')) {
            throw new Exception("未安装 Swoole 扩展。");
        }

        foreach ($this->tasks as $task) {
            $timerId = Swoole\Timer::tick($task['interval'], $task['callback']);
            $this->timerIds[] = $timerId;
        }
        
        // 保持进程运行
        // 在 Swoole 环境下，如果只有 Timer，进程可能会退出，通常需要 EventLoop
        if (php_sapi_name() === 'cli') {
             // 简单的保持运行机制，实际可能集成在 Server 中
             Swoole\Event::wait();
        }
    }

    /**
     * 停止服务
     * @return void
     */
    public function stop(): void
    {
        foreach ($this->timerIds as $timerId) {
            Swoole\Timer::clear($timerId);
        }
        $this->timerIds = [];
    }

    /**
     * 重载服务
     * @return void
     */
    public function reload(): void
    {
        $this->stop();
        $this->start();
    }
}
