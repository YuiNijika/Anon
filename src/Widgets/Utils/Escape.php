<?php
namespace Anon\Widgets\Utils;



use Utils;if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XSS 防护工具类
 */
class Escape
{
    /**
     * 转义输出
     * @param mixed $data 数据
     * @param string $context 上下文：html, js, css, url, attribute
     * @return mixed
     */
    public static function output($data, string $context = 'html')
    {
        if (is_array($data)) {
            return array_map(function($value) use ($context) {
                return self::output($value, $context);
            }, $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        switch ($context) {
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'js':
                return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            
            case 'css':
                return addslashes($data);
            
            case 'url':
                return urlencode($data);
            
            case 'attribute':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            default:
                return $data;
        }
    }
    
    /**
     * 净化 HTML（允许安全标签）
     * @param string $html HTML 内容
     * @return string
     */
    public static function purify(string $html): string
    {
        // 简单实现，生产建议使用 HTMLPurifier
        $allowedTags = '<p><br><strong><em><u><ul><ol><li><a><img><code><pre>';
        return strip_tags($html, $allowedTags);
    }
    
    /**
     * 移除危险 HTML 标签
     * @param string $html HTML 内容
     * @return string
     */
    public static function stripDangerousTags(string $html): string
    {
        // 移除 script、iframe、object 等危险标签
        return preg_replace('/<(script|iframe|object|embed|form|input|textarea|select|button)[^>]*>.*?<\/\1>/is', '', $html);
    }
}
