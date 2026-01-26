<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 配置管理类
 * 处理系统配置相关的功能
 */
class Anon_Cms_Admin_Config
{
    /**
     * 获取配置信息
     * @return void
     */
    public static function getConfig()
    {
        try {
            $config = Anon_System_Config::getConfig();
            Anon_Http_Response::success($config, '获取配置信息成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

