<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/../../Contract/ServerInterface.php';

/**
 * Swoole 进程管理驱动
 * 负责启动、停止和管理自定义的 Swoole 进程
 */
class Anon_Server_Driver_Swoole_Process implements Anon_Server_Contract_ServerInterface
{
    /**
     * @var array 进程列表
     */
    protected $processes = [];

    /**
     * @var array 配置选项
     */
    protected $config = [];

    /**
     * 构造函数
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 添加进程
     * @param callable $callback 进程回调函数
     * @param bool $redirectStdinStdout 是否重定向标准输入输出
     * @param int $pipeType 管道类型
     * @param bool $enableCoroutine 是否启用协程
     * @return Swoole\Process
     * @throws Exception 如果未安装 Swoole 扩展
     */
    public function add(callable $callback, bool $redirectStdinStdout = false, int $pipeType = 2, bool $enableCoroutine = false)
    {
        if (!class_exists('Swoole\Process')) {
            throw new Exception("未安装 Swoole 扩展。");
        }

        $process = new Swoole\Process($callback, $redirectStdinStdout, $pipeType, $enableCoroutine);
        $this->processes[] = $process;
        return $process;
    }

    /**
     * 启动服务
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        // 示例：启动默认配置的进程，如果有
        // 在实际应用中，通常通过 add() 方法添加进程，然后调用 start()
        
        foreach ($this->processes as $process) {
            $process->start();
        }

        // 等待进程退出以避免僵尸进程
        // 在真正的管理器中，这通常由主服务器循环或管理进程处理
        if (php_sapi_name() === 'cli' && class_exists('Swoole\Process')) {
            while ($ret = Swoole\Process::wait(false)) {
                // 进程退出
                // echo "PID={$ret['pid']}\n";
            }
        }
    }

    /**
     * 停止服务
     * @return void
     */
    public function stop(): void
    {
        foreach ($this->processes as $process) {
            // 发送 SIGTERM 信号
            Swoole\Process::kill($process->pid, SIGTERM);
        }
    }

    /**
     * 重载服务
     * @return void
     */
    public function reload(): void
    {
        // 进程通常不支持热重载，可能需要重启
        $this->stop();
        $this->start();
    }
}
