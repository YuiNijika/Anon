<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Theme_Options
{
    /**
     * 获取主题名
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function themeName(?string $themeName = null): string
    {
        if ($themeName !== null && $themeName !== '') {
            return $themeName;
        }
        return Anon_Cms_Theme::getCurrentTheme();
    }

    /**
     * 获取主题设置 options 键名
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function optionKey(?string $themeName = null): string
    {
        $themeName = self::themeName($themeName);
        return "theme:{$themeName}";
    }

    /**
     * 获取主题设置定义 options 键名
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function schemaKey(?string $themeName = null): string
    {
        $themeName = self::themeName($themeName);
        return "theme:{$themeName}:settings";
    }

    /**
     * 注册主题设置项
     * @param string $key 键名
     * @param array $args 参数
     * @param string|null $themeName 主题名
     * @return void
     */
    public static function register(string $key, array $args = [], ?string $themeName = null): void
    {
        $optionKey = self::optionKey($themeName);
        $schemaKey = self::schemaKey($themeName);

        $settings = Anon_Cms_Options::get($optionKey, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $schema = Anon_Cms_Options::get($schemaKey, []);
        if (!is_array($schema)) {
            $schema = [];
        }

        $defaultArgs = [
            'type' => 'text',
            'label' => $key,
            'description' => '',
            'default' => '',
            'sanitize_callback' => null,
            'validate_callback' => null,
        ];
        $args = array_merge($defaultArgs, $args);

        if (!array_key_exists($key, $settings)) {
            $settings[$key] = $args['default'];
        }

        $schema[$key] = $args;

        Anon_Cms_Options::set($schemaKey, $schema);
        Anon_Cms_Options::set($optionKey, $settings);
    }

    /**
     * 获取设置值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @param string|null $themeName 主题名
     * @return mixed
     */
    public static function get(string $key, $default = null, ?string $themeName = null)
    {
        $settings = Anon_Cms_Options::get(self::optionKey($themeName), []);
        if (!is_array($settings)) {
            return $default;
        }
        return $settings[$key] ?? $default;
    }

    /**
     * 设置设置值
     * @param string $key 键名
     * @param mixed $value 值
     * @param string|null $themeName 主题名
     * @return bool
     */
    public static function set(string $key, $value, ?string $themeName = null): bool
    {
        $optionKey = self::optionKey($themeName);
        $schemaKey = self::schemaKey($themeName);

        $settings = Anon_Cms_Options::get($optionKey, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $schema = Anon_Cms_Options::get($schemaKey, []);
        if (is_array($schema) && isset($schema[$key])) {
            $def = $schema[$key];

            if (isset($def['validate_callback']) && is_callable($def['validate_callback'])) {
                $ok = call_user_func($def['validate_callback'], $value);
                if ($ok === false) {
                    return false;
                }
            }

            if (isset($def['sanitize_callback']) && is_callable($def['sanitize_callback'])) {
                $value = call_user_func($def['sanitize_callback'], $value);
            }
        }

        $settings[$key] = $value;
        return Anon_Cms_Options::set($optionKey, $settings);
    }

    /**
     * 批量设置
     * @param array $data 数据
     * @param string|null $themeName 主题名
     * @return bool
     */
    public static function setMany(array $data, ?string $themeName = null): bool
    {
        $ok = true;
        foreach ($data as $key => $value) {
            if (!self::set((string)$key, $value, $themeName)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * 获取所有设置
     * @param string|null $themeName 主题名
     * @return array
     */
    public static function all(?string $themeName = null): array
    {
        $settings = Anon_Cms_Options::get(self::optionKey($themeName), []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * 获取设置定义
     * @param string|null $themeName 主题名
     * @return array
     */
    public static function schema(?string $themeName = null): array
    {
        $schema = Anon_Cms_Options::get(self::schemaKey($themeName), []);
        return is_array($schema) ? $schema : [];
    }
}

