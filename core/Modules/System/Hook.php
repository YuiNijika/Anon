<?php

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_Hook {
    
    /**
     * @var array 注册的钩子
     */
    private static $hooks = [];
    
    /**
     * @var array 钩子统计
     */
    private static $stats = [];
    
    /**
     * @var array 当前钩子栈
     */
    private static $currentHook = [];

    /**
     * @var array 回调缓存
     */
    private static $callbackCache = [];

    /**
     * @var array 结果缓存
     */
    private static $resultCache = [];
    
    /**
     * 添加动作钩子
     * 
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $accepted_args 参数数量
     * @return bool
     */
    public static function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return self::add_hook($hook_name, $callback, $priority, $accepted_args, 'action');
    }
    
    /**
     * 添加过滤器钩子
     * 
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $accepted_args 参数数量
     * @return bool
     */
    public static function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return self::add_hook($hook_name, $callback, $priority, $accepted_args, 'filter');
    }
    
    /**
     * 执行动作钩子
     * 
     * @param string $hook_name 钩子名称
     * @param mixed ...$args 参数
     * @return void
     */
    public static function do_action($hook_name, ...$args) {
        self::execute_hooks($hook_name, $args, 'action');
    }
    
    /**
     * 应用过滤器钩子
     * 
     * @param string $hook_name 钩子名称
     * @param mixed $value 初始值
     * @param mixed ...$args 额外参数
     * @return mixed
     */
    public static function apply_filters($hook_name, $value, ...$args) {
        array_unshift($args, $value);
        return self::execute_hooks($hook_name, $args, 'filter');
    }
    
    /**
     * 移除钩子
     * 
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @return bool
     */
    public static function removeHook($hook_name, $callback, $priority = 10) {
        if (!isset(self::$hooks[$hook_name][$priority])) {
            return false;
        }
        
        $hook_id = self::getHookId($callback);
        if (isset(self::$hooks[$hook_name][$priority][$hook_id])) {
            unset(self::$hooks[$hook_name][$priority][$hook_id]);
            
            if (empty(self::$hooks[$hook_name][$priority])) {
                unset(self::$hooks[$hook_name][$priority]);
            }
            
            if (empty(self::$hooks[$hook_name])) {
                unset(self::$hooks[$hook_name]);
            }
            
            self::debugLog("移除钩子: {$hook_name} (优先级: {$priority})");
            return true;
        }
        
        return false;
    }
    
    /**
     * 移除所有钩子
     * 
     * @param string|null $hook_name 钩子名称
     * @param int|null $priority 优先级
     * @return bool
     */
    public static function removeAllHooks($hook_name = null, $priority = null) {
        if ($hook_name === null) {
            self::$hooks = [];
            self::debugLog("移除所有钩子");
            return true;
        }
        
        if (!isset(self::$hooks[$hook_name])) {
            return false;
        }
        
        if ($priority !== null) {
            if (isset(self::$hooks[$hook_name][$priority])) {
                unset(self::$hooks[$hook_name][$priority]);
                self::debugLog("移除钩子组: {$hook_name} (优先级: {$priority})");
            }
        } else {
            unset(self::$hooks[$hook_name]);
            self::debugLog("移除所有钩子: {$hook_name}");
        }
        
        return true;
    }
    
    /**
     * 检查钩子
     * 
     * @param string $hook_name 钩子名称
     * @param callable|null $callback 回调函数
     * @return bool|int
     */
    public static function hasHook($hook_name, $callback = null) {
        if (!isset(self::$hooks[$hook_name])) {
            return false;
        }
        
        if ($callback === null) {
            return !empty(self::$hooks[$hook_name]);
        }
        
        $hook_id = self::getHookId($callback);
        foreach (self::$hooks[$hook_name] as $priority => $hooks) {
            if (isset($hooks[$hook_id])) {
                return $priority;
            }
        }
        
        return false;
    }
    
    /**
     * 获取当前钩子
     * 
     * @return string|null
     */
    public static function getCurrentHook() {
        return end(self::$currentHook) ?: null;
    }
    
    /**
     * 获取钩子统计
     * 
     * @param string|null $hook_name 钩子名称
     * @return array
     */
    public static function getHookStats($hook_name = null) {
        if ($hook_name !== null) {
            return self::$stats[$hook_name] ?? [];
        }
        return self::$stats;
    }
    
    /**
     * 获取所有钩子
     * 
     * @return array
     */
    public static function getAllHooks() {
        return self::$hooks;
    }
    
    /**
     * 清除统计
     * 
     * @param string|null $hook_name 钩子名称
     */
    public static function clearStats($hook_name = null) {
        if ($hook_name !== null) {
            unset(self::$stats[$hook_name]);
        } else {
            self::$stats = [];
        }
    }
    
    /**
     * 添加钩子内部实现
     */
    private static function add_hook($hook_name, $callback, $priority, $accepted_args, $type) {
        if (!is_callable($callback)) {
            self::debugLog("无效的回调函数: {$hook_name}", 'ERROR');
            return false;
        }

        if ($callback instanceof Closure) {
            self::debugLog("警告: 建议使用命名函数或类方法: {$hook_name}", 'WARN');
        }
        
        $hook_id = self::getHookId($callback);
        
        if (!isset(self::$callbackCache[$hook_id])) {
            self::$callbackCache[$hook_id] = [
                'callback' => $callback,
                'is_closure' => $callback instanceof Closure,
                'is_string' => is_string($callback),
                'is_array' => is_array($callback)
            ];
        }
        
        self::$hooks[$hook_name][$priority][$hook_id] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args,
            'type' => $type,
            'added_at' => microtime(true)
        ];
        
        ksort(self::$hooks[$hook_name]);
        
        if (isset(self::$resultCache[$hook_name])) {
            unset(self::$resultCache[$hook_name]);
        }
        
        self::debugLog("添加{$type}钩子: {$hook_name} (优先级: {$priority})");
        return true;
    }
    
    /**
     * 执行钩子内部实现
     */
    private static function execute_hooks($hook_name, $args, $type) {
        if (!isset(self::$hooks[$hook_name])) {
            return $type === 'filter' ? ($args[0] ?? null) : null;
        }
        
        self::$currentHook[] = $hook_name;
        
        $start_time = microtime(true);
        $executed_count = 0;
        $value = $type === 'filter' ? ($args[0] ?? null) : null;
        
        try {
            foreach (self::$hooks[$hook_name] as $priority => $hooks) {
                foreach ($hooks as $hook_id => $hook_data) {
                    if ($hook_data['type'] !== $type) {
                        continue;
                    }
                    
                    $callback = $hook_data['callback'];
                    $accepted_args = $hook_data['accepted_args'];
                    
                    $callback_args = array_slice($args, 0, $accepted_args);
                    
                    try {
                        $hook_start = microtime(true);
                        
                        if ($type === 'action') {
                            call_user_func_array($callback, $callback_args);
                        } else {
                            $value = call_user_func_array($callback, array_merge([$value], array_slice($callback_args, 1)));
                            $args[0] = $value;
                        }
                        
                        $hook_time = microtime(true) - $hook_start;
                        $executed_count++;
                        
                        self::debugLog("执行钩子: {$hook_name} (优先级: {$priority}, 耗时: " . number_format($hook_time * 1000, 2) . "ms)");
                        
                    } catch (Exception $e) {
                        self::debugLog("钩子执行错误: {$hook_name} - " . $e->getMessage(), 'ERROR');
                        echo "Hook callback error: " . $e->getMessage() . "\n";
                    } catch (Error $e) {
                        self::debugLog("钩子回调错误: {$hook_name} - " . $e->getMessage(), 'ERROR');
                        echo "Hook callback error: " . $e->getMessage() . "\n";
                    }
                }
            }
        } finally {
            array_pop(self::$currentHook);
        }
        
        $total_time = microtime(true) - $start_time;
        
        if (!isset(self::$stats[$hook_name])) {
            self::$stats[$hook_name] = [
                'total_calls' => 0,
                'total_time' => 0,
                'total_executed' => 0,
                'type' => $type
            ];
        }
        
        self::$stats[$hook_name]['total_calls']++;
        self::$stats[$hook_name]['total_time'] += $total_time;
        self::$stats[$hook_name]['total_executed'] += $executed_count;
        
        return $type === 'filter' ? $value : null;
    }
    
    /**
     * 生成钩子ID
     */
    private static function getHookId($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return spl_object_hash($callback[0]) . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif ($callback instanceof Closure) {
            return spl_object_hash($callback);
        } else {
            return serialize($callback);
        }
    }
    
    /**
     * 调试日志
     */
    private static function debugLog($message, $level = 'DEBUG') {
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::log($message, $level, 'HOOK');
        }
    }
}