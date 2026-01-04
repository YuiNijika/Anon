<?php
/**
 * Plugin Name: HelloWorld
 * Plugin Description: Hello World
 * Version: 1.0.0
 * Author: YuiNijika
 * Plugin URI: https://github.com/YuiNijika
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_HelloWorld
{
    /**
     * 插件初始化方法
     */
    public static function init()
    {
        // 注册路由，配置元数据
        Anon::route('/hello', function () {
            Anon::success([
                self::index()
            ], 'Hello World from Plugin');
        }, [
            'header' => true,
            'requireLogin' => false,
            'method' => ['GET'],
            'token' => false,
            'cache' => [
                'enabled' => true,
                'time' => 3600, // 缓存1小时
            ],
        ]);
    }

    /**
     * 插件激活时调用
     */
    public static function activate()
    {
        Anon_Debug::info('HelloWorld 插件已激活');
    }

    /**
     * 插件停用时调用
     */
    public static function deactivate()
    {
        Anon_Debug::info('HelloWorld 插件已停用');
    }

    /**
     * 自定义方法
     */
    public static function index()
    {
        return 'Hello World';
    }
}