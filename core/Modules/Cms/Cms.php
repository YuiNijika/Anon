<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms
{
    /**
     * 默认路由规则
     */
    public const DEFAULT_ROUTES = [
        '/' => 'index',
        '/post/{id}' => 'post',
        '/category/{slug}' => 'category',
        '/category/{slug}/{page}' => 'category',
        '/tag/{slug}' => 'tag',
        '/tag/{slug}/{page}' => 'tag',
    ];

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
        $specialTypes = ['index', 'post', 'page', 'error', 'user', 'author'];

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

        $result = array_filter($items, function ($item) {
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
     * 检查是否为文件，带缓存
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
        $queryCount = Anon_Database::getQueryCount() ?? null;
        echo '<script>';
        echo 'console.log("页面加载耗时: ' . $loadTime . 'ms | SQL查询: ' . $queryCount . '次");';
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

        // 增加文章阅读量
        if (!isset(self::$viewedPosts[$id])) {
            $currentViews = (int)($post['views'] ?? 0);
            $post['views'] = $currentViews + 1;
            self::$viewedPosts[$id] = true;

            register_shutdown_function(function () use ($id, $currentViews) {
                try {
                    $db = Anon_Database::getInstance();
                    $db->db('posts')
                        ->where('id', $id)
                        ->where('type', 'post')
                        ->update(['views' => $currentViews + 1]);
                } catch (Exception $e) {
                    Anon_Debug::error("异步增加文章浏览量失败", ['message' => $e->getMessage()]);
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

        // 增加页面阅读量
        if (!isset(self::$viewedPosts[$page['id']])) {
            $currentViews = (int)($page['views'] ?? 0);
            $page['views'] = $currentViews + 1;
            self::$viewedPosts[$page['id']] = true;

            register_shutdown_function(function () use ($page, $currentViews) {
                try {
                    $db = Anon_Database::getInstance();
                    $db->db('posts')
                        ->where('id', $page['id'])
                        ->where('type', 'page')
                        ->update(['views' => $currentViews + 1]);
                } catch (Exception $e) {
                    Anon_Debug::error("异步增加页面浏览量失败", ['message' => $e->getMessage()]);
                }
            });
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

    /**
     * 判断是否为 AJAX/JSON 请求
     */
    private static function isCommentAjaxRequest(): bool
    {
        $with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? (string) $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
        if (strtolower($with) === 'xmlhttprequest') {
            return true;
        }
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * 获取站点根 URL
     */
    private static function getSiteBaseUrl(): string
    {
        $base = Anon_Cms_Options::get('site_url', '');
        if ($base === '' || $base === null) {
            $base = Anon_Cms_Options::get('home', '');
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
     * @param int $postId
     * @return array
     */
    public static function getCommentsByPostId(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        try {
            $db = Anon_Database::getInstance();
            $rows = $db->db('comments')
                ->select(['id', 'post_id', 'parent_id', 'uid', 'type', 'name', 'email', 'url', 'content', 'created_at'])
                ->where('post_id', $postId)
                ->where('status', 'approved')
                ->orderBy('created_at', 'ASC')
                ->get();
            if (!is_array($rows)) {
                return [];
            }
            $flat = self::enrichCommentsForTheme($rows, $db);
            return self::flatCommentsToTree($flat);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** 将扁平评论列表转为树形，根节点带 children */
    private static function flatCommentsToTree(array $rows): array
    {
        $roots = [];
        $childrenMap = [];
        foreach ($rows as $r) {
            $pid = isset($r['parent_id']) ? (int) $r['parent_id'] : 0;
            if ($pid === 0) {
                $roots[] = $r;
            } else {
                if (!isset($childrenMap[$pid])) {
                    $childrenMap[$pid] = [];
                }
                $childrenMap[$pid][] = $r;
            }
        }
        $tree = [];
        foreach ($roots as $r) {
            $node = $r;
            $node['children'] = $childrenMap[(int) $r['id']] ?? [];
            $tree[] = $node;
        }
        return $tree;
    }

    /** @return array */
    private static function enrichCommentsForTheme(array $rows, $db): array
    {
        $uids = [];
        foreach ($rows as $r) {
            if (!empty($r['type']) && $r['type'] === 'user' && isset($r['uid']) && (int) $r['uid'] > 0) {
                $uids[(int) $r['uid']] = true;
            }
        }
        $userRepo = isset($db->userRepository) ? $db->userRepository : null;
        $userMap = [];
        if (!empty($uids) && $userRepo && method_exists($userRepo, 'getUserInfo')) {
            foreach (array_keys($uids) as $uid) {
                $user = $userRepo->getUserInfo($uid);
                if ($user) {
                    $userMap[$uid] = [
                        'name' => isset($user['display_name']) && (string) $user['display_name'] !== ''
                            ? (string) $user['display_name']
                            : (string) ($user['name'] ?? ''),
                        'email' => (string) ($user['email'] ?? ''),
                        'avatar' => (string) ($user['avatar'] ?? ''),
                    ];
                }
            }
        }
        foreach ($rows as &$r) {
            if (!empty($r['type']) && $r['type'] === 'user' && isset($r['uid']) && isset($userMap[(int) $r['uid']])) {
                $r['name'] = $userMap[(int) $r['uid']]['name'];
                $r['email'] = $userMap[(int) $r['uid']]['email'];
                $r['avatar'] = $userMap[(int) $r['uid']]['avatar'];
                $r['url'] = $r['url'] ?? null;
            } elseif ((!isset($r['avatar']) || $r['avatar'] === '') && !empty($r['email']) && $userRepo && method_exists($userRepo, 'getAvatarByEmail')) {
                $r['avatar'] = $userRepo->getAvatarByEmail($r['email']);
            }
        }
        unset($r);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        foreach ($rows as &$r) {
            if (!empty($r['parent_id']) && isset($byId[(int) $r['parent_id']])) {
                $r['reply_to_name'] = (string) ($byId[(int) $r['parent_id']]['name'] ?? '');
            }
        }
        unset($r);

        // Build Tree
        $tree = [];
        foreach ($rows as $key => &$row) {
            $row['children'] = [];
            if (empty($row['parent_id'])) {
                $tree[] = &$row;
            } else {
                if (isset($byId[(int) $row['parent_id']])) {
                    // Find parent in the reference array (which should be linked to the rows)
                    // Since $byId is a copy, we need to find the parent in $rows
                    // But $rows is a list.
                    // Easier approach: Use references.
                }
            }
        }

        // Re-implement with references for robust tree building
        $refs = [];
        foreach ($rows as &$r) {
            $r['children'] = [];
            $refs[$r['id']] = &$r;
        }
        unset($r);

        $tree = [];
        foreach ($rows as &$r) {
            if (empty($r['parent_id'])) {
                $tree[] = &$r;
            } else {
                if (isset($refs[$r['parent_id']])) {
                    $refs[$r['parent_id']]['children'][] = &$r;
                }
            }
        }
        unset($r);

        return $tree;
    }

    /**
     * 获取当前登录用户信息，主题或插件内通过 $this->user() 获取封装对象
     * @return array|null 用户数组，含 uid/name/email/display_name/avatar 等；未登录返回 null
     */
    public static function getCurrentUser(): ?array
    {
        if (!Anon_Check::isLoggedIn()) {
            return null;
        }
        $userId = Anon_Http_Request::getUserId();
        if (!$userId) {
            return null;
        }
        $db = Anon_Database::getInstance();
        $userRepo = isset($db->userRepository) ? $db->userRepository : null;
        if (!$userRepo || !method_exists($userRepo, 'getUserInfo')) {
            return null;
        }
        $user = $userRepo->getUserInfo($userId);
        if (!$user || empty($user['name'])) {
            return null;
        }
        $user['uid'] = (int) ($user['uid'] ?? $user['id'] ?? $userId);
        return $user;
    }

    /**
     * 根据 uid 获取用户信息，供用户页模板使用
     * @param int $uid 用户 ID
     * @return array|null 用户数组，不含 password
     */
    public static function getUserByUid(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $db = Anon_Database::getInstance();
        $userRepo = isset($db->userRepository) ? $db->userRepository : null;
        if (!$userRepo || !method_exists($userRepo, 'getUserInfo')) {
            return null;
        }
        $user = $userRepo->getUserInfo($uid);
        if (!$user || empty($user['name'])) {
            return null;
        }
        $user['uid'] = (int) ($user['uid'] ?? $user['id'] ?? $uid);
        return $user;
    }

    /**
     * 根据登录名获取用户信息，供用户页模板使用
     * @param string $name 登录名
     * @return array|null 用户数组，不含 password
     */
    public static function getUserByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $db = Anon_Database::getInstance();
        $userRepo = isset($db->userRepository) ? $db->userRepository : null;
        if (!$userRepo || !method_exists($userRepo, 'getUserInfoByName')) {
            return null;
        }
        $user = $userRepo->getUserInfoByName($name);
        if (!is_array($user) || empty($user['name'])) {
            return null;
        }
        unset($user['password']);
        $user['uid'] = (int) ($user['uid'] ?? $user['id'] ?? 0);
        return $user;
    }

    /** @return string */
    public static function getCurrentUserDisplayName(): string
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return '';
        }
        return (isset($user['display_name']) && (string) $user['display_name'] !== '')
            ? (string) $user['display_name']
            : (string) $user['name'];
    }

    /** GET /anon/cms/comments */
    public static function handleCommentsGet(): void
    {
        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $data = ['comments' => []];
        if ($postId > 0) {
            $data['comments'] = self::getCommentsByPostId($postId);
        }
        if (Anon_Check::isLoggedIn()) {
            $userId = Anon_Http_Request::getUserId();
            if ($userId) {
                $db = Anon_Database::getInstance();
                $userRepo = isset($db->userRepository) ? $db->userRepository : null;
                if ($userRepo && method_exists($userRepo, 'getUserInfo')) {
                    $user = $userRepo->getUserInfo($userId);
                    if ($user && !empty($user['name'])) {
                        $data['currentUser'] = [
                            'displayName' => isset($user['display_name']) && (string) $user['display_name'] !== ''
                                ? (string) $user['display_name']
                                : (string) $user['name'],
                            'name' => (string) ($user['name'] ?? ''),
                            'avatar' => (string) ($user['avatar'] ?? ''),
                        ];
                    }
                }
            }
        }
        Anon_Http_Response::success($data, '获取成功');
    }

    /** POST /anon/cms/comments */
    public static function handleCommentSubmit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Anon_Http_Response::error('仅支持 POST', null, 405);
            return;
        }
        $isAjax = self::isCommentAjaxRequest();
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = trim((string) ($_POST['content'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $base = self::getSiteBaseUrl();
        $redirectPost = $postId > 0 ? $base . '/post/' . $postId : $base . '/';
        $redirect = function (string $query) use ($redirectPost) {
            Anon_Common::Header(302, true, false);
            header('Location: ' . $redirectPost . (strpos($redirectPost, '?') !== false ? '&' : '?') . $query);
            exit;
        };

        $uid = null;
        $commentType = 'guest';
        $name = null;
        $email = null;
        if (Anon_Check::isLoggedIn()) {
            $uid = Anon_Http_Request::getUserId();
            if ($uid) {
                $commentType = 'user';
            }
        }
        if ($commentType === 'guest') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
        }

        if ($postId <= 0 || $content === '') {
            if ($isAjax) {
                Anon_Http_Response::error($uid ? '请填写评论内容' : '请填写名称、邮箱和评论内容');
                return;
            }
            $redirect('comment=error');
        }
        if ($commentType === 'guest' && ($name === '' || $email === '')) {
            if ($isAjax) {
                Anon_Http_Response::error('请填写名称、邮箱和评论内容');
                return;
            }
            $redirect('comment=error');
        }

        // 验证码验证
        // 确保验证码模块已加载
        if ($commentType === 'guest' && Anon_System_Env::get('app.captcha.enabled', false)) {
            if (!class_exists('Anon_Auth_Captcha')) {
                require_once Anon_Main::MODULES_DIR . 'Auth/Captcha.php';
            }
            if (class_exists('Anon_Auth_Captcha') && Anon_Auth_Captcha::isEnabled()) {
                $captchaCode = trim((string) ($_POST['captcha'] ?? ''));
                if ($captchaCode === '') {
                    if ($isAjax) {
                        Anon_Http_Response::error('请输入验证码');
                        return;
                    }
                    $redirect('comment=captcha_required');
                }
                if (!Anon_Auth_Captcha::verify($captchaCode)) {
                    if ($isAjax) {
                        Anon_Http_Response::error('验证码错误，请重新输入');
                        return;
                    }
                    $redirect('comment=captcha_error');
                }
                // 验证成功后清除验证码，防止重复使用
                Anon_Auth_Captcha::clear();
            }
        }

        $post = self::getPostIfExists($postId);
        if (!$post || ($post['type'] ?? '') !== 'post') {
            if ($isAjax) {
                Anon_Http_Response::error('文章不存在或无法评论');
                return;
            }
            $redirect('comment=error');
        }
        if (($post['comment_status'] ?? 'open') !== 'open') {
            if ($isAjax) {
                Anon_Http_Response::error('该文章已关闭评论');
                return;
            }
            $redirect('comment=closed');
        }
        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        if ($parentId > 0) {
            $parent = Anon_Database::getInstance()->db('comments')
                ->select(['id', 'post_id', 'parent_id', 'status'])
                ->where('id', $parentId)
                ->first();
            if (!$parent || (int) $parent['post_id'] !== $postId || (int) ($parent['parent_id'] ?? 0) !== 0) {
                if ($isAjax) {
                    Anon_Http_Response::error('无法回复该评论');
                    return;
                }
                $redirect('comment=error');
            }
            if (($parent['status'] ?? '') !== 'approved') {
                if ($isAjax) {
                    Anon_Http_Response::error('该评论暂不可回复');
                    return;
                }
                $redirect('comment=error');
            }
        }

        $ip = Anon_Common::GetClientIp();
        if (!is_string($ip) || $ip === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        try {
            $initialStatus = ($commentType === 'user') ? 'approved' : 'pending';
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? trim((string) $_SERVER['HTTP_USER_AGENT']) : null;
            if ($userAgent === '') {
                $userAgent = null;
            }
            $insert = [
                'post_id' => $postId,
                'parent_id' => $parentId > 0 ? $parentId : null,
                'uid' => $uid,
                'type' => $commentType,
                'ip' => $ip,
                'content' => $content,
                'status' => $initialStatus,
            ];
            if ($userAgent !== null) {
                $insert['user_agent'] = $userAgent;
            }
            if ($commentType === 'guest') {
                $insert['name'] = $name;
                $insert['email'] = $email;
                $insert['url'] = $url === '' ? null : $url;
            } else {
                $insert['name'] = null;
                $insert['email'] = null;
                $insert['url'] = $url === '' ? null : $url;
            }
            Anon_Database::getInstance()->db('comments')->insert($insert);
        } catch (Throwable $e) {
            if ($isAjax) {
                Anon_Http_Response::error('提交失败，请稍后重试');
                return;
            }
            $redirect('comment=error');
        }
        if ($isAjax) {
            $msg = ($commentType === 'user') ? '评论发表成功' : '评论已提交，待审核通过后会显示。';
            Anon_Http_Response::success(null, $msg);
            return;
        }
        $redirect(($commentType === 'user') ? 'comment=ok' : 'comment=pending');
    }

    /** GET list / POST submit */
    public static function handleCommentsRequest(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'GET') {
            self::handleCommentsGet();
            return;
        }
        if ($method === 'POST') {
            self::handleCommentSubmit();
            return;
        }
        Anon_Http_Response::error('仅支持 GET 或 POST', null, 405);
    }
}
