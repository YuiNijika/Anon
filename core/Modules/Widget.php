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
    
    public function register(string $id, string $name, callable $callback, array $args = []): bool
    {
        $this->widgets[$id] = [
            'id' => $id,
            'name' => $name,
            'callback' => $callback,
            'args' => array_merge([
                'description' => '',
                'class' => '',
            ], $args),
        ];
        
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('widget_registered', $id, $name);
        }
        
        return true;
    }
    
    public function unregister(string $id): bool
    {
        if (isset($this->widgets[$id])) {
            unset($this->widgets[$id]);
            
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('widget_unregistered', $id);
            }
            
            return true;
        }
        return false;
    }
    
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
        
        if (class_exists('Anon_Hook')) {
            $widgetArgs = Anon_Hook::apply_filters('widget_args', $widgetArgs, $id);
        }
        
        ob_start();
        call_user_func($callback, $widgetArgs);
        $output = ob_get_clean();
        
        if (class_exists('Anon_Hook')) {
            $output = Anon_Hook::apply_filters('widget_output', $output, $id);
        }
        
        return $output;
    }
    
    public function all(): array
    {
        return $this->widgets;
    }
    
    public function exists(string $id): bool
    {
        return isset($this->widgets[$id]);
    }
}

