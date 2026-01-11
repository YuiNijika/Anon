<?php
/**
 * 助手工具类
 *
 * 提供常用功能的静态代理访问，简化代码调用。
 * 实际上是各个工具类的 Facade。
 *
 * @package Anon/Core/Modules
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Helper
{
    private function __construct()
    {
    }
    
    /**
     * 转义 HTML
     * @see Anon_Utils_Escape::html
     */
    public static function escHtml(string $text): string
    {
        return Anon_Utils_Escape::html($text);
    }
    
    /**
     * 转义 URL
     * @see Anon_Utils_Escape::url
     */
    public static function escUrl(string $url): string
    {
        return Anon_Utils_Escape::url($url);
    }
    
    /**
     * 转义 HTML 属性
     * @see Anon_Utils_Escape::attr
     */
    public static function escAttr(string $text): string
    {
        return Anon_Utils_Escape::attr($text);
    }
    
    /**
     * 转义 JS
     * @see Anon_Utils_Escape::js
     */
    public static function escJs(string $text): string
    {
        return Anon_Utils_Escape::js($text);
    }
    
    /**
     * 清理文本
     * @see Anon_Utils_Sanitize::text
     */
    public static function sanitizeText(string $text): string
    {
        return Anon_Utils_Sanitize::text($text);
    }
    
    /**
     * 清理邮箱
     * @see Anon_Utils_Sanitize::email
     */
    public static function sanitizeEmail(string $email): string
    {
        return Anon_Utils_Sanitize::email($email);
    }
    
    /**
     * 验证邮箱
     * @see Anon_Utils_Validate::email
     */
    public static function isValidEmail(string $email): bool
    {
        return Anon_Utils_Validate::email($email);
    }
    
    /**
     * 清理 URL
     * @see Anon_Utils_Sanitize::url
     */
    public static function sanitizeUrl(string $url): string
    {
        return Anon_Utils_Sanitize::url($url);
    }
    
    /**
     * 验证 URL
     * @see Anon_Utils_Validate::url
     */
    public static function isValidUrl(string $url): bool
    {
        return Anon_Utils_Validate::url($url);
    }
    
    /**
     * 截断文本
     * @see Anon_Utils_Text::truncate
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        return Anon_Utils_Text::truncate($text, $length, $suffix);
    }
    
    /**
     * 生成 Slug
     * @see Anon_Utils_Text::slugify
     */
    public static function slugify(string $text): string
    {
        return Anon_Utils_Text::slugify($text);
    }
    
    /**
     * 时间转换为友好格式
     * @see Anon_Utils_Text::timeAgo
     */
    public static function timeAgo(int $timestamp): string
    {
        return Anon_Utils_Text::timeAgo($timestamp);
    }
    
    /**
     * 格式化字节
     * @see Anon_Utils_Format::bytes
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        return Anon_Utils_Format::bytes($bytes, $precision);
    }
    
    /**
     * 生成随机字符串
     * @see Anon_Utils_Random::string
     */
    public static function randomString(int $length = 32, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        return Anon_Utils_Random::string($length, $chars);
    }
    
    /**
     * 获取数组值
     * @see Anon_Utils_Array::get
     */
    public static function get(array $array, string $key, $default = null)
    {
        return Anon_Utils_Array::get($array, $key, $default);
    }
    
    /**
     * 设置数组值
     * @see Anon_Utils_Array::set
     */
    public static function set(array &$array, string $key, $value): void
    {
        Anon_Utils_Array::set($array, $key, $value);
    }
    
    /**
     * 合并数组
     * @see Anon_Utils_Array::merge
     */
    public static function merge(array $array1, array $array2): array
    {
        return Anon_Utils_Array::merge($array1, $array2);
    }
}
