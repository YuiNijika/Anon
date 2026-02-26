<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Loader
{
    private static $loadedModules = [];

    /**
     * 加载所有核心组件
     * @return void
     */
    public static function loadAll()
    {
        self::loadWidgets();
        self::loadCoreModules();
        self::loadExtensions();
    }

    /**
     * 加载 CMS 相关模块
     * @return void
     */
    public static function loadCmsModules()
    {
        if (isset(self::$loadedModules['cms'])) {
            return;
        }

        require_once Anon_Main::MODULES_DIR . 'Cms/Cms.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Options.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/PageMeta.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/AccessLog.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Theme/FatalError.php';
        require_once Anon_Main::WIDGETS_DIR . 'Cms/ThemeHelper.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Theme/Theme.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Theme/Options.php';
        require_once Anon_Main::WIDGETS_DIR . 'Cms/User.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Attachment.php';
        require_once Anon_Main::MODULES_DIR . 'Cms/Admin/Admin.php';

        Anon_Cms_Admin::init();
        self::$loadedModules['cms'] = true;
        Anon_System_Hook::add_action('theme_foot', [Anon_Cms::class, 'outputCopyright']);
        Anon_System_Hook::add_action('theme_foot', [Anon_Cms::class, 'outputPageLoadTimeScript']);
    }

    /**
     * 加载 Widgets
     * @return void
     */
    public static function loadWidgets()
    {
        if (isset(self::$loadedModules['widgets'])) {
            return;
        }

        require_once Anon_Main::WIDGETS_DIR . 'Connection.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Escape.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Sanitize.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Validate.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Text.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Format.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Array.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Random.php';
        require_once Anon_Main::WIDGETS_DIR . 'Utils/Jsx.php';

        self::$loadedModules['widgets'] = true;
    }

    /**
     * 加载核心模块
     * @return void
     */
    public static function loadCoreModules()
    {
        if (isset(self::$loadedModules['core'])) {
            return;
        }

        // 先加载 Debug，因为其他模块可能会使用它
        self::loadDebug();

        require_once Anon_Main::WIDGETS_DIR . 'Connection.php';
        require_once Anon_Main::MODULES_DIR . 'System/Exception.php';
        require_once Anon_Main::MODULES_DIR . 'Database.php';
        require_once Anon_Main::MODULES_DIR . 'System/Install.php';
        require_once Anon_Main::MODULES_DIR . 'Http/ResponseHelper.php';
        require_once Anon_Main::MODULES_DIR . 'Http/RequestHelper.php';
        require_once Anon_Main::MODULES_DIR . 'System/Hook.php';
        require_once Anon_Main::MODULES_DIR . 'Helper.php';
        require_once Anon_Main::MODULES_DIR . 'System/Widget.php';
        require_once Anon_Main::MODULES_DIR . 'System/Container.php';
        require_once Anon_Main::MODULES_DIR . 'Http/Router.php';
        require_once Anon_Main::MODULES_DIR . 'Http/Middleware.php';
        require_once Anon_Main::MODULES_DIR . 'System/Cache.php';
        require_once Anon_Main::MODULES_DIR . 'Database/QueryBuilder.php';
        require_once Anon_Main::MODULES_DIR . 'Database/QueryOptimizer.php';
        require_once Anon_Main::MODULES_DIR . 'Database/Sharding.php';

        self::$loadedModules['core'] = true;
    }

    /**
     * 加载可选模块
     * @param string|null $module 模块名 null 时加载全部
     * @return void
     */
    public static function loadOptionalModules(?string $module = null)
    {
        $modules = [
            'token' => Anon_Main::MODULES_DIR . 'Auth/Token.php',
            'captcha' => Anon_Main::MODULES_DIR . 'Auth/Captcha.php',
            'ratelimit' => Anon_Main::MODULES_DIR . 'Auth/RateLimit.php',
            'csrf' => Anon_Main::MODULES_DIR . 'Auth/Csrf.php',
            'security' => Anon_Main::MODULES_DIR . 'Security/Security.php',
            'capability' => Anon_Main::MODULES_DIR . 'Auth/Capability.php',
            'console' => Anon_Main::MODULES_DIR . 'System/Console.php',
        ];

        if ($module !== null) {
            $module = strtolower($module);
            if (isset($modules[$module]) && !isset(self::$loadedModules['optional_' . $module])) {
                require_once $modules[$module];
                self::$loadedModules['optional_' . $module] = true;
            }
            return;
        }

        foreach ($modules as $key => $path) {
            if (!isset(self::$loadedModules['optional_' . $key])) {
                require_once $path;
                self::$loadedModules['optional_' . $key] = true;
            }
        }
    }

    /**
     * 加载配置相关模块 Token/Captcha/Csrf
     * @return void
     */
    public static function loadConfigModules(): void
    {
        self::loadOptionalModules('token');
        self::loadOptionalModules('captcha');
        self::loadOptionalModules('csrf');
    }

    /**
     * 加载调试模块
     * @return void
     */
    public static function loadDebug(): void
    {
        if (isset(self::$loadedModules['debug'])) {
            return;
        }

        require_once Anon_Main::MODULES_DIR . 'Debug.php';
        self::$loadedModules['debug'] = true;
    }

    /**
     * 加载扩展与插件支持
     * @return void
     */
    public static function loadExtensions(): void
    {
        if (isset(self::$loadedModules['extensions'])) {
            return;
        }

        require_once Anon_Main::MODULES_DIR . 'Anon.php';
        require_once Anon_Main::MODULES_DIR . 'System/Plugin.php';
        require_once Anon_Main::MODULES_DIR . '/../Compatibility.php';

        self::$loadedModules['extensions'] = true;
    }
}
