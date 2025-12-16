<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 中间件接口
 */
interface Anon_MiddlewareInterface
{
    /**
     * 处理请求
     * @param mixed $request 请求对象
     * @param callable $next 下一个中间件
     * @return mixed
     */
    public function handle($request, callable $next);
}

/**
 * 中间件管理器
 */
class Anon_Middleware
{
    /**
     * @var array 全局中间件
     */
    private static $globalMiddleware = [];

    /**
     * @var array 路由中间件
     */
    private static $routeMiddleware = [];

    /**
     * @var array 中间件别名
     */
    private static $aliases = [];

    /**
     * 注册全局中间件
     * @param string|callable|object $middleware 中间件类名、闭包或实例
     * @return void
     */
    public static function global($middleware): void
    {
        self::$globalMiddleware[] = $middleware;
    }

    /**
     * 注册别名路由中间件
     * @param string $alias 中间件别名
     * @param string|callable|object $middleware 中间件类名、闭包或实例
     * @return void
     */
    public static function alias(string $alias, $middleware): void
    {
        self::$aliases[$alias] = $middleware;
    }

    /**
     * 获取中间件实例
     * @param string|callable|object $middleware 中间件
     * @return callable
     */
    private static function resolve($middleware): callable
    {
        // 如果是闭包，直接返回
        if (is_callable($middleware) && !is_string($middleware)) {
            return $middleware;
        }

        // 如果是字符串 则认为是类名或别名
        if (is_string($middleware)) {
            // 检查是否是别名
            if (isset(self::$aliases[$middleware])) {
                $middleware = self::$aliases[$middleware];
            }

            // 如果是类名，尝试从容器解析或直接实例化
            if (is_string($middleware) && class_exists($middleware)) {
                if (class_exists('Anon_Container')) {
                    $instance = Anon_Container::getInstance()->make($middleware);
                } else {
                    $instance = new $middleware();
                }

                if ($instance instanceof Anon_MiddlewareInterface) {
                    return [$instance, 'handle'];
                }

                if (is_callable($instance)) {
                    return $instance;
                }
            }
        }

        // 如果是对象
        if (is_object($middleware)) {
            if ($middleware instanceof Anon_MiddlewareInterface) {
                return [$middleware, 'handle'];
            }

            if (is_callable($middleware)) {
                return $middleware;
            }
        }

        throw new RuntimeException("无效的中间件: " . gettype($middleware));
    }

    /**
     * 执行中间件管道
     * @param array $middlewares 中间件列表
     * @param mixed $request 请求对象
     * @param callable $finalHandler 最终处理器
     * @return mixed
     */
    public static function pipeline(array $middlewares, $request, callable $finalHandler)
    {
        // 合并全局中间件和路由中间件
        $allMiddlewares = array_merge(self::$globalMiddleware, $middlewares);

        // 构建中间件管道
        $pipeline = array_reduce(
            array_reverse($allMiddlewares),
            function ($carry, $middleware) {
                return function ($request) use ($middleware, $carry) {
                    $handler = self::resolve($middleware);
                    return $handler($request, $carry);
                };
            },
            $finalHandler
        );

        return $pipeline($request);
    }

    /**
     * 清空中间件 用于测试
     * @return void
     */
    public static function flush(): void
    {
        self::$globalMiddleware = [];
        self::$routeMiddleware = [];
        self::$aliases = [];
    }
}

