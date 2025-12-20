<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Widget
{
    private static $instance = null;
    private $widgets = [];
    
    private function __construct()
    {
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 注册 Widget 组件
     * @param string $id Widget ID
     * @param string $name Widget 名称
     * @param callable $callback 回调函数可以返回数组或对象用于 JSON 模式或输出 HTML 用于 HTML 模式
     * @param array $args 额外参数
     * @param string $type 输出类型：'auto' 自动检测、'html' HTML 输出、'json' JSON 数据
     * @return bool
     */
    public function register(string $id, string $name, callable $callback, array $args = [], string $type = 'auto'): bool
    {
        $this->widgets[$id] = [
            'id' => $id,
            'name' => $name,
            'callback' => $callback,
            'type' => in_array($type, ['auto', 'html', 'json']) ? $type : 'auto',
            'args' => array_merge([
                'description' => '',
                'class' => '',
            ], $args),
        ];
        
        Anon_Hook::do_action('widget_registered', $id, $name);
        
        return true;
    }
    
    public function unregister(string $id): bool
    {
        if (isset($this->widgets[$id])) {
            unset($this->widgets[$id]);
            
            Anon_Hook::do_action('widget_unregistered', $id);
            
            return true;
        }
        return false;
    }
    
    /**
     * 渲染 HTML 输出模式的 Widget
     * @param string $id Widget ID
     * @param array $args 额外参数
     * @return string HTML 字符串
     */
    public function render(string $id, array $args = []): string
    {
        if (!isset($this->widgets[$id])) {
            return '';
        }
        
        $widget = $this->widgets[$id];
        $callback = $widget['callback'];
        
        if (!is_callable($callback)) {
            return '';
        }
        
        $widgetArgs = array_merge($widget['args'], $args);
        
        $widgetArgs = Anon_Hook::apply_filters('widget_args', $widgetArgs, $id);
        
        ob_start();
        $result = call_user_func($callback, $widgetArgs);
        $output = ob_get_clean();
        
        // 如果回调返回了值且输出为空则使用返回值以保持向后兼容
        if (empty($output) && $result !== null) {
            $output = is_string($result) ? $result : '';
        }
        
        $output = Anon_Hook::apply_filters('widget_output', $output, $id);
        
        return $output;
    }
    
    /**
     * 获取 JSON API 模式的 Widget 数据
     * @param string $id Widget ID
     * @param array $args 额外参数
     * @return array|null Widget 数据数组，不存在或失败返回 null
     */
    public function getData(string $id, array $args = []): ?array
    {
        if (!isset($this->widgets[$id])) {
            return null;
        }
        
        $widget = $this->widgets[$id];
        $callback = $widget['callback'];
        
        if (!is_callable($callback)) {
            return null;
        }
        
        $widgetArgs = array_merge($widget['args'], $args);
        
        $widgetArgs = Anon_Hook::apply_filters('widget_args', $widgetArgs, $id);
        
        // 执行回调
        $result = call_user_func($callback, $widgetArgs);
        
        // 如果回调返回数组或对象，直接使用
        if (is_array($result)) {
            $data = $result;
        } elseif (is_object($result)) {
            // 对象转数组
            $data = method_exists($result, 'toArray') ? $result->toArray() : (array)$result;
        } else {
            // 如果回调没有返回值则尝试从输出缓冲获取以保持向后兼容
            ob_start();
            call_user_func($callback, $widgetArgs);
            $output = ob_get_clean();
            
            // 尝试解析JSON输出
            if (!empty($output)) {
                $decoded = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                } else {
                    // 如果不是JSON，返回原始输出作为data字段
                    $data = ['content' => $output];
                }
            } else {
                return null;
            }
        }
        
        // 应用过滤器
        $data = Anon_Hook::apply_filters('widget_data', $data, $id);
        
        return $data;
    }
    
    /**
     * 获取 JSON API 模式的 Widget JSON 字符串
     * @param string $id Widget ID
     * @param array $args 额外参数
     * @param int $options JSON 编码选项
     * @return string|null JSON 字符串，不存在或失败返回 null
     */
    public function getJson(string $id, array $args = [], int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): ?string
    {
        $data = $this->getData($id, $args);
        
        if ($data === null) {
            return null;
        }
        
        $json = json_encode($data, $options);
        
        if ($json === false) {
            return null;
        }
        
        return $json;
    }
    
    /**
     * 获取不执行回调的 Widget 信息
     * @param string $id Widget ID
     * @return array|null Widget 信息，不存在返回 null
     */
    public function getInfo(string $id): ?array
    {
        if (!isset($this->widgets[$id])) {
            return null;
        }
        
        $widget = $this->widgets[$id];
        
        return [
            'id' => $widget['id'],
            'name' => $widget['name'],
            'type' => $widget['type'],
            'args' => $widget['args'],
        ];
    }
    
    /**
     * 获取仅信息不执行回调的所有 Widget 列表
     * @return array Widget 信息列表
     */
    public function list(): array
    {
        $list = [];
        foreach ($this->widgets as $id => $widget) {
            $list[] = [
                'id' => $widget['id'],
                'name' => $widget['name'],
                'type' => $widget['type'],
                'args' => $widget['args'],
            ];
        }
        return $list;
    }
    
    /**
     * 获取所有包含回调函数的 Widget 用于内部使用
     * @return array
     */
    public function all(): array
    {
        return $this->widgets;
    }
    
    public function exists(string $id): bool
    {
        return isset($this->widgets[$id]);
    }
}

