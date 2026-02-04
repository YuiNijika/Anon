<?php

/**
 * 格式化工具类
 *
 * 提供文件大小、数字等格式化方法。
 *
 * @package Anon/Core/Widgets/Utils
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Format
{
    /**
     * 格式化字节数
     * @param int $bytes 字节数
     * @param int $precision 小数位数
     * @return string 格式化后的字符串，例如 1.5 MB
     */
    public static function bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
