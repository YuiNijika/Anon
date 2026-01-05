<?php

/**
 * Anon配置
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_Config
{
    /**
     * 路由配置
     * @var array
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
     * @param array $meta 路由元数据（可选）
     * @throws RuntimeException 如果路由冲突
     */
    public static function addRoute(string $path, callable $handler, array $meta = [])
    {
        // 规范化路由键，确保以 / 开头，避免匹配失败
        $normalized = (strpos($path, '/') === 0) ? $path : '/' . $path;
        
        // 检测路由冲突
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
        
        // 存储路由元数据
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
            
            // 验证并合并元数据
            $allowedKeys = ['header', 'requireLogin', 'method', 'cors', 'response', 'code', 'token', 'middleware', 'cache'];
            $meta = array_intersect_key($meta, array_flip($allowedKeys));
            
            // 验证 cache 配置结构
            if (isset($meta['cache']) && is_array($meta['cache'])) {
                $cacheAllowedKeys = ['enabled', 'time'];
                $meta['cache'] = array_intersect_key($meta['cache'], array_flip($cacheAllowedKeys));
                
                // 类型验证
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
     * @return array|null 路由元数据，未找到返回 null
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
     * @param string $filePath 文件完整路径
     * @param string $mimeType MIME类型
     * @param int $cacheTime 缓存时间（秒），0表示不缓存
     * @param bool $compress 是否启用压缩
     */
    public static function addStaticRoute(string $route, string $filePath, string $mimeType, int $cacheTime = 31536000, bool $compress = true, array $meta = [])
    {
        $defaultMeta = [
            'header' => false,
            'token' => false,
            'requireLogin' => false
        ];
        $meta = array_merge($defaultMeta, $meta);
        
        self::addRoute($route, function() use ($filePath, $mimeType, $cacheTime, $compress) {
            if (!is_file($filePath) || !is_readable($filePath)) {
                http_response_code(404);
                exit;
            }
            
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
            
            if ($cacheTime > 0) {
                header('Cache-Control: public, max-age=' . $cacheTime);
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
            } else {
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
            
            if ($compress && extension_loaded('zlib')) {
                $compressibleTypes = ['text/html', 'text/css', 'text/javascript', 'application/javascript', 'application/json', 'text/xml', 'application/xml'];
                if (in_array($mimeType, $compressibleTypes)) {
                    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
                    $fileContent = file_get_contents($filePath);
                    if ($fileContent !== false && strpos($acceptEncoding, 'gzip') !== false) {
                        $compressed = gzencode($fileContent);
                        header('Content-Encoding: gzip');
                        header('Content-Length: ' . strlen($compressed));
                        echo $compressed;
                        exit;
                    }
                }
            }
            
            readfile($filePath);
            exit;
        }, $meta);
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
        return defined('ANON_INSTALLED') && ANON_INSTALLED;
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
        
        self::addRoute('/anon/common/license', function() {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::LICENSE_TEXT(), '获取许可证信息成功');
        });
        self::addRoute('/anon/common/system', function() {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::SystemInfo(), '获取系统信息成功');
        });
        self::addRoute('/anon/common/client-ip', function() {
            Anon_Common::Header();
            $ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
            Anon_Http_Response::success(['ip' => $ip], '获取客户端IP成功');
        });
        self::addRoute('/anon/common/config', function() {
            Anon_Common::Header();
            $config = [
                'token' => Anon_Auth_Token::isEnabled(),
                'captcha' => Anon_Auth_Captcha::isEnabled(),
                'csrfToken' => class_exists('Anon_Csrf') ? Anon_Auth_Csrf::generateToken() : null
            ];
            Anon_Http_Response::success($config, '获取配置信息成功');
        });
        self::addRoute('/anon/ciallo', function() {
            Anon_Common::Header();
            Anon_Http_Response::success(Anon_Common::Ciallo(), '恰喽~');
        });

        // 注册静态文件路由
        $staticDir = __DIR__ . '/../../Static/';
        
        // 获取 debug 缓存配置
        $debugCacheEnabled = Anon_System_Env::get('app.debug.cache.enabled', false);
        $debugCacheTime = Anon_System_Env::get('app.debug.cache.time', 0);
        $debugCacheTime = $debugCacheEnabled ? $debugCacheTime : 0;
        
        self::addStaticRoute('/anon/static/debug/css', $staticDir . 'debug.css', 'text/css', $debugCacheTime, true, ['token' => false]);
        self::addStaticRoute('/anon/static/debug/js', $staticDir . 'debug.js', 'application/javascript', $debugCacheTime, true, ['token' => false]);
        self::addStaticRoute('/anon/static/vue', $staticDir . 'vue.global.prod.js', 'application/javascript', 31536000, true, ['token' => false]);

        // 注册Install路由
        self::addRoute('/anon/install', [Anon_System_Install::class, 'index']);
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
                Anon_System_Install::index();
            }
        });
        
        // 调试输出已注册的路由
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registered system routes: " . json_encode(array_keys(self::$routerConfig['routes'])));
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
        // API路由
        self::addRoute('/anon/debug/api/info', [Anon_Debug::class, 'debugInfo']);
        self::addRoute('/anon/debug/api/performance', [Anon_Debug::class, 'performanceApi']);
        self::addRoute('/anon/debug/api/logs', [Anon_Debug::class, 'logs']);
        self::addRoute('/anon/debug/api/errors', [Anon_Debug::class, 'errors']);
        self::addRoute('/anon/debug/api/hooks', [Anon_Debug::class, 'hooks']);
        self::addRoute('/anon/debug/api/tools', [Anon_Debug::class, 'tools']);
        self::addRoute('/anon/debug/api/clear', [Anon_Debug::class, 'clearData']);
        // 页面路由
        self::addRoute('/anon/debug/login', [Anon_Debug::class, 'login']);
        self::addRoute('/anon/debug/console', [Anon_Debug::class, 'console']);

        // 调试输出已注册的路由
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log("Registered app routes: " . json_encode(array_keys(self::$routerConfig['routes'])));
        }
    }

    /**
     * 检测路由冲突
     * @param string $path 路由路径
     * @param callable $newHandler 新的处理函数
     * @param callable $existingHandler 已存在的处理函数
     * @return array 冲突信息
     */
    private static function detectRouteConflict(string $path, callable $newHandler, callable $existingHandler): array
    {
        // 检查是否是同一个处理函数（允许重复注册相同的处理函数）
        if ($newHandler === $existingHandler) {
            return ['conflict' => false, 'details' => ''];
        }
        
        // 检查处理函数是否相同（通过反射比较）
        $newReflection = self::getCallableReflection($newHandler);
        $existingReflection = self::getCallableReflection($existingHandler);
        
        if ($newReflection && $existingReflection) {
            $newInfo = $newReflection['file'] . ':' . $newReflection['line'];
            $existingInfo = $existingReflection['file'] . ':' . $existingReflection['line'];
            
            if ($newInfo === $existingInfo) {
                return ['conflict' => false, 'details' => ''];
            }
        }
        
        // 存在冲突
        $newInfo = self::formatHandlerInfo($newHandler);
        $existingInfo = self::formatHandlerInfo($existingHandler);
        
        return [
            'conflict' => true,
            'details' => "新处理函数: {$newInfo}, 已存在: {$existingInfo}"
        ];
    }

    /**
     * 获取可调用对象的反射信息
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
            // 忽略反射错误
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
     * 获取所有路由列表（用于 CLI 命令）
     * @return array 路由列表，包含冲突信息
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
        
        // 检测冲突（检查是否有多个处理函数注册到同一路径）
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
                // 标记冲突的路由
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
