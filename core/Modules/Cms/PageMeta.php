<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_PageMeta
{
    /**
     * 元数据缓存
     * @var array
     */
    private static $metaCache = [];
    
    /**
     * URL缓存
     * @var string|null
     */
    private static $canonicalCache = null;

    /**
     * 获取当前页面元数据
     * @param array $overrides 覆盖值
     * @return array
     */
    public static function get(array $overrides = []): array
    {
        $meta = [];
        if (!empty($GLOBALS['_anon_rendering_error_page'])) {
            $siteTitle = trim((string) Anon_Cms_Options::get('title', ''));
            $title = $siteTitle !== '' ? '404 Not Found - ' . $siteTitle : '404 Not Found';
            $meta = [
                'title' => $title,
                'description' => '页面不存在或已被删除',
                'keywords' => ['404', '页面未找到', '错误'],
                'robots' => 'noindex, nofollow',
            ];
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
            $meta['canonical'] = self::getCanonical();
            $meta['og'] = ['title' => $meta['title'], 'description' => $meta['description'], 'type' => 'website', 'url' => $meta['canonical']];
            $meta['twitter'] = ['card' => 'summary', 'title' => $meta['title'], 'description' => $meta['description']];
            return $meta;
        }

        if (!empty($overrides) && (isset($overrides['title']) || isset($overrides['description']) || isset($overrides['robots']))) {
            $meta = $overrides;
        } else {
            $pageType = self::detectPageType();
            if ($pageType === 'error') {
                $errorInfo = self::getError($overrides);
                $meta = [
                    'title' => $errorInfo['title'],
                    'description' => $errorInfo['description'],
                    'keywords' => self::getErrorKeywords($overrides),
                    'robots' => 'noindex, nofollow',
                    'code' => $errorInfo['code'],
                    'message' => $errorInfo['message'],
                ];
                $meta = Anon_System_Hook::apply_filters('cms_page_meta_error', $meta, $errorInfo);
            } elseif (defined('Anon_PageMeta')) {
                $meta = is_array(Anon_PageMeta) ? Anon_PageMeta : [];
                $meta = array_merge($meta, $overrides);
            } else {
                if ($pageType === 'index') {
                    $meta = self::getIndexMeta();
                } elseif ($pageType === 'post' || $pageType === 'page') {
                    $postData = self::getPostData($pageType);
                    $meta = $postData ? [
                        'title' => $postData['title'] ?? null,
                        'description' => self::extractDescription($postData['content'] ?? ''),
                        'keywords' => self::getPostKeywords($postData['id'] ?? 0),
                    ] : self::getIndexMeta();
                } else {
                    $meta = self::getIndexMeta();
                }
                $meta = array_merge($meta, $overrides);
            }
        }

        $pageType = self::detectPageType();
        if ($pageType !== 'index') {
            $siteTitle = trim((string) Anon_Cms_Options::get('title', ''));
            if ($siteTitle !== '') {
                if (!empty($meta['title'])) {
                    $meta['title'] = trim((string) $meta['title']) . ' - ' . $siteTitle;
                } else {
                    $meta['title'] = $siteTitle;
                }
            }
        }

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
        $meta['canonical'] = self::getCanonical();
        $meta['og'] = ['title' => $meta['title'] ?? Anon_Common::NAME, 'description' => $meta['description'] ?? '', 'type' => 'website', 'url' => $meta['canonical']];
        $meta['twitter'] = ['card' => 'summary', 'title' => $meta['title'] ?? Anon_Common::NAME, 'description' => $meta['description'] ?? ''];
        return $meta;
    }

    /**
     * 获取 SEO 信息供 head 输出
     * @param array $overrides 覆盖值
     * @return array
     */
    public static function getSeo(array $overrides = []): array
    {
        $m = self::get($overrides);
        return ['title' => $m['title'], 'description' => $m['description'], 'keywords' => $m['keywords'], 'robots' => $m['robots'], 'canonical' => $m['canonical'], 'og' => $m['og'], 'twitter' => $m['twitter']];
    }

    /**
     * 获取错误页信息
     * @param array $overrides 可含 code、message
     * @return array
     */
    public static function getError(array $overrides = []): array
    {
        $scopeCode = self::getScopeVariable('code');
        $scopeMessage = self::getScopeVariable('message');
        $statusCode = $overrides['code'] ?? ($scopeCode ?? 400);
        $errorMessage = $overrides['message'] ?? ($scopeMessage ?? '操作失败');
        $statusText = [400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 500 => 'Internal Server Error'];
        $text = $statusText[$statusCode] ?? 'Error';
        $metaTitle = null;
        $metaDescription = null;
        if (defined('Anon_PageMeta') && is_array(Anon_PageMeta)) {
            $metaTitle = Anon_PageMeta['title'] ?? null;
            $metaDescription = Anon_PageMeta['description'] ?? null;
        }
        return [
            'code' => $statusCode,
            'message' => $errorMessage,
            'title' => $metaTitle ?? ($statusCode . ' ' . $text),
            'description' => $metaDescription ?? (!empty($errorMessage) ? $errorMessage : ($statusCode . ' ' . $text)),
            'text' => $text,
        ];
    }

    /**
     * 获取当前页面URL
     * @return string 当前页面的完整URL
     */
    private static function getCanonical(): string
    {
        if (self::$canonicalCache !== null) {
            return self::$canonicalCache;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        self::$canonicalCache = $scheme . '://' . $host . $uri;
        return self::$canonicalCache;
    }

    /**
     * 获取错误页面关键词
     * @param array $overrides 覆盖值
     * @return array 关键词数组
     */
    private static function getErrorKeywords(array $overrides = []): array
    {
        if (defined('Anon_PageMeta') && is_array(Anon_PageMeta) && !empty(Anon_PageMeta['keywords'])) {
            $k = Anon_PageMeta['keywords'];
            return is_array($k) ? $k : [trim((string)$k)];
        }
        return ['错误页面', '404', '页面未找到'];
    }

    /**
     * 获取首页元数据
     * @return array 首页元数据
     */
    private static function getIndexMeta(): array
    {
        $siteTitle = Anon_Cms_Options::get('title', '');
        $siteSubtitle = Anon_Cms_Options::get('subtitle', '');
        $siteDesc = Anon_Cms_Options::get('description', '');
        $siteKw = Anon_Cms_Options::get('keywords', '');
        $fullTitle = $siteTitle ?: null;
        if ($fullTitle && trim((string)$siteSubtitle) !== '') {
            $fullTitle .= ' - ' . trim((string)$siteSubtitle);
        }
        $kw = [];
        if (!empty($siteKw)) {
            $kw = is_array($siteKw) ? $siteKw : array_filter(array_map('trim', explode(',', $siteKw)));
        }
        return ['title' => $fullTitle, 'description' => $siteDesc ?: null, 'keywords' => $kw ?: null];
    }

    /**
     * 检测当前页面类型
     * @return string 页面类型
     */
    private static function detectPageType(): string
    {
        if (isset($GLOBALS['code']) && is_numeric($GLOBALS['code'])) {
            return 'error';
        }
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($bt as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            $base = pathinfo($frame['file'], PATHINFO_FILENAME);
            if (strtolower($base) === 'error') {
                return 'error';
            }
            $t = Anon_Cms::getPageType($base);
            if ($t !== 'other') {
                return $t;
            }
        }
        return 'other';
    }

    /**
     * 从作用域获取变量值
     * @param string $varName 变量名
     * @return mixed 变量值
     */
    private static function getScopeVariable(string $varName)
    {
        if (isset($GLOBALS[$varName])) {
            return $GLOBALS[$varName];
        }
        if (isset($_GET[$varName])) {
            return $_GET[$varName];
        }
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = array_filter(explode('/', trim($path, '/')));
        if (empty($parts)) {
            return null;
        }
        $last = end($parts);
        if ($varName === 'id' && is_numeric($last)) {
            return (int)$last;
        }
        if ($varName === 'slug' && $last !== '' && !is_numeric($last)) {
            return $last;
        }
        return null;
    }

    /**
     * 获取文章或页面数据
     * @param string $type 类型 post或page
     * @return array|null 文章数据
     */
    private static function getPostData(string $type): ?array
    {
        $db = Anon_Database::getInstance();
        if ($type === 'post') {
            $id = self::getScopeVariable('id');
            return $id ? ($db->db('posts')->where('id', $id)->where('type', 'post')->where('status', 'publish')->first() ?: null) : null;
        }
        if ($type === 'page') {
            $slug = self::getScopeVariable('slug');
            return $slug ? ($db->db('posts')->where('slug', $slug)->where('type', 'page')->where('status', 'publish')->first() ?: null) : null;
        }
        return null;
    }

    /**
     * 从内容中提取描述
     * @param string $content 内容
     * @param int $length 长度限制
     * @return string 描述文本
     */
    private static function extractDescription(string $content, int $length = 200): string
    {
        if ($content === '') {
            return '';
        }
        $content = ltrim($content);
        if (strpos($content, '<!--markdown-->') === 0) {
            $content = substr($content, 15);
        }
        $text = strip_tags($content);
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/`[^`]*`/u', ' ', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/(^|\s)#+\s+/u', ' ', $text);
        $text = preg_replace('/[*_~>#-]{1,3}/u', ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length) . '...';
    }

    /**
     * 获取文章关键词
     * @param int $postId 文章ID
     * @return array 关键词数组
     */
    private static function getPostKeywords(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        
        try {
            $db = Anon_Database::getInstance();
            $keywords = [];
            $categories = [];
            $tags = [];
            
            // 获取已发布的文章信息
            $post = $db->db('posts')
                ->where('id', $postId)
                ->where('status', 'publish')
                ->first(['category_id', 'tag_ids']);
            
            if (!$post) {
                return [];
            }
            
            // 获取分类信息
            if (!empty($post['category_id'])) {
                $category = $db->db('metas')
                    ->where('id', (int)$post['category_id'])
                    ->where('type', 'category')
                    ->first(['name']);
                
                if ($category && !empty($category['name'])) {
                    $categories[] = trim($category['name']);
                }
            }
            
            // 获取标签信息
            if (!empty($post['tag_ids'])) {
                $tagIds = json_decode($post['tag_ids'], true);
                
                if (is_array($tagIds) && !empty($tagIds)) {
                    // 过滤有效的标签ID
                    $validTagIds = array_filter($tagIds, function($id) {
                        return is_numeric($id) && $id > 0;
                    });
                    
                    if (!empty($validTagIds)) {
                        $tagRows = $db->db('metas')
                            ->whereIn('id', $validTagIds)
                            ->where('type', 'tag')
                            ->orderBy('name', 'asc')
                            ->get(['name']);
                        
                        foreach ($tagRows as $tag) {
                            if (!empty($tag['name'])) {
                                $tags[] = trim($tag['name']);
                            }
                        }
                    }
                }
            }
            
            // 组合关键词，分类优先
            $keywords = array_merge($categories, $tags);
            
            // 去重并保持顺序
            $keywords = array_values(array_unique($keywords));
            
            // 记录调试信息
            if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                Anon_Debug::info('文章关键词获取成功', [
                    'post_id' => $postId,
                    'category_id' => $post['category_id'],
                    'tag_ids' => $post['tag_ids'],
                    'categories' => $categories,
                    'tags' => $tags,
                    'keywords' => $keywords
                ]);
            }
            
            return $keywords;
            
        } catch (Exception $e) {
            // 记录错误日志
            if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                Anon_Debug::error('获取文章关键词失败', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }

    /**
     * 清除元数据与 canonical 缓存
     * @return void
     */
    public static function clearCache(): void
    {
        self::$metaCache = [];
        self::$canonicalCache = null;
    }
}
