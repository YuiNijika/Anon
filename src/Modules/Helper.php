<?php
namespace Anon\Modules;

use Anon\Widgets\UtilsArrayUtils;
use Anon\Widgets\UtilsEscape;
use Anon\Widgets\UtilsFormat;
use Anon\Widgets\UtilsRandom;
use Anon\Widgets\UtilsSanitize;
use Anon\Widgets\UtilsText;
use Anon\Widgets\UtilsValidate;
use Anon\Modules\UtilsSanitize as Sanitize1;
use Modules;
use ArrayUtils;
use Escape;
use Format;
use Random;
use Sanitize;
use Text;
use Validate;

/**
 * 助手工具类
 *
 * 提供常用功能的静态代理访问，简化代码调用。
 * 实际上是各个工具类的 Facade。
 *
 * @package Anon/Core/Modules
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Helper
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
        return (string) Escape::output($text, 'html');
    }
    
    /**
     * 转义 URL
     * @see Anon_Utils_Escape::url
     */
    public static function escUrl(string $url): string
    {
        return (string) Escape::output($url, 'url');
    }
    
    /**
     * 转义 HTML 属性
     * @see Anon_Utils_Escape::attr
     */
    public static function escAttr(string $text): string
    {
        return (string) Escape::output($text, 'attribute');
    }
    
    /**
     * 转义 JS
     * @see Anon_Utils_Escape::js
     */
    public static function escJs(string $text): string
    {
        return (string) Escape::output($text, 'js');
    }
    
    /**
     * 清理文本
     * @see Sanitize1::text
     */
    public static function sanitizeText(string $text): string
    {
        return Sanitize::text($text);
    }
    
    /**
     * 清理邮箱
     * @see Sanitize1::email
     */
    public static function sanitizeEmail(string $email): string
    {
        return Sanitize::email($email);
    }
    
    /**
     * 验证邮箱
     * @see Anon_Utils_Validate::email
     */
    public static function isValidEmail(string $email): bool
    {
        return Validate::email($email);
    }
    
    /**
     * 清理 URL
     * @see Sanitize1::url
     */
    public static function sanitizeUrl(string $url): string
    {
        return Sanitize::url($url);
    }
    
    /**
     * 验证 URL
     * @see Anon_Utils_Validate::url
     */
    public static function isValidUrl(string $url): bool
    {
        return Validate::url($url);
    }
    
    /**
     * 截断文本
     * @see Anon_Utils_Text::truncate
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        return Text::truncate($text, $length, $suffix);
    }
    
    /**
     * 生成 Slug
     * @see Anon_Utils_Text::slugify
     */
    public static function slugify(string $text): string
    {
        return Text::slugify($text);
    }
    
    /**
     * 时间转换为友好格式
     * @see Anon_Utils_Text::timeAgo
     */
    public static function timeAgo(int $timestamp): string
    {
        return Text::timeAgo($timestamp);
    }
    
    /**
     * 格式化字节
     * @see Anon_Utils_Format::bytes
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        return Format::bytes($bytes, $precision);
    }
    
    /**
     * 生成随机字符串
     * @see Anon_Utils_Random::string
     */
    public static function randomString(int $length = 32, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        return Random::string($length, $chars);
    }
    
    /**
     * 获取数组值
     * @see Anon_Utils_Array::get
     */
    public static function get(array $array, string $key, $default = null)
    {
        return ArrayUtils::get($array, $key, $default);
    }
    
    /**
     * 设置数组值
     * @see Anon_Utils_Array::set
     */
    public static function set(array &$array, string $key, $value): void
    {
        ArrayUtils::set($array, $key, $value);
    }
    
    /**
     * 合并数组
     * @see Anon_Utils_Array::merge
     */
    public static function merge(array $array1, array $array2): array
    {
        return ArrayUtils::merge($array1, $array2);
    }
}
