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
            
            // 先加载应用路由配置（从 app/App.php 注册到配置中心）
            self::registerAppRoutes(require __DIR__ . '/../../app/App.php');

            // 再加载配置中心中的所有路由
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

        require $filePath;
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
                $useLoginCheck = $value['useLoginCheck'] ?? false;

                // 注册前钩子
                if (class_exists('Anon_Hook')) {
                    Anon_Hook::do_action('app_before_register_route', $currentPath, $view, $useLoginCheck);
                }

                // 生成处理器
                $handler = function () use ($view, $currentPath, $useLoginCheck) {
                    // 登录检查
                    if ($useLoginCheck) {
                        // 前置钩子
                        if (class_exists('Anon_Hook')) {
                            Anon_Hook::do_action('app_before_login_check', $currentPath);
                        }

                        if (!Anon_Check::isLoggedIn()) {
                            // 失败钩子
                            if (class_exists('Anon_Hook')) {
                                Anon_Hook::do_action('app_login_check_failed', $currentPath);
                            }

                            Anon_Common::Header();
                            echo json_encode([
                                'code' => 401,
                                'message' => 'Unauthorized'
                            ]);
                            exit;
                        }

                        // 成功钩子
                        if (class_exists('Anon_Hook')) {
                            Anon_Hook::do_action('app_login_check_success', $currentPath);
                        }
                    }

                    // 执行最终视图
                    Anon_Router::View($view);
                };

                // 注册到配置
                Anon_Config::addRoute($currentPath, $handler);

                // 注册后钩子
                if (class_exists('Anon_Hook')) {
                    Anon_Hook::do_action('app_after_register_route', $currentPath, $view, $useLoginCheck);
                }
            } else {
                // 递归子节点
                self::registerAppRoutes($value, $currentPath);
            }
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
        
        // 去除前端代理的 /apiService 前缀（前端代理会将 /apiService 转发到后端）
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
            // 检查路由模式是否包含参数（如 {id}）
            if (strpos($routePattern, '{') !== false) {
                // 将路由模式转换为正则表达式
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
            // 将参数添加到 $_GET 超全局变量中，以便在处理器中访问
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
