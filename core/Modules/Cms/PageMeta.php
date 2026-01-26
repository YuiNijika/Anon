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
        } else {
            // 如果没有定义常量，则根据页面类型从数据库获取 SEO 信息
            $pageType = self::detectPageType();
            
            if ($pageType === 'index') {
                // 首页
                $meta = self::getIndexMeta();
            } elseif ($pageType === 'post' || $pageType === 'page') {
                // 文章/页面
                $postData = self::getPostData($pageType);
                if ($postData) {
                    $meta = [
                        'title' => $postData['title'] ?? null,
                        'description' => self::extractDescription($postData['content'] ?? ''),
                        'keywords' => self::getPostKeywords($postData['id'] ?? 0),
                    ];
                } else {
                    // 如果查询失败，使用首页默认 SEO
                    $meta = self::getIndexMeta();
                }
            } elseif ($pageType === 'error') {
                // 错误页面
                $errorInfo = self::getError($overrides);
                $meta = [
                    'title' => $errorInfo['title'],
                    'description' => $errorInfo['message'],
                    'keywords' => [],
                ];
            } else {
                // 其他类型或未匹配到，使用首页默认 SEO
                $meta = self::getIndexMeta();
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
     * 获取首页 SEO 信息
     * @return array
     */
    private static function getIndexMeta(): array
    {
        $siteTitle = Anon_Cms_Options::get('title', '');
        $siteDescription = Anon_Cms_Options::get('description', '');
        $siteKeywords = Anon_Cms_Options::get('keywords', '');
        
        $keywordsArray = [];
        if (!empty($siteKeywords)) {
            if (is_array($siteKeywords)) {
                $keywordsArray = $siteKeywords;
            } else {
                $keywordsArray = array_filter(array_map('trim', explode(',', $siteKeywords)));
            }
        }
        
        return [
            'title' => $siteTitle ?: null,
            'description' => $siteDescription ?: null,
            'keywords' => $keywordsArray ?: null,
        ];
    }

    /**
     * 检测当前页面类型
     * @return string
     */
    private static function detectPageType(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($backtrace as $frame) {
            if (isset($frame['file'])) {
                $fileName = basename($frame['file']);
                $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
                $pageType = Anon_Cms::getPageType($fileBaseName);
                if ($pageType !== 'other') {
                    return $pageType;
                }
            }
        }
        return 'other';
    }

    /**
     * 获取模板作用域变量
     * @param string $varName 变量名
     * @return mixed
     */
    private static function getScopeVariable(string $varName)
    {
        // 尝试从全局变量获取（模板渲染时 extract 的变量可能在全局作用域）
        if (isset($GLOBALS[$varName])) {
            return $GLOBALS[$varName];
        }
        
        // 尝试从 $_GET 获取
        if (isset($_GET[$varName])) {
            return $_GET[$varName];
        }
        
        // 尝试从请求路径解析
        $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
        $pathParts = array_filter(explode('/', trim($requestPath, '/')));
        
        if ($varName === 'id' && !empty($pathParts)) {
            $lastPart = end($pathParts);
            if (is_numeric($lastPart)) {
                return (int)$lastPart;
            }
        } elseif ($varName === 'slug' && !empty($pathParts)) {
            $lastPart = end($pathParts);
            if (!empty($lastPart) && !is_numeric($lastPart)) {
                return $lastPart;
            }
        }
        
        return null;
    }

    /**
     * 获取文章/页面数据
     * @param string $type 类型：post 或 page
     * @return array|null
     */
    private static function getPostData(string $type): ?array
    {
        $db = Anon_Database::getInstance();
        $tableName = (defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '') . 'posts';
        
        if ($type === 'post') {
            $id = self::getScopeVariable('id');
            if ($id) {
                $post = $db->db('posts')->where('id', $id)->where('type', 'post')->where('status', 'publish')->first();
                return $post ?: null;
            }
        } elseif ($type === 'page') {
            $slug = self::getScopeVariable('slug');
            if ($slug) {
                $post = $db->db('posts')->where('slug', $slug)->where('type', 'page')->where('status', 'publish')->first();
                return $post ?: null;
            }
        }
        
        return null;
    }

    /**
     * 从内容中提取简介
     * @param string $content 内容
     * @param int $length 长度
     * @return string
     */
    private static function extractDescription(string $content, int $length = 200): string
    {
        if (empty($content)) {
            return '';
        }
        
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * 获取文章的分类和标签作为关键词
     * @param int $postId 文章 ID
     * @return array
     */
    private static function getPostKeywords(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        
        $db = Anon_Database::getInstance();
        $keywords = [];
        
        // 尝试查询关联表（如果存在）
        // 检查是否存在 post_metas 或类似的关联表
        $tablePrefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';
        
        // 尝试查询 post_metas 表
        try {
            $metas = $db->db('post_metas')
                ->join('metas', 'post_metas.meta_id', '=', 'metas.id')
                ->where('post_metas.post_id', $postId)
                ->whereIn('metas.type', ['category', 'tag'])
                ->get(['metas.name']);
            
            foreach ($metas as $meta) {
                if (!empty($meta['name'])) {
                    $keywords[] = $meta['name'];
                }
            }
        } catch (Exception $e) {
            // 如果关联表不存在，尝试其他方式
            // 可以后续根据实际表结构调整
        }
        
        return $keywords;
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

