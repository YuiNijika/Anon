<?php
namespace Anon\Modules\System;


use Throwable;
use System;
use Anon\Modules\Debug;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 中间件扩展系统
 */
class Extension
{
    /**  
     * @var array 已注册的中间件
     */
    private static $middlewares = [];

    /**
     * @var bool 初始化状态
     */
    private static $initialized = false;

    /**
     * 注册中间件
     * @param string $name 中间件名称
     * @param callable|array $handler 处理函数或类
     * @param int $priority 优先级
     * @param array $options 选项
     */
    public static function register(string $name, $handler, int $priority = 10, array $options = []): void
    {
        self::$middlewares[$name] = [
            'handler' => $handler,
            'priority' => $priority,
            'options' => $options,
        ];

        Debug::debug("Extension registered", ['name' => $name, 'priority' => $priority]);
    }

    /**
     * 注销中间件
     * @param string $name 中间件名称
     */
    public static function unregister(string $name): void
    {
        if (isset(self::$middlewares[$name])) {
            unset(self::$middlewares[$name]);
            Debug::debug("Extension unregistered", ['name' => $name]);
        }
    }

    /**
     * 检查中间件是否已注册
     * @param string $name 中间件名称
     * @return bool
     */
    public static function isRegistered(string $name): bool
    {
        return isset(self::$middlewares[$name]);
    }

    /**
     * 获取所有已注册的中间件
     * @return array
     */
    public static function getAll(): array
    {
        return self::$middlewares;
    }

    /**
     * 按优先级排序并执行中间件
     * @param string $hookName 钩子名称
     * @param mixed $data 传递的数据
     * @return mixed
     */
    public static function execute(string $hookName, $data = null)
    {
        $middlewares = self::getByHook($hookName);

        foreach ($middlewares as $middleware) {
            $data = self::callHandler($middleware['handler'], $data);
        }

        return $data;
    }

    /**
     * 获取指定钩子的中间件列表
     * @param string $hookName 钩子名称
     * @return array
     */
    private static function getByHook(string $hookName): array
    {
        $middlewares = [];

        foreach (self::$middlewares as $name => $middleware) {
            $hooks = $middleware['options']['hooks'] ?? [$hookName];
            
            if (!is_array($hooks)) {
                $hooks = [$hooks];
            }

            if (in_array($hookName, $hooks, true) || in_array('*', $hooks, true)) {
                $middlewares[$name] = $middleware;
            }
        }

        uasort($middlewares, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $middlewares;
    }

    /**
     * 调用中间件处理器
     * @param callable|array $handler 处理器
     * @param mixed $data 数据
     * @return mixed
     */
    private static function callHandler($handler, $data)
    {
        try {
            if (is_callable($handler)) {
                return $handler($data);
            }

            if (is_array($handler) && isset($handler[0], $handler[1])) {
                if (is_string($handler[0]) && class_exists($handler[0])) {
                    $method = $handler[1];
                    if (method_exists($handler[0], $method)) {
                        return $handler[0]::$method($data);
                    }
                }

                if (is_object($handler[0])) {
                    $method = $handler[1];
                    if (method_exists($handler[0], $method)) {
                        return $handler[0]->$method($data);
                    }
                }
            }

            if (is_string($handler) && class_exists($handler)) {
                if (method_exists($handler, 'handle')) {
                    return $handler::handle($data);
                }
            }

            Debug::error("Extension handler not callable", ['handler' => $handler]);
            return $data;
        } catch (Throwable $e) {
            Debug::error("Extension execution failed", [
                'handler' => is_string($handler) ? $handler : get_class($handler),
                'error' => $e->getMessage()
            ]);
            return $data;
        }
    }

    /**
     * 初始化系统
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        Hook::do_action('extension_system_initialized', self::$middlewares);
    }

    /**
     * 清除所有中间件
     */
    public static function clear(): void
    {
        self::$middlewares = [];
        Debug::info("All extensions cleared");
    }
}
