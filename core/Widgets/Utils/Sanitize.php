<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XSS 过滤工具类
 * 提供多种数据清理和过滤方法
 */
class Anon_Security_Sanitize
{
    /**
     * 清理文本内容，移除 HTML 标签
     * @param string $text 原始文本
     * @return string 清理后的文本
     */
    public static function text(string $text): string
    {
        return trim(strip_tags($text));
    }
    
    /**
     * 清理邮箱地址
     * @param string $email 原始邮箱
     * @return string 清理后的邮箱
     */
    public static function email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * 清理 URL
     * @param string $url 原始 URL
     * @return string 清理后的 URL
     */
    public static function url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * 清理 HTML 内容，允许指定标签
     * @param string $html 原始 HTML
     * @param string|null $allowedTags 允许的标签，如 '<p><a><strong>'
     * @return string 清理后的 HTML
     */
    public static function html(string $html, ?string $allowedTags = null): string
    {
        if ($allowedTags === null) {
            // 默认只允许安全的标签
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        }
        return strip_tags($html, $allowedTags);
    }
    
    /**
     * 清理整数
     * @param mixed $value 原始值
     * @return int 清理后的整数
     */
    public static function int($value): int
    {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * 清理浮点数
     * @param mixed $value 原始值
     * @return float 清理后的浮点数
     */
    public static function float($value): float
    {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * 清理字符串，移除特殊字符
     * @param string $string 原始字符串
     * @return string 清理后的字符串
     */
    public static function string(string $string): string
    {
        return htmlspecialchars($string, ENT_NOQUOTES, 'UTF-8');
    }
    
    /**
     * 深度清理数组，递归清理所有字符串值
     * @param array $data 原始数组
     * @param bool $stripHtml 是否移除 HTML 标签
     * @return array 清理后的数组
     */
    public static function array(array $data, bool $stripHtml = true): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleanedKey = is_string($key) ? self::text($key) : $key;
            
            if (is_array($value)) {
                $cleaned[$cleanedKey] = self::array($value, $stripHtml);
            } elseif (is_string($value)) {
                $cleaned[$cleanedKey] = $stripHtml ? self::text($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $cleaned[$cleanedKey] = $value;
            }
        }
        return $cleaned;
    }
    
    /**
     * 清理 SQL 特殊字符，仅用于日志记录，不应用于实际查询
     * 注意：实际查询应使用预处理语句，此方法仅用于日志记录
     * @param string $string 原始字符串
     * @return string 清理后的字符串
     */
    public static function sqlForLog(string $string): string
    {
        // 移除可能导致 SQL 注入的特殊字符，仅用于日志
        return str_replace(['\'', '"', ';', '--', '/*', '*/'], '', $string);
    }
}

