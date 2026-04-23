<?php
namespace Anon\Modules\Http;

use Anon\Modules\System\Config;
use Anon\Modules\System\Cache\Cache;
use Anon\Modules\System\Env;
use Anon\Modules\Auth\Token;
use Anon\Modules\Database\Database;
use Anon\Modules\Debug;
use Anon\Modules\System\Attachment;
use Anon\Modules\System\Hook;
use Anon\Modules\Common;
use Anon\Modules\Cms\AccessLog;
use Anon\Modules\Cms\Cms;
use Anon\Modules\Cms\Theme\FatalError;
use Anon\Modules\Cms\Theme\Theme;
use Anon\Modules\Http\Middleware;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\ApiPrefix;
use RuntimeException;
use Throwable;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Router
{
    private static $routes = [];
    private static $errorHandlers = [];
    private static $logFile = __DIR__ . '/../../../logs/router.log';
    private static $logInitialized = false;
    private static $routeMatchCache = [];
    private static $cachedRequestPath = null;
    private static $routerMetaCache = [];
    private static $usePersistentMetaCache = null;


    /**
     * 初始化
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
            Hook::do_action('router_before_init');

            Debug::info('Router system initializing');
            Debug::startPerformance('router_init');

            $mode = Env::get('app.mode', 'api');

            if ($mode === 'cms') {
                Theme::init();
                Theme::registerAssets();

                self::registerThemeTemplates();

                $cmsRoutes = Env::get('app.cms.routes');
                if (!is_array($cmsRoutes) || empty($cmsRoutes)) {
                    $cmsRoutes = Cms::DEFAULT_ROUTES;
                }

                if (is_array($cmsRoutes) && !empty($cmsRoutes)) {
                    // 过滤已存在的路由，避免冲突
                    $registeredRoutes = Config::getRouterConfig()['routes'] ?? [];
                    foreach ($cmsRoutes as $path => $template) {
                        $normalizedPath = (strpos($path, '/') === 0) ? $path : '/' . $path;
                        if (isset($registeredRoutes[$normalizedPath])) {
                            unset($cmsRoutes[$path]);
                        }
                    }

                    if (!empty($cmsRoutes)) {
                        self::registerCmsRouteMappings($cmsRoutes);
                    }
                }

                self::registerApiRoutesWithPrefix();
            } else {
                self::registerApiRoutesWithPrefix();
            }

            self::loadConfig();

            Hook::do_action('router_config_loaded', self::$routes, self::$errorHandlers);

            Debug::endPerformance('router_init');
            Debug::info('Router system initialized successfully');

            Hook::do_action('router_after_init');
        } catch (RuntimeException $e) {
            Hook::do_action('router_init_error', $e);

            Debug::error("Router ERROR: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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
            Common::Header(200, false, true);
            exit;
        }

        // 记录请求开始时间，用于访问日志
        $startTime = microtime(true);

        // 注册关闭函数，在请求结束时记录访问日志
        register_shutdown_function(function () use ($startTime) {
            try {
                AccessLog::log(['start_time' => $startTime]);
            } catch (Throwable $e) {
            }
        });

        self::handleRequest();
    }

    // 静默失败，避免影响正常请求
    private static function loadConfig(): void
    {
        $routerConfig = Config::getRouterConfig();

        if (!is_array($routerConfig)) {
            throw new RuntimeException("Invalid router configuration");
        }

        self::$routes = $routerConfig['routes'] ?? [];
        self::$errorHandlers = $routerConfig['error_handlers'] ?? [];

        /**
     * 加载配置
     */
        Debug::info('Router config loaded', [
            'total_routes' => count(self::$routes),
            'routes_list' => array_keys(self::$routes)
        ]);


    }

    /**
     * View
     */
    public static function View(string $fileView): void
    {
        if (strpos($fileView, '..') !== false || strpos($fileView, '/') === 0) {
            throw new RuntimeException("无效的路径 {$fileView}");
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
            Middleware::pipeline($middlewares, $_REQUEST, function () use ($filePath) {
                require $filePath;
            });
        } else {
            require $filePath;
        }
    }

    // 记录加载的路由信息
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

        if (preg_match('/const\s+\\\s*=\s*(\[[\s\S]*?\]);/i', $content, $matches) || preg_match('/const\s+RouterMeta\s*=\s*(\[[\s\S]*?\]);/i', $content, $matches)) {
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
                        Debug::warn("Failed to parse \Anon\RouterMeta in {$filePath}: " . $e->getMessage());
                    }
                }

                if (is_array($metaArray)) {
                    $allowedKeys = ['header', 'requireLogin', 'requireAdmin', 'method', 'cors', 'response', 'code', 'token', 'middleware', 'cache'];
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
                Debug::warn("Failed to parse \Anon\RouterMeta in {$filePath}: " . $e->getMessage());
            }
        }

        self::$routerMetaCache[$cacheKey] = $result;

        if (self::shouldUsePersistentMetaCache()) {
            self::savePersistentMetaCache($filePath, $result);
        }

        return $result;
    }
    
    /**
     * 读取元数据
     * @param string $filePath 文件路径
     * @return array
     */
    private static function scanAndRegisterAutoRoutes(): void
    {
        $routerDir = ANON_ROOT . 'app/Router';
        if (!is_dir($routerDir)) {
            return;
        }
        
        try {
            self::scanDirectoryRecursively($routerDir, '');
            Debug::info('Auto-scanned routes from app/Router');
        } catch (Throwable $e) {
            Debug::error('Failed to auto-scan routes', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * 自动扫描并注册路由（Nuxt.js 风格）
     * 当不存在 useRouter.php 时启用
     */
    private static function scanDirectoryRecursively(string $dir, string $pathPrefix): void
    {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($fullPath)) {
                /**
     * 递归扫描目录
     * @param string $dir 目录路径
     * @param string $pathPrefix 路径前缀
     */
                if (in_array(strtolower($file), ['auth', 'user'])) {
                    // 跳过特殊目录
                    $subPrefix = $pathPrefix . '/' . strtolower($file);
                    self::scanDirectoryRecursively($fullPath, $subPrefix);
                } else {
                    // 特殊目录作为 API 分组
                    self::scanDirectoryRecursively($fullPath, $pathPrefix . '/' . $file);
                }
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                // 普通子目录
                $routeName = pathinfo($file, PATHINFO_FILENAME);
                
                // PHP 文件作为路由
                $routePath = $pathPrefix;
                if ($routeName !== 'index') {
                    $routePath .= '/' . $routeName;
                }
                
                if (empty($routePath)) {
                    $routePath = '/';
                }
                
                // 构建路由路径
                self::registerAutoRouteByScan($fullPath, $routePath);
            }
        }
    }
    
    // 注册路由
    private static function registerAutoRouteByScan(string $filePath, string $routePath): void
    {
        /**
     * 注册自动路由
     * @param string $filePath 文件路径
     * @param string $routePath 路由路径
     */
        
        // 读取文件元数据
        
        // 应用元数据
        self::route($routePath, function() use ($filePath, $meta) {
            if (isset($meta['middleware'])) {
                $middlewares = is_array($meta['middleware']) ? $meta['middleware'] : [$meta['middleware']];
                Middleware::pipeline($middlewares, $_REQUEST, function() use ($filePath) {
                    require $filePath;
                });
            } else {
                require $filePath;
            }
        }, $meta);
    }

    // 注册路由
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
     * 检查持久化启用
     * @return bool
     */
    private static function loadPersistentMetaCache(string $filePath): ?array
    {
        try {
            $cacheKey = 'router_meta_' . md5($filePath);
            $cached = Cache::get($cacheKey);

            if (!is_array($cached) || !isset($cached['meta']) || !isset($cached['mtime'])) {
                return null;
            }

            $sourceTime = filemtime($filePath);
            if ($sourceTime > $cached['mtime']) {
                Cache::delete($cacheKey);
                return null;
            }

            return $cached['meta'];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 加载持久化
     * @param string $filePath 文件路径
     * @return array|null
     */
    private static function savePersistentMetaCache(string $filePath, array $meta): void
    {
        try {
            $cacheKey = 'router_meta_' . md5($filePath);
            $cacheData = [
                'meta' => $meta,
                'mtime' => filemtime($filePath)
            ];

            Cache::set($cacheKey, $cacheData, 86400);
        } catch (Throwable $e) {
            /**
     * 保存持久化
     * @param string $filePath 文件路径
     * @param array $meta 元数据
     */
        }
    }

    // 忽略
    public static function clearMetaCache(): void
    {
        self::$routerMetaCache = [];

        Debug::info('Router meta cache cleared (memory cache only, file cache will expire naturally)');
    }

    /**
     * 清除元数据缓存
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
     * PHP数组转JSON
     * @param string $phpArrayStr PHP数组字符串
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
            $segments = array_map(function ($segment) {
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
     * 获取文件路径
     * @param string $routeKey 路由标识
     * @param callable $handler 处理器
     * @return string|null
     */
    private static function applyRouterMeta(array $meta): void
    {
        if ($meta['header'] ?? true) {
            $code = $meta['code'] ?? 200;
            $response = isset($meta['response']) && $meta['response'] !== null ? $meta['response'] : true;
            $cors = isset($meta['cors']) && $meta['cors'] !== null ? $meta['cors'] : true;

            /**
     * 应用元数据
     * @param array $meta 元数据
     */
            if ($mode === 'cms' && !RequestHelper::wantsJson()) {
                http_response_code($code);
                if ($cors) {
                    Common::setCorsHeaders();
                }
                if ($response) {
                    header('Content-Type: text/html; charset=utf-8');
                }
            } else {
                Common::Header($code, $response, $cors);
            }
        }

        if (isset($meta['token'])) {
            if ($meta['token'] === true) {
                RequestHelper::requireToken(true, false);
            } elseif ($meta['token'] === false) {
                // CMS 模式下，如果不是明确要求 JSON，设置 HTML 响应头
                RequestHelper::requireToken(false, false);
            }
        } else {
            if (Token::isEnabled()) {
                RequestHelper::requireToken(true, false);
            }
        }

        if (!empty($meta['requireLogin'])) {
            $message = is_string($meta['requireLogin']) ? $meta['requireLogin'] : null;
            Common::RequireLogin($message);
        }

        // 检查管理员权限
        if (!empty($meta['requireAdmin'])) {
            $userId = RequestHelper::getUserId();
            if (!$userId) {
                Common::Header(401);
                ResponseHelper::unauthorized('请先登录');
                exit;
            }

            $db = Database::getInstance();
            if (!$db->isUserAdmin($userId)) {
                $message = is_string($meta['requireAdmin']) ? $meta['requireAdmin'] : '需要管理员权限';
                Common::Header(403);
                ResponseHelper::forbidden($message);
                exit;
            }
        }

        if (!empty($meta['method'])) {
            $allowedMethods = is_array($meta['method'])
                ? $meta['method']
                : array_values(array_filter(array_map('trim', explode(',', (string) $meta['method']))));
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            if (!in_array(strtoupper($requestMethod), array_map('strtoupper', $allowedMethods))) {
                Common::Header(405);
                ResponseHelper::error('不允许的请求方法', [
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
            $cacheEnabled = Env::get('app.base.cache.enabled', false);
            $cacheTime = Env::get('app.base.cache.time', 0);
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
     * 应用缓存
     * @param array $meta 元数据
     */
    private static function shouldExcludeFromCache(array $meta): bool
    {
        $requestPath = self::getRequestPath();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $excludePatterns = Env::get('app.base.cache.exclude', [
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
     * 检查排除缓存
     * @param array $meta 元数据
     * @return bool
     */
    private static function logRouteDebug(string $requestPath): void
    {
        Debug::debug("Router: Processing request path", ['path' => $requestPath, 'available_routes' => array_keys(self::$routes)]);
        if (isset(self::$routes[$requestPath])) {
            Debug::debug("Router: Exact match found", ['path' => $requestPath]);
        } else {
            Debug::debug("Router: No exact match", ['path' => $requestPath]);
        }
    }

    /**
     * 记录调试
     * @param string $requestPath 请求路径
     */
    private static function endRouteMatching(string $message, string $level = 'info', array $context = []): void
    {
        Debug::endPerformance('route_matching');
        if ($level === 'warn') {
            Debug::warn($message, $context);
        } else {
            Debug::info($message, $context);
        }
    }

    /**
     * 结束性能记录
     * @param string $message 消息
     * @param string $level 级别
     * @param array $context 上下文
     */
    public static function registerAppRoutes(array $routeTree, string $basePath = ''): void
    {
        $mode = Env::get('app.mode', 'api');

        foreach ($routeTree as $key => $value) {
            $currentPath = $basePath ? $basePath . '/' . $key : $key;

            if (strpos($currentPath, '/') !== 0) {
                $currentPath = '/' . $currentPath;
            }

            if (is_string($value)) {
                $view = $value;
            } elseif (is_array($value) && isset($value['view'])) {
                $view = $value['view'];
            } else {
                self::registerAppRoutes($value, $currentPath);
                continue;
            }

            Hook::do_action('app_before_register_route', $currentPath, $view, false);

            if ($mode === 'cms') {
                $handler = function () use ($view, $currentPath) {
                    $isParamRoute = strpos($currentPath, '{') !== false;

                    if ($isParamRoute) {
                        $requestPath = self::getRequestPath();
                        $params = self::extractRouteParams($currentPath, $requestPath);
                        Theme::render($view, $params);
                    } else {
                        Theme::render($view, []);
                    }
                };
            } else {
                $handler = function () use ($view, $currentPath) {
                    self::View($view);
                };
            }

            Config::addRoute($currentPath, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Registered route: {$currentPath} -> {$view}");
            }

            Hook::do_action('app_after_register_route', $currentPath, $view, false);
        }
    }

    /**
     * 注册应用路由
     * @param array $routeTree 路由树
     * @param string $basePath 基础路径
     */
    private static function registerThemeTemplates(): void
    {
        $themeDir = Theme::getThemeDir();
        $indexFile = Cms::findFileCaseInsensitive($themeDir, 'index');

        if ($indexFile !== null) {
            Hook::do_action('app_before_register_route', '/', 'index', false);

            $handler = function () {
                Theme::render('index', []);
            };

            Config::addRoute('/', $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Registered theme template route: / -> index");
            }

            Hook::do_action('app_after_register_route', '/', 'index', false);
        }
    }


    /**
     * 注册主题根目录下的模板文件
     * @return void
     */
    private static function registerApiRoutesWithPrefix(): void
    {
        $mode = Env::get('app.mode', 'api');

        /**
     * 注册带前缀的 API 路由
     * @return void
     */
        $apiPrefix = self::normalizeRoutePrefix(ApiPrefix::get());
            
        Debug::info(
            strtoupper($mode) . " mode: Registering API routes with prefix: " . ($apiPrefix === '' ? '/' : $apiPrefix)
        );
            
        $autoRouter = strtolower((string) Env::get('app.base.router.mode', 'manual')) === 'auto';
        $routerConfig = __DIR__ . '/../../../app/useRouter.php';

        if ($autoRouter || !file_exists($routerConfig)) {
            self::autoRegisterRoutesWithPrefix($apiPrefix);
            
        // 使用统一的 API前缀管理
            // CMS 模式下总是启用自动路由，确保基础路由被注册
                    
            // 在配置的 API前缀下注册路由
            if ($apiPrefix !== '') {
                self::autoRegisterRoutesWithPrefix('');
            }

            return;
        }

        self::registerAppRoutesWithPrefix(require $routerConfig, $apiPrefix);
    }

    // 同时在根路径下注册一份，确保兼容性
    private static function registerAppRoutesWithPrefix(array $routeTree, string $prefix, string $basePath = ''): void
    {
        $prefix = self::normalizeRoutePrefix($prefix);

        foreach ($routeTree as $key => $value) {
            $currentPath = $basePath ? $basePath . '/' . $key : $key;

            if (strpos($currentPath, '/') !== 0) {
                $currentPath = '/' . $currentPath;
            }

            $prefixedPath = self::buildPrefixedRoutePath($prefix, $currentPath);

            if (is_string($value)) {
                $view = $value;
            } elseif (is_array($value) && isset($value['view'])) {
                $view = $value['view'];
            } else {
                self::registerAppRoutesWithPrefix($value, $prefix, $currentPath);
                continue;
            }

            Hook::do_action('app_before_register_route', $prefixedPath, $view, false);

            $handler = function () use ($view) {
                self::View($view);
            };

            Config::addRoute($prefixedPath, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Registered API route with prefix: {$prefixedPath} -> {$view}");
            }

            Hook::do_action('app_after_register_route', $prefixedPath, $view, false);
        }
    }

    /**
     * 注册应用路由
     * @param array $routeTree 路由树
     * @param string $prefix 路由前缀
     * @param string $basePath 基础路径
     * @return void
     */
    private static function autoRegisterRoutesWithPrefix(string $prefix): void
    {
        $routerDir = __DIR__ . '/../../../app/Router';
        $prefix = self::normalizeRoutePrefix($prefix);

        if (!is_dir($routerDir)) {
            Debug::warn("Router directory not found: {$routerDir}");
            return;
        }

        Hook::do_action('router_before_auto_register');

        Debug::info("Auto-registering routes with prefix: {$prefix}");
        self::scanDirectoryWithPrefix($routerDir, '', $prefix);

        Hook::do_action('router_after_auto_register');
        
        Debug::info("Auto-registered routes completed");
    }

    /**
     * 自动注册路由
     * @param string $prefix 路由前缀
     * @return void
     */
    private static function scanDirectoryWithPrefix(string $dir, string $basePath, string $prefix): void
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
                self::scanDirectoryWithPrefix($itemPath, $routePath, $prefix);
            } elseif (is_file($itemPath) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $prefixedPath = self::buildPrefixedRoutePath($prefix, $routePath);
                Debug::debug("Registering route: {$prefixedPath} (file: {$itemName}.php)");
                self::registerAutoRoute($prefixedPath, $itemPath, $dir);
            }
        }
    }

    /**
     * 递归扫描目录
     * @param string $dir 目录路径
     * @param string $basePath 基础路径
     * @param string $prefix 路由前缀
     * @return void
     */
    private static function normalizeRoutePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        return '/' . trim($prefix, '/');
    }

    /**
     * 规范化路由前缀
     * @param string $prefix
     * @return string
     */
    private static function buildPrefixedRoutePath(string $prefix, string $routePath): string
    {
        $prefix = self::normalizeRoutePrefix($prefix);

        if ($routePath === '' || $routePath[0] !== '/') {
            $routePath = '/' . ltrim($routePath, '/');
        }

        return $prefix === '' ? $routePath : $prefix . $routePath;
    }

    /**
     * 组合前缀和路由路径
     * @param string $prefix
     * @param string $routePath
     * @return string
     */
    private static function registerCmsRouteMappings(array $routeMappings): void
    {
        foreach ($routeMappings as $routePath => $templateName) {
            if (strpos($routePath, '/') !== 0) {
                $routePath = '/' . $routePath;
            }

            Hook::do_action('app_before_register_route', $routePath, $templateName, false);

            $handler = function () use ($templateName, $routePath) {
                $isParamRoute = strpos($routePath, '{') !== false;

                if ($isParamRoute) {
                    $requestPath = self::getRequestPath();
                    $params = self::extractRouteParams($routePath, $requestPath);
                    Theme::render($templateName, $params);
                } else {
                    Theme::render($templateName, []);
                }
            };

            Config::addRoute($routePath, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Registered CMS route mapping: {$routePath} -> {$templateName}");
            }

            Hook::do_action('app_after_register_route', $routePath, $templateName, false);
        }
    }

    /**
     * 注册 CMS 路由映射关系
     * @param array $routeMappings 路由映射
     * @return void
     */
    private static function extractRouteParams(string $routePattern, string $requestPath): array
    {
        $params = [];

        $pattern = preg_quote($routePattern, '/');
        $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';

        if (preg_match($pattern, $requestPath, $matches)) {
            preg_match_all('/\{([^\/]+)\}/', $routePattern, $paramNames);
            $paramNames = $paramNames[1];

            for ($i = 0, $len = count($paramNames); $i < $len; $i++) {
                if (isset($matches[$i + 1])) {
                    $params[$paramNames[$i]] = $matches[$i + 1];
                }
            }
        }

        return $params;
    }

    /**
     * 从请求路径中提取路由参数
     * @param string $routePattern 路由模式
     * @param string $requestPath 请求路径
     * @return array
     */
    private static function autoRegisterRoutes(): void
    {
        $routerDir = __DIR__ . '/../../../app/Router';
        if (!is_dir($routerDir)) {
            return;
        }

        Hook::do_action('router_before_auto_register');

        self::scanDirectory($routerDir, '');

        Hook::do_action('router_after_auto_register');
    }

    // 将路由模式转换为正则表达式
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

    // 提取参数名
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
            Hook::do_action('app_before_register_route', $path, $viewPath, false);

            $handler = function () use ($viewPath, $path) {
                self::View($viewPath);
            };

            Config::addRoute($path, $handler);

            if (self::isDebugEnabled()) {
                self::debugLog("Auto registered route: {$path} -> {$viewPath}");
            }

            Hook::do_action('app_after_register_route', $path, $viewPath, false);
        }
    }


    // 匹配参数值
    private static function isDebugEnabled(): bool
    {
        return defined('ANON_ROUTER_DEBUG') && ANON_ROUTER_DEBUG;
    }

    /**
     * 自动注册路由
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
     * 递归扫描
     * @param string $dir 目录
     * @param string $basePath 基础路径
     */
    private static function debugLog(string $message): void
    {
        if (self::isDebugEnabled()) {
            self::writeLog("[DEBUG] " . $message);
        }
    }

    /**
     * 注册自动路由
     * @param string $routePath 路径
     * @param string $filePath 文件
     * @param string $baseDir 基础目录
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
     * 调试模式
     * @return bool
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
     * 日志初始化
     */
    private static function handleRequest(): void
    {
        try {
            $requestPath = self::getRequestPath();
            self::debugLog("Request path: " . $requestPath);

            if (self::isDebugEnabled()) {
                self::logRouteDebug($requestPath);
            }

            $requestPath = Hook::apply_filters('router_request_path', $requestPath);
            Hook::do_action('router_before_request', $requestPath);

            Debug::info("Processing request: " . $requestPath, [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            Debug::startPerformance('route_matching');

            if (isset(self::$routeMatchCache[$requestPath])) {
                $cachedMatch = self::$routeMatchCache[$requestPath];
                if ($cachedMatch['type'] === 'exact') {
                    self::endRouteMatching("Route matched (cached): " . $requestPath);
                    Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                    self::dispatch($requestPath);
                    return;
                } elseif ($cachedMatch['type'] === 'param' && $cachedMatch['route']) {
                    self::endRouteMatching("Parameter route matched (cached): " . $cachedMatch['route']['route']);
                    Hook::do_action('router_param_route_matched', $cachedMatch['route']['route'], $cachedMatch['route']['params']);
                    self::dispatchWithParams($cachedMatch['route']['route'], $cachedMatch['route']['params']);
                    return;
                } elseif ($cachedMatch['type'] === 'none') {
                    self::endRouteMatching("No route matched (cached): " . $requestPath, 'warn');
                    Hook::do_action('router_no_match', $requestPath);
                    self::handleError(404);
                    return;
                }
            }

            if (isset(self::$routes[$requestPath])) {
                self::debugLog("Matched route: " . $requestPath);

                self::$routeMatchCache[$requestPath] = ['type' => 'exact', 'route' => $requestPath];
                self::endRouteMatching("Route matched: " . $requestPath);
                Hook::do_action('router_route_matched', $requestPath, self::$routes[$requestPath]);
                self::dispatch($requestPath);
            } else {
                $matchedRoute = self::matchParameterRoute($requestPath);
                if ($matchedRoute) {
                    self::debugLog("Matched parameter route: " . $matchedRoute['route']);

                    self::$routeMatchCache[$requestPath] = ['type' => 'param', 'route' => $matchedRoute];

                    self::endRouteMatching("Parameter route matched: " . $matchedRoute['route'], 'info', [
                        'params' => $matchedRoute['params']
                    ]);
                    Hook::do_action('router_param_route_matched', $matchedRoute['route'], $matchedRoute['params']);
                    self::dispatchWithParams($matchedRoute['route'], $matchedRoute['params']);
                } else {
                    self::debugLog("No route matched for: " . $requestPath);

                    self::$routeMatchCache[$requestPath] = ['type' => 'none', 'route' => null];
                    self::endRouteMatching("No route matched for: " . $requestPath, 'warn');
                    Hook::do_action('router_no_match', $requestPath);
                    self::handleError(404);
                }
            }
        } catch (Throwable $e) {
            Hook::do_action('router_request_error', $e, $requestPath ?? '');

            Debug::error("Request handling failed: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            self::logError("Request handling failed: " . $e->getMessage(), $e->getFile(), $e->getLine());
            ResponseHelper::handleException($e);
            exit;
        }
    }

    /**
     * 记录调试
     * @param string $message
     */
    private static function dispatch(string $routeKey): void
    {
        $handler = self::$routes[$routeKey];
        self::debugLog("Dispatching route: " . $routeKey);

        if (!is_callable($handler)) {
            self::debugLog("Invalid handler for route: " . $routeKey);

            Debug::error("Invalid handler for route: " . $routeKey);

            self::handleError(404);
            return;
        }

        try {
            $routeMeta = Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                self::applyRouterMeta($routeMeta);
            }

            Hook::do_action('router_before_dispatch', $routeKey, $handler);

            Debug::startPerformance('route_execution_' . $routeKey);

            $handler();

            Debug::endPerformance('route_execution_' . $routeKey);
            Debug::info("Route executed successfully: " . $routeKey);

            Hook::do_action('router_after_dispatch', $routeKey, $handler);
        } catch (Throwable $e) {
            Hook::do_action('router_dispatch_error', $e, $routeKey, $handler);

            Debug::error("Route execution failed [{$routeKey}]: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            ResponseHelper::handleException($e);
            exit;
        }

        exit;
    }

    /**
     * 记录错误
     * @param string $message
     * @param string $file
     * @param int $line
     */
    private static function getRequestPath(): string
    {
        if (self::$cachedRequestPath !== null) {
            return self::$cachedRequestPath;
        }

        if (isset($_GET['s']) && $_GET['s'] !== '') {
            $path = (string) $_GET['s'];
        } else {
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            $path = (string) parse_url($requestUri, PHP_URL_PATH);
        }

        $path = (string) (strstr((string) $path, '?', true) ?: $path);
        if ($path === '') {
            $path = '/';
        }

        /**
     * 写入日志
     * @param string $message
     */
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        /**
     * 处理请求
     */
        $configuredPrefix = (string) ApiPrefix::get();
        
        /**
     * 执行处理器
     * @param string $routeKey 标识
     */
        // 如果配置的前缀不是 / 或空，且为 /auth/ 路由，则必须匹配该前缀
        if ($configuredPrefix !== '' && $configuredPrefix !== '/' && strpos($path, '/auth/') === 0) {
            if (strpos($path, $configuredPrefix) === 0) {
                // 去除配置的前缀
                $path = substr($path, strlen($configuredPrefix));
                if (empty($path)) {
                    $path = '/';
                }
            } else {
                // 请求路径不包含配置的前缀，返回 404
                self::handleError(404);
                exit;
            }
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
        foreach (self::$routes as $routePattern => $route) {
            // 跳过没有参数的路由
            if (strpos($routePattern, '{') === false) {
                continue; 
            }
            
            // 将路由模式转换为正则表达式
            // 例如: /page/{page} -> /^\/page\/([^\/]+)$/
            $pattern = preg_quote($routePattern, '/');
            $pattern = preg_replace('#\\\\\{[^}]+\\\\\}#', '([^/]+)', $pattern);
            $pattern = '/^' . $pattern . '$/';

            if (preg_match($pattern, $requestPath, $matches)) {
                // 提取参数名
                preg_match_all('#\{([^}]+)\}#', $routePattern, $paramNamesMatch);
                $paramNames = $paramNamesMatch[1];

                $params = [];
                for ($i = 0; $i < count($paramNames); $i++) {
                    if (isset($matches[$i + 1])) {
                        $params[$paramNames[$i]] = $matches[$i + 1];
                    }
                }

                if (self::isDebugEnabled()) {
                    self::debugLog("Route matched: {$routePattern} with params: " . json_encode($params));
                }

                return [
                    'route' => $routePattern,
                    'params' => $params
                ];
            }
        }

        return null;
    }

    // 直接遍历所有路由，按注册顺序匹配
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
            $routeMeta = Config::getRouteMeta($routeKey);
            if ($routeMeta) {
                self::applyRouterMeta($routeMeta);
            }

            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }

            $handler();
        } catch (Throwable $e) {
            self::logError("Route execution failed [{$routeKey}]: " . $e->getMessage(), $e->getFile(), $e->getLine());
            ResponseHelper::handleException($e);
            exit;
        }

        exit;
    }

    // 跳过无参数路由
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

    // 将路由模式转换为正则表达式
    private static function showDefaultError(int $statusCode): void
    {
        $mode = Env::get('app.mode', 'api');

        if ($mode === 'cms' && !RequestHelper::wantsJson()) {
            // 提取参数名
            try {
                Theme::render('Error', [
                    'code' => $statusCode,
                    'message' => null
                ]);
            } catch (Error $e) {
                /**
     * 带参执行
     * @param string $routeKey 标识
     * @param array $params 参数
     */
                FatalError::render(
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    get_class($e)
                );
            } catch (Throwable $e) {
                /**
     * 处理错误
     * @param int $statusCode 状态码
     */
                $message = $e->getMessage();
                $isFatal = strpos($message, 'Call to undefined') !== false ||
                    strpos($message, 'not found') !== false ||
                    strpos($message, 'Class') !== false;

                if ($isFatal) {
                    FatalError::render(
                        $message,
                        $e->getFile(),
                        $e->getLine(),
                        get_class($e)
                    );
                } else {
                    /**
     * 显示默认错误
     * @param int $statusCode 状态码
     */
                    self::showSimpleError($statusCode);
                }
            }
            return;
        }

        // CMS 模式
        Common::Header($statusCode);

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

    // 严重错误
    private static function showSimpleError(int $statusCode, ?string $message = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');

        $statusText = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error'
        ];
        $text = $statusText[$statusCode] ?? 'Error';
        $errorMessage = $message ? htmlspecialchars($message) : $text;

        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$statusCode} {$text}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f2f5; color: #333; }
                .container { text-align: center; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
                h1 { margin: 0 0 1rem; font-size: 3rem; color: #ff4d4f; }
                p { font-size: 1.2rem; color: #666; margin: 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>{$statusCode}</h1>
                <p>{$errorMessage}</p>
            </div>
        </body>
        </html>";
    }
}

// 检查是否是严重错误
