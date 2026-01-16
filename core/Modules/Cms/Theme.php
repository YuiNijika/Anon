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
        if (!self::$initialized || self::$themeDir === null) {
            self::init();
        }
        return self::$themeDir;
    }

    /**
     * 渲染模板文件
     * @param string $templateName 模板名称
     * @param array $data 数据
     * @return void
     */
    public static function render(string $templateName, array $data = []): void
    {
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
        include $templatePath;
        $content = ob_get_clean();

        echo $content;
    }

    /**
     * 查找模板文件
     * @param string $templateName 模板名称
     * @return string|null
     */
    private static function findTemplate(string $templateName): ?string
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
        
        if (strpos($path, 'assets/') === 0) {
            $path = substr($path, 7);
        }
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $typeDirs = self::getTypeDirs();
        
        if ($ext && isset($typeDirs[$ext])) {
            $url = "/assets/" . $typeDirs[$ext] . "/{$fileName}";
        } else {
            $url = "/assets/{$path}";
        }
        
        if ($type === null) {
            $type = self::detectAssetType($ext);
        }
        
        if ($type === null) {
            return $url;
        }
        
        $type = strtolower($type);
        $attrs = self::buildAttributes($attributes);
        
        switch ($type) {
            case 'css':
                echo '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>' . "\n";
                break;
            case 'js':
                echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '></script>' . "\n";
                break;
            case 'favicon':
            case 'icon':
                echo '<link rel="icon" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>' . "\n";
                break;
            default:
                return $url;
        }
        
        return '';
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
    }

    /**
     * 递归扫描 assets 目录并注册路由
     * @param string $dir 目录路径
     * @param string $basePath 基础路径
     * @return void
     */
    private static function scanAssetsDirectory(string $dir, string $basePath): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = Anon_Cms::scanDirectory($dir);
        if ($items === null) {
            return;
        }
        
        $themeDir = self::getThemeDir();
        $assetsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'assets');
        
        if ($assetsDir === null) {
            return;
        }
        
        $realAssetsDir = realpath($assetsDir);
        
        if (!$realAssetsDir) {
            return;
        }
        
        foreach ($items as $item) {
            
            $itemPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($itemPath)) {
                $newBasePath = $basePath ? $basePath . '/' . $item : $item;
                self::scanAssetsDirectory($itemPath, $newBasePath);
            } elseif (is_file($itemPath)) {
                $realItemPath = realpath($itemPath);
                if (!$realItemPath) {
                    continue;
                }
                
                $relativePath = str_replace($realAssetsDir . DIRECTORY_SEPARATOR, '', $realItemPath);
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                $mimeTypes = self::getMimeTypes();
                $typeDirs = self::getTypeDirs();
                
                $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
                $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
                $typeDir = $typeDirs[$ext] ?? 'files';
                
                $assetRoute = "/assets/{$typeDir}/{$fileName}";
                Anon_System_Config::addStaticRoute($assetRoute, $realItemPath, $mimeType, 31536000, true, ['token' => false]);
            }
        }
    }

    /**
     * 获取主题资源 URL
     * @param string $path 资源路径
     * @return string
     * @deprecated 使用 assets() 方法替代
     */
    public static function asset(string $path): string
    {
        return self::assets($path);
    }

    /**
     * 获取主题信息
     * @param string|null $key 信息键名
     * @return mixed
     */
    public static function info(?string $key = null)
    {
        if (self::$themeDir === null) {
            self::init();
        }
        
        $themeName = self::$currentTheme;
        
        if (isset(self::$themeInfoCache[$themeName])) {
            $info = self::$themeInfoCache[$themeName];
        } else {
            $themeDir = self::getThemeDir();
            $infoFile = $themeDir . 'Info.json';
            
            if (!file_exists($infoFile)) {
                $items = Anon_Cms::scanDirectory($themeDir);
                if ($items !== null) {
                    foreach ($items as $item) {
                        if (strtolower($item) === 'info.json') {
                            $infoFile = $themeDir . $item;
                            break;
                        }
                    }
                }
            }
            
            $info = [];
            if (file_exists($infoFile)) {
                $jsonContent = file_get_contents($infoFile);
                if ($jsonContent !== false) {
                    $decoded = json_decode($jsonContent, true);
                    if (is_array($decoded)) {
                        $info = $decoded;
                    }
                }
            }
            
            self::$themeInfoCache[$themeName] = $info;
        }
        
        if ($key !== null) {
            return $info[$key] ?? null;
        }
        
        return $info;
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
            return;
        }
        
        $partialPath = Anon_Cms::findFileCaseInsensitive($partialsDir, $partialName, ['php']);
        
        if ($partialPath !== null) {
            extract($data, EXTR_SKIP);
            include $partialPath;
        }
    }


    /**
     * 获取当前主题名称
     * @return string
     */
    public static function getCurrentTheme(): string
    {
        return self::$currentTheme;
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
                $screenshotFileName = $themeInfo['screenshot'];
                $screenshotFilePath = $themePath . DIRECTORY_SEPARATOR . $screenshotFileName;
                
                if (!file_exists($screenshotFilePath)) {
                    $screenshotFilePath = Anon_Cms::findFileCaseInsensitive($themePath, pathinfo($screenshotFileName, PATHINFO_FILENAME), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']);
                }
                
                if ($screenshotFilePath && file_exists($screenshotFilePath)) {
                    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $screenshot = "{$scheme}://{$host}/anon/static/cms/theme/{$item}/screenshot";
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
     * 导入组件
     * @param string $componentPath 组件路径
     * @param array $data 数据
     * @return void
     */
    public static function components(string $componentPath, array $data = []): void
    {
        $componentPath = preg_replace('/^components[.\/\\\\]/i', '', $componentPath);
        self::import($componentPath, $data);
    }

    /**
     * 导入组件
     * @param string $componentPath 组件路径
     * @param array $data 数据
     * @return void
     */
    private static function import(string $componentPath, array $data = []): void
    {
        $componentPath = str_replace(['.', '/'], DIRECTORY_SEPARATOR, $componentPath);
        
        $themeDir = self::getThemeDir();
        $componentsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'components');
        
        if ($componentsDir === null) {
            throw new RuntimeException("组件目录未找到: components");
        }
        
        $pathParts = explode(DIRECTORY_SEPARATOR, $componentPath);
        $componentName = array_pop($pathParts);
        $componentDir = $componentsDir;
        
        foreach ($pathParts as $part) {
            $foundDir = Anon_Cms::findDirectoryCaseInsensitive($componentDir, $part);
            if ($foundDir === null) {
                throw new RuntimeException("组件目录未找到: {$componentPath}");
            }
            $componentDir = $foundDir;
        }
        
        $componentFile = Anon_Cms::findFileCaseInsensitive($componentDir, $componentName);
        
        if ($componentFile !== null) {
            extract($data, EXTR_SKIP);
            include $componentFile;
            return;
        }
        
        throw new RuntimeException("组件未找到: {$componentPath}");
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
        $siteName = Anon_System_Env::get('app.name', Anon_Common::NAME);
        
        if ($title === null) {
            $title = $siteName;
        } else {
            if ($reverse) {
                $title = $siteName . $separator . $title;
            } else {
                $title = $title . $separator . $siteName;
            }
        }
        
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
    }

    /**
     * 输出 SEO meta 标签
     * @param array $meta SEO 信息数组
     * @return void
     */
    public static function meta(array $meta = []): void
    {
        if (isset($meta['description'])) {
            echo '<meta name="description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        
        if (isset($meta['keywords'])) {
            $keywords = is_array($meta['keywords']) ? implode(', ', $meta['keywords']) : $meta['keywords'];
            echo '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        
        if (isset($meta['author'])) {
            echo '<meta name="author" content="' . htmlspecialchars($meta['author'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        
        if (isset($meta['robots'])) {
            echo '<meta name="robots" content="' . htmlspecialchars($meta['robots'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        
        if (isset($meta['canonical'])) {
            echo '<link rel="canonical" href="' . htmlspecialchars($meta['canonical'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        
        if (isset($meta['og']) && is_array($meta['og'])) {
            foreach ($meta['og'] as $property => $content) {
                echo '<meta property="og:' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }
        
        if (isset($meta['twitter']) && is_array($meta['twitter'])) {
            foreach ($meta['twitter'] as $name => $content) {
                echo '<meta name="twitter:' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }
    }

    /**
     * 输出完整的 SEO head 标签
     * @param array $options SEO 选项数组
     * @return void
     */
    public static function head(array $options = []): void
    {
        $defaults = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'author' => null,
            'robots' => 'index, follow',
            'canonical' => null,
            'og' => [],
            'twitter' => [],
            'charset' => 'UTF-8',
            'viewport' => 'width=device-width, initial-scale=1.0',
            'lang' => 'zh-CN',
        ];
        
        $options = array_merge($defaults, $options);
        
        echo '<meta charset="' . htmlspecialchars($options['charset'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="viewport" content="' . htmlspecialchars($options['viewport'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        
        self::title($options['title']);
        
        $meta = [];
        if ($options['description'] !== null) {
            $meta['description'] = $options['description'];
        }
        if ($options['keywords'] !== null) {
            $meta['keywords'] = $options['keywords'];
        }
        if ($options['author'] !== null) {
            $meta['author'] = $options['author'];
        }
        if ($options['robots'] !== null) {
            $meta['robots'] = $options['robots'];
        }
        if ($options['canonical'] !== null) {
            $meta['canonical'] = $options['canonical'];
        }
        if (!empty($options['og'])) {
            $meta['og'] = $options['og'];
        }
        if (!empty($options['twitter'])) {
            $meta['twitter'] = $options['twitter'];
        }
        
        if (!empty($meta)) {
            self::meta($meta);
        }
    }

    /**
     * 构建 HTML 属性字符串
     * @param array $attributes 属性数组
     * @return string
     */
    private static function buildAttributes(array $attributes): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $attrs;
    }

    /**
     * 输出样式表链接
     * @param string|array $styles 样式文件路径
     * @param array $attributes 额外属性
     * @return void
     * @deprecated 使用 assets() 方法替代
     */
    public static function stylesheet($styles, array $attributes = []): void
    {
        if (is_string($styles)) {
            $styles = [$styles];
        }
        
        foreach ($styles as $style) {
            if (strpos($style, 'http') === 0 || strpos($style, '//') === 0) {
                $attrs = self::buildAttributes($attributes);
                echo '<link rel="stylesheet" href="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>' . "\n";
            } else {
                self::assets($style, null, $attributes);
            }
        }
    }

    /**
     * 输出脚本标签
     * @param string|array $scripts 脚本文件路径
     * @param array $attributes 额外属性
     * @return void
     * @deprecated 使用 assets() 方法替代
     */
    public static function script($scripts, array $attributes = []): void
    {
        if (is_string($scripts)) {
            $scripts = [$scripts];
        }
        
        foreach ($scripts as $script) {
            if (strpos($script, 'http') === 0 || strpos($script, '//') === 0) {
                $attrs = self::buildAttributes($attributes);
                echo '<script src="' . htmlspecialchars($script, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '></script>' . "\n";
            } else {
                self::assets($script, null, $attributes);
            }
        }
    }

    /**
     * 输出 favicon 链接
     * @param string $path favicon 路径
     * @param array $attributes 额外属性
     * @return void
     * @deprecated 使用 assets() 方法替代
     */
    public static function favicon(string $path = 'favicon.ico', array $attributes = []): void
    {
        self::assets($path, null, $attributes);
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
}


