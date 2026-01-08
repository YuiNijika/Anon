<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

interface Anon_Server_Contract_ServerInterface
{
    /**
     * 启动服务
     */
    public function start();

    /**
     * 停止服务
     */
    public function stop();

    /**
     * 重载服务
     */
    public function reload();
}
