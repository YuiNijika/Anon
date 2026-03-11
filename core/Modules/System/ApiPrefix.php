<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * API前缀
 * 统一管理系统 API前缀配置
 */
class Anon_System_ApiPrefix
{
    /**
     * @var string|null API前缀缓存
     */
    private static $prefix = null;
    
    /**
     * 获取当前 API 前缀
     * 根路径返回空字符串
     * @return string
     */
    public static function get(): string
    {
        if (self::$prefix !== null) {
            return self::$prefix;
        }

        $mode = defined('ANON_APP_MODE') ? ANON_APP_MODE : 'api';
        self::$prefix = $mode === 'cms' ? '/api' : '';

        try {
            if (Anon_System_Env::get('app.mode') === 'api') {
                self::$prefix = self::normalize(Anon_System_Env::get('app.baseUrl', '/'));
            } elseif (Anon_System_Env::get('app.mode') === 'cms' && class_exists('Anon_Cms_Options')) {
                self::$prefix = self::normalize(Anon_Cms_Options::get('apiPrefix', '/api'));
            }
        } catch (Exception $e) {
        }

        Anon_Debug::info("API Prefix loaded: " . (self::$prefix === '' ? '/' : self::$prefix));
        return self::$prefix;
    }

    /**
     * 规范化 API 前缀
     * @param mixed $prefix
     * @return string
     */
    private static function normalize($prefix): string
    {
        if (!is_string($prefix)) {
            return '';
        }

        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        $prefix = '/' . ltrim($prefix, '/');
        return rtrim($prefix, '/');
    }
    
    /**
     * 清除缓存的前缀
     * @return void
     */
    public static function clearCache(): void
    {
        self::$prefix = null;
    }
}
