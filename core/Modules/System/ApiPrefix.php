<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * API前缀管理
 * 统一管理系统 API前缀配置
 */
class Anon_System_ApiPrefix
{
    /**
     * @var string|null API前缀缓存
     */
    private static $prefix = null;
    
    /**
     * 获取 API 前缀
     * @return string
     */
    public static function get(): string
    {
        if (self::$prefix !== null) {
            return self::$prefix;
        }
            
        // 默认为 /api
        self::$prefix = '/api';
            
        try {
            // API 模式
            if (Anon_System_Env::get('app.mode') === 'api') {
                $baseUrl = Anon_System_Env::get('app.baseUrl', '/');
                if (!empty($baseUrl) && $baseUrl !== '/') {
                    self::$prefix = rtrim($baseUrl, '/');
                } else {
                    self::$prefix = '/';
                }
            }
            // CMS 模式
            elseif (Anon_System_Env::get('app.mode') === 'cms' && class_exists('Anon_Cms_Options')) {
                $cmsPrefix = Anon_Cms_Options::get('apiPrefix', '/api');
                if (!empty($cmsPrefix) && $cmsPrefix !== '/') {
                    self::$prefix = rtrim($cmsPrefix, '/');
                }
            }
        } catch (Exception $e) {
            // 如果无法读取，使用默认值
        }
            
        Anon_Debug::info("API Prefix loaded: " . self::$prefix);
        return self::$prefix;
    }
    
    /**
     * 清除缓存
     * @return void
     */
    public static function clearCache(): void
    {
        self::$prefix = null;
    }
}
