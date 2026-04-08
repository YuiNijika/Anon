<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 短代码系统
 */
class Anon_System_Shortcode
{
    /**
     * @var array 注册的短代码
     */
    private static $shortcodes = [];

    /**
     * @var bool 是否已初始化
     */
    private static $initialized = false;

    /**
     * 初始化短代码
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::register_default_shortcodes();

        Anon_System_Hook::do_action('anon_register_shortcodes');

        self::$initialized = true;
    }

    /**
     * 注册默认短代码
     */
    private static function register_default_shortcodes(): void
    {
        self::add_shortcode('Anon_Plugin_Editor', function($attrs) {
            return self::render_react_component('MarkdownEditor', [
                'placeholder' => $attrs['placeholder'] ?? '开始写作...',
                'height' => $attrs['height'] ?? '400px',
                'preview' => ($attrs['preview'] ?? 'true') === 'true',
            ]);
        });

        self::add_shortcode('Anon_Plugin_Gallery', function($attrs) {
            $images = isset($attrs['images']) ? explode(',', $attrs['images']) : [];
            return self::render_react_component('ImageGallery', [
                'images' => $images,
                'columns' => intval($attrs['columns'] ?? 3),
                'gap' => intval($attrs['gap'] ?? 16),
                'lightbox' => ($attrs['lightbox'] ?? 'true') === 'true',
            ]);
        });

        self::add_shortcode('Anon_Plugin_Alert', function($attrs) {
            return self::render_react_component('AlertBox', [
                'type' => $attrs['type'] ?? 'info',
                'title' => $attrs['title'] ?? '',
                'closable' => ($attrs['closable'] ?? 'true') === 'true',
                'icon' => ($attrs['icon'] ?? 'true') === 'true',
            ]);
        });
    }

    /**
     * 注册短代码
     * @param string $tag 短代码标签
     * @param callable $callback 回调函数返回HTML标记
     */
    public static function add_shortcode(string $tag, callable $callback): void
    {
        self::$shortcodes[$tag] = $callback;
    }

    /**
     * 移除短代码
     * @param string $tag 短代码标签
     */
    public static function remove_shortcode(string $tag): void
    {
        unset(self::$shortcodes[$tag]);
    }

    /**
     * 获取所有注册的短代码
     * @return array
     */
    public static function get_shortcodes(): array
    {
        return array_keys(self::$shortcodes);
    }

    /**
     * 解析内容中的短代码
     * @param string $content 要解析的内容
     * @return string 解析后的内容
     */
    public static function do_shortcode(string $content): string
    {
        if (empty(self::$shortcodes)) {
            return $content;
        }

        $pattern = '/\[([a-zA-Z0-9_]+)(\s+[^\]]*)?\](?:\[\/\1\])?/';
        
        return preg_replace_callback($pattern, function($matches) {
            $tag = $matches[1];
            $attrs_string = isset($matches[2]) ? trim($matches[2]) : '';
            
            if (!isset(self::$shortcodes[$tag])) {
                return $matches[0];
            }

            $attrs = self::parse_attrs($attrs_string);
            
            $callback = self::$shortcodes[$tag];
            return call_user_func($callback, $attrs);
        }, $content);
    }

    /**
     * 解析短代码属性支持key="value" key='value' key=value格式
     * @param string $attrs_string 属性字符串
     * @return array
     */
    private static function parse_attrs(string $attrs_string): array
    {
        $attrs = [];
        
        if (empty($attrs_string)) {
            return $attrs;
        }

        preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/', $attrs_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
            $attrs[$key] = $value;
        }

        return $attrs;
    }

    /**
     * 生成React组件挂载点HTML
     * @param string $component_name React组件名称
     * @param array $props 传递给组件的属性
     * @return string HTML标记
     */
    public static function render_react_component(string $component_name, array $props = []): string
    {
        $id = 'react-' . md5($component_name . microtime(true) . rand());
        $props_json = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        
        return sprintf(
            '<div class="anon-react-component" data-component="%s" data-props=\'%s\' id="%s"></div>',
            htmlspecialchars($component_name, ENT_QUOTES, 'UTF-8'),
            $props_json,
            $id
        );
    }
}
