<?php

/**
 * 系统配置
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_Config
{
    /**
     * @var array 路由配置
     */
    private static $routerConfig = [
        'routes' => [],
        'route_meta' => [],
        'error_handlers' => []
    ];

    /**
     * 注册路由
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     * @param array $meta 路由元数据
     * @throws RuntimeException
     */
    public static function addRoute(string $path, callable $handler, array $meta = [])
    {
        $normalized = (strpos($path, '/') === 0) ? $path : '/' . $path;

        if (isset(self::$routerConfig['routes'][$normalized])) {
            $existingHandler = self::$routerConfig['routes'][$normalized];
            $conflictInfo = self::detectRouteConflict($normalized, $handler, $existingHandler);

            if ($conflictInfo['conflict']) {
                $message = "路由冲突: {$normalized}";
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    $message .= " - " . $conflictInfo['details'];
                }
                throw new RuntimeException($message);
            }
        }

        self::$routerConfig['routes'][$normalized] = $handler;

        if (!empty($meta)) {
            $defaultMeta = [
                'header' => true,
                'requireLogin' => false,
                'method' => null,
                'cache' => [
                    'enabled' => false,
                    'time' => 0,
                ],
            ];

            $allowedKeys = ['header', 'requireLogin', 'requireAdmin', 'method', 'cors', 'response', 'code', 'token', 'middleware', 'cache'];
            $meta = array_intersect_key($meta, array_flip($allowedKeys));

            if (isset($meta['cache']) && is_array($meta['cache'])) {
                $cacheAllowedKeys = ['enabled', 'time'];
                $meta['cache'] = array_intersect_key($meta['cache'], array_flip($cacheAllowedKeys));

                if (isset($meta['cache']['enabled']) && !is_bool($meta['cache']['enabled'])) {
                    unset($meta['cache']['enabled']);
                }
                if (isset($meta['cache']['time']) && (!is_int($meta['cache']['time']) || $meta['cache']['time'] < 0)) {
                    unset($meta['cache']['time']);
                }
            }

            self::$routerConfig['route_meta'][$normalized] = array_merge($defaultMeta, $meta);
        }
    }

    /**
     * 获取路由元数据
     * @param string $path 路由路径
     * @return array|null
     */
    public static function getRouteMeta(string $path): ?array
    {
        $normalized = (strpos($path, '/') === 0) ? $path : '/' . $path;
        return self::$routerConfig['route_meta'][$normalized] ?? null;
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
     * 注册静态文件路由
     * @param string $route 路由路径
     * @param string|callable $filePath 文件路径或回调函数
     * @param string|callable $mimeType MIME类型或回调函数
     * @param int $cacheTime 缓存时间，单位为秒
     * @param bool $compress 是否启用压缩
     */
    public static function addStaticRoute(string $route, $filePath, $mimeType = 'application/octet-stream', int $cacheTime = 31536000, bool $compress = true, array $meta = [])
    {
        $defaultMeta = [
            'header' => false,
            'token' => false,
            'requireLogin' => false
        ];
        $meta = array_merge($defaultMeta, $meta);

        self::addRoute($route, function () use ($filePath, $mimeType, $cacheTime, $compress) {
            $actualFilePath = is_callable($filePath) ? $filePath() : $filePath;
            $actualMimeType = is_callable($mimeType) ? $mimeType() : $mimeType;

            if (!$actualFilePath || !is_file($actualFilePath) || !is_readable($actualFilePath)) {
                http_response_code(404);
                exit;
            }

            header('Content-Type: ' . $actualMimeType);
            header('Content-Length: ' . filesize($actualFilePath));

            // 检测是否有 nocache 参数，如果有则不缓存
            $hasNoCacheParam = isset($_GET['nocache']) && ($_GET['nocache'] === '1' || $_GET['nocache'] === 'true');

            // 检测是否有 ver 参数，ver 参数作为版本标识，不同版本视为不同资源
            $hasVerParam = isset($_GET['ver']) && $_GET['ver'] !== '';

            if ($hasNoCacheParam) {
                // 有 nocache 参数，强制不缓存
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            } elseif ($cacheTime > 0) {
                // 正常缓存逻辑
                // ver 参数在 URL 中，不同版本号会被浏览器视为不同资源
                // 当版本号变化时，浏览器会请求新 URL 并缓存新资源
                header('Cache-Control: public, max-age=' . $cacheTime);
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
                if ($hasVerParam) {
                    // 设置 Last-Modified 为文件修改时间，帮助浏览器判断资源是否更新
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($actualFilePath)) . ' GMT');
                }
            } else {
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }

            if ($compress && extension_loaded('zlib')) {
                $compressibleTypes = ['text/html', 'text/css', 'text/javascript', 'application/javascript', 'application/json', 'text/xml', 'application/xml'];
                if (in_array($actualMimeType, $compressibleTypes)) {
                    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
                    $fileContent = file_get_contents($actualFilePath);
                    if ($fileContent !== false && strpos($acceptEncoding, 'gzip') !== false) {
                        $compressed = gzencode($fileContent);
                        header('Content-Encoding: gzip');
                        header('Content-Length: ' . strlen($compressed));
                        echo $compressed;
                        exit;
                    }
                }
            }

            readfile($actualFilePath);
            exit;
        }, $meta);
    }

    /**
     * 获取路由配置
     * @return array
     */
    public static function getRouterConfig(): array
    {
        return self::$routerConfig;
    }

    /**
     * 检查安装状态
     * @return bool
     */
    public static function isInstalled(): bool
    {
        return defined('ANON_INSTALLED') && ANON_INSTALLED;
    }

    /**
     * 获取全局配置信息
     * 可通过 config 钩子扩展配置字段
     * @return array
     */
    public static function getConfig(): array
    {
        $config = [
            'token' => class_exists('Anon_Auth_Token') && Anon_Auth_Token::isEnabled(),
            'captcha' => class_exists('Anon_Auth_Captcha') && Anon_Auth_Captcha::isEnabled(),
            'csrfToken' => class_exists('Anon_Auth_Csrf') ? Anon_Auth_Csrf::generateToken() : ''
        ];

        $config = Anon_System_Hook::apply_filters('config', $config);

        return $config;
    }

    /**
     * 初始化系统路由
     */
    public static function initSystemRoutes()
    {
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::debug("Registering system routes");
        }

        self::addRoute('/anon/common/license', function () {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::LICENSE_TEXT(), '获取许可证信息成功');
        });
        self::addRoute('/anon/common/system', function () {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::SystemInfo(), '获取系统信息成功');
        });
        self::addRoute('/anon/common/client-ip', function () {
            Anon_Common::Header();
            $ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
            Anon_Http_Response::success(['ip' => $ip], '获取客户端IP成功');
        });
        self::addRoute('/anon/ciallo', function () {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::Ciallo(), '恰喽~');
        });

        $staticDir = __DIR__ . '/../../Static/';

        $debugCacheEnabled = Anon_System_Env::get('app.debug.cache.enabled', false);
        $debugCacheTime = Anon_System_Env::get('app.debug.cache.time', 0);
        $debugCacheTime = $debugCacheEnabled ? $debugCacheTime : 0;

        if (Anon_System_Env::get('app.mode') === 'cms') {
            // 注册分页路由
            self::addRoute('/page/{page}', function () {
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if (preg_match('#/page/(\d+)#', $path, $matches)) {
                    $_GET['page'] = (int)$matches[1];
                }
                Anon_Cms_Theme::render('index');
            });
            self::addRoute('/category/{slug}/{page}', function () {
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if (preg_match('#/category/([^/]+)/(\d+)#', $path, $matches)) {
                    $_GET['slug'] = urldecode($matches[1]);
                    $_GET['page'] = (int)$matches[2];
                }
                $template = Anon_Cms_Theme::findTemplate('category') ? 'category' : 'index';
                Anon_Cms_Theme::render($template);
            });
            self::addRoute('/tag/{slug}/{page}', function () {
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if (preg_match('#/tag/([^/]+)/(\d+)#', $path, $matches)) {
                    $_GET['slug'] = urldecode($matches[1]);
                    $_GET['page'] = (int)$matches[2];
                }
                $template = Anon_Cms_Theme::findTemplate('tag') ? 'tag' : 'index';
                Anon_Cms_Theme::render($template);
            });

            self::addRoute('/anon/cms/api-prefix', function () {
                Anon_Common::Header();
                $apiPrefix = Anon_Cms_Options::get('apiPrefix', '/api');
                Anon_Http_Response::success(['apiPrefix' => $apiPrefix], '获取 API 前缀成功');
            });
            self::addRoute('/anon/cms/comments', [Anon_Cms::class, 'handleCommentsRequest'], ['method' => 'GET,POST', 'token' => false]);
            self::addRoute('/admin', function () {
                Anon_Common::Header(200, false, false);
                header('Content-Type: text/html; charset=utf-8');
                try {
                    Anon_Common::Components('Admin/Console');
                } catch (RuntimeException $e) {
                    Anon_Common::Header(500);
                    Anon_Http_Response::serverError('控制台页面文件不存在: ' . $e->getMessage());
                }
                exit;
            });
            self::addStaticRoute('/anon/static/admin/css', $staticDir . 'admin/index.css', 'text/css', 31536000, true, ['token' => false]);
            self::addStaticRoute('/anon/static/admin/js', $staticDir . 'admin/index.js', 'application/javascript', 31536000, true, ['token' => false]);
            self::addStaticRoute('/anon/static/comments', $staticDir . 'comments.js', 'application/javascript', 31536000, true, ['token' => false]);
        }

        if (Anon_Debug::isEnabled()) {
            self::addStaticRoute('/anon/static/debug/css', $staticDir . 'debug.css', 'text/css', $debugCacheTime, true, ['token' => false]);
            self::addStaticRoute('/anon/static/debug/js', $staticDir . 'debug.js', 'application/javascript', $debugCacheTime, true, ['token' => false]);
        }

        self::addStaticRoute('/anon/static/vue', $staticDir . 'vue.global.prod.js', 'application/javascript', 31536000, true, ['token' => false]);
        self::addStaticRoute('/anon/static/install/css', $staticDir . 'install.css', 'text/css', 31536000, true, ['token' => false]);
        self::addStaticRoute('/anon/static/install/js', $staticDir . 'install.js', 'application/javascript', 31536000, true, ['token' => false]);

        self::addRoute('/anon/install', [Anon_System_Install::class, 'index']);
        self::addRoute('/anon/install/api/token', [Anon_System_Install::class, 'apiGetToken']);
        self::addRoute('/anon/install/api/mode', [Anon_System_Install::class, 'apiSelectMode']);
        self::addRoute('/anon/install/api/get-mode', [Anon_System_Install::class, 'apiGetMode']);
        self::addRoute('/anon/install/api/database', [Anon_System_Install::class, 'apiDatabaseConfig']);
        self::addRoute('/anon/install/api/site', [Anon_System_Install::class, 'apiSiteConfig']);
        self::addRoute('/anon/install/api/back', [Anon_System_Install::class, 'apiBack']);
        self::addRoute('/anon/install/api/install', [Anon_System_Install::class, 'apiInstall']);
        self::addRoute('/anon/install/api/confirm-overwrite', [Anon_System_Install::class, 'apiConfirmOverwrite']);
        self::addRoute('/anon', function () {
            if (self::isInstalled()) {
                Anon_Common::Header(403);
                echo json_encode([
                    'code' => 403,
                    'message' => 'Forbidden'
                ]);
                exit;
            } else {
                Anon_System_Install::index();
            }
        });

        Anon_Debug::info("Registered system routes", ['routes' => array_keys(self::$routerConfig['routes'])]);
    }

    /**
     * 初始化应用路由
     */
    public static function initAppRoutes()
    {
        Anon_Debug::debug("Registering app routes");

        if (Anon_Debug::isEnabled()) {
            self::addRoute('/anon/debug/api/info', [Anon_Debug::class, 'debugInfo']);
            self::addRoute('/anon/debug/api/performance', [Anon_Debug::class, 'performanceApi']);
            self::addRoute('/anon/debug/api/logs', [Anon_Debug::class, 'logs']);
            self::addRoute('/anon/debug/api/errors', [Anon_Debug::class, 'errors']);
            self::addRoute('/anon/debug/api/hooks', [Anon_Debug::class, 'hooks']);
            self::addRoute('/anon/debug/api/tools', [Anon_Debug::class, 'tools']);
            self::addRoute('/anon/debug/api/clear', [Anon_Debug::class, 'clearData']);
            self::addRoute('/anon/debug/login', [Anon_Debug::class, 'login']);
            self::addRoute('/anon/debug/console', [Anon_Debug::class, 'console']);
        }

        Anon_Debug::info("Registered app routes", ['routes' => array_keys(self::$routerConfig['routes'])]);
    }

    /**
     * 检测路由冲突
     * @param string $path 路由路径
     * @param callable $newHandler 新处理函数
     * @param callable $existingHandler 现有处理函数
     * @return array
     */
    private static function detectRouteConflict(string $path, callable $newHandler, callable $existingHandler): array
    {
        if ($newHandler === $existingHandler) {
            return ['conflict' => false, 'details' => ''];
        }

        $newReflection = self::getCallableReflection($newHandler);
        $existingReflection = self::getCallableReflection($existingHandler);

        if ($newReflection && $existingReflection) {
            $newInfo = $newReflection['file'] . ':' . $newReflection['line'];
            $existingInfo = $existingReflection['file'] . ':' . $existingReflection['line'];

            if ($newInfo === $existingInfo) {
                return ['conflict' => false, 'details' => ''];
            }
        }

        $newInfo = self::formatHandlerInfo($newHandler);
        $existingInfo = self::formatHandlerInfo($existingHandler);

        return [
            'conflict' => true,
            'details' => "新处理函数: {$newInfo}, 已存在: {$existingInfo}"
        ];
    }

    /**
     * 获取反射信息
     * @param callable $handler 处理函数
     * @return array|null
     */
    private static function getCallableReflection(callable $handler): ?array
    {
        try {
            if (is_string($handler) && function_exists($handler)) {
                $reflection = new ReflectionFunction($handler);
                return [
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine()
                ];
            } elseif (is_array($handler) && count($handler) === 2) {
                $reflection = new ReflectionMethod($handler[0], $handler[1]);
                return [
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine()
                ];
            } elseif ($handler instanceof Closure) {
                $reflection = new ReflectionFunction($handler);
                return [
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine()
                ];
            }
        } catch (Exception $e) {
            // 忽略
        }

        return null;
    }

    /**
     * 格式化处理函数信息
     * @param callable $handler 处理函数
     * @return string
     */
    private static function formatHandlerInfo(callable $handler): string
    {
        if (is_string($handler)) {
            return "function:{$handler}";
        } elseif (is_array($handler) && count($handler) === 2) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];
            return "method:{$class}::{$handler[1]}";
        } elseif ($handler instanceof Closure) {
            return "closure";
        }

        return "unknown";
    }

    /**
     * 获取路由列表
     * @return array
     */
    public static function getRoutesList(): array
    {
        $routes = [];
        $conflicts = [];

        foreach (self::$routerConfig['routes'] as $path => $handler) {
            $handlerInfo = self::formatHandlerInfo($handler);
            $reflection = self::getCallableReflection($handler);

            $routeInfo = [
                'path' => $path,
                'handler' => $handlerInfo,
                'meta' => self::$routerConfig['route_meta'][$path] ?? [],
                'conflict' => false
            ];

            if ($reflection) {
                $routeInfo['file'] = $reflection['file'];
                $routeInfo['line'] = $reflection['line'];
            }

            $routes[] = $routeInfo;
        }

        $pathCounts = [];
        foreach (self::$routerConfig['routes'] as $path => $handler) {
            if (!isset($pathCounts[$path])) {
                $pathCounts[$path] = [];
            }
            $pathCounts[$path][] = $handler;
        }

        foreach ($pathCounts as $path => $handlers) {
            if (count($handlers) > 1) {
                $conflicts[$path] = count($handlers);
                foreach ($routes as &$route) {
                    if ($route['path'] === $path) {
                        $route['conflict'] = true;
                    }
                }
            }
        }

        return [
            'routes' => $routes,
            'conflicts' => $conflicts,
            'total' => count($routes)
        ];
    }
}
