<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_Widget
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
     * 注册组件
     * @param string $id ID
     * @param string $name 名称
     * @param callable $callback 回调函数
     * @param array $args 参数
     * @param string $type 输出类型
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
        
        Anon_System_Hook::do_action('widget_registered', $id, $name);
        
        return true;
    }
    
    public function unregister(string $id): bool
    {
        if (isset($this->widgets[$id])) {
            unset($this->widgets[$id]);
            
            Anon_System_Hook::do_action('widget_unregistered', $id);
            
            return true;
        }
        return false;
    }
    
    /**
     * 渲染HTML
     * @param string $id ID
     * @param array $args 参数
     * @return string
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
        
        $widgetArgs = Anon_System_Hook::apply_filters('widget_args', $widgetArgs, $id);
        
        ob_start();
        $result = call_user_func($callback, $widgetArgs);
        $output = ob_get_clean();
        
        if (empty($output) && $result !== null) {
            $output = is_string($result) ? $result : '';
        }
        
        $output = Anon_System_Hook::apply_filters('widget_output', $output, $id);
        
        return $output;
    }
    
    /**
     * 获取JSON数据
     * @param string $id ID
     * @param array $args 参数
     * @return array|null
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
        
        $widgetArgs = Anon_System_Hook::apply_filters('widget_args', $widgetArgs, $id);
        
        $result = call_user_func($callback, $widgetArgs);
        
        if (is_array($result)) {
            $data = $result;
        } elseif (is_object($result)) {
            $data = method_exists($result, 'toArray') ? $result->toArray() : (array)$result;
        } else {
            ob_start();
            call_user_func($callback, $widgetArgs);
            $output = ob_get_clean();
            
            if (!empty($output)) {
                $decoded = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                } else {
                    $data = ['content' => $output];
                }
            } else {
                return null;
            }
        }
        
        $data = Anon_System_Hook::apply_filters('widget_data', $data, $id);
        
        return $data;
    }
    
    /**
     * 获取JSON字符串
     * @param string $id ID
     * @param array $args 参数
     * @param int $options JSON选项
     * @return string|null
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
     * 获取组件信息
     * @param string $id ID
     * @return array|null
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
     * 获取组件列表
     * @return array
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
     * 获取所有组件
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

