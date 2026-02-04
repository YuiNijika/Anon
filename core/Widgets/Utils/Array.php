<?php

/**
 * 数组工具类
 *
 * 提供数组操作的便捷方法，如深度获取、设置、合并等。
 *
 * @package Anon/Core/Widgets/Utils
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Array
{
    /**
     * 获取数组中的值，支持点号分隔的键名
     * @param array $array 数组
     * @param string $key 键名，例如 a.b.c
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置数组中的值，支持点号分隔的键名
     * @param array $array 数组引用
     * @param string $key 键名，例如 a.b.c
     * @param mixed $value 值
     * @return void
     */
    public static function set(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * 深度合并两个数组
     * @param array $array1 基础数组
     * @param array $array2 覆盖数组
     * @return array 合并后的数组
     */
    public static function merge(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = self::merge($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
