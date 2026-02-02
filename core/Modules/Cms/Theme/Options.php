<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Theme_Options
{
    /**
     * 主题名用于存储时的取值
     *
     * @param string|null $themeName 主题名，任意大小写
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
     * 规范为主题 key：小写，供 optionKey/schemaKey 使用，实现主题名不区分大小写
     *
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function normalizeThemeKey(?string $themeName = null): string
    {
        return strtolower(self::themeName($themeName));
    }

    /**
     * 主题设置 values 的 options 键名（小写主题名）
     *
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function optionKey(?string $themeName = null): string
    {
        $key = self::normalizeThemeKey($themeName);
        return "theme:{$key}";
    }

    /**
     * 主题设置 schema 的 options 键名（小写主题名）
     *
     * @param string|null $themeName 主题名
     * @return string
     */
    private static function schemaKey(?string $themeName = null): string
    {
        $key = self::normalizeThemeKey($themeName);
        return "theme:{$key}:settings";
    }

    /**
     * 从按 tab 分组的 schema 数组批量注册
     * @param array $schema tab 名为键、选项名为子键的数组
     * @param string|null $themeName 主题名
     * @return void
     */
    public static function registerFromSchema(array $schema, ?string $themeName = null): void
    {
        foreach ($schema as $tab => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $key => $args) {
                $args = is_array($args) ? $args : [];
                $args['tab'] = $tab;
                self::register((string) $key, $args, $themeName);
            }
        }
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
     * 主题 options 行写入
     * @param array $value 完整主题设置键值对，将整体序列化为 JSON 写入
     * @param string|null $themeName 主题名
     * @return bool
     */
    public static function setStorage(array $value, ?string $themeName = null): bool
    {
        $name = self::optionKey($themeName);
        $valueStr = json_encode($value, JSON_UNESCAPED_UNICODE);
        $db = Anon_Database::getInstance();
        $row = $db->db('options')->where('name', $name)->first();
        if ($row && isset($row['name'])) {
            $ok = $db->db('options')->where('name', $name)->update(['value' => $valueStr]);
        } else {
            $ok = $db->db('options')->insert(['name' => $name, 'value' => $valueStr]);
        }
        if ($ok) {
            Anon_Cms_Options::clearCache();
        }
        return $ok !== false;
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
        return self::setStorage($settings, $themeName);
    }

    /**
     * 批量设置
     * @param array $data 键值对
     * @param string|null $themeName 主题名
     * @return bool
     */
    public static function setMany(array $data, ?string $themeName = null): bool
    {
        $optionKey = self::optionKey($themeName);
        $settings = Anon_Cms_Options::get($optionKey, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        foreach ($data as $key => $value) {
            $settings[$key] = $value;
        }
        return self::setStorage($settings, $themeName);
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
     * 获取该主题设置定义，从数据库读取
     * @param string|null $themeName 主题名
     * @return array
     */
    public static function schema(?string $themeName = null): array
    {
        $schema = Anon_Cms_Options::get(self::schemaKey($themeName), []);
        return is_array($schema) ? $schema : [];
    }
}

