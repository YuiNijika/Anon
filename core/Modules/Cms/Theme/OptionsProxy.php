<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 统一选项代理，合并插件、主题、系统三源
 * 插件内默认顺序 plugin > theme > system，主题内默认 theme > plugin > system
 */
class Anon_Cms_Options_Proxy
{
    /** @var string 上下文 plugin 或 theme */
    private $context;

    /** @var string|null 插件 slug */
    private $pluginSlug;

    /** @var string|null 主题名 */
    private $themeName;

    public function __construct(string $context, ?string $pluginSlug = null, ?string $themeName = null)
    {
        $this->context = $context;
        $this->pluginSlug = $pluginSlug;
        $this->themeName = $themeName;
    }

    /**
     * 解析优先级得到源顺序
     * @param string|null $priority plugin 或 theme 或 system，null 时按上下文
     * @return string[] 源顺序
     */
    private function order(?string $priority): array
    {
        if ($priority === 'plugin') {
            return ['plugin', 'theme', 'system'];
        }
        if ($priority === 'theme') {
            return ['theme', 'plugin', 'system'];
        }
        if ($priority === 'system') {
            return ['system'];
        }
        return $this->context === 'plugin'
            ? ['plugin', 'theme', 'system']
            : ['theme', 'plugin', 'system'];
    }

    /**
     * 获取选项值
     * 第三参可为 bool 或 string：bool 表示是否 echo，string 且为 plugin/theme/system 表示优先级；不传时默认 echo
     * @param string $name 选项名
     * @param mixed $default 默认值
     * @param bool|string|null $outputOrPriority true 先 echo 再返回，false 仅返回；或传 plugin/theme/system 指定优先级并 echo
     * @param string|null $priority 第四参时有效，指定优先级
     * @return mixed
     */
    public function get(string $name, $default = null, $outputOrPriority = true, ?string $priority = null)
    {
        $output = true;
        $resolvedPriority = null;
        if (func_num_args() >= 3) {
            $third = $outputOrPriority;
            if (is_string($third) && in_array($third, ['plugin', 'theme', 'system'], true)) {
                $resolvedPriority = $third;
                $output = func_num_args() >= 4 ? (bool) $priority : true;
            } else {
                $output = (bool) $third;
                $resolvedPriority = func_num_args() >= 4 ? $priority : null;
            }
        }

        if (Anon_Debug::isEnabled()) {
            Anon_Debug::debug('[Anon_Cms_Options_Proxy] get() 参数解析', [
                'option_name' => $name,
                'func_num_args' => func_num_args(),
                'outputOrPriority' => $outputOrPriority,
                'outputOrPriority_type' => gettype($outputOrPriority),
                'priority' => $priority,
                'resolved_output' => $output,
                'resolved_priority' => $resolvedPriority
            ]);
        }
        $order = $this->order($resolvedPriority);
        $value = $default;

        foreach ($order as $source) {
            if ($source === 'plugin' && $this->pluginSlug !== null && $this->pluginSlug !== '') {
                $all = Anon_System_Plugin::getPluginOptions($this->pluginSlug);
                if (array_key_exists($name, $all)) {
                    $value = $all[$name];
                    break;
                }
            }
            if ($source === 'theme') {
                $themeName = $this->themeName !== null && $this->themeName !== ''
                    ? $this->themeName
                    : Anon_Cms_Theme::getCurrentTheme();
                if ($themeName !== '') {
                    $all = Anon_Theme_Options::all($themeName);
                    if (Anon_Debug::isEnabled()) {
                        Anon_Debug::debug('[Anon_Cms_Options_Proxy] get() 查找主题选项', [
                            'option_name' => $name,
                            'theme_name' => $themeName,
                            'all_options' => array_keys($all),
                            'option_exists' => array_key_exists($name, $all),
                            'option_value' => array_key_exists($name, $all) ? $all[$name] : 'NOT_FOUND',
                            'option_value_type' => array_key_exists($name, $all) ? gettype($all[$name]) : null
                        ]);
                    }
                    if (array_key_exists($name, $all)) {
                        $value = $all[$name];
                        break;
                    }
                }
            }
            if ($source === 'system') {
                $value = Anon_Cms_Options::get($name, $default);
                break;
            }
        }

        if (is_object($value) && $value instanceof Anon_Cms_Options_Proxy) {
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::error('[Anon_Cms_Options_Proxy] get() 返回了代理对象', [
                    'option_name' => $name,
                    'default' => $default,
                    'outputOrPriority' => $outputOrPriority,
                    'priority' => $priority,
                    'context' => $this->context,
                    'pluginSlug' => $this->pluginSlug,
                    'themeName' => $this->themeName,
                    'order' => $order,
                    'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                ]);
            }
            $value = $default;
        }

        if ($output) {
            echo $value;
        }
        return $value;
    }

    /**
     * 设置选项值，写入系统 options 表
     * @param string $name 选项名
     * @param mixed $value 选项值
     * @return bool
     */
    public function set(string $name, $value): bool
    {
        return Anon_Cms_Options::set($name, $value);
    }
}
