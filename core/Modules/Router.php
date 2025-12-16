<?php

/**
 * 路由处理
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Router
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
    private static $logFile = __DIR__ . '/../../logs/router.log';

    /**
     * @var bool 是否已初始化日志系统
     */
    private static $logInitialized = false;

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
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_before_init');
            }
            
            // 记录路由系统启动
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::info('Router system initializing');
                Anon_Debug::startPerformance('router_init');
            }
            
            // 加载应用路由配置
            $autoRouter = Anon_Env::get('app.autoRouter', false);
            if ($autoRouter) {
                // 自动路由模式：扫描 Router 目录
                self::autoRegisterRoutes();
            } else {
                // 手动路由模式：加载 useRouter.php 配置
                $routerConfig = __DIR__ . '/../../app/useRouter.php';
                if (file_exists($routerConfig)) {
                    self::registerAppRoutes(require $routerConfig);
                }
            }

            // 加载系统路由配置
            self::loadConfig();
            
            // 执行配置加载后钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_config_loaded', self::$routes, self::$errorHandlers);
            }
            
            self::handleRequest();
            
            // 记录路由系统完成
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::endPerformance('router_init');
                Anon_Debug::info('Router system initialized successfully');
            }
            
            // 执行路由初始化完成钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_after_init');
            }
        } catch (RuntimeException $e) {
            // 执行路由错误钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_init_error', $e);
            }
            
            // 记录错误到调试系统
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
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
        $routerConfig = Anon_Config::getRouterConfig();

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
            throw new RuntimeException("无效的视图路径: {$fileView}");
        }
        
        $baseDir = realpath(__DIR__ . '/../../app/Router');
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

        require $filePath;
    }

    /**
     * 读取路由文件中的 Anon_RouterMeta 常量配置
     * @param string $filePath 路由文件路径
     * @return array 路由元数据配置
     */
    private static function readRouterMeta(string $filePath): array
    {
        $defaultMeta = [
            'header' => true,
            'requireLogin' => false,
            'method' => null,
            'cors' => true,
            'response' => true,
        ];

        if (!file_exists($filePath)) {
            return $defaultMeta;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return $defaultMeta;
        }

        // 查找 const Anon_RouterMeta = [...] 的定义
        // 支持多种格式：const Anon_RouterMeta = [...] 或 const Anon_RouterMeta=[...]
        if (preg_match('/const\s+Anon_RouterMeta\s*=\s*(\[[\s\S]*?\]);/i', $content, $matches)) {
            try {
                // 使用 eval 安全地解析数组（仅在找到匹配时）
                $metaArray = eval('return ' . $matches[1] . ';');
                if (is_array($metaArray)) {
                    return array_merge($defaultMeta, $metaArray);
                }
            } catch (Throwable $e) {
                // 解析失败时使用默认配置
                if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                    Anon_Debug::warning("Failed to parse Anon_RouterMeta in {$filePath}: " . $e->getMessage());
                }
            }
        }

        return $defaultMeta;
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
            $response = $meta['response'] ?? true;
            $cors = $meta['cors'] ?? true;
            Anon_Common::Header($code, $response, $cors);
        }

        // 检查登录状态
        if ($meta['requireLogin'] ?? false) {
            Anon_Common::RequireLogin();
        }

        // 检查 HTTP 方法
        if (!empty($meta['method'])) {
            $allowedMethods = is_array($meta['method']) ? $meta['method'] : [$meta['method']];
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            
            if (!in_array(strtoupper($requestMethod), array_map('strtoupper', $allowedMethods))) {
                Anon_Common::Header(405);
                Anon_ResponseHelper::error('不允许的请求方法', [
                    'allowed' => $allowedMethods,
                    'received' => $requestMethod
                ], 405);
                exit;
            }
        }
    }

    /**
     * 注册应用路由（从树形配置生成并注册到配置中心）
     * @param array $routeTree 路由树
     * @param string $basePath 基础路径
     */
    public static function registerAppRoutes(array $routeTree, string $basePath = ''): void
    {
        foreach ($routeTree as $key => $value) {
            $currentPath = $basePath ? $basePath . '/' . $key : $key;

            // 叶子节点：包含视图
            if (isset($value['view'])) {
                $view = $value['view'];

                // 注册前钩子
                if (class_exists('Anon_Hook')) {
                    Anon_Hook::do_action('app_before_register_route', $currentPath, $view, false);
                }

                // 生成处理器（登录检查等由 Anon_RouterMeta 统一处理）
                $handler = function () use ($view, $currentPath) {
                    // 执行最终视图（View 方法会自动读取并应用 Anon_RouterMeta 配置）
                    Anon_Router::View($view);
                };

                // 注册到配置
                Anon_Config::addRoute($currentPath, $handler);

                // 调试日志
                if (self::isDebugEnabled()) {
                    self::debugLog("Registered route: {$currentPath} -> {$view}");
                }

                // 注册后钩子
                if (class_exists('Anon_Hook')) {
                    Anon_Hook::do_action('app_after_register_route', $currentPath, $view, false);
                }
            } else {
                // 递归子节点
                self::registerAppRoutes($value, $currentPath);
            }
        }
    }

    /**
     * 自动注册路由（扫描 Router 目录）
     * 类似 Nuxt 的自动路由功能，不区分大小写，所有路由转为小写
     */
    private static function autoRegisterRoutes(): void
    {
        $routerDir = __DIR__ . '/../../app/Router';
        if (!is_dir($routerDir)) {
            return;
        }

        // 执行自动路由扫描前钩子
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('router_before_auto_register');
        }

        // 扫描目录并注册路由
        self::scanDirectory($routerDir, '');

        // 执行自动路由扫描后钩子
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('router_after_auto_register');
        }
    }

    /**
     * 递归扫描目录并注册路由
     * @param string $dir 目录路径
     * @param string $basePath 基础路由路径（小写）
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
        $routerBaseDir = __DIR__ . '/../../app/Router';
        $relativePath = str_replace($routerBaseDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $viewPath = str_replace('.php', '', $relativePath);

        // 注册前钩子
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('app_before_register_route', $routePath, $viewPath, false);
        }

        // 生成处理器
        $handler = function () use ($viewPath, $routePath) {
            Anon_Router::View($viewPath);
        };

        // 注册到配置
        Anon_Config::addRoute($routePath, $handler);

        // 调试日志
        if (self::isDebugEnabled()) {
            self::debugLog("Auto registered route: {$routePath} -> {$viewPath}");
        }

        // 注册后钩子
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('app_after_register_route', $routePath, $viewPath, false);
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
            
            // 调试输出当前路由和请求路径
            if (self::isDebugEnabled()) {
                error_log("Router: Processing request path: " . $requestPath);
                error_log("Router: Available routes: " . json_encode(array_keys(self::$routes)));
            }
            
            // 执行请求处理前钩子
            if (class_exists('Anon_Hook')) {
                $requestPath = Anon_Hook::apply_filters('router_request_path', $requestPath);
                Anon_Hook::do_action('router_before_request', $requestPath);
            }
            
            // 记录请求信息到调试系统
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::info("Processing request: " . $requestPath, [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                Anon_Debug::startPerformance('route_matching');
            }

            // 精确匹配
            if (isset(self::$routes[$requestPath])) {
                self::debugLog("Matched route: " . $requestPath);
                
                if (self::isDebugEnabled()) {
                    error_log("Router: Route matched: " . $requestPath);
                }
                
                if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                    Anon_Debug::endPerformance('route_matching');
                    Anon_Debug::info("Route matched: " . $requestPath);
                }
                
                // 执行路由匹配钩子
                if (class_exists('Anon_Hook')) {
                    Anon_Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                }
                
                self::dispatch($requestPath);
            } else {
                // 参数路由匹配
                $matchedRoute = self::matchParameterRoute($requestPath);
                if ($matchedRoute) {
                    self::debugLog("Matched parameter route: " . $matchedRoute['route']);
                    
                    if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                        Anon_Debug::endPerformance('route_matching');
                        Anon_Debug::info("Parameter route matched: " . $matchedRoute['route'], [
                            'params' => $matchedRoute['params']
                        ]);
                    }
                    
                    // 执行参数路由匹配钩子
                    if (class_exists('Anon_Hook')) {
                        Anon_Hook::do_action('router_param_route_matched', $matchedRoute['route'], $matchedRoute['params']);
                    }
                    
                    self::dispatchWithParams($matchedRoute['route'], $matchedRoute['params']);
                } else {
                    self::debugLog("No route matched for: " . $requestPath);
                    
                    if (self::isDebugEnabled()) {
                        error_log("Router: No route matched for: " . $requestPath);
                    }
                    
                    if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                        Anon_Debug::endPerformance('route_matching');
                        Anon_Debug::warn("No route matched for: " . $requestPath);
                    }
                    
                    // 执行路由未匹配钩子
                    if (class_exists('Anon_Hook')) {
                        Anon_Hook::do_action('router_no_match', $requestPath);
                    }
                    
                    self::handleError(404);
                }
            }
        } catch (Throwable $e) {
            // 执行请求处理错误钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_request_error', $e, $requestPath ?? '');
            }
            
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::error("Request handling failed: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            self::logError("Request handling failed: " . $e->getMessage(), $e->getFile(), $e->getLine());
            self::handleError(500);
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
            
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::error("Invalid handler for route: " . $routeKey);
            }
            
            self::handleError(404);
            return;
        }

        try {
            // Token 验证
            if (class_exists('Anon_Token') && Anon_Token::isEnabled()) {
                Anon_RequestHelper::requireToken(true);
            }
            
            // 执行路由执行前钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_before_dispatch', $routeKey, $handler);
            }
            
            // 开始执行性能监控
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::startPerformance('route_execution_' . $routeKey);
            }
            
            $handler();
            
            // 结束执行性能监控
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::endPerformance('route_execution_' . $routeKey);
                Anon_Debug::info("Route executed successfully: " . $routeKey);
            }
            
            // 执行路由执行后钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_after_dispatch', $routeKey, $handler);
            }
        } catch (Throwable $e) {
            // 执行路由执行错误钩子
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('router_dispatch_error', $e, $routeKey, $handler);
            }
            
            if (self::isDebugEnabled() && class_exists('Anon_Debug')) {
                Anon_Debug::error("Route execution failed [{$routeKey}]: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            self::handleError(500);
        }

        exit;
    }

    /**
     * 获取规范化请求路径
     * @return string 不含查询参数的路径
     */
    private static function getRequestPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        
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
            // Token 验证
            if (class_exists('Anon_Token') && Anon_Token::isEnabled()) {
                Anon_RequestHelper::requireToken(true);
            }
            
            // 将路由参数添加到 $_GET
            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }

            $handler();
        } catch (Throwable $e) {
            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            self::handleError(500);
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
Anon_Router::init();
