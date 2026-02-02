<?php
/**
 * Name: HelloWorld
 * Description: Hello World
 * Mode: auto
 * Version: 1.0.0
 * Author: YuiNijika
 * URI: https://github.com/YuiNijika
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_HelloWorld extends Anon_Plugin_Base
{
    /**
     * 插件初始化，可在内部使用 $this->options()->get() 读取选项
     * @return void
     */
    public function init()
    {
        $plugin = $this;
        if (Anon_System_Plugin::isApiMode()) {
            Anon::route('/hello', function () use ($plugin) {
                Anon::success([
                    $plugin->index()
                ], 'Hello World from Plugin (API Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => ['enabled' => true, 'time' => 3600],
            ]);
        } elseif (Anon_System_Plugin::isCmsMode()) {
            Anon::route('/hello', function () use ($plugin) {
                Anon::success([
                    $plugin->index()
                ], 'Hello World from Plugin (CMS Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => ['enabled' => true, 'time' => 3600],
            ]);
        }
    }

    /**
     * 插件激活时调用
     * @return void
     */
    public static function activate()
    {
        Anon_Debug::info('HelloWorld 插件已激活');
    }

    /**
     * 插件停用时调用
     * @return void
     */
    public static function deactivate()
    {
        Anon_Debug::info('HelloWorld 插件已停用');
    }

    /**
     * 设置页 schema，键为字段名，值为 type、label、default 等，供管理端读取
     * @return array
     */
    public static function getSettingsSchema(): array
    {
        return [
            'greeting' => [
                'type' => 'text',
                'label' => '问候语',
                'default' => 'Hello, World!',
            ],
        ];
    }

    /**
     * 自定义方法，通过选项代理读取插件、主题、系统选项
     * @return string
     */
    public function index()
    {
        $proxy = $this->options();
        return $proxy !== null
            ? $proxy->get('greeting', 'Hello, World!', false, null)
            : 'Hello, World!';
    }
}