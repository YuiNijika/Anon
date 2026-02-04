<?php

/**
 * 转义工具类
 *
 * 提供 HTML、URL、Attribute、JS 等内容的转义方法，防止 XSS 攻击。
 *
 * @package Anon/Core/Widgets/Utils
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Escape
{
    /**
     * 转义 HTML 内容
     * @param string $text 原始文本
     * @return string 转义后的 HTML
     */
    public static function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 转义 URL
     * @param string $url 原始 URL
     * @return string 转义后的 URL
     */
    public static function url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 转义 HTML 属性值
     * @param string $text 原始文本
     * @return string 转义后的属性值
     */
    public static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 转义 JavaScript 字符串
     * @param string $text 原始文本
     * @return string 转义后的 JSON 字符串，包含引号
     */
    public static function js(string $text): string
    {
        return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
