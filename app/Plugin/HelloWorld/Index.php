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

class Anon_Plugin_HelloWorld
{
    /**
     * 插件初始化方法
     */
    public static function init()
    {
        // 判断当前应用模式
        if (Anon_System_Plugin::isApiMode()) {
            // API 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (API Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        } elseif (Anon_System_Plugin::isCmsMode()) {
            // CMS 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (CMS Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        }
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
     * 设置页 schema，键为字段名，值为 type/label/default 等
     * @return array
     */
    public static function options(): array
    {
        return [
            'greeting' => [
                'type' => 'text',
                'label' => '问候语',
                'default' => 'Hello World',
            ],
        ];
    }

    /**
     * 自定义方法
     */
    public static function index()
    {
        return 'Hello World';
    }
}