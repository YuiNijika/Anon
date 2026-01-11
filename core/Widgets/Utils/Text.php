<?php
/**
 * 文本工具类
 *
 * 提供文本截断、Slug 生成、时间转换等功能。
 *
 * @package Anon/Core/Widgets/Utils
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Text
{
    /**
     * 截断文本
     * @param string $text 原始文本
     * @param int $length 长度
     * @param string $suffix 后缀
     * @return string
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . $suffix;
    }
    
    /**
     * 生成 URL 友好的 Slug
     * @param string $text 原始文本
     * @return string
     */
    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
    
    /**
     * 转换时间为"多久前"格式
     * @param int $timestamp 时间戳
     * @return string
     */
    public static function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
}
