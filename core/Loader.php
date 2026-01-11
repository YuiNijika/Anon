<?php
/**
 * 核心加载器
 *
 * 负责加载框架的所有组件、模块和依赖文件。
 * 通过集中管理文件引入，保持 Main 类的整洁，并便于未来扩展自动加载机制。
 *
 * @package Anon/Core
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Loader
{
    /**
     * 加载所有核心组件
     * @return void
     */
    public static function loadAll()
    {
        self::loadWidgets();
        self::loadCoreModules();
        self::loadOptionalModules();
        self::loadDebug();
        self::loadExtensions();
    }

    /**
     * 加载 Widgets
     * @return void
     */
    public static function loadWidgets()
    {
        require_once Anon_Main::WIDGETS_DIR . 'Connection.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Escape.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Sanitize.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Validate.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Text.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Format.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Array.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Random.php';
    }

    /**
     * 加载 Modules
     * @return void
     */
    public static function loadCoreModules()
    {
        require_once Anon_Main::MODULES_DIR . 'System/Exception.php';
        require_once Anon_Main::MODULES_DIR . 'Database.php';
        require_once Anon_Main::MODULES_DIR . 'System/Install.php';
        require_once Anon_Main::MODULES_DIR . 'Http/ResponseHelper.php';
        require_once Anon_Main::MODULES_DIR . 'Http/RequestHelper.php';
        require_once Anon_Main::MODULES_DIR . 'System/Hook.php';
        require_once Anon_Main::MODULES_DIR . 'Helper.php';
        require_once Anon_Main::MODULES_DIR . 'System/Widget.php';
        require_once Anon_Main::MODULES_DIR . 'System/Container.php';
        require_once Anon_Main::MODULES_DIR . 'Http/Middleware.php';
        require_once Anon_Main::MODULES_DIR . 'System/Cache.php';
        require_once Anon_Main::MODULES_DIR . 'Database/QueryBuilder.php';
        require_once Anon_Main::MODULES_DIR . 'Database/QueryOptimizer.php';
        require_once Anon_Main::MODULES_DIR . 'Database/Sharding.php';
    }

    /**
     * 加载 Optional Modules
     * @return void
     */
    public static function loadOptionalModules()
    {
        require_once Anon_Main::MODULES_DIR . 'Auth/Token.php';
        require_once Anon_Main::MODULES_DIR . 'Auth/Captcha.php';
        require_once Anon_Main::MODULES_DIR . 'Auth/RateLimit.php';
        require_once Anon_Main::MODULES_DIR . 'Auth/Csrf.php';
        require_once Anon_Main::MODULES_DIR . 'Security/Security.php';
        require_once Anon_Main::MODULES_DIR . 'Auth/Capability.php';
        require_once Anon_Main::MODULES_DIR . 'System/Console.php';
    }

    /**
     * 加载调试模块
     * @return void
     */
    public static function loadDebug()
    {
        require_once Anon_Main::MODULES_DIR . 'Debug.php';
    }

    /**
     * 加载扩展和插件支持
     * @return void
     */
    public static function loadExtensions()
    {
        // 加载快捷方法封装类
        require_once Anon_Main::MODULES_DIR . 'Anon.php';
        
        // 加载插件系统
        require_once Anon_Main::MODULES_DIR . 'System/Plugin.php';
        
        // 加载兼容层
        require_once Anon_Main::MODULES_DIR . '/../Compatibility.php';
    }
}

