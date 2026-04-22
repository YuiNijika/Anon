<?php
namespace Anon\Modules\System;

use Anon\Modules\Cms\Options;
use Anon\Modules\Debug;
use Exception;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * API前缀
 * 统一管理系统 API前缀配置
 */
class ApiPrefix
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
        
        try {
            if ($mode === 'api') {
                // API 模式
                self::$prefix = self::normalize(Env::get('app.base.router.prefix', '/'));
            } elseif ($mode === 'cms') {
                // CMS 模式
                self::$prefix = self::normalize(Options::get('apiPrefix', '/api'));
            } else {
                self::$prefix = '';
            }
        } catch (Exception $e) {
            self::$prefix = $mode === 'cms' ? '/api' : '';
        }

        Debug::info("API Prefix loaded: " . (self::$prefix === '' ? '/' : self::$prefix));
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