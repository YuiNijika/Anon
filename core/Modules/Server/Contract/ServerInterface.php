<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 服务器接口定义
 * 定义了所有服务器驱动需要实现的方法
 */
interface Anon_Server_Contract_ServerInterface
{
    /**
     * 启动服务器
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void;

    /**
     * 停止服务器
     * @return void
     */
    public function stop(): void;

    /**
     * 重载服务器
     * @return void
     */
    public function reload(): void;
}
