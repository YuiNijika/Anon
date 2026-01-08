<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Server_Driver_Swoole_Process
{
    protected $processes = [];

    public function add($callback, $redirectStdinStdout = false, $pipeType = 2, $enableCoroutine = false)
    {
        if (!class_exists('Swoole\Process')) {
            throw new Exception("未安装 Swoole 扩展。");
        }

        $process = new Swoole\Process($callback, $redirectStdinStdout, $pipeType, $enableCoroutine);
        $this->processes[] = $process;
        return $process;
    }

    public function startAll()
    {
        foreach ($this->processes as $process) {
            $process->start();
        }

        // 等待进程退出以避免僵尸进程
        // 在真正的管理器中，这通常由主服务器循环或管理进程处理
        if (php_sapi_name() === 'cli') {
            while ($ret = Swoole\Process::wait(false)) {
                // 进程退出
                // echo "PID={$ret['pid']}\n";
            }
        }
    }
}
