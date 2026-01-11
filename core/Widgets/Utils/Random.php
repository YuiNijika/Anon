<?php
/**
 * 随机数工具类
 *
 * 提供生成随机字符串等功能。
 *
 * @package Anon/Core/Widgets/Utils
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Random
{
    /**
     * 生成随机字符串
     * @param int $length 长度
     * @param string $chars 字符集
     * @return string
     */
    public static function string(int $length = 32, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $str = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $max)];
        }
        
        return $str;
    }
}
