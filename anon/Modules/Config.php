<?php

/**
 * Anon配置
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Config
{
    /**
     * 路由配置
     * @var array
     */
    private static $routerConfig = [
        'routes' => [],
        'error_handlers' => []
    ];

    /**
     * 注册路由
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     */
    public static function addRoute(string $path, callable $handler)
    {
        // 规范化路由键，确保以 / 开头，避免匹配失败
        $normalized = (strpos($path, '/') === 0) ? $path : '/' . $path;
        self::$routerConfig['routes'][$normalized] = $handler;
    }

    /**
     * 注册错误处理器
     * @param int $code HTTP状态码
     * @param callable $handler 处理函数
     */
    public static function addErrorHandler(int $code, callable $handler)
    {
        self::$routerConfig['error_handlers'][$code] = $handler;
    }

    /**
     * 获取路由配置
     * @return array 路由配置数组
     */
    public static function getRouterConfig(): array
    {
        return self::$routerConfig;
    }

    /**
     * 判断程序是否安装
     * @return bool
     */
    public static function isInstalled(): bool
    {
        return ANON_INSTALLED;
    }

    /**
     * 初始化系统路由
     */
    public static function initSystemRoutes()
    {
        // 调试输出路由注册信息
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registering system routes...");
        }
        
        self::addRoute('/anon/common/system', function() {
            Anon_Common::Header();
            Anon_ResponseHelper::success(Anon_Common::SystemInfo(), '获取系统信息成功');
        });
        self::addRoute('/anon/common/client-ip', function() {
            Anon_Common::Header();
            Anon_ResponseHelper::success(Anon_Common::GetClientIp(), '获取客户端IP成功');
        });

        // 注册Install路由
        self::addRoute('/anon/install', [Anon_Install::class, 'index']);
        // 注册anon路由
        self::addRoute('/anon', function() {
            // 检查系统是否已安装
            if (self::isInstalled()) {
                Anon_Common::Header(403);
                echo json_encode([
                    'code' => 403,
                    'message' => 'Forbidden'
                ]);
                exit;
            } else {
                // 如果未安装，调用安装类
                Anon_Install::index();
            }
        });
        
        // 调试输出已注册的路由
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registered routes: " . json_encode(array_keys(self::$routerConfig['routes'])));
        }
    }

    /**
     * 初始化应用路由
     */
    public static function initAppRoutes()
    {
        // 调试输出路由注册信息
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registering app routes...");
        }

        // 注册Debug路由
        if (class_exists('Anon_Debug')) {
            self::addRoute('/anon/debug/info', [Anon_Debug::class, 'debugInfo']);
            self::addRoute('/anon/debug/performance', [Anon_Debug::class, 'performanceApi']);
            self::addRoute('/anon/debug/logs', [Anon_Debug::class, 'logs']);
            self::addRoute('/anon/debug/errors', [Anon_Debug::class, 'errors']);
            self::addRoute('/anon/debug/hooks', [Anon_Debug::class, 'hooks']);
            self::addRoute('/anon/debug/tools', [Anon_Debug::class, 'tools']);
            self::addRoute('/anon/debug/clear', [Anon_Debug::class, 'clearData']);
            self::addRoute('/anon/debug/console', [Anon_Debug::class, 'console']);
        }

        // 调试输出已注册的路由
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registered app routes: " . json_encode(array_keys(self::$routerConfig['routes'])));
        }
    }
}
