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

        return self::$cache[$name] ?? $default;
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
        $tablePrefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';
        $tableName = $tablePrefix . 'options';

        $valueStr = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;

        $existing = $db->db($tableName)->where('name', $name)->first();
        
        if ($existing) {
            $result = $db->db($tableName)->where('name', $name)->update(['value' => $valueStr]);
        } else {
            $result = $db->db($tableName)->insert(['name' => $name, 'value' => $valueStr]);
        }

        if ($result) {
            self::$cache[$name] = $value;
        }

        return $result !== false;
    }

    /**
     * 加载所有选项
     * @return void
     */
    private static function loadAll(): void
    {
        if (!class_exists('Anon_Database')) {
            self::$loaded = true;
            return;
        }

        try {
            $db = Anon_Database::getInstance();
            $tablePrefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';
            $tableName = $tablePrefix . 'options';

            $options = $db->db($tableName)->get();
            
            foreach ($options as $option) {
                $value = $option['value'];
                if ($value !== null) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
                self::$cache[$option['name']] = $value;
            }
        } catch (Exception $e) {
            // 忽略错误，可能表不存在
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

