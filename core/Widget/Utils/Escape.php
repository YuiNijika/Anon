<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Escape
{
    public static function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    public static function url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    
    public static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    public static function js(string $text): string
    {
        return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

