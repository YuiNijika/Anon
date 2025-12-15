<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Helper
{
    private function __construct()
    {
    }
    
    public static function escHtml(string $text): string
    {
        return Anon_Utils_Escape::html($text);
    }
    
    public static function escUrl(string $url): string
    {
        return Anon_Utils_Escape::url($url);
    }
    
    public static function escAttr(string $text): string
    {
        return Anon_Utils_Escape::attr($text);
    }
    
    public static function escJs(string $text): string
    {
        return Anon_Utils_Escape::js($text);
    }
    
    public static function sanitizeText(string $text): string
    {
        return Anon_Utils_Sanitize::text($text);
    }
    
    public static function sanitizeEmail(string $email): string
    {
        return Anon_Utils_Sanitize::email($email);
    }
    
    public static function isValidEmail(string $email): bool
    {
        return Anon_Utils_Validate::email($email);
    }
    
    public static function sanitizeUrl(string $url): string
    {
        return Anon_Utils_Sanitize::url($url);
    }
    
    public static function isValidUrl(string $url): bool
    {
        return Anon_Utils_Validate::url($url);
    }
    
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        return Anon_Utils_Text::truncate($text, $length, $suffix);
    }
    
    public static function slugify(string $text): string
    {
        return Anon_Utils_Text::slugify($text);
    }
    
    public static function timeAgo(int $timestamp): string
    {
        return Anon_Utils_Text::timeAgo($timestamp);
    }
    
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        return Anon_Utils_Format::bytes($bytes, $precision);
    }
    
    public static function randomString(int $length = 32, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        return Anon_Utils_Random::string($length, $chars);
    }
    
    public static function get(array $array, string $key, $default = null)
    {
        return Anon_Utils_Array::get($array, $key, $default);
    }
    
    public static function set(array &$array, string $key, $value): void
    {
        Anon_Utils_Array::set($array, $key, $value);
    }
    
    public static function merge(array $array1, array $array2): array
    {
        return Anon_Utils_Array::merge($array1, $array2);
    }
}

