<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_PluginMeta = [
    'name' => 'HelloWorld',
    'description' => 'Hello World',
    'version' => '1.0.0',
    'author' => 'YuiNijika',
    'url' => 'https://github.com/YuiNijika',
];

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