<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Sanitize
{
    public static function text(string $text): string
    {
        return trim(strip_tags($text));
    }
    
    public static function email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    public static function url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

