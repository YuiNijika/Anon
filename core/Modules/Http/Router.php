<?php

/**
 * 路由处理
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Http_Router
{
    /**
     * @var array 路由配置
     */
    private static $routes = [];

    /**
     * @var array 错误处理器配置
     */
    private static $errorHandlers = [];

    /**
     * @var string 日志文件路径
     */
    private static $logFile = __DIR__ . '/../../../logs/router.log';

    /**
     * @var bool 是否已初始化日志系统
     */
    private static $logInitialized = false;

    /**
     * @var array 路由匹配结果缓存
     */
    private static $routeMatchCache = [];

    /**
     * @var string|null 缓存的请求路径
     */
    private static $cachedRequestPath = null;

    /**
     * @var array 路由元数据缓存
     */
    private static $routerMetaCache = [];

    /**
     * @var bool 是否使用持久化元数据缓存
     */
    private static $usePersistentMetaCache = null;

    /**
     * 初始化路由系统
     * @throws RuntimeException 如果路由配置无效
     */
    public static function init(): void
    {
        try {
            // 如果是 OPTIONS 预检请求，直接返回
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                Anon_Common::Header(200, false, true);
                exit;
            }

            // 执行路由初始化前钩子
            Anon_System_Hook::do_action('router_before_init');

            // 记录路由系统启动
            if (self::isDebugEnabled()) {
                Anon_Debug::info('Router system initializing');
                Anon_Debug::startPerformance('router_init');
            }

            // 加载应用路由配置
            $autoRouter = Anon_System_Env::get('app.autoRouter', false);
            if ($autoRouter) {
                // 自动路由 扫描 Router 目录
                self::autoRegisterRoutes();
            } else {
                // 手动路由 加载 useRouter.php 配置
                $routerConfig = __DIR__ . '/../../../app/useRouter.php';
                if (file_exists($routerConfig)) {
                    self::registerAppRoutes(require $routerConfig);
                } else {
                    throw new RuntimeException("Router config file not found: {$routerConfig}");
                }
            }

            // 加载系统路由配置
            self::loadConfig();

            // 执行配置加载后钩子
            Anon_System_Hook::do_action('router_config_loaded', self::$routes, self::$errorHandlers);

            self::handleRequest();

            // 记录路由系统完成
            if (self::isDebugEnabled()) {
                Anon_Debug::endPerformance('router_init');
                Anon_Debug::info('Router system initialized successfully');
            }

            // 执行路由初始化完成钩子
            Anon_System_Hook::do_action('router_after_init');
        } catch (RuntimeException $e) {
            // 执行路由错误钩子
            Anon_System_Hook::do_action('router_init_error', $e);

            // 记录错误到调试系统
            if (self::isDebugEnabled()) {
                Anon_Debug::error("Router ERROR: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            self::logError("Router ERROR: " . $e->getMessage(), $e->getFile(), $e->getLine());
            self::handleError(500);
        }
    }

    /**
     * 加载路由配置
     */
    private static function loadConfig(): void
    {
        $routerConfig = Anon_System_Config::getRouterConfig();

        if (!is_array($routerConfig)) {
            throw new RuntimeException("Invalid router configuration");
        }

        self::$routes = $routerConfig['routes'] ?? [];
        self::$errorHandlers = $routerConfig['error_handlers'] ?? [];
    }

    /**
     * View
     */
    public static function View(string $fileView): void
    {
        if (strpos($fileView, '..') !== false || strpos($fileView, '/') === 0) {
            throw new RuntimeException("无效的路径: {$fileView}");
        }

        $baseDir = realpath(__DIR__ . '/../../../app/Router');
        $filePath = realpath($baseDir . '/' . $fileView . '.php');

        if (!$filePath || !file_exists($filePath)) {
            throw new RuntimeException("Router view file not found: {$fileView}");
        }

        if (strpos($filePath, $baseDir) !== 0) {
            throw new RuntimeException("路径遍历攻击检测: {$fileView}");
        }

        // 读取路由元数据配置
        $meta = self::readRouterMeta($filePath);

        // 应用元数据配置
        self::applyRouterMeta($meta);

        // 执行中间件
        if (isset($meta['middleware'])) {
            $middlewares = is_array($meta['middleware']) ? $meta['middleware'] : [$meta['middleware']];
            Anon_Http_Middleware::pipeline($middlewares, $_REQUEST, function () use ($filePath) {
                require $filePath;
            });
        } else {
            require $filePath;
        }
    }

    /**
     * 读取路由文件中的 Anon_RouterMeta 常量配置
     * 使用内存缓存和可选的持久化缓存提升性能
     * @param string $filePath 路由文件路径
     * @return array 路由元数据配置
     */
    private static function readRouterMeta(string $filePath): array
    {
        $defaultMeta = [
            'header' => true,
            'requireLogin' => false,
            'method' => null,
            'cache' => [
                'enabled' => false,
                'time' => 0,
            ],
        ];

        // 检查内存缓存
        $cacheKey = 'meta_' . md5($filePath);
        if (isset(self::$routerMetaCache[$cacheKey])) {
            return self::$routerMetaCache[$cacheKey];
        }

        // 检查持久化缓存（生产环境）
        if (self::shouldUsePersistentMetaCache()) {
            $persistentCache = self::loadPersistentMetaCache($filePath);
            if ($persistentCache !== null) {
                self::$routerMetaCache[$cacheKey] = $persistentCache;
                return $persistentCache;
            }
        }

        if (!file_exists($filePath)) {
            self::$routerMetaCache[$cacheKey] = $defaultMeta;
            return $defaultMeta;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            self::$routerMetaCache[$cacheKey] = $defaultMeta;
            return $defaultMeta;
        }

        $result = $defaultMeta;

        // 查找 const Anon_RouterMeta = [...] 的定义
        // const Anon_RouterMeta = [...] 或 const Anon_RouterMeta=[...]
        if (preg_match('/const\s+Anon_RouterMeta\s*=\s*(\[[\s\S]*?\]);/i', $content, $matches)) {
            try {
                $arrayStr = trim($matches[1]);
                
                // 使用 eval 解析数组
                $metaArray = null;
                // 验证只包含安全的字符
                $safePattern = '/^[\s\[\]"\',:\-0-9a-zA-Z_\.\s]+$/';
                $hasKeywords = preg_match('/(true|false|null)/i', $arrayStr);
                $unsafePattern = '/(\$|function\s*\(|eval\s*\(|exec\s*\(|system\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|fwrite\s*\(|unlink\s*\(|include\s*\(|require\s*\()/i';
                
                if ((preg_match($safePattern, $arrayStr) || $hasKeywords) && !preg_match($unsafePattern, $arrayStr)) {
                    try {
                        $metaArray = eval('return ' . $arrayStr . ';');
                    } catch (Throwable $e) {
                        if (self::isDebugEnabled()) {
                            Anon_Debug::warn("Failed to parse Anon_RouterMeta in {$filePath}: " . $e->getMessage());
                        }
                    }
                }
                
                if (is_array($metaArray)) {
                    // 验证数组结构，只允许白名单键
                    $allowedKeys = ['header', 'requireLogin', 'method', 'cors', 'response', 'code', 'token', 'middleware', 'cache'];
                    $metaArray = array_intersect_key($metaArray, array_flip($allowedKeys));
                    
                    // 验证 cache 配置结构
                    if (isset($metaArray['cache']) && is_array($metaArray['cache'])) {
                        $cacheAllowedKeys = ['enabled', 'time'];
                        $metaArray['cache'] = array_intersect_key($metaArray['cache'], array_flip($cacheAllowedKeys));
                        
                        // 类型验证
                        if (isset($metaArray['cache']['enabled']) && !is_bool($metaArray['cache']['enabled'])) {
                            unset($metaArray['cache']['enabled']);
                        }
                        if (isset($metaArray['cache']['time']) && (!is_int($metaArray['cache']['time']) || $metaArray['cache']['time'] < 0)) {
                            unset($metaArray['cache']['time']);
                        }
                    }
                    
                    $result = array_merge($defaultMeta, $metaArray);
                }
            } catch (Throwable $e) {
                // 解析失败时使用默认配置
                if (self::isDebugEnabled()) {
                    Anon_Debug::warn("Failed to parse Anon_RouterMeta in {$filePath}: " . $e->getMessage());
                }
            }
        }

        // 缓存结果
        self::$routerMetaCache[$cacheKey] = $result;
        
        // 保存到持久化缓存（生产环境）
        if (self::shouldUsePersistentMetaCache()) {
            self::savePersistentMetaCache($filePath, $result);
        }

        return $result;
    }

    /**
     * 是否使用持久化元数据缓存
     * 生产环境启用，开发环境禁用
     * @return bool
     */
    private static function shouldUsePersistentMetaCache(): bool
    {
        if (self::$usePersistentMetaCache !== null) {
            return self::$usePersistentMetaCache;
        }

        // 调试模式下禁用持久化缓存
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        self::$usePersistentMetaCache = !$isDebug;
        
        return self::$usePersistentMetaCache;
    }

    /**
     * 加载持久化元数据缓存
     * @param string $filePath 路由文件路径
     * @return array|null 缓存的元数据，不存在或过期返回 null
     */
    private static function loadPersistentMetaCache(string $filePath): ?array
    {
        try {
            $cacheKey = 'router_meta_' . md5($filePath);
            $cached = Anon_Cache::get($cacheKey);
            
            if (!is_array($cached) || !isset($cached['meta']) || !isset($cached['mtime'])) {
                return null;
            }

            // 检查缓存是否过期，基于源文件修改时间
            $sourceTime = filemtime($filePath);
            if ($sourceTime > $cached['mtime']) {
                Anon_Cache::delete($cacheKey);
                return null;
            }

            return $cached['meta'];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 保存持久化元数据缓存
     * @param string $filePath 路由文件路径
     * @param array $meta 元数据
     */
    private static function savePersistentMetaCache(string $filePath, array $meta): void
    {
        try {
            $cacheKey = 'router_meta_' . md5($filePath);
            $cacheData = [
                'meta' => $meta,
                'mtime' => filemtime($filePath)
            ];
            
            // 使用框架缓存系统，24小时过期
            Anon_Cache::set($cacheKey, $cacheData, 86400);
        } catch (Throwable $e) {
            // 缓存失败不影响业务
        }
    }

    /**
     * 清除所有路由元数据缓存
     */
    public static function clearMetaCache(): void
    {
        // 清除内存缓存
        self::$routerMetaCache = [];
        
        // 清除持久化缓存，由于使用了框架缓存系统，需要清除所有 router_meta_ 前缀的键
        // 注意：Anon_Cache 的 clear() 会清除所有缓存，这里只记录日志
        if (self::isDebugEnabled()) {
            Anon_Debug::info('Router meta cache cleared (memory cache only, file cache will expire naturally)');
        }
    }

    /**
     * 将 PHP 数组语法安全转换为 JSON 格式
     * @param string $phpArrayStr PHP 数组字符串
     * @return string|null JSON 字符串，转换失败返回 null
     */
    private static function convertPhpArrayToJson(string $phpArrayStr): ?string
    {
        // 移除注释和多余空白
        $phpArrayStr = preg_replace('/\/\*.*?\*\//s', '', $phpArrayStr);
        $phpArrayStr = preg_replace('/\/\/.*$/m', '', $phpArrayStr);
        $phpArrayStr = trim($phpArrayStr);
        
        // 验证只包含安全的字符和结构
        $safePattern = '/^[\s\[\]"\',:\-0-9a-zA-Z_\.\s]+$/';
        $hasKeywords = preg_match('/(true|false|null)/i', $phpArrayStr);
        $unsafePattern = '/(\$|function\s*\(|eval\s*\(|exec\s*\(|system\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|fwrite\s*\(|unlink\s*\(|include\s*\(|require\s*\()/i';
        
        if (!((preg_match($safePattern, $phpArrayStr) || $hasKeywords) && !preg_match($unsafePattern, $phpArrayStr))) {
            return null;
        }
        
        // 将 PHP 数组语法转换为 JSON
        // 单引号转双引号
        $jsonStr = preg_replace("/(['\"])(.*?)\\1/", '"$2"', $phpArrayStr);
        
        // PHP 布尔值和 null
        $jsonStr = preg_replace('/\btrue\b/i', 'true', $jsonStr);
        $jsonStr = preg_replace('/\bfalse\b/i', 'false', $jsonStr);
        $jsonStr = preg_replace('/\bnull\b/i', 'null', $jsonStr);
        
        // 移除数组键的引号（如果键是有效的标识符）
        $jsonStr = preg_replace('/"([a-zA-Z_][a-zA-Z0-9_]*)":/', '$1:', $jsonStr);
        
        // 验证 JSON 格式
        $testDecode = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($testDecode)) {
            return json_encode($testDecode, JSON_UNESCAPED_UNICODE);
        }
        
        return null;
    }

    /**
     * 获取路由文件路径
     * @param string $routeKey 路由标识
     * @param callable $handler 路由处理器
     * @return string|null 路由文件路径，如果无法获取则返回 null
     */
    private static function getRouteFilePath(string $routeKey, callable $handler): ?string
    {
        $routerBaseDir = __DIR__ . '/../../../app/Router';
        
        // 尝试从闭包中提取视图路径，适用于手动路由和自动路由
        if ($handler instanceof Closure) {
            $reflection = new ReflectionFunction($handler);
            $uses = $reflection->getStaticVariables();
            // 手动路由使用 $view，自动路由使用 $viewPath
            $view = $uses['view'] ?? $uses['viewPath'] ?? null;
            if ($view) {
                $filePath = realpath($routerBaseDir . '/' . $view . '.php');
                if ($filePath && file_exists($filePath)) {
                    return $filePath;
                }
            }
        }
        
        // 尝试根据路由路径推断文件路径
        // 将路由路径转换为文件路径
        // 例如：/user/profile -> User/Profile.php
        $filePath = trim($routeKey, '/');
        if (empty($filePath)) {
            $filePath = 'Index';
        } else {
            // 将路径段转换为首字母大写的格式
            $segments = explode('/', $filePath);
            $segments = array_map(function($segment) {
                // 将连字符转换为下划线，然后转换为首字母大写
                $segment = str_replace('-', '_', $segment);
                return ucfirst(strtolower($segment));
            }, $segments);
            $filePath = implode('/', $segments);
        }
        
        $fullPath = $routerBaseDir . '/' . $filePath . '.php';
        if (file_exists($fullPath)) {
            return realpath($fullPath);
        }
        
        // 尝试 Index.php
        $indexPath = $routerBaseDir . '/' . $filePath . '/Index.php';
        if (file_exists($indexPath)) {
            return realpath($indexPath);
        }
        
        return null;
    }

    /**
     * 应用路由元数据配置
     * @param array $meta 路由元数据配置
     */
    private static function applyRouterMeta(array $meta): void
    {
        // 设置 Header
        if ($meta['header'] ?? true) {
            $code = $meta['code'] ?? 200;
            $response = isset($meta['response']) && $meta['response'] !== null ? $meta['response'] : true;
            $cors = isset($meta['cors']) && $meta['cors'] !== null ? $meta['cors'] : true;
            Anon_Common::Header($code, $response, $cors);
        }

        // Token 验证
        if (isset($meta['token'])) {
            if ($meta['token'] === true) {
                Anon_Http_Request::requireToken(true, false);
            }
            // token 为 false 时，跳过 Token 验证，不调用 requireToken
            // token 为 null 时，使用全局配置
        } else {
            // 如果路由元数据中没有设置 token，使用全局配置
            if (Anon_Auth_Token::isEnabled()) {
                Anon_Http_Request::requireToken(true, false);
            }
        }

        // 检查登录状态
        if (!empty($meta['requireLogin'])) {
            // 支持 requireLogin 为字符串（自定义消息）或布尔值（使用默认消息/钩子）
            $message = is_string($meta['requireLogin']) ? $meta['requireLogin'] : null;
            Anon_Common::RequireLogin($message);
        }

        // 检查 HTTP 方法
        if (!empty($meta['method'])) {
            $allowedMethods = is_array($meta['method']) ? $meta['method'] : [$meta['method']];
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            if (!in_array(strtoupper($requestMethod), array_map('strtoupper', $allowedMethods))) {
                Anon_Common::Header(405);
                Anon_Http_Response::error('不允许的请求方法', [
                    'allowed' => $allowedMethods,
                    'received' => $requestMethod
                ], 405);
                exit;
            }
        }

        // 设置缓存控制头
        self::applyCacheHeaders($meta);
    }

    /**
     * 应用缓存控制头
     * @param array $meta 路由元数据
     */
    private static function applyCacheHeaders(array $meta): void
    {
        // 获取缓存配置
        if (isset($meta['cache'])) {
            $cacheConfig = $meta['cache'];
            $cacheEnabled = $cacheConfig['enabled'] ?? false;
            $cacheTime = $cacheConfig['time'] ?? 0;
        } else {
            $cacheEnabled = Anon_System_Env::get('app.cache.enabled', false);
            $cacheTime = Anon_System_Env::get('app.cache.time', 0);
        }
        
        // 检查是否应该排除缓存
        if (self::shouldExcludeFromCache($meta)) {
            $cacheEnabled = false;
            $cacheTime = 0;
        }
        
        // 设置缓存头
        if ($cacheEnabled && $cacheTime > 0) {
            header('Cache-Control: public, max-age=' . $cacheTime);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        } else {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * 检查路由是否应该排除缓存
     * @param array $meta 路由元数据
     * @return bool 是否应该排除缓存
     */
    private static function shouldExcludeFromCache(array $meta): bool
    {
        $requestPath = self::getRequestPath();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // 检查排除路径模式
        $excludePatterns = Anon_System_Env::get('app.cache.exclude', [
            '/auth/',
            '/anon/debug/',
            '/anon/install',
        ]);
        
        if (is_array($excludePatterns)) {
            foreach ($excludePatterns as $pattern) {
                if (strpos($requestPath, $pattern) === 0) {
                    return true;
                }
            }
        }
        
        // 需要登录的接口不缓存
        if ($meta['requireLogin'] ?? false) {
            return true;
        }
        
        // POST 请求不缓存
        if ($requestMethod === 'POST') {
            return true;
        }
        
        return false;
    }

    /**
     * 记录路由调试信息
     * @param string $requestPath 请求路径
     */
    private static function logRouteDebug(string $requestPath): void
    {
        error_log("Router: Processing request path: " . $requestPath);
        error_log("Router: Available routes: " . json_encode(array_keys(self::$routes)));
        if (isset(self::$routes[$requestPath])) {
            error_log("Router: Exact match found for: " . $requestPath);
        } else {
            error_log("Router: No exact match for: " . $requestPath);
        }
    }

    /**
     * 结束路由匹配性能记录
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @param array $context 上下文数据
     */
    private static function endRouteMatching(string $message, string $level = 'info', array $context = []): void
    {
        if (self::isDebugEnabled()) {
            Anon_Debug::endPerformance('route_matching');
            if ($level === 'warn') {
                Anon_Debug::warn($message, $context);
            } else {
                Anon_Debug::info($message, $context);
            }
        }
    }

    /**
     * 从树形配置生成并注册到配置中心的应用路由
     * @param array $routeTree 路由树
     * @param string $basePath 基础路径
     */
    public static function registerAppRoutes(array $routeTree, string $basePath = ''): void
    {
        foreach ($routeTree as $key => $value) {
            $currentPath = $basePath ? $basePath . '/' . $key : $key;

            // 叶子节点：字符串直接作为视图路径，数组则检查view键
            if (is_string($value)) {
                $view = $value;
            } elseif (is_array($value) && isset($value['view'])) {
                $view = $value['view'];
            } else {
                // 递归子节点
                self::registerAppRoutes($value, $currentPath);
                continue;
            }

            // 注册前钩子
            Anon_System_Hook::do_action('app_before_register_route', $currentPath, $view, false);

            // 生成处理器，登录检查等由Anon_RouterMeta统一处理
            $handler = function () use ($view, $currentPath) {
                // 执行最终视图，View方法会自动读取并应用Anon_RouterMeta配置
                Anon_Http_Router::View($view);
            };

            // 注册到配置
            Anon_System_Config::addRoute($currentPath, $handler);

            // 调试日志
            if (self::isDebugEnabled()) {
                self::debugLog("Registered route: {$currentPath} -> {$view}");
            }

            // 注册后钩子
            Anon_System_Hook::do_action('app_after_register_route', $currentPath, $view, false);
        }
    }

    /**
     * 扫描 Router 目录自动注册路由
     * 类似 Nuxt 的自动路由功能，不区分大小写，所有路由转为小写
     */
    private static function autoRegisterRoutes(): void
    {
        $routerDir = __DIR__ . '/../../../app/Router';
        if (!is_dir($routerDir)) {
            return;
        }

        // 执行自动路由扫描前钩子
        Anon_System_Hook::do_action('router_before_auto_register');

        // 扫描目录并注册路由
        self::scanDirectory($routerDir, '');

        // 执行自动路由扫描后钩子
        Anon_System_Hook::do_action('router_after_auto_register');
    }

    /**
     * 递归扫描目录并注册路由
     * @param string $dir 目录路径
     * @param string $basePath 小写的基础路由路径
     */
    private static function scanDirectory(string $dir, string $basePath): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            // 跳过隐藏文件和当前/父目录
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            $itemName = pathinfo($item, PATHINFO_FILENAME); // 去除扩展名
            $itemNameLower = strtolower($itemName); // 转为小写
            $itemNameRoute = str_replace('_', '-', $itemNameLower); // 下划线转换为连字符

            // 构建路由路径
            if (empty($basePath)) {
                $routePath = '/' . $itemNameRoute;
            } else {
                $routePath = $basePath . '/' . $itemNameRoute;
            }

            if (is_dir($itemPath)) {
                // 递归扫描子目录
                self::scanDirectory($itemPath, $routePath);
            } elseif (is_file($itemPath) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                // 注册 PHP 文件为路由
                self::registerAutoRoute($routePath, $itemPath, $dir);
            }
        }
    }

    /**
     * 注册自动路由
     * @param string $routePath 路由路径
     * @param string $filePath 文件完整路径
     * @param string $baseDir Router 基础目录
     */
    private static function registerAutoRoute(string $routePath, string $filePath, string $baseDir): void
    {
        // 计算相对路径
        $routerBaseDir = __DIR__ . '/../../../app/Router';
        $relativePath = str_replace($routerBaseDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $viewPath = str_replace('.php', '', $relativePath);

        // 获取文件名
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        $isIndex = strtolower($fileName) === 'index';

        // 需要注册的路由路径列表
        $routesToRegister = [$routePath];

        // 如果是Index.php，同时注册父级路径
        if ($isIndex) {
            // 计算父级路径
            if ($routePath === '/index') {
                // 根目录下的Index.php，注册 /
                $routesToRegister[] = '/';
            } else {
                // 子目录下的Index.php，注册父级路径
                $parentPath = preg_replace('/\/index$/', '', $routePath);
                if (!empty($parentPath)) {
                    $routesToRegister[] = $parentPath;
                }
            }
        }

        // 注册所有路由路径
        foreach ($routesToRegister as $path) {
            // 注册前钩子
            Anon_System_Hook::do_action('app_before_register_route', $path, $viewPath, false);

            // 生成处理器
            $handler = function () use ($viewPath, $path) {
                Anon_Http_Router::View($viewPath);
            };

            // 注册到配置
            Anon_System_Config::addRoute($path, $handler);

            // 调试日志
            if (self::isDebugEnabled()) {
                self::debugLog("Auto registered route: {$path} -> {$viewPath}");
            }

            // 注册后钩子
            Anon_System_Hook::do_action('app_after_register_route', $path, $viewPath, false);
        }
    }


    /**
     * 是否启用调试模式
     * @return bool
     */
    private static function isDebugEnabled(): bool
    {
        return defined('ANON_ROUTER_DEBUG') && ANON_ROUTER_DEBUG;
    }

    /**
     * 初始化日志系统
     */
    private static function initLogSystem(): void
    {
        if (self::$logInitialized) {
            return;
        }

        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        self::$logInitialized = true;
    }

    /**
     * 记录调试信息
     * @param string $message
     */
    private static function debugLog(string $message): void
    {
        if (self::isDebugEnabled()) {
            self::writeLog("[DEBUG] " . $message);
        }
    }

    /**
     * 记录错误信息
     * @param string $message
     * @param string $file
     * @param int $line
     */
    private static function logError(string $message, string $file = '', int $line = 0): void
    {
        $logMessage = "[ERROR] " . $message;
        if ($file) {
            $logMessage .= " in {$file}";
            if ($line) {
                $logMessage .= " on line {$line}";
            }
        }
        self::writeLog($logMessage);
    }

    /**
     * 写入日志文件
     * @param string $message
     */
    private static function writeLog(string $message): void
    {
        self::initLogSystem();
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;

        file_put_contents(
            self::$logFile,
            $logMessage,
            FILE_APPEND | LOCK_EX
        );
    }


    /**
     * 处理当前请求
     */
    private static function handleRequest(): void
    {
        try {
            $requestPath = self::getRequestPath();
            self::debugLog("Request path: " . $requestPath);

            if (self::isDebugEnabled()) {
                self::logRouteDebug($requestPath);
            }

            $requestPath = Anon_System_Hook::apply_filters('router_request_path', $requestPath);
            Anon_System_Hook::do_action('router_before_request', $requestPath);

            if (self::isDebugEnabled()) {
                Anon_Debug::info("Processing request: " . $requestPath, [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                Anon_Debug::startPerformance('route_matching');
            }

            // 检查路由匹配缓存
            if (isset(self::$routeMatchCache[$requestPath])) {
                $cachedMatch = self::$routeMatchCache[$requestPath];
                if ($cachedMatch['type'] === 'exact') {
                    self::endRouteMatching("Route matched (cached): " . $requestPath);
                    Anon_System_Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                    self::dispatch($requestPath);
                    return;
                } elseif ($cachedMatch['type'] === 'param' && $cachedMatch['route']) {
                    self::endRouteMatching("Parameter route matched (cached): " . $cachedMatch['route']['route']);
                    Anon_System_Hook::do_action('router_param_route_matched', $cachedMatch['route']['route'], $cachedMatch['route']['params']);
                    self::dispatchWithParams($cachedMatch['route']['route'], $cachedMatch['route']['params']);
                    return;
                } elseif ($cachedMatch['type'] === 'none') {
                    self::endRouteMatching("No route matched (cached): " . $requestPath, 'warn');
                    Anon_System_Hook::do_action('router_no_match', $requestPath);
                    self::handleError(404);
                    return;
                }
            }

            // 精确匹配
            if (isset(self::$routes[$requestPath])) {
                self::debugLog("Matched route: " . $requestPath);

                // 缓存匹配结果
                self::$routeMatchCache[$requestPath] = ['type' => 'exact', 'route' => $requestPath];
                self::endRouteMatching("Route matched: " . $requestPath);
                Anon_System_Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                self::dispatch($requestPath);
            } else {
                // 参数路由匹配
                $matchedRoute = self::matchParameterRoute($requestPath);
                if ($matchedRoute) {
                    self::debugLog("Matched parameter route: " . $matchedRoute['route']);

                    // 缓存匹配结果
                    self::$routeMatchCache[$requestPath] = ['type' => 'param', 'route' => $matchedRoute];

                    self::endRouteMatching("Parameter route matched: " . $matchedRoute['route'], 'info', [
                        'params' => $matchedRoute['params']
                    ]);
                    Anon_System_Hook::do_action('router_param_route_matched', $matchedRoute['route'], $matchedRoute['params']);
                    self::dispatchWithParams($matchedRoute['route'], $matchedRoute['params']);
                } else {
                    self::debugLog("No route matched for: " . $requestPath);

                    // 缓存未匹配结果
                    self::$routeMatchCache[$requestPath] = ['type' => 'none', 'route' => null];
                    self::endRouteMatching("No route matched for: " . $requestPath, 'warn');
                    Anon_System_Hook::do_action('router_no_match', $requestPath);
                    self::handleError(404);
                }
            }
        } catch (Anon_System_Exception $e) {
            // 处理框架异常
            Anon_System_Hook::do_action('router_request_error', $e, $requestPath ?? '');
            
            if (self::isDebugEnabled()) {
                Anon_Debug::error("Request handling failed: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            Anon_Common::Header($e->getHttpCode());
            Anon_Http_Response::handleException($e);
            exit;
        } catch (Throwable $e) {
            // 执行请求处理错误钩子
            Anon_System_Hook::do_action('router_request_error', $e, $requestPath ?? '');

            if (self::isDebugEnabled()) {
                Anon_Debug::error("Request handling failed: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            self::logError("Request handling failed: " . $e->getMessage(), $e->getFile(), $e->getLine());
            Anon_Common::Header(500);
            Anon_Http_Response::handleException($e);
            exit;
        }
    }

    /**
     * 执行路由处理器
     * @param string $routeKey 路由标识
     */
    private static function dispatch(string $routeKey): void
    {
        $handler = self::$routes[$routeKey];
        self::debugLog("Dispatching route: " . $routeKey);

        if (!is_callable($handler)) {
            self::debugLog("Invalid handler for route: " . $routeKey);

            if (self::isDebugEnabled()) {
                Anon_Debug::error("Invalid handler for route: " . $routeKey);
            }

            self::handleError(404);
            return;
        }

        try {
            // 获取路由元数据
            // 对于自动路由，路由元数据会由 View 方法从路由文件中读取，这里不需要提前应用
            $routeMeta = Anon_System_Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                // 手动路由的元数据从配置中获取，直接应用
                self::applyRouterMeta($routeMeta);
            }
            // 自动路由的元数据由 View 方法处理，这里不应用空数组，避免触发全局 token 验证

            // 执行路由执行前钩子
            Anon_System_Hook::do_action('router_before_dispatch', $routeKey, $handler);

            // 开始执行性能监控
            if (self::isDebugEnabled()) {
                Anon_Debug::startPerformance('route_execution_' . $routeKey);
            }

            $handler();

            // 结束执行性能监控
            if (self::isDebugEnabled()) {
                Anon_Debug::endPerformance('route_execution_' . $routeKey);
                Anon_Debug::info("Route executed successfully: " . $routeKey);
            }

            // 执行路由执行后钩子
            Anon_System_Hook::do_action('router_after_dispatch', $routeKey, $handler);
        } catch (Anon_System_Exception $e) {
            // 处理框架异常
            Anon_System_Hook::do_action('router_dispatch_error', $e, $routeKey, $handler);
            
            if (self::isDebugEnabled()) {
                Anon_Debug::error("Route execution failed [{$routeKey}]: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            Anon_Common::Header($e->getHttpCode());
            Anon_Http_Response::handleException($e);
            exit;
        } catch (Throwable $e) {
            // 执行路由执行错误钩子
            Anon_System_Hook::do_action('router_dispatch_error', $e, $routeKey, $handler);

            if (self::isDebugEnabled()) {
                Anon_Debug::error("Route execution failed [{$routeKey}]: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            Anon_Common::Header(500);
            Anon_Http_Response::handleException($e);
            exit;
        }

        exit;
    }

    /**
     * 获取规范化请求路径
     * @return string 不含查询参数的路径
     */
    private static function getRequestPath(): string
    {
        // 使用缓存避免重复解析
        if (self::$cachedRequestPath !== null) {
            return self::$cachedRequestPath;
        }

        // 优先从 GET 参数 s 获取路径
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $path = $_GET['s'];
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($requestUri, PHP_URL_PATH);
        }

        // 去掉查询参数，但保留前导斜杠
        $path = strstr($path, '?', true) ?: $path;

        // 去除前端代理前缀
        if (strpos($path, '/apiService') === 0) {
            $path = substr($path, strlen('/apiService'));
        }

        // 确保路径以 / 开头
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        // 缓存结果
        self::$cachedRequestPath = $path;
        return $path;
    }

    /**
     * 匹配带参数的路由
     * @param string $requestPath 请求路径
     * @return array|null 匹配结果，包含路由和参数
     */
    private static function matchParameterRoute(string $requestPath): ?array
    {
        foreach (self::$routes as $routePattern => $handler) {
            // 匹配参数路由，如 /user/{id}
            if (strpos($routePattern, '{') !== false) {
                // 转换为正则表达式
                $pattern = preg_quote($routePattern, '/');
                $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $pattern);
                $pattern = '/^' . $pattern . '$/';

                // 检查是否匹配
                if (preg_match($pattern, $requestPath, $matches)) {
                    // 提取参数名
                    preg_match_all('/\{([^\/]+)\}/', $routePattern, $paramNames);
                    $paramNames = $paramNames[1];

                    // 构建参数数组
                    $params = [];
                    for ($i = 0; $i < count($paramNames); $i++) {
                        $params[$paramNames[$i]] = $matches[$i + 1];
                    }

                    return [
                        'route' => $routePattern,
                        'params' => $params
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 带参数执行路由处理器
     * @param string $routeKey 路由标识
     * @param array $params 路由参数
     */
    private static function dispatchWithParams(string $routeKey, array $params): void
    {
        $handler = self::$routes[$routeKey];
        self::debugLog("Dispatching route with params: " . $routeKey);

        if (!is_callable($handler)) {
            self::debugLog("Invalid handler for route: " . $routeKey);
            self::handleError(404);
            return;
        }

        try {
            // 获取路由元数据
            // 对于自动路由，路由元数据会由 View 方法从路由文件中读取，这里不需要提前应用
            $routeMeta = Anon_System_Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                // 手动路由的元数据从配置中获取，直接应用
                self::applyRouterMeta($routeMeta);
            }
            // 自动路由的元数据由 View 方法处理，这里不应用空数组，避免触发全局 token 验证

            // 将路由参数添加到$_GET
            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }

            $handler();
        } catch (Anon_System_Exception $e) {
            // 处理框架异常
            Anon_Common::Header($e->getHttpCode());
            Anon_Http_Response::handleException($e);
            exit;
        } catch (Throwable $e) {
            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            Anon_Common::Header(500);
            Anon_Http_Response::handleException($e);
            exit;
        }

        exit;
    }

    /**
     * 处理HTTP错误
     * @param int $statusCode HTTP状态码
     */
    private static function handleError(int $statusCode): void
    {
        http_response_code($statusCode);

        if (
            isset(self::$errorHandlers[$statusCode]) &&
            is_callable(self::$errorHandlers[$statusCode])
        ) {
            self::$errorHandlers[$statusCode]();
        } else {
            self::showDefaultError($statusCode);
        }

        exit;
    }

    /**
     * 显示默认错误页
     * @param int $statusCode HTTP状态码
     */
    private static function showDefaultError(int $statusCode): void
    {
        Anon_Common::Header($statusCode);
        $messages = [
            400 => [
                'code' => 400,
                'message' => '400 Bad Request',
            ],
            404 => [
                'code' => 404,
                'message' => '404 Not Found'
            ],
            500 => [
                'code' => 500,
                'message' => '500 Internal Server Error',
            ]
        ];

        echo json_encode($messages[$statusCode] ?? [
            'code' => $statusCode,
            'message' => "HTTP {$statusCode}",
        ]);
    }
}

// 初始化路由
Anon_Http_Router::init();
