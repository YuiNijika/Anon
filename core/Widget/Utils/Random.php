<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Random
{
    public static function string(int $length = 32, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $str = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $max)];
        }
        
        return $str;
    }
}

