<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Options
{
    private static $cache = [];
    private static $loaded = false;

    /**
     * 获取选项值
     * @param string $name 选项名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        if (!self::$loaded) {
            self::loadAll();
        }

        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        // 缓存中没有时直接从数据库查询
        $db = Anon_Database::getInstance();
        $option = $db->db('options')->where('name', $name)->first();

        if ($option) {
            $value = $option['value'] ?? null;

            // 尝试解析JSON
            if ($value !== null && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            self::$cache[$name] = $value;
            return $value;
        }

        return $default;
    }

    /**
     * 设置选项值
     * @param string $name 选项名称
     * @param mixed $value 选项值
     * @return bool
     */
    public static function set(string $name, $value): bool
    {
        $db = Anon_Database::getInstance();
        $valueStr = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;

        $existing = $db->db('options')->where('name', $name)->first();

        if ($existing) {
            $result = $db->db('options')->where('name', $name)->update(['value' => $valueStr]);
        } else {
            $result = $db->db('options')->insert(['name' => $name, 'value' => $valueStr]);
        }

        if ($result) {
            self::$cache[$name] = $value;
        }

        return $result !== false;
    }

    /**
     * 加载所有选项到缓存
     * @return void
     */
    private static function loadAll(): void
    {
        $db = Anon_Database::getInstance();
        $options = $db->db('options')->get();

        foreach ($options as $option) {
            if (!isset($option['name'])) {
                continue;
            }

            $value = $option['value'] ?? null;

            // 尝试解析JSON
            if ($value !== null && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            self::$cache[$option['name']] = $value;
        }

        self::$loaded = true;
    }

    /**
     * 清除缓存
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }
}
