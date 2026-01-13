<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_PageMeta
{
    /**
     * @var array 页面元数据缓存
     */
    private static $metaCache = [];

    /**
     * 获取当前页面的元数据
     * 优先从 Anon_PageMeta 常量读取，如果没有则返回默认值
     * @param array $overrides 覆盖值，用于从外部变量传入，如错误页面的 code 和 message
     * @return array 页面元数据
     */
    public static function get(array $overrides = []): array
    {
        $cacheKey = self::getCacheKey() . md5(serialize($overrides));
        
        if (isset(self::$metaCache[$cacheKey])) {
            return self::$metaCache[$cacheKey];
        }
        
        $meta = [];
        
        // 尝试从常量读取
        if (defined('Anon_PageMeta')) {
            $meta = Anon_PageMeta;
            if (!is_array($meta)) {
                $meta = [];
            }
        }
        
        // 应用覆盖值，外部变量优先
        $meta = array_merge($meta, $overrides);
        
        // 设置默认值
        $defaults = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'robots' => 'index, follow',
            'canonical' => null,
            'og' => [],
            'twitter' => [],
            'code' => null,
            'message' => null,
        ];
        
        $meta = array_merge($defaults, $meta);
        
        // 自动生成 canonical URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $meta['canonical'] = $scheme . '://' . $host . $uri;
        
        // 自动生成 Open Graph 数据
        $meta['og'] = [
            'title' => $meta['title'] ?? Anon_Common::NAME,
            'description' => $meta['description'] ?? '',
            'type' => 'website',
            'url' => $meta['canonical'],
        ];
        
        // 自动生成 Twitter Card 数据
        $meta['twitter'] = [
            'card' => 'summary',
            'title' => $meta['title'] ?? Anon_Common::NAME,
            'description' => $meta['description'] ?? '',
        ];
        
        self::$metaCache[$cacheKey] = $meta;
        
        return $meta;
    }

    /**
     * 获取 SEO 信息，用于 head 标签
     * @param array $overrides 覆盖值
     * @return array SEO 信息数组
     */
    public static function getSeo(array $overrides = []): array
    {
        $meta = self::get($overrides);
        
        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'keywords' => $meta['keywords'],
            'robots' => $meta['robots'],
            'canonical' => $meta['canonical'],
            'og' => $meta['og'],
            'twitter' => $meta['twitter'],
        ];
    }

    /**
     * 获取错误信息，用于错误页面
     * @param array $overrides 覆盖值，可以传入 code 和 message
     * @return array 错误信息数组，包含 code 和 message
     */
    public static function getError(array $overrides = []): array
    {
        $meta = self::get($overrides);
        
        $statusCode = $meta['code'] ?? ($overrides['code'] ?? 400);
        $errorMessage = $meta['message'] ?? ($overrides['message'] ?? '操作失败');
        
        $statusText = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error'
        ];
        $text = $statusText[$statusCode] ?? 'Error';
        
        // 如果常量中没有定义 title，自动生成
        if (empty($meta['title'])) {
            $meta['title'] = $statusCode . ' ' . $text;
        }
        
        return [
            'code' => $statusCode,
            'message' => $errorMessage,
            'title' => $meta['title'],
            'text' => $text,
        ];
    }

    /**
     * 获取缓存键
     * @return string 缓存键
     */
    private static function getCacheKey(): string
    {
        $file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '';
        return md5($file);
    }

    /**
     * 清除缓存
     * @return void
     */
    public static function clearCache(): void
    {
        self::$metaCache = [];
    }
}

