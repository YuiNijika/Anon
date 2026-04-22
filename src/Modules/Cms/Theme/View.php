<?php
namespace Anon\Modules\Cms\Theme;

use Anon\Modules\Cms\Options as CmsOptions;
use Anon\Widgets\CmsUser;
use Anon\Widgets\CmsThemeHelper;
use Anon\Modules\Cms\Theme\Theme;
use Anon\Modules\Debug;
use Anon\Modules\Check;
use Anon\Modules\Cms\Cms;
use Anon\Modules\Database\Database;
use Anon\Widgets\Cms\User;
use Anon\Widgets\Cms\ThemeHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 主题视图对象
 * 在模板中通过 $this 调用主题方法和读取渲染数据
 */
class View
{
    /**
     * @var array 模板数据
     */
    private $data = [];

    /**
     * @var array 已渲染的组件路径
     */
    private static $renderedComponents = [];

    /**
     * 清空已渲染组件记录，每次 Theme::render 开始时调用，避免重复引入或跨页误判
     */
    public static function clearRenderedComponents(): void
    {
        self::$renderedComponents = [];
    }

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * 创建子视图
     * @param array $data
     * @return self
     */
    private function child(array $data = []): self
    {
        if (empty($data)) {
            return $this;
        }
        return new self(array_merge($this->data, $data));
    }

    /**
     * 渲染模板文件
     * @param string $templatePath
     * @return void
     */
    public function render(string $templatePath): void
    {
        include $templatePath;
    }

    /**
     * 获取模板数据
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 转义输出
     * @param string $text
     * @param int $flags
     * @return string
     */
    public function escape($text, int $flags = ENT_QUOTES): string
    {
        if ($text === null) {
            return '';
        }
        return Theme::escape((string)$text, $flags);
    }

    /**
     * 渲染 Markdown
     * @param string $content
     * @return string
     */
    public function markdown(string $content): string
    {
        return Theme::markdown($content);
    }

