<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Server_Driver_Swoole_Crontab
{
    protected $tasks = [];

    /**
     * 添加定时任务
     * @param int $intervalMs 间隔时间（毫秒）
     * @param callable $callback 回调函数
     */
    public function add($intervalMs, callable $callback)
    {
        $this->tasks[] = [
            'interval' => $intervalMs,
            'callback' => $callback
        ];
    }

    public function start()
    {
        if (!class_exists('Swoole\Timer')) {
            throw new Exception("未安装 Swoole 扩展。");
        }

        foreach ($this->tasks as $task) {
            Swoole\Timer::tick($task['interval'], $task['callback']);
        }
    }
}
