<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Theme
{
    private static $currentTheme = 'default';
    private static $themeDir = null;
    private static $templateCache = [];
    private static $themeInfoCache = [];
    private static $mimeTypesCache = null;
    private static $typeDirsCache = null;

    /**
     * 获取 MIME 类型映射
     * @return array
     */
    private static function getMimeTypes(): array
    {
        if (self::$mimeTypesCache === null) {
            $defaultMimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
            ];
            
            self::$mimeTypesCache = Anon_System_Hook::apply_filters('cms_theme_mime_types', $defaultMimeTypes);
        }
        
        return self::$mimeTypesCache;
    }

    /**
     * 获取文件类型到目录的映射
     * @return array
     */
    private static function getTypeDirs(): array
    {
        if (self::$typeDirsCache === null) {
            $defaultTypeDirs = [
                'css' => 'css',
                'js' => 'js',
                'json' => 'json',
                'png' => 'images',
                'jpg' => 'images',
                'jpeg' => 'images',
                'gif' => 'images',
                'svg' => 'images',
                'ico' => 'images',
                'woff' => 'fonts',
                'woff2' => 'fonts',
                'ttf' => 'fonts',
                'eot' => 'fonts',
            ];
            
            self::$typeDirsCache = Anon_System_Hook::apply_filters('cms_theme_type_dirs', $defaultTypeDirs);
        }
        
        return self::$typeDirsCache;
    }

    private static $initialized = false;

    /**
     * 初始化主题系统
     * @param string|null $themeName 主题名称
     * @return void
     */
    public static function init(?string $themeName = null): void
    {
        try {
            if (self::$initialized && self::$themeDir !== null) {
                return;
            }
            
            if ($themeName === null) {
                $themeName = Anon_System_Env::get('app.cms.theme', 'default');
            }
            
            $actualThemeDir = self::findThemeDirectory($themeName);
            if ($actualThemeDir === null) {
                throw new RuntimeException("主题目录未找到: {$themeName}");
            }
            
            self::$currentTheme = basename($actualThemeDir);
            self::$themeDir = $actualThemeDir;
            self::$initialized = true;
            
            self::loadThemeFunctions();
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * 加载主题 functions.php 文件
     * @return void
     */
    private static function loadThemeFunctions(): void
    {
        try {
            $functionsFile = self::$themeDir . DIRECTORY_SEPARATOR . 'functions.php';
            if (file_exists($functionsFile)) {
                require_once $functionsFile;
            }
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 查找主题目录
     * @param string $themeName 主题名称
     * @return string|null
     */
    private static function findThemeDirectory(string $themeName): ?string
    {
        $themesDir = Anon_Main::APP_DIR . 'Theme/';
        return Anon_Cms::findDirectoryCaseInsensitive($themesDir, $themeName);
    }

    /**
     * 获取当前主题目录
     * @return string
     */
    public static function getThemeDir(): string
    {
        try {
            if (!self::$initialized || self::$themeDir === null) {
                self::init();
            }
            return self::$themeDir;
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 渲染模板文件
     * @param string $templateName 模板名称
     * @param array $data 数据
     * @return void
     */
    public static function render(string $templateName, array $data = []): void
    {
        try {
            if (!self::$initialized) {
                self::init();
            }
            
            if (!self::$assetsRegistered) {
                self::registerAssets();
            }
            
            Anon_Cms::startPageLoad();
            
            $templatePath = self::findTemplate($templateName);
            
            if ($templatePath === null) {
                $pageType = Anon_Cms::getPageType($templateName);
                if ($pageType !== 'index' && in_array($pageType, ['post', 'page', 'error'])) {
                    $templatePath = self::findTemplate('index');
                }
            }
            
            if ($templatePath === null) {
                throw new RuntimeException("模板文件未找到: {$templateName}");
            }

            extract($data, EXTR_SKIP);

            ob_start();
            try {
                include $templatePath;
            } catch (Error $e) {
                ob_end_clean();
                self::handleFatalError($e);
            } catch (Throwable $e) {
                ob_end_clean();
                if (self::isFatalError($e)) {
                    self::handleFatalError($e);
                } else {
                    throw $e;
                }
            }
            $content = ob_get_clean();

            echo $content;
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 查找模板文件
     * @param string $templateName 模板名称
     * @return string|null
     */
    public static function findTemplate(string $templateName): ?string
    {
        $cacheKey = self::$currentTheme . ':' . strtolower($templateName);
        
        if (isset(self::$templateCache[$cacheKey])) {
            return self::$templateCache[$cacheKey];
        }

        $themeDir = self::getThemeDir();
        $templatePath = Anon_Cms::findFileCaseInsensitive($themeDir, $templateName);
        
        if ($templatePath !== null) {
            self::$templateCache[$cacheKey] = $templatePath;
        }
        
        return $templatePath;
    }

    /**
     * 获取主题资源 URL 或输出 HTML 标签
     * @param string $path 资源路径
     * @param string|null $type 资源类型（可选，不指定则根据文件后缀自动判断）
     * @param array $attributes 额外属性
     * @return string 如果只返回 URL，否则返回空字符串
     */
    public static function assets(string $path, ?string $type = null, array $attributes = []): string
    {
        $path = ltrim($path, '/');
        
        if (!self::$assetsRegistered) {
            self::registerAssets();
        }
        
        $themeDir = self::getThemeDir();
        $assetsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'assets');
        
        if ($assetsDir === null) {
            return '';
        }
        
        $filePath = $assetsDir . DIRECTORY_SEPARATOR . $path;
        $fileExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if ($type === null) {
            $type = self::detectAssetType($fileExt);
        }
        
        $typeDirs = self::getTypeDirs();
        $typeDir = $typeDirs[$fileExt] ?? 'files';
        
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $url = '/assets/' . $typeDir . '/' . $fileName;
        
        $mimeTypes = self::getMimeTypes();
        $mimeType = $mimeTypes[$fileExt] ?? 'application/octet-stream';
        
        if ($type === 'css' || $type === 'js') {
            if ($type === 'css') {
                $rel = $attributes['rel'] ?? 'stylesheet';
                $media = isset($attributes['media']) ? ' media="' . htmlspecialchars($attributes['media']) . '"' : '';
                echo '<link rel="' . htmlspecialchars($rel) . '" href="' . htmlspecialchars($url) . '"' . $media . '>' . "\n";
            } elseif ($type === 'js') {
                $defer = isset($attributes['defer']) ? ' defer' : '';
                $async = isset($attributes['async']) ? ' async' : '';
                echo '<script src="' . htmlspecialchars($url) . '"' . $defer . $async . '></script>' . "\n";
            }
            return '';
        }
        
        if ($type === 'favicon' || $type === 'icon') {
            $rel = $type === 'favicon' ? 'icon' : 'icon';
            echo '<link rel="' . $rel . '" href="' . htmlspecialchars($url) . '" type="' . htmlspecialchars($mimeType) . '">' . "\n";
            return '';
        }
        
        if (!empty($attributes)) {
            return '';
        }
        
        return $url;
    }

    /**
     * 根据文件后缀检测资源类型
     * @param string $ext 文件后缀
     * @return string|null
     */
    private static function detectAssetType(string $ext): ?string
    {
        $ext = strtolower($ext);
        
        $typeMap = [
            'css' => 'css',
            'js' => 'js',
            'ico' => 'favicon',
            'png' => 'icon',
            'jpg' => 'icon',
            'jpeg' => 'icon',
            'gif' => 'icon',
            'svg' => 'icon',
        ];
        
        return $typeMap[$ext] ?? null;
    }

    private static $assetsRegistered = false;

    /**
     * 注册主题静态资源路由
     * @return void
     */
    public static function registerAssets(): void
    {
        try {
            if (self::$assetsRegistered) {
                return;
            }
            
            if (self::$themeDir === null) {
                self::init();
            }
            
            $themeDir = self::getThemeDir();
            $assetsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'assets');
            
            if ($assetsDir === null) {
                self::$assetsRegistered = true;
                return;
            }
            
            self::scanAssetsDirectory($assetsDir, '');
            self::$assetsRegistered = true;
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 递归扫描 assets 目录并注册路由
     * @param string $dir 目录路径
     * @param string $prefix URL 前缀
     * @return void
     */
    private static function scanAssetsDirectory(string $dir, string $prefix): void
    {
        $items = Anon_Cms::scanDirectory($dir);
        if ($items === null) {
            return;
        }
        
        $typeDirs = self::getTypeDirs();
        $mimeTypes = self::getMimeTypes();
        
        foreach ($items as $item) {
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($itemPath)) {
                self::scanAssetsDirectory($itemPath, $prefix . '/' . $item);
                continue;
            }
            
            if (!is_file($itemPath)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $typeDir = $typeDirs[$ext] ?? 'files';
            $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
            
            $fileName = pathinfo($item, PATHINFO_FILENAME);
            $urlPath = '/assets/' . $typeDir . '/' . $fileName;
            
            if (!empty($prefix)) {
                $urlPath = '/assets/' . $typeDir . $prefix . '/' . $fileName;
            }
            
            Anon_System_Config::addStaticRoute(
                $urlPath,
                $itemPath,
                $mimeType,
                31536000,
                false,
                [
                    'header' => false,
                    'requireLogin' => false,
                    'requireAdmin' => false,
                    'method' => 'GET',
                    'token' => false,
                ]
            );
        }
    }

    /**
     * 包含模板片段
     * @param string $partialName 片段名称
     * @param array $data 数据
     * @return void
     */
    public static function partial(string $partialName, array $data = []): void
    {
        $themeDir = self::getThemeDir();
        $partialsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'partials');
        
        if ($partialsDir === null) {
            throw new RuntimeException("模板片段目录未找到: partials");
        }
        
        $partialPath = Anon_Cms::findFileCaseInsensitive($partialsDir, $partialName);
        
        if ($partialPath === null) {
            throw new RuntimeException("模板片段未找到: {$partialName}");
        }
        
        extract($data, EXTR_SKIP);
        include $partialPath;
    }

    /**
     * 导入组件
     * @param string $componentPath 组件路径
     * @param array $data 数据
     * @return void
     */
    public static function components(string $componentPath, array $data = []): void
    {
        try {
            $componentPath = str_replace(['.', '/'], DIRECTORY_SEPARATOR, $componentPath);
            
            $themeDir = self::getThemeDir();
            $componentsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'components');
            
            if ($componentsDir === null) {
                self::outputComponentError("组件目录未找到: components");
                return;
            }
            
            $pathParts = explode(DIRECTORY_SEPARATOR, $componentPath);
            $componentName = array_pop($pathParts);
            $componentDir = $componentsDir;
            
            foreach ($pathParts as $part) {
                $foundDir = Anon_Cms::findDirectoryCaseInsensitive($componentDir, $part);
                if ($foundDir === null) {
                    self::outputComponentError("组件目录未找到: {$componentPath}");
                    return;
                }
                $componentDir = $foundDir;
            }
            
            $componentFile = Anon_Cms::findFileCaseInsensitive($componentDir, $componentName);
            
            if ($componentFile !== null) {
                extract($data, EXTR_SKIP);
                include $componentFile;
                return;
            }
            
            self::outputComponentError("组件未找到: {$componentPath}");
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                self::outputComponentError($e->getMessage());
            }
        }
    }

    /**
     * 输出页面标题
     * @param string|null $title 页面标题
     * @param string $separator 分隔符
     * @param bool $reverse 是否反转顺序
     * @return void
     */
    public static function title(?string $title = null, string $separator = ' - ', bool $reverse = false): void
    {
        $siteTitle = Anon_Cms_Options::get('title', '');
        
        if ($title === null) {
            $title = $siteTitle;
        } else {
            if ($reverse) {
                $title = $siteTitle . $separator . $title;
            } else {
                $title = $title . $separator . $siteTitle;
            }
        }
        
        echo '<title>' . htmlspecialchars($title) . '</title>' . "\n";
    }

    /**
     * 输出完整的 HTML head 标签
     * @param array $options 选项
     * @return void
     */
    public static function head(array $options = []): void
    {
        $title = $options['title'] ?? null;
        $description = $options['description'] ?? '';
        $keywords = $options['keywords'] ?? '';
        $author = $options['author'] ?? Anon_Cms_Options::get('author', '');
        $robots = $options['robots'] ?? 'index, follow';
        $canonical = $options['canonical'] ?? '';
        $og = $options['og'] ?? [];
        $twitter = $options['twitter'] ?? [];
        $charset = $options['charset'] ?? 'UTF-8';
        $viewport = $options['viewport'] ?? 'width=device-width, initial-scale=1.0';
        $lang = $options['lang'] ?? 'zh-CN';
        
        echo '<meta charset="' . htmlspecialchars($charset) . '">' . "\n";
        echo '<meta name="viewport" content="' . htmlspecialchars($viewport) . '">' . "\n";
        
        self::title($title);
        
        if (!empty($description)) {
            echo '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
        }
        
        if (!empty($keywords)) {
            if (is_array($keywords)) {
                $keywords = implode(', ', $keywords);
            }
            echo '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
        }
        
        if (!empty($author)) {
            echo '<meta name="author" content="' . htmlspecialchars($author) . '">' . "\n";
        }
        
        if (!empty($robots)) {
            echo '<meta name="robots" content="' . htmlspecialchars($robots) . '">' . "\n";
        }
        
        if (!empty($canonical)) {
            echo '<link rel="canonical" href="' . htmlspecialchars($canonical) . '">' . "\n";
        }
        
        if (!empty($og)) {
            foreach ($og as $key => $value) {
                if (!empty($value)) {
                    echo '<meta property="og:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n";
                }
            }
        }
        
        if (!empty($twitter)) {
            foreach ($twitter as $key => $value) {
                if (!empty($value)) {
                    echo '<meta name="twitter:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n";
                }
            }
        }
    }

    /**
     * 输出页面 head meta 标签
     * 自动从 Anon_Cms_PageMeta 获取 SEO 信息并输出
     * @param array $overrides 覆盖值
     * @return void
     */
    public static function headMeta(array $overrides = []): void
    {
        try {
            $seo = Anon_Cms_PageMeta::getSeo($overrides);
            
            if (!empty($seo['title'])) {
                self::title($seo['title']);
            } else {
                self::title();
            }
            
            self::meta($seo);
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 输出 SEO meta 标签
     * @param array $meta Meta 数据
     * @return void
     */
    public static function meta(array $meta = []): void
    {
        if (isset($meta['description']) && !empty($meta['description'])) {
            echo '<meta name="description" content="' . htmlspecialchars($meta['description']) . '">' . "\n";
        }
        
        if (isset($meta['keywords']) && !empty($meta['keywords'])) {
            $keywords = $meta['keywords'];
            if (is_array($keywords)) {
                $keywords = implode(', ', $keywords);
            }
            echo '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
        }
        
        if (isset($meta['author']) && !empty($meta['author'])) {
            echo '<meta name="author" content="' . htmlspecialchars($meta['author']) . '">' . "\n";
        }
        
        if (isset($meta['robots']) && !empty($meta['robots'])) {
            echo '<meta name="robots" content="' . htmlspecialchars($meta['robots']) . '">' . "\n";
        }
        
        if (isset($meta['canonical']) && !empty($meta['canonical'])) {
            echo '<link rel="canonical" href="' . htmlspecialchars($meta['canonical']) . '">' . "\n";
        }
        
        if (isset($meta['og']) && is_array($meta['og']) && !empty($meta['og'])) {
            foreach ($meta['og'] as $key => $value) {
                if (!empty($value)) {
                    echo '<meta property="og:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n";
                }
            }
        }
        
        if (isset($meta['twitter']) && is_array($meta['twitter']) && !empty($meta['twitter'])) {
            foreach ($meta['twitter'] as $key => $value) {
                if (!empty($value)) {
                    echo '<meta name="twitter:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n";
                }
            }
        }
    }

    /**
     * 输出样式表链接
     * @param string|array $styles 样式文件路径
     * @param array $attributes 额外属性
     * @return void
     */
    public static function stylesheet($styles, array $attributes = []): void
    {
        if (is_string($styles)) {
            $styles = [$styles];
        }
        
        foreach ($styles as $style) {
            $url = self::assets($style, 'css', $attributes);
            if (empty($url)) {
                continue;
            }
            
            $rel = $attributes['rel'] ?? 'stylesheet';
            $media = isset($attributes['media']) ? ' media="' . htmlspecialchars($attributes['media']) . '"' : '';
            echo '<link rel="' . htmlspecialchars($rel) . '" href="' . htmlspecialchars($url) . '"' . $media . '>' . "\n";
        }
    }

    /**
     * 输出脚本标签
     * @param string|array $scripts 脚本文件路径
     * @param array $attributes 额外属性
     * @return void
     */
    public static function script($scripts, array $attributes = []): void
    {
        if (is_string($scripts)) {
            $scripts = [$scripts];
        }
        
        foreach ($scripts as $script) {
            $url = self::assets($script, 'js', $attributes);
            if (empty($url)) {
                continue;
            }
            
            $defer = isset($attributes['defer']) ? ' defer' : '';
            $async = isset($attributes['async']) ? ' async' : '';
            echo '<script src="' . htmlspecialchars($url) . '"' . $defer . $async . '></script>' . "\n";
        }
    }

    /**
     * 输出 favicon 链接
     * @param string $path Favicon 路径
     * @return void
     */
    public static function favicon(string $path = 'favicon.ico'): void
    {
        self::assets($path, 'favicon');
    }

    /**
     * 获取当前主题名称
     * @return string
     */
    public static function getCurrentTheme(): string
    {
        try {
            if (!self::$initialized) {
                self::init();
            }
            return self::$currentTheme;
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 获取所有可用主题列表
     * @return array
     */
    public static function getAllThemes(): array
    {
        $themesDir = Anon_Main::APP_DIR . 'Theme/';
        $themes = [];
        
        if (!is_dir($themesDir)) {
            return $themes;
        }
        
        $items = Anon_Cms::scanDirectory($themesDir);
        if ($items === null) {
            return $themes;
        }
        
        foreach ($items as $item) {
            $themePath = $themesDir . $item;
            if (!is_dir($themePath)) {
                continue;
            }
            
            $infoFile = null;
            $themeItems = Anon_Cms::scanDirectory($themePath);
            if ($themeItems !== null) {
                foreach ($themeItems as $themeItem) {
                    if (strtolower($themeItem) === 'info.json') {
                        $infoFile = $themePath . DIRECTORY_SEPARATOR . $themeItem;
                        break;
                    }
                }
            }
            
            $themeInfo = [];
            if ($infoFile && file_exists($infoFile)) {
                $jsonContent = file_get_contents($infoFile);
                if ($jsonContent !== false) {
                    $decoded = json_decode($jsonContent, true);
                    if (is_array($decoded)) {
                        $themeInfo = $decoded;
                    }
                }
            }
            
            $screenshot = '';
            if (!empty($themeInfo['screenshot'])) {
                $screenshotFile = $themePath . DIRECTORY_SEPARATOR . $themeInfo['screenshot'];
                if (file_exists($screenshotFile)) {
                    $screenshot = '/anon/static/cms/theme/' . $item . '/screenshot';
                }
            }
            
            $themes[] = [
                'name' => $item,
                'displayName' => $themeInfo['name'] ?? $item,
                'description' => $themeInfo['description'] ?? '',
                'author' => $themeInfo['author'] ?? '',
                'version' => $themeInfo['version'] ?? '',
                'url' => $themeInfo['url'] ?? '',
                'screenshot' => $screenshot,
            ];
        }
        
        return $themes;
    }

    /**
     * 获取主题信息
     * @param string|null $key 信息键名
     * @return mixed
     */
    public static function info(?string $key = null)
    {
        if (!self::$initialized) {
            self::init();
        }
        
        $cacheKey = self::$currentTheme;
        
        if (!isset(self::$themeInfoCache[$cacheKey])) {
            $themeDir = self::getThemeDir();
            $infoFile = Anon_Cms::findFileCaseInsensitive($themeDir, 'info');
            
            $themeInfo = [];
            if ($infoFile !== null && file_exists($infoFile)) {
                $jsonContent = file_get_contents($infoFile);
                if ($jsonContent !== false) {
                    $decoded = json_decode($jsonContent, true);
                    if (is_array($decoded)) {
                        $themeInfo = $decoded;
                    }
                }
            }
            
            self::$themeInfoCache[$cacheKey] = $themeInfo;
        }
        
        $themeInfo = self::$themeInfoCache[$cacheKey];
        
        if ($key === null) {
            return $themeInfo;
        }
        
        return $themeInfo[$key] ?? null;
    }

    /**
     * 转义 HTML 输出
     * @param string $text 文本
     * @param int $flags 转义标志
     * @return string
     */
    public static function escape(string $text, int $flags = ENT_QUOTES): string
    {
        return htmlspecialchars($text, $flags, 'UTF-8');
    }

    /**
     * 输出 JSON-LD 结构化数据
     * @param array $data 结构化数据数组
     * @return void
     */
    public static function jsonLd(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * 输出页面底部 meta 信息
     * 触发 theme_foot 钩子，执行所有注册的回调函数
     * @return void
     */
    public static function footMeta(): void
    {
        try {
            Anon_System_Hook::do_action('theme_foot');
        } catch (Error $e) {
            self::handleFatalError($e);
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 注册主题设置项
     * @param string $key 设置项键名
     * @param array $args 设置项参数
     * @return void
     */
    public static function registerSetting(string $key, array $args = []): void
    {
        $themeName = self::getCurrentTheme();
        $optionName = "theme:{$themeName}";
        
        // 获取当前主题设置
        $settings = Anon_Cms_Options::get($optionName, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // 合并默认参数
        $defaultArgs = [
            'type' => 'text',
            'label' => $key,
            'description' => '',
            'default' => '',
            'sanitize_callback' => null,
            'validate_callback' => null,
        ];
        
        $args = array_merge($defaultArgs, $args);
        
        // 如果设置项不存在，使用默认值
        if (!isset($settings[$key])) {
            $settings[$key] = $args['default'];
        }
        
        // 保存设置项定义（用于后续验证和显示）
        $registeredSettings = Anon_Cms_Options::get("theme:{$themeName}:settings", []);
        if (!is_array($registeredSettings)) {
            $registeredSettings = [];
        }
        $registeredSettings[$key] = $args;
        Anon_Cms_Options::set("theme:{$themeName}:settings", $registeredSettings);
        
        // 保存设置值
        Anon_Cms_Options::set($optionName, $settings);
    }

    /**
     * 获取主题设置值
     * @param string $key 设置项键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getSetting(string $key, $default = null)
    {
        $themeName = self::getCurrentTheme();
        $optionName = "theme:{$themeName}";
        
        $settings = Anon_Cms_Options::get($optionName, []);
        if (!is_array($settings)) {
            return $default;
        }
        
        return $settings[$key] ?? $default;
    }

    /**
     * 设置主题设置值
     * @param string $key 设置项键名
     * @param mixed $value 设置值
     * @return bool
     */
    public static function setSetting(string $key, $value): bool
    {
        $themeName = self::getCurrentTheme();
        $optionName = "theme:{$themeName}";
        
        $settings = Anon_Cms_Options::get($optionName, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // 获取设置项定义进行验证
        $registeredSettings = Anon_Cms_Options::get("theme:{$themeName}:settings", []);
        if (isset($registeredSettings[$key])) {
            $settingDef = $registeredSettings[$key];
            
            // 执行验证回调
            if (isset($settingDef['validate_callback']) && is_callable($settingDef['validate_callback'])) {
                $valid = call_user_func($settingDef['validate_callback'], $value);
                if ($valid === false) {
                    return false;
                }
            }
            
            // 执行清理回调
            if (isset($settingDef['sanitize_callback']) && is_callable($settingDef['sanitize_callback'])) {
                $value = call_user_func($settingDef['sanitize_callback'], $value);
            }
        }
        
        $settings[$key] = $value;
        return Anon_Cms_Options::set($optionName, $settings);
    }

    /**
     * 获取所有主题设置
     * @return array
     */
    public static function getAllSettings(): array
    {
        $themeName = self::getCurrentTheme();
        $optionName = "theme:{$themeName}";
        
        $settings = Anon_Cms_Options::get($optionName, []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * 获取所有已注册的主题设置项定义
     * @return array
     */
    public static function getRegisteredSettings(): array
    {
        $themeName = self::getCurrentTheme();
        $registeredSettings = Anon_Cms_Options::get("theme:{$themeName}:settings", []);
        return is_array($registeredSettings) ? $registeredSettings : [];
    }

    /**
     * 判断是否是严重错误
     * @param Throwable $e 异常
     * @return bool
     */
    private static function isFatalError(Throwable $e): bool
    {
        $fatalErrors = [
            'Error',
            'ParseError',
            'TypeError',
            'ArgumentCountError',
            'FatalError',
        ];
        
        $className = get_class($e);
        foreach ($fatalErrors as $fatalError) {
            if ($className === $fatalError || strpos($className, $fatalError) !== false) {
                return true;
            }
        }
        
        $message = $e->getMessage();
        $fatalPatterns = [
            'Call to undefined method',
            'Call to undefined function',
            'Class \'[^\']+\' not found',
            'Undefined class constant',
            'Undefined property',
        ];
        
        foreach ($fatalPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $message)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 处理严重错误
     * @param Throwable $e 异常
     * @return void
     */
    private static function handleFatalError(Throwable $e): void
    {
        if (class_exists('Anon_Cms_Theme_FatalError')) {
            Anon_Cms_Theme_FatalError::render(
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                get_class($e)
            );
        } else {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>严重错误</title></head><body>';
            echo '<h1>站点遇到严重错误</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</body></html>';
            exit;
        }
    }

    /**
     * 输出组件错误信息
     * @param string $message 错误消息
     * @return void
     */
    private static function outputComponentError(string $message): void
    {
        $showDetails = defined('ANON_DEBUG') && ANON_DEBUG;
        
        echo '<div style="';
        echo 'background: #fff3cd;';
        echo 'border: 1px solid #ffc107;';
        echo 'border-left: 4px solid #ff9800;';
        echo 'padding: 15px;';
        echo 'margin: 10px 0;';
        echo 'border-radius: 4px;';
        echo 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';
        echo '">';
        echo '<strong style="color: #856404;">组件错误：</strong>';
        echo '<span style="color: #856404;">' . htmlspecialchars($message) . '</span>';
        if ($showDetails) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if (isset($backtrace[1])) {
                $caller = $backtrace[1];
                $file = $caller['file'] ?? '';
                $line = $caller['line'] ?? 0;
                echo '<br><small style="color: #856404; margin-top: 5px; display: block;">';
                echo '调用位置：' . htmlspecialchars($file) . ':' . $line;
                echo '</small>';
            }
        }
        echo '</div>';
    }

}