    /**
     * 输出组件
     * @param string $componentPath
     * @param array $data
     * @return void
     */
    public function components(string $componentPath, array $data = []): void
    {
        $componentPath = str_replace(['.', '/'], DIRECTORY_SEPARATOR, $componentPath);

        $themeDir = Theme::getThemeDir();
        $componentsDir = Cms::findDirectoryCaseInsensitive($themeDir, 'app/components');
        if ($componentsDir === null) {
            Debug::warn('[Anon Theme components] 组件目录未找到', ['themeDir' => $themeDir, 'lookup' => 'app/components']);
            return;
        }

        $pathParts = explode(DIRECTORY_SEPARATOR, $componentPath);
        $componentName = array_pop($pathParts);
        $componentDir = $componentsDir;

        foreach ($pathParts as $part) {
            $foundDir = Cms::findDirectoryCaseInsensitive($componentDir, $part);
            if ($foundDir === null) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Debug::warn('[Anon Theme components] 组件子目录未找到', ['componentPath' => $componentPath, 'part' => $part, 'componentDir' => $componentDir]);
                }
                return;
            }
            $componentDir = $foundDir;
        }

        $componentFile = Cms::findFileCaseInsensitive($componentDir, $componentName);
        if ($componentFile === null) {
            Debug::warn('[Anon Theme components] 组件文件未找到', ['componentPath' => $componentPath, 'componentName' => $componentName, 'componentDir' => $componentDir]);
            return;
        }

        $componentKey = $componentFile;
        if (isset(self::$renderedComponents[$componentKey])) {
            return;
        }

        self::$renderedComponents[$componentKey] = true;
        $this->child($data)->render($componentFile);
    }

    /**
     * 输出主题资源
     * @param string $path
     * @param string|null $type
     * @param array $attributes
     * @return string
     */
    public function assets(string $path, $forceNoCacheOrType = null, array $attributes = []): string
    {
        return Theme::assets($path, $forceNoCacheOrType, $attributes);
    }

    /**
     * 输出页面头部 meta
     * @param array $overrides 覆盖项
     * @return void
     */
    public function headMeta(array $overrides = []): void
    {
        Theme::headMeta($overrides);
        if ($this->paginator) {
            if ($this->paginator->hasPrev()) {
                echo '<link rel="prev" href="' . $this->escape($this->paginator->pageLink($this->paginator->prevPage())) . '">' . "\n";
            }
            if ($this->paginator->hasNext()) {
                echo '<link rel="next" href="' . $this->escape($this->paginator->pageLink($this->paginator->nextPage())) . '">' . "\n";
            }
        }
    }

    /**
     * 输出 HTML 头部，同 headMeta，类似 Typecho header()
     * @param array $overrides 覆盖项
     * @return void
     */
    public function header(array $overrides = []): void
    {
        Theme::headMeta($overrides);
    }

    /**
     * 当前归档标题：有文章/页面时为其标题，否则为站点标题，类似 Typecho archiveTitle
     * @return string
     */
    public function archiveTitle(): string
    {
        $p = $this->post();
        if ($p !== null) {
            return $p->title();
        }
        $p = $this->page();
        if ($p !== null) {
            return $p->title();
        }
        return (string) $this->options()->get('title', '', false);
    }

    /**
     * 输出或返回关键词，类似 Typecho keywords
     * @param string $separator 多个关键词分隔符
     * @param string $default 默认值
     * @param bool $output true 直接输出，false 仅返回
     * @return string
     */
    public function keywords(string $separator = ',', string $default = '', bool $output = true): string
    {
        $kw = $this->options()->get('keywords', $default, false);
        if (is_array($kw)) {
            $kw = implode($separator, $kw);
        }
        $kw = (string) $kw;
        if ($output && $kw !== '') {
            echo $this->escape($kw);
        }
        return $kw;
    }

    /**
     * 输出或返回站点描述，类似 Typecho description
     * @param string $default 默认值
     * @param bool $output true 直接输出，false 仅返回
     * @return string
     */
    public function description(string $default = '', bool $output = true): string
    {
        $desc = (string) $this->options()->get('description', $default, false);
        if ($output && $desc !== '') {
            echo $this->escape($desc);
        }
        return $desc;
    }

    /**
     * 输出页面底部 meta
     * @return void
     */
    public function footMeta(): void
    {
        Theme::footMeta();
    }

    /**
     * 输出片段
     * @param string $partialName
     * @param array $data
     * @return void
     */
    public function partial(string $partialName, array $data = []): void
    {
        $themeDir = Theme::getThemeDir();
        $partialsDir = Cms::findDirectoryCaseInsensitive($themeDir, 'partials');
        if ($partialsDir === null) {
            return;
        }

        $partialPath = Cms::findFileCaseInsensitive($partialsDir, $partialName);
        if ($partialPath === null) {
            return;
        }

        $this->child($data)->render($partialPath);
    }

    /**
     * 获取当前文章对象，不存在时由框架渲染 404 并结束
     * @return Post|null
     */
    public function post(): ?Post
    {
        $data = Cms::getPost();
        return $data ? new Post($data) : null;
    }

    /**
     * 获取当前页面对象，不存在时由框架渲染 404 并结束
     * @return Post|null
     */
    public function page(): ?Post
    {
        $data = Cms::getPage();
        return $data ? new Post($data) : null;
    }

    /**
     * 文章列表缓存
     * @var array
     */
    private static $postsCache = [];

    /**
     * 分页器实例
     * @var Paginator|null
     */
    private $paginator = null;

    /**
     * 获取最新文章列表
     * @param int $pageSize 每页数量
     * @param int|null $page 当前页码
     * @return Post[]
     */
    public function posts(int $pageSize = 10, ?int $page = null): array
    {
        if ($pageSize <= 0) {
            $pageSize = 10;
            $db = Database::getInstance();
            $rows = $db->db('posts')
                ->select(['id', 'type', 'title', 'slug', 'content', 'status', 'author_id', 'category_id', 'tag_ids', 'views', 'comment_status', 'created_at', 'updated_at'])
                ->where('type', 'post')
                ->where('status', 'publish')
                ->orderBy('created_at', 'DESC')
                ->limit($pageSize)
                ->get();

            $rawPosts = is_array($rows) ? $rows : [];
            $result = [];
            foreach ($rawPosts as $postData) {
                $result[] = new Post($postData);
            }
            return $result;
        }

        $pageSize = max(1, min(100, $pageSize));

        if ($page === null) {
            // 优先检查是否有路由参数传入的 page
            if ($this->get('page')) {
                $page = max(1, (int)$this->get('page'));
            } elseif (isset($_GET['page'])) {
                $page = max(1, (int)$_GET['page']);
            } else {
                $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
                $path = parse_url($requestUri, PHP_URL_PATH);
                if (preg_match('#/page/(\d+)#', $path, $matches)) {
                    $page = max(1, (int)$matches[1]);
                } else {
                    $page = 1;
                }
            }
        } else {
            $page = max(1, $page);
        }

        $cacheKey = 'posts_' . $pageSize . '_' . $page;
        if (isset(self::$postsCache[$cacheKey])) {
            $cached = self::$postsCache[$cacheKey];
            $this->paginator = $cached['paginator'];
            return $cached['posts'];
        }

        $db = Database::getInstance();
        $total = $db->db('posts')
            ->where('type', 'post')
            ->where('status', 'publish')
            ->count();

        $totalPages = max(1, (int)ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $rows = $db->db('posts')
            ->select(['id', 'type', 'title', 'slug', 'content', 'status', 'author_id', 'category_id', 'tag_ids', 'views', 'comment_status', 'created_at', 'updated_at'])
            ->where('type', 'post')
            ->where('status', 'publish')
            ->orderBy('created_at', 'DESC')
            ->offset($offset)
            ->limit($pageSize)
            ->get();

        $rawPosts = is_array($rows) ? $rows : [];
        $result = [];
        foreach ($rawPosts as $postData) {
            $result[] = new Post($postData);
        }

        $this->paginator = new Paginator($page, $pageSize, $total, $totalPages);
        self::$postsCache[$cacheKey] = [
            'posts' => $result,
            'paginator' => $this->paginator,
        ];

        return $result;
    }

    /**
     * 获取分页导航数据
     * @return array|null
     */
    public function pageNav(): ?array
    {
        if ($this->paginator === null || $this->paginator->totalPages() <= 1) {
            return null;
        }

        $paginator = $this->paginator;
        $result = [
            'prev' => null,
            'next' => null,
            'pages' => [],
            'current' => $paginator->currentPage(),
            'total' => $paginator->totalPages(),
        ];

        if ($paginator->hasPrev()) {
            $result['prev'] = [
                'page' => $paginator->prevPage(),
                'link' => $paginator->pageLink($paginator->prevPage()),
            ];
        }

        if ($paginator->hasNext()) {
            $result['next'] = [
                'page' => $paginator->nextPage(),
                'link' => $paginator->pageLink($paginator->nextPage()),
            ];
        }

        $pageNumbers = $paginator->getPageNumbers();
        foreach ($pageNumbers as $pageNum) {
            $result['pages'][] = [
                'page' => $pageNum,
                'link' => $paginator->pageLink($pageNum),
                'current' => $pageNum == $paginator->currentPage(),
            ];
        }

        return $result;
    }

    public function isLoggedIn(): bool
    {
        return Check::isLoggedIn();
    }

    public function getCommentDisplayName(): string
    {
        return Cms::getCurrentUserDisplayName();
    }

    public function getPostComments(int $postId): array
    {
        return Cms::getCommentsByPostId($postId);
    }

    public function getCommentsByPostId(int $postId): array
    {
        return Cms::getCommentsByPostId($postId);
    }

    public function getPostIfExists(?int $id = null): ?array
    {
        return Cms::getPostIfExists($id);
    }

    public function getPageIfExists(?string $slug = null): ?array
    {
        return Cms::getPageIfExists($slug);
    }

    public function getPageType(string $templateName): string
    {
        return Cms::getPageType($templateName);
    }

    public function getTemplateExtensions(): array
    {
        return Cms::getTemplateExtensions();
    }

    public function getPageLoadTime(): float
    {
        return Cms::getPageLoadTime();
    }

    public function findDirectoryCaseInsensitive(string $baseDir, string $dirName): ?string
    {
        return Cms::findDirectoryCaseInsensitive($baseDir, $dirName);
    }

    public function findFileCaseInsensitive(string $baseDir, string $fileName, ?array $extensions = null): ?string
    {
        return Cms::findFileCaseInsensitive($baseDir, $fileName, $extensions);
    }

    public function options(?string $name = null, $default = null, $outputOrPriority = false, ?string $priority = null)
    {
        $proxy = new OptionsProxy('theme', null, Theme::getCurrentTheme());
        if ($name === null) {
            return $proxy;
        }
        try {
            $result = $proxy->get($name, $default, $outputOrPriority, $priority);

            if (Debug::isEnabled()) {
                Debug::debug('[View] options() 调用结果', [
                    'option_name' => $name,
                    'default' => $default,
                    'outputOrPriority' => $outputOrPriority,
                    'priority' => $priority,
                    'result' => $result,
                    'result_type' => gettype($result),
                    'result_is_null' => is_null($result),
                    'result_is_empty' => empty($result),
                    'result_is_object' => is_object($result),
                    'result_class' => is_object($result) ? get_class($result) : null,
                    'will_output' => (bool) $outputOrPriority
                ]);
            }

            if (is_object($result)) {
                if ($result instanceof OptionsProxy) {
                    if (Debug::isEnabled()) {
                        Debug::error('[View] options() 返回了代理对象', [
                            'option_name' => $name,
                            'default' => $default,
                            'outputOrPriority' => $outputOrPriority,
                            'priority' => $priority,
                            'result_type' => get_class($result),
                            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                        ]);
                    }
                    return $default;
                }
                if (method_exists($result, '__toString')) {
                    $stringValue = (string) $result;
                    if (Debug::isEnabled()) {
                        Debug::warn('[View] options() 返回了对象，已转换为字符串', [
                            'option_name' => $name,
                            'result_type' => get_class($result),
                            'converted_value' => $stringValue
                        ]);
                    }
                    return $stringValue;
                }
                if (Debug::isEnabled()) {
                    Debug::error('[View] options() 返回了无法转换的对象', [
                        'option_name' => $name,
                        'default' => $default,
                        'result_type' => get_class($result),
                        'result_methods' => get_class_methods($result),
                        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                    ]);
                }
                return $default;
            }
            return $result;
        } catch (Throwable $e) {
            if (Debug::isEnabled()) {
                Debug::error('[View] options() 发生异常', [
                    'option_name' => $name,
                    'default' => $default,
                    'outputOrPriority' => $outputOrPriority,
                    'priority' => $priority,
                    'exception_type' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
                ]);
            }
            return $default;
        }
    }

    /**
     * 当前登录用户对象，未登录为 null
     * @return User|null
     */
    public function user(): ?User
    {
        $user = Cms::getCurrentUser();
        return $user !== null ? new User($user) : null;
    }

    /**
     * 用户页当前被访问的用户对象，由路由 uid 或 name 解析，不存在为 null
     * @return User|null
     */
    public function profileUser(): ?User
    {
        $uid = $this->get('uid');
        $name = $this->get('name');
        $user = null;
        if ($uid !== null && $uid !== '') {
            $user = Cms::getUserByUid((int) $uid);
        } elseif (is_string($name) && $name !== '') {
            $user = Cms::getUserByName($name);
        }
        return $user !== null ? new User($user) : null;
    }

    /**
     * 主题辅助对象，仅读当前主题名与主题选项
     * @return ThemeHelper
     */
    public function theme(): ThemeHelper
    {
        return new ThemeHelper(Theme::getCurrentTheme());
    }

    /**
     * 生成永久链接
     * @param Post|array|null $post 文章或页面对象
     * @return string
     */
    public function permalink($post = null): string
    {
        if ($post === null) {
            $post = $this->post();
            if ($post === null) {
                $post = $this->page();
            }
        }

        if ($post === null) {
            return '';
        }

        if (is_array($post)) {
            $post = new Post($post);
        }

        $type = $post->type();
        if (empty($type)) {
            return '';
        }

        $routesValue = CmsOptions::get('routes', '');
        $routes = [];

        if (is_array($routesValue)) {
            $routes = $routesValue;
        } elseif (is_string($routesValue) && !empty($routesValue)) {
            $decoded = json_decode($routesValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $routes = $decoded;
            }
        }

        $routePattern = null;

        foreach ($routes as $pattern => $template) {
            if ($template === $type) {
                $routePattern = $pattern;
                break;
            }
        }

        if ($routePattern === null) {
            $typeMapping = [
                'post' => 'post',
                'page' => 'page',
            ];

            $templateName = $typeMapping[$type] ?? null;
            if ($templateName !== null) {
                foreach ($routes as $pattern => $template) {
                    if ($template === $templateName) {
                        $routePattern = $pattern;
                        break;
                    }
                }
            }
        }

        if ($routePattern === null) {
            if ($type === 'post') {
                $routePattern = '/post/{id}';
            } elseif ($type === 'page') {
                $routePattern = '/{slug}';
            } else {
                return '';
            }
        }

        $url = $routePattern;

        if (strpos($url, '{id}') !== false) {
            $id = $post->id();
            $url = str_replace('{id}', $id, $url);
        }

        if (strpos($url, '{slug}') !== false) {
            $slug = $post->slug();
            $url = str_replace('{slug}', urlencode($slug), $url);
        }

        if (strpos($url, '{category}') !== false) {
            $categoryId = $post->categoryId();
            if ($categoryId) {
                $url = str_replace('{category}', $categoryId, $url);
            } else {
                $url = str_replace('{category}', '', $url);
            }
        }

        if (strpos($url, '{directory}') !== false) {
            $categoryId = $post->categoryId();
            if ($categoryId) {
                $url = str_replace('{directory}', $categoryId, $url);
            } else {
                $url = str_replace('{directory}', '', $url);
            }
        }

        $date = $post->date('Y-m-d');
        if (!empty($date)) {
            $dateParts = explode('-', $date);
            if (count($dateParts) === 3) {
                $year = $dateParts[0];
                $month = $dateParts[1];
                $day = $dateParts[2];

                $url = str_replace('{year}', $year, $url);
                $url = str_replace('{month}', $month, $url);
                $url = str_replace('{day}', $day, $url);
            }
        }

        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }

        return self::getSiteBaseUrl() . $url;
    }

    /**
     * 站点根 URL，带参数时拼接相对路径
     * @param string $suffix 相对路径，如 /about、/post/1
     * @return string
     */
    public function siteUrl(string $suffix = ''): string
    {
        $base = self::getSiteBaseUrl();
        if ($suffix === '') {
            return $base;
        }
        $suffix = '/' . ltrim($suffix, '/');
        return $base . $suffix;
    }

    /**
     * 获取站点根链接
     * 优先使用 options 中的 site_url，否则根据当前请求生成
     * @return string
     */
    public static function getSiteBaseUrl(): string
    {
        $base = CmsOptions::get('site_url', '');
        if ($base === '' || $base === null) {
            $base = CmsOptions::get('home', '');
        }
        if (is_string($base) && $base !== '') {
            $base = rtrim($base, '/');
            if ($base !== '') {
                return $base;
            }
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    /**
     * 调用 ThemeHelper 的方法
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $themeHelper = $this->theme();
        if (method_exists($themeHelper, $name)) {
            return $themeHelper->$name(...$arguments);
        }

        // 兼容旧版或错误调用
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Debug::warn("Call to undefined method " . __CLASS__ . "::$name()");
        }
        return null;
    }
}
