<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms
{
    private const TEMPLATE_EXTENSIONS = ['php', 'html', 'htm'];
    private static $pageStartTime = null;
    private static $postCache = [];
    private static $pageCache = [];
    private static $fileSystemCache = [
        'scandir' => [],
        'file_exists' => [],
        'is_dir' => [],
        'is_file' => [],
        'finddir' => [],
        'findfile' => [],
    ];

    /**
     * 获取页面类型
     * @param string $templateName 模板名
     * @return string
     */
    public static function getPageType(string $templateName): string
    {
        $templateNameLower = strtolower($templateName);
        $fileName = pathinfo(basename($templateNameLower), PATHINFO_FILENAME);
        $specialTypes = ['index', 'post', 'page', 'error'];
        
        if (in_array($fileName, $specialTypes)) {
            return $fileName;
        }
        
        $pathParts = explode('/', str_replace('\\', '/', $templateNameLower));
        foreach ($pathParts as $part) {
            $part = pathinfo($part, PATHINFO_FILENAME);
            if (in_array($part, $specialTypes)) {
                return $part;
            }
        }
        
        return 'other';
    }

    /**
     * 获取模板扩展名
     * @return array
     */
    public static function getTemplateExtensions(): array
    {
        return self::TEMPLATE_EXTENSIONS;
    }

    /**
     * 查找目录不区分大小写
     * @param string $baseDir 基础目录
     * @param string $dirName 目录名
     * @return string|null
     */
    public static function findDirectoryCaseInsensitive(string $baseDir, string $dirName): ?string
    {
        $cacheKey = 'finddir:' . $baseDir . ':' . $dirName;
        if (isset(self::$fileSystemCache['finddir'][$cacheKey])) {
            return self::$fileSystemCache['finddir'][$cacheKey];
        }
        
        $exactPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $dirName . DIRECTORY_SEPARATOR;
        if (self::isDir($exactPath)) {
            self::$fileSystemCache['finddir'][$cacheKey] = $exactPath;
            return $exactPath;
        }
        
        $dirNameLower = strtolower($dirName);
        $items = self::scanDirectory($baseDir);
        
        if ($items === null) {
            self::$fileSystemCache['finddir'][$cacheKey] = null;
            return null;
        }
        
        foreach ($items as $item) {
            $itemPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $item;
            if (self::isDir($itemPath) && strtolower($item) === $dirNameLower) {
                $result = $itemPath . DIRECTORY_SEPARATOR;
                self::$fileSystemCache['finddir'][$cacheKey] = $result;
                return $result;
            }
        }
        
        self::$fileSystemCache['finddir'][$cacheKey] = null;
        return null;
    }

    /**
     * 查找文件不区分大小写
     * @param string $baseDir 基础目录
     * @param string $fileName 文件名
     * @param array|null $extensions 扩展名
     * @return string|null
     */
    public static function findFileCaseInsensitive(string $baseDir, string $fileName, ?array $extensions = null): ?string
    {
        if ($extensions === null) {
            $extensions = self::TEMPLATE_EXTENSIONS;
        }
        
        $extKey = implode(',', $extensions);
        $cacheKey = 'findfile:' . $baseDir . ':' . $fileName . ':' . $extKey;
        if (isset(self::$fileSystemCache['findfile'][$cacheKey])) {
            return self::$fileSystemCache['findfile'][$cacheKey];
        }
        
        $fileNameLower = strtolower($fileName);
        
        foreach ($extensions as $ext) {
            $exactPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $fileName . '.' . $ext;
            if (self::fileExists($exactPath)) {
                self::$fileSystemCache['findfile'][$cacheKey] = $exactPath;
                return $exactPath;
            }
        }
        
        $items = self::scanDirectory($baseDir);
        if ($items === null) {
            self::$fileSystemCache['findfile'][$cacheKey] = null;
            return null;
        }
        
        foreach ($items as $item) {
            $itemPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $item;
            if (!self::isFile($itemPath)) {
                continue;
            }
            
            $itemName = pathinfo($item, PATHINFO_FILENAME);
            $itemExt = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            if (strtolower($itemName) === $fileNameLower && in_array($itemExt, $extensions)) {
                self::$fileSystemCache['findfile'][$cacheKey] = $itemPath;
                return $itemPath;
            }
        }
        
        self::$fileSystemCache['findfile'][$cacheKey] = null;
        return null;
    }

    /**
     * 扫描目录
     * @param string $dir 目录路径
     * @return array|null
     */
    public static function scanDirectory(string $dir): ?array
    {
        $cacheKey = 'scandir:' . $dir;
        if (isset(self::$fileSystemCache['scandir'][$cacheKey])) {
            return self::$fileSystemCache['scandir'][$cacheKey];
        }
        
        if (!self::isDir($dir)) {
            self::$fileSystemCache['scandir'][$cacheKey] = null;
            return null;
        }
        
        $items = scandir($dir);
        if ($items === false) {
            self::$fileSystemCache['scandir'][$cacheKey] = null;
            return null;
        }
        
        $result = array_filter($items, function($item) {
            return $item !== '.' && $item !== '..';
        });
        
        self::$fileSystemCache['scandir'][$cacheKey] = $result;
        return $result;
    }
    
    /**
     * 检查是否为目录
     * @param string $path 路径
     * @return bool
     */
    public static function isDir(string $path): bool
    {
        if (isset(self::$fileSystemCache['is_dir'][$path])) {
            return self::$fileSystemCache['is_dir'][$path];
        }
        
        $result = is_dir($path);
        self::$fileSystemCache['is_dir'][$path] = $result;
        return $result;
    }
    
    /**
     * 检查文件是否存在
     * @param string $path 路径
     * @return bool
     */
    public static function fileExists(string $path): bool
    {
        if (isset(self::$fileSystemCache['file_exists'][$path])) {
            return self::$fileSystemCache['file_exists'][$path];
        }
        
        $result = file_exists($path);
        self::$fileSystemCache['file_exists'][$path] = $result;
        return $result;
    }
    
    /**
     * 检查是否为文件（带缓存）
     * @param string $path 路径
     * @return bool
     */
    public static function isFile(string $path): bool
    {
        if (isset(self::$fileSystemCache['is_file'][$path])) {
            return self::$fileSystemCache['is_file'][$path];
        }
        
        $result = is_file($path);
        self::$fileSystemCache['is_file'][$path] = $result;
        return $result;
    }

    /**
     * 记录页面加载开始时间
     * @return void
     */
    public static function startPageLoad(): void
    {
        if (self::$pageStartTime === null) {
            self::$pageStartTime = microtime(true);
        }
    }

    /**
     * 获取页面加载耗时
     * @return float
     */
    public static function getPageLoadTime(): float
    {
        if (self::$pageStartTime === null) {
            return 0.0;
        }
        
        return round((microtime(true) - self::$pageStartTime) * 1000, 2);
    }

    /**
     * 输出版权
     * @return void
     */
    public static function outputCopyright(): void
    {
        $version = Anon_Common::VERSION;
        echo '<script>';
        echo 'console.log("%c Anon Framework v' . $version . ' %c https://github.com/YuiNijika/Anon", "color: #fff; background: #34495e; padding:5px 0;", "color: #fff; background: #d6293e; padding:5px 0;");';
        echo '</script>' . "\n";
    }

    /**
     * 输出页面加载耗时脚本
     * @return void
     */
    public static function outputPageLoadTimeScript(): void
    {
        $loadTime = self::getPageLoadTime();
        echo '<script>';
        echo 'console.log("页面加载耗时: ' . $loadTime . 'ms");';
        echo '</script>' . "\n";
    }

    /**
     * 已增加浏览量的文章ID集合
     * @var array
     */
    private static $viewedPosts = [];

    /**
     * 获取文章数据仅查询不存在不渲染错误页
     * @param int|null $id 文章 ID
     * @return array|null
     */
    public static function getPostIfExists(?int $id = null): ?array
    {
        if ($id === null) {
            $id = $GLOBALS['id'] ?? $_GET['id'] ?? null;
            if ($id && is_numeric($id)) {
                $id = (int)$id;
            } else {
                $id = null;
            }
        }

        if (!$id) {
            return null;
        }

        $cacheKey = 'post_' . $id;
        if (isset(self::$postCache[$cacheKey])) {
            return self::$postCache[$cacheKey];
        }

        $db = Anon_Database::getInstance();
        $post = $db->db('posts')
            ->select(['id', 'type', 'title', 'slug', 'content', 'status', 'author_id', 'category_id', 'tag_ids', 'views', 'comment_status', 'created_at', 'updated_at'])
            ->where('id', $id)
            ->where('type', 'post')
            ->where('status', 'publish')
            ->first();

        if (!$post) {
            return null;
        }

        if (!isset(self::$viewedPosts[$id])) {
            $currentViews = (int)($post['views'] ?? 0);
            $post['views'] = $currentViews + 1;
            self::$viewedPosts[$id] = true;
            
            register_shutdown_function(function() use ($id, $currentViews) {
                try {
                    $db = Anon_Database::getInstance();
                    $db->db('posts')
                        ->where('id', $id)
                        ->where('type', 'post')
                        ->update(['views' => $currentViews + 1]);
                } catch (Exception $e) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        error_log("异步增加文章浏览量失败: " . $e->getMessage());
                    }
                }
            });
        }

        self::$postCache[$cacheKey] = $post;

        return $post;
    }

    /**
     * 获取文章数据不存在则渲染错误页并 exit
     * @param int|null $id 文章 ID
     * @return array|null
     */
    public static function getPost(?int $id = null): ?array
    {
        $post = self::getPostIfExists($id);
        if ($post === null) {
            self::renderError(404, '文章不存在或已被删除');
            return null;
        }
        return $post;
    }

    /**
     * 获取页面数据仅查询不存在不渲染错误页
     * @param string|null $slug 页面 slug
     * @return array|null
     */
    public static function getPageIfExists(?string $slug = null): ?array
    {
        if ($slug === null) {
            $slug = $GLOBALS['slug'] ?? $_GET['slug'] ?? '';
        }

        if (empty($slug)) {
            return null;
        }

        $cacheKey = 'page_' . md5($slug);
        if (isset(self::$pageCache[$cacheKey])) {
            return self::$pageCache[$cacheKey];
        }

        $db = Anon_Database::getInstance();
        $page = $db->db('posts')
            ->select(['id', 'type', 'title', 'slug', 'content', 'status', 'author_id', 'category_id', 'tag_ids', 'views', 'comment_status', 'created_at', 'updated_at'])
            ->where('slug', $slug)
            ->where('type', 'page')
            ->where('status', 'publish')
            ->first();

        if (!$page) {
            return null;
        }

        self::$pageCache[$cacheKey] = $page;

        return $page;
    }

    /**
     * 获取页面数据不存在则渲染错误页并 exit
     * @param string|null $slug 页面 slug
     * @return array|null
     */
    public static function getPage(?string $slug = null): ?array
    {
        $page = self::getPageIfExists($slug);
        if ($page === null) {
            self::renderError(404, '页面不存在或已被删除');
            return null;
        }
        return $page;
    }

    /**
     * 渲染错误页并 exit
     * @param int $code 错误码
     * @param string $message 错误信息
     * @return void
     */
    private static function renderError(int $code, string $message): void
    {
        Anon_Cms_Theme::render('error', [
            'code' => $code,
            'message' => $message,
        ]);
        exit;
    }
}

