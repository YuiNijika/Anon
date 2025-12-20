<?php

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Hook {
    
    /**
     * 存储所有注册的钩子
     * @var array
     */
    private static $hooks = [];
    
    /**
     * 存储钩子执行统计信息
     * @var array
     */
    private static $stats = [];
    
    /**
     * 当前执行的钩子栈
     * @var array
     */
    private static $currentHook = [];
    
    /**
     * 添加动作钩子
     * 
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级，数字越小优先级越高
     * @param int $accepted_args 接受的参数数量
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
     * @param int $priority 优先级，数字越小优先级越高
     * @param int $accepted_args 接受的参数数量
     * @return bool
     */
    public static function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return self::add_hook($hook_name, $callback, $priority, $accepted_args, 'filter');
    }
    
    /**
     * 执行动作钩子
     * 
     * @param string $hook_name 钩子名称
     * @param mixed ...$args 传递给钩子的参数
     * @return void
     */
    public static function do_action($hook_name, ...$args) {
        self::execute_hooks($hook_name, $args, 'action');
    }
    
    /**
     * 应用过滤器钩子
     * 
     * @param string $hook_name 钩子名称
     * @param mixed $value 要过滤的值
     * @param mixed ...$args 额外参数
     * @return mixed 过滤后的值
     */
    public static function apply_filters($hook_name, $value, ...$args) {
        array_unshift($args, $value);
        return self::execute_hooks($hook_name, $args, 'filter');
    }
    
    /**
     * 移除指定钩子
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
            
            // 如果该优先级下没有钩子了，删除该优先级
            if (empty(self::$hooks[$hook_name][$priority])) {
                unset(self::$hooks[$hook_name][$priority]);
            }
            
            // 如果该钩子名下没有任何钩子了，删除该钩子名
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
     * @param string|null $hook_name 钩子名称，null表示移除所有钩子
     * @param int|null $priority 优先级，null表示移除所有优先级
     * @return bool
     */
    public static function removeAllHooks($hook_name = null, $priority = null) {
        if ($hook_name === null) {
            // 移除所有钩子
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
     * 检查钩子是否存在
     * 
     * @param string $hook_name 钩子名称
     * @param callable|null $callback 回调函数，null表示检查钩子名是否存在
     * @return bool|int 存在返回true或优先级，不存在返回false
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
     * 获取当前执行的钩子名称
     * 
     * @return string|null
     */
    public static function getCurrentHook() {
        return end(self::$currentHook) ?: null;
    }
    
    /**
     * 获取钩子统计信息
     * 
     * @param string|null $hook_name 钩子名称，null返回所有统计
     * @return array
     */
    public static function getHookStats($hook_name = null) {
        if ($hook_name !== null) {
            return self::$stats[$hook_name] ?? [];
        }
        return self::$stats;
    }
    
    /**
     * 获取所有注册的钩子
     * 
     * @return array
     */
    public static function getAllHooks() {
        return self::$hooks;
    }
    
    /**
     * 清除钩子统计信息
     * 
     * @param string|null $hook_name 钩子名称，null清除所有统计
     */
    public static function clearStats($hook_name = null) {
        if ($hook_name !== null) {
            unset(self::$stats[$hook_name]);
        } else {
            self::$stats = [];
        }
    }
    
    // ========== 向后兼容 ==========
    
    /**
     * @deprecated 使用 removeHook() 代替
     */
    public static function remove_hook($hook_name, $callback, $priority = 10) {
        return self::removeHook($hook_name, $callback, $priority);
    }
    
    /**
     * @deprecated 使用 removeAllHooks() 代替
     */
    public static function remove_all_hooks($hook_name = null, $priority = null) {
        return self::removeAllHooks($hook_name, $priority);
    }
    
    /**
     * @deprecated 使用 hasHook() 代替
     */
    public static function has_hook($hook_name, $callback = null) {
        return self::hasHook($hook_name, $callback);
    }
    
    /**
     * @deprecated 使用 getCurrentHook() 代替
     */
    public static function current_hook() {
        return self::getCurrentHook();
    }
    
    /**
     * @deprecated 使用 getHookStats() 代替
     */
    public static function get_hook_stats($hook_name = null) {
        return self::getHookStats($hook_name);
    }
    
    /**
     * @deprecated 使用 getAllHooks() 代替
     */
    public static function get_all_hooks() {
        return self::getAllHooks();
    }
    
    /**
     * @deprecated 使用 clearStats() 代替
     */
    public static function clear_stats($hook_name = null) {
        return self::clearStats($hook_name);
    }
    
    /**
     * 添加钩子的内部实现
     * 
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $accepted_args 接受的参数数量
     * @param string $type 钩子类型 (action|filter)
     * @return bool
     */
    private static function add_hook($hook_name, $callback, $priority, $accepted_args, $type) {
        if (!is_callable($callback)) {
            self::debugLog("无效的回调函数: {$hook_name}", 'ERROR');
            return false;
        }
        
        $hook_id = self::getHookId($callback);
        
        self::$hooks[$hook_name][$priority][$hook_id] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args,
            'type' => $type,
            'added_at' => microtime(true)
        ];
        
        // 按优先级排序
        ksort(self::$hooks[$hook_name]);
        
        self::debugLog("添加{$type}钩子: {$hook_name} (优先级: {$priority})");
        return true;
    }
    
    /**
     * 执行钩子的内部实现
     * 
     * @param string $hook_name 钩子名称
     * @param array $args 参数数组
     * @param string $type 钩子类型 (action|filter)
     * @return mixed
     */
    private static function execute_hooks($hook_name, $args, $type) {
        if (!isset(self::$hooks[$hook_name])) {
            return $type === 'filter' ? ($args[0] ?? null) : null;
        }
        
        // 记录当前执行的钩子
        self::$currentHook[] = $hook_name;
        
        $start_time = microtime(true);
        $executed_count = 0;
        $value = $type === 'filter' ? ($args[0] ?? null) : null;
        
        try {
            // 按优先级执行钩子
            foreach (self::$hooks[$hook_name] as $priority => $hooks) {
                foreach ($hooks as $hook_id => $hook_data) {
                    if ($hook_data['type'] !== $type) {
                        continue;
                    }
                    
                    $callback = $hook_data['callback'];
                    $accepted_args = $hook_data['accepted_args'];
                    
                    // 准备参数
                    $callback_args = array_slice($args, 0, $accepted_args);
                    
                    try {
                        $hook_start = microtime(true);
                        
                        if ($type === 'action') {
                            call_user_func_array($callback, $callback_args);
                        } else {
                            $value = call_user_func_array($callback, array_merge([$value], array_slice($callback_args, 1)));
                            $args[0] = $value; // 更新第一个参数为过滤后的值
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
            // 移除当前钩子
            array_pop(self::$currentHook);
        }
        
        $total_time = microtime(true) - $start_time;
        
        // 记录统计信息
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
     * 
     * @param callable $callback 回调函数
     * @return string
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
     * 
     * @param string $message 消息
     * @param string $level 日志级别
     */
    private static function debugLog($message, $level = 'DEBUG') {
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::log($message, $level, 'HOOK');
        }
    }
}