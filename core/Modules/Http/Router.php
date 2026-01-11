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
     * @var array 错误处理器
     */
    private static $errorHandlers = [];

    /**
     * @var string 日志路径
     */
    private static $logFile = __DIR__ . '/../../../logs/router.log';

    /**
     * @var bool 日志初始化
     */
    private static $logInitialized = false;

    /**
     * @var array 匹配缓存
     */
    private static $routeMatchCache = [];

    /**
     * @var string|null 路径缓存
     */
    private static $cachedRequestPath = null;

    /**
     * @var array 元数据缓存
     */
    private static $routerMetaCache = [];

    /**
     * @var bool 持久化缓存
     */
    private static $usePersistentMetaCache = null;

    /**
     * 初始化
     * @throws RuntimeException
     */
    public static function init(): void
    {
        self::loadRoutes();
        self::dispatchRequest();
    }

    /**
     * 加载配置
     */
    public static function loadRoutes(): void
    {
        if (!empty(self::$routes)) {
            return;
        }

        try {
            Anon_System_Hook::do_action('router_before_init');

            if (self::isDebugEnabled()) {
                Anon_Debug::info('Router system initializing');
                Anon_Debug::startPerformance('router_init');
            }

            $autoRouter = Anon_System_Env::get('app.autoRouter', false);
            if ($autoRouter) {
                self::autoRegisterRoutes();
            } else {
                $routerConfig = __DIR__ . '/../../../app/useRouter.php';
                if (file_exists($routerConfig)) {
                    self::registerAppRoutes(require $routerConfig);
                } else {
                    throw new RuntimeException("Router config file not found: {$routerConfig}");
                }
            }

            self::loadConfig();

            Anon_System_Hook::do_action('router_config_loaded', self::$routes, self::$errorHandlers);

            if (self::isDebugEnabled()) {
                Anon_Debug::endPerformance('router_init');
                Anon_Debug::info('Router system initialized successfully');
            }

            Anon_System_Hook::do_action('router_after_init');
        } catch (RuntimeException $e) {
            Anon_System_Hook::do_action('router_init_error', $e);

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
     * 分发请求
     */
    public static function dispatchRequest(): void
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            Anon_Common::Header(200, false, true);
            exit;
        }

        self::handleRequest();
    }

    /**
     * 加载配置
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

        $meta = self::readRouterMeta($filePath);

        self::applyRouterMeta($meta);

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
     * 读取元数据
     * @param string $filePath 文件路径
     * @return array
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

        $cacheKey = 'meta_' . md5($filePath);
        if (isset(self::$routerMetaCache[$cacheKey])) {
            return self::$routerMetaCache[$cacheKey];
        }

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

        if (preg_match('/const\s+Anon_RouterMeta\s*=\s*(\[[\s\S]*?\]);/i', $content, $matches)) {
            try {
                $arrayStr = trim($matches[1]);
                
                $metaArray = null;
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
                    $allowedKeys = ['header', 'requireLogin', 'method', 'cors', 'response', 'code', 'token', 'middleware', 'cache'];
                    $metaArray = array_intersect_key($metaArray, array_flip($allowedKeys));
                    
                    if (isset($metaArray['cache']) && is_array($metaArray['cache'])) {
                        $cacheAllowedKeys = ['enabled', 'time'];
                        $metaArray['cache'] = array_intersect_key($metaArray['cache'], array_flip($cacheAllowedKeys));
                        
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
                if (self::isDebugEnabled()) {
                    Anon_Debug::warn("Failed to parse Anon_RouterMeta in {$filePath}: " . $e->getMessage());
                }
            }
        }

        self::$routerMetaCache[$cacheKey] = $result;
        
        if (self::shouldUsePersistentMetaCache()) {
            self::savePersistentMetaCache($filePath, $result);
        }

        return $result;
    }

    /**
     * 检查持久化启用
     * @return bool
     */
    private static function shouldUsePersistentMetaCache(): bool
    {
        if (self::$usePersistentMetaCache !== null) {
            return self::$usePersistentMetaCache;
        }

        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        self::$usePersistentMetaCache = !$isDebug;
        
        return self::$usePersistentMetaCache;
    }

    /**
     * 加载持久化
     * @param string $filePath 文件路径
     * @return array|null
     */
    private static function loadPersistentMetaCache(string $filePath): ?array
    {
        try {
            $cacheKey = 'router_meta_' . md5($filePath);
            $cached = Anon_Cache::get($cacheKey);
            
            if (!is_array($cached) || !isset($cached['meta']) || !isset($cached['mtime'])) {
                return null;
            }

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
     * 保存持久化
     * @param string $filePath 文件路径
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
            
            Anon_Cache::set($cacheKey, $cacheData, 86400);
        } catch (Throwable $e) {
            // 忽略
        }
    }

    /**
     * 清除元数据缓存
     */
    public static function clearMetaCache(): void
    {
        self::$routerMetaCache = [];
        
        if (self::isDebugEnabled()) {
            Anon_Debug::info('Router meta cache cleared (memory cache only, file cache will expire naturally)');
        }
    }

    /**
     * PHP数组转JSON
     * @param string $phpArrayStr PHP数组字符串
     * @return string|null
     */
    private static function convertPhpArrayToJson(string $phpArrayStr): ?string
    {
        $phpArrayStr = preg_replace('/\/\*.*?\*\//s', '', $phpArrayStr);
        $phpArrayStr = preg_replace('/\/\/.*$/m', '', $phpArrayStr);
        $phpArrayStr = trim($phpArrayStr);
        
        $safePattern = '/^[\s\[\]"\',:\-0-9a-zA-Z_\.\s]+$/';
        $hasKeywords = preg_match('/(true|false|null)/i', $phpArrayStr);
        $unsafePattern = '/(\$|function\s*\(|eval\s*\(|exec\s*\(|system\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|fwrite\s*\(|unlink\s*\(|include\s*\(|require\s*\()/i';
        
        if (!((preg_match($safePattern, $phpArrayStr) || $hasKeywords) && !preg_match($unsafePattern, $phpArrayStr))) {
            return null;
        }
        
        $jsonStr = preg_replace("/(['\"])(.*?)\\1/", '"$2"', $phpArrayStr);
        
        $jsonStr = preg_replace('/\btrue\b/i', 'true', $jsonStr);
        $jsonStr = preg_replace('/\bfalse\b/i', 'false', $jsonStr);
        $jsonStr = preg_replace('/\bnull\b/i', 'null', $jsonStr);
        
        $jsonStr = preg_replace('/"([a-zA-Z_][a-zA-Z0-9_]*)":/', '$1:', $jsonStr);
        
        $testDecode = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($testDecode)) {
            return json_encode($testDecode, JSON_UNESCAPED_UNICODE);
        }
        
        return null;
    }

    /**
     * 获取文件路径
     * @param string $routeKey 路由标识
     * @param callable $handler 处理器
     * @return string|null
     */
    private static function getRouteFilePath(string $routeKey, callable $handler): ?string
    {
        $routerBaseDir = __DIR__ . '/../../../app/Router';
        
        if ($handler instanceof Closure) {
            $reflection = new ReflectionFunction($handler);
            $uses = $reflection->getStaticVariables();
            $view = $uses['view'] ?? $uses['viewPath'] ?? null;
            if ($view) {
                $filePath = realpath($routerBaseDir . '/' . $view . '.php');
                if ($filePath && file_exists($filePath)) {
                    return $filePath;
                }
            }
        }
        
        $filePath = trim($routeKey, '/');
        if (empty($filePath)) {
            $filePath = 'Index';
        } else {
            $segments = explode('/', $filePath);
            $segments = array_map(function($segment) {
                $segment = str_replace('-', '_', $segment);
                return ucfirst(strtolower($segment));
            }, $segments);
            $filePath = implode('/', $segments);
        }
        
        $fullPath = $routerBaseDir . '/' . $filePath . '.php';
        if (file_exists($fullPath)) {
            return realpath($fullPath);
        }
        
        $indexPath = $routerBaseDir . '/' . $filePath . '/Index.php';
        if (file_exists($indexPath)) {
            return realpath($indexPath);
        }
        
        return null;
    }

    /**
     * 应用元数据
     * @param array $meta 元数据
     */
    private static function applyRouterMeta(array $meta): void
    {
        if ($meta['header'] ?? true) {
            $code = $meta['code'] ?? 200;
            $response = isset($meta['response']) && $meta['response'] !== null ? $meta['response'] : true;
            $cors = isset($meta['cors']) && $meta['cors'] !== null ? $meta['cors'] : true;
            Anon_Common::Header($code, $response, $cors);
        }

        if (isset($meta['token'])) {
            if ($meta['token'] === true) {
                Anon_Http_Request::requireToken(true, false);
            }
        } else {
            if (Anon_Auth_Token::isEnabled()) {
                Anon_Http_Request::requireToken(true, false);
            }
        }

        if (!empty($meta['requireLogin'])) {
            $message = is_string($meta['requireLogin']) ? $meta['requireLogin'] : null;
            Anon_Common::RequireLogin($message);
        }

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

        self::applyCacheHeaders($meta);
    }

    /**
     * 应用缓存
     * @param array $meta 元数据
     */
    private static function applyCacheHeaders(array $meta): void
    {
        if (isset($meta['cache'])) {
            $cacheConfig = $meta['cache'];
            $cacheEnabled = $cacheConfig['enabled'] ?? false;
            $cacheTime = $cacheConfig['time'] ?? 0;
        } else {
            $cacheEnabled = Anon_System_Env::get('app.cache.enabled', false);
            $cacheTime = Anon_System_Env::get('app.cache.time', 0);
        }
        
        if (self::shouldExcludeFromCache($meta)) {
            $cacheEnabled = false;
            $cacheTime = 0;
        }
        
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
     * 检查排除缓存
     * @param array $meta 元数据
     * @return bool
     */
    private static function shouldExcludeFromCache(array $meta): bool
    {
        $requestPath = self::getRequestPath();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
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
        
        if ($meta['requireLogin'] ?? false) {
            return true;
        }
        
        if ($requestMethod === 'POST') {
            return true;
        }
        
        return false;
    }

    /**
     * 记录调试
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
     * 结束性能记录
     * @param string $message 消息
     * @param string $level 级别
     * @param array $context 上下文
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
     * 注册应用路由
     * @param array $routeTree 路由树
     * @param string $basePath 基础路径
     */
    public static function registerAppRoutes(array $routeTree, string $basePath = ''): void
    {
        foreach ($routeTree as $key => $value) {
            $currentPath = $basePath ? $basePath . '/' . $key : $key;

            if (is_string($value)) {
                $view = $value;
            } elseif (is_array($value) && isset($value['view'])) {
                $view = $value['view'];
            } else {
                self::registerAppRoutes($value, $currentPath);
                continue;
            }

            Anon_System_Hook::do_action('app_before_register_route', $currentPath, $view, false);

            $handler = function () use ($view, $currentPath) {
                Anon_Http_Router::View($view);
            };

            Anon_System_Config::addRoute($currentPath, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Registered route: {$currentPath} -> {$view}");
            }

            Anon_System_Hook::do_action('app_after_register_route', $currentPath, $view, false);
        }
    }

    /**
     * 自动注册路由
     */
    private static function autoRegisterRoutes(): void
    {
        $routerDir = __DIR__ . '/../../../app/Router';
        if (!is_dir($routerDir)) {
            return;
        }

        Anon_System_Hook::do_action('router_before_auto_register');

        self::scanDirectory($routerDir, '');

        Anon_System_Hook::do_action('router_after_auto_register');
    }

    /**
     * 递归扫描
     * @param string $dir 目录
     * @param string $basePath 基础路径
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
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            $itemName = pathinfo($item, PATHINFO_FILENAME);
            $itemNameLower = strtolower($itemName);
            $itemNameRoute = str_replace('_', '-', $itemNameLower);

            if (empty($basePath)) {
                $routePath = '/' . $itemNameRoute;
            } else {
                $routePath = $basePath . '/' . $itemNameRoute;
            }

            if (is_dir($itemPath)) {
                self::scanDirectory($itemPath, $routePath);
            } elseif (is_file($itemPath) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                self::registerAutoRoute($routePath, $itemPath, $dir);
            }
        }
    }

    /**
     * 注册自动路由
     * @param string $routePath 路径
     * @param string $filePath 文件
     * @param string $baseDir 基础目录
     */
    private static function registerAutoRoute(string $routePath, string $filePath, string $baseDir): void
    {
        $routerBaseDir = __DIR__ . '/../../../app/Router';
        $relativePath = str_replace($routerBaseDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $viewPath = str_replace('.php', '', $relativePath);

        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        $isIndex = strtolower($fileName) === 'index';

        $routesToRegister = [$routePath];

        if ($isIndex) {
            if ($routePath === '/index') {
                $routesToRegister[] = '/';
            } else {
                $parentPath = preg_replace('/\/index$/', '', $routePath);
                if (!empty($parentPath)) {
                    $routesToRegister[] = $parentPath;
                }
            }
        }

        foreach ($routesToRegister as $path) {
            Anon_System_Hook::do_action('app_before_register_route', $path, $viewPath, false);

            $handler = function () use ($viewPath, $path) {
                Anon_Http_Router::View($viewPath);
            };

            Anon_System_Config::addRoute($path, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Auto registered route: {$path} -> {$viewPath}");
            }

            Anon_System_Hook::do_action('app_after_register_route', $path, $viewPath, false);
        }
    }


    /**
     * 调试模式
     * @return bool
     */
    private static function isDebugEnabled(): bool
    {
        return defined('ANON_ROUTER_DEBUG') && ANON_ROUTER_DEBUG;
    }

    /**
     * 日志初始化
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
     * 记录调试
     * @param string $message
     */
    private static function debugLog(string $message): void
    {
        if (self::isDebugEnabled()) {
            self::writeLog("[DEBUG] " . $message);
        }
    }

    /**
     * 记录错误
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
     * 写入日志
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
     * 处理请求
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

            if (isset(self::$routes[$requestPath])) {
                self::debugLog("Matched route: " . $requestPath);

                self::$routeMatchCache[$requestPath] = ['type' => 'exact', 'route' => $requestPath];
                self::endRouteMatching("Route matched: " . $requestPath);
                Anon_System_Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                self::dispatch($requestPath);
            } else {
                $matchedRoute = self::matchParameterRoute($requestPath);
                if ($matchedRoute) {
                    self::debugLog("Matched parameter route: " . $matchedRoute['route']);

                    self::$routeMatchCache[$requestPath] = ['type' => 'param', 'route' => $matchedRoute];

                    self::endRouteMatching("Parameter route matched: " . $matchedRoute['route'], 'info', [
                        'params' => $matchedRoute['params']
                    ]);
                    Anon_System_Hook::do_action('router_param_route_matched', $matchedRoute['route'], $matchedRoute['params']);
                    self::dispatchWithParams($matchedRoute['route'], $matchedRoute['params']);
                } else {
                    self::debugLog("No route matched for: " . $requestPath);

                    self::$routeMatchCache[$requestPath] = ['type' => 'none', 'route' => null];
                    self::endRouteMatching("No route matched for: " . $requestPath, 'warn');
                    Anon_System_Hook::do_action('router_no_match', $requestPath);
                    self::handleError(404);
                }
            }
        } catch (Anon_System_Exception $e) {
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
     * 执行处理器
     * @param string $routeKey 标识
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
            $routeMeta = Anon_System_Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                self::applyRouterMeta($routeMeta);
            }

            Anon_System_Hook::do_action('router_before_dispatch', $routeKey, $handler);

            if (self::isDebugEnabled()) {
                Anon_Debug::startPerformance('route_execution_' . $routeKey);
            }

            $handler();

            if (self::isDebugEnabled()) {
                Anon_Debug::endPerformance('route_execution_' . $routeKey);
                Anon_Debug::info("Route executed successfully: " . $routeKey);
            }

            Anon_System_Hook::do_action('router_after_dispatch', $routeKey, $handler);
        } catch (Anon_System_Exception $e) {
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
     * 获取规范路径
     * @return string
     */
    private static function getRequestPath(): string
    {
        if (self::$cachedRequestPath !== null) {
            return self::$cachedRequestPath;
        }

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $path = $_GET['s'];
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($requestUri, PHP_URL_PATH);
        }

        $path = strstr($path, '?', true) ?: $path;

        if (strpos($path, '/apiService') === 0) {
            $path = substr($path, strlen('/apiService'));
        }

        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        self::$cachedRequestPath = $path;
        return $path;
    }

    /**
     * 匹配参数路由
     * @param string $requestPath 请求路径
     * @return array|null
     */
    private static function matchParameterRoute(string $requestPath): ?array
    {
        foreach (self::$routes as $routePattern => $handler) {
            if (strpos($routePattern, '{') !== false) {
                $pattern = preg_quote($routePattern, '/');
                $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $pattern);
                $pattern = '/^' . $pattern . '$/';

                if (preg_match($pattern, $requestPath, $matches)) {
                    preg_match_all('/\{([^\/]+)\}/', $routePattern, $paramNames);
                    $paramNames = $paramNames[1];

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
     * 带参执行
     * @param string $routeKey 标识
     * @param array $params 参数
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
            $routeMeta = Anon_System_Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                self::applyRouterMeta($routeMeta);
            }

            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }

            $handler();
        } catch (Anon_System_Exception $e) {
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
     * 处理错误
     * @param int $statusCode 状态码
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
     * 显示默认错误
     * @param int $statusCode 状态码
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
// Anon_Http_Router::init();
