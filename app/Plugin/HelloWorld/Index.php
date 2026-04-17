<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_HelloWorld extends Anon_Plugin_Base
{
    /**
     * 插件初始化，可在内部使用 $this->options()->get() 读取选项
     * @return void
     */
    public function init()
    {
        Anon::route('/hello', function () {
            $greeting = $this->options()->get('greeting', 'Hello, World!', false, null);
            $mode = Anon_System_Plugin::isApiMode() ? 'API' : 'CMS';
            
            Anon::success([
                'message' => $greeting,
                'plugin' => 'HelloWorld',
                'mode' => $mode
            ], "Hello World from Plugin ({$mode} Mode)");
        }, [
            'header' => true,
            'requireLogin' => false,
            'method' => ['GET'],
            'token' => false,
            'cache' => ['enabled' => true, 'time' => 3600],
        ]);
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
    public function getSettingsSchema(): array
    {
        return [
            'greeting' => [
                'type' => 'text',
                'label' => '问候语',
                'default' => 'Hello, World!',
            ],
        ];
    }
}
