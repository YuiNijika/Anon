<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Theme
{
    private static $currentTheme = 'default';
    private static $themeDir = null;
    private static $themeDirCache = null;
    private static $templateCache = [];
    private static $themeInfoCache = [];
    private static $mimeTypesCache = null;
    private static $typeDirsCache = null;
    private static $allThemesCache = null;

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
     * 获取文件类型到目录映射
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
     * @param string|null $themeName 主题名
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
            
            self::loadThemeCode();
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
     * 加载主题 app/code.php 与 app/setup.php，分别为自定义代码与主题设置项
     * @return void
     */
    private static function loadThemeCode(): void
    {
        try {
            $codeFile = self::$themeDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'code.php';
            if (Anon_Cms::fileExists($codeFile)) {
                require_once $codeFile;
            }
            self::loadThemeSetupFile(self::$themeDir);
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
     * 从主题目录 app/setup.php 读取设置定义，按分组树形返回
     *
     * @param string $themeName 主题名
     * @return array 树形 [ 分组名 => [ 选项名 => [ type, label, default, ... ] ], ... ]，无文件或非数组时返回空数组
     */
    public static function getSchemaFromSetupFile(string $themeName): array
    {
        $themeDir = self::findThemeDirectory($themeName);
        if ($themeDir === null) {
            return [];
        }
        $setupFile = rtrim($themeDir, '/\\') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'setup.php';
        if (!file_exists($setupFile)) {
            return [];
        }
        if (!defined('ANON_ALLOWED_ACCESS')) {
            define('ANON_ALLOWED_ACCESS', true);
        }
        $raw = include $setupFile;
        if (!is_array($raw)) {
            return [];
        }
        $defaultArgs = ['type' => 'text', 'label' => '', 'description' => '', 'default' => null];
        $tree = [];
        foreach ($raw as $group => $items) {
            if (!is_array($items)) {
                continue;
            }
            $tree[$group] = [];
            foreach ($items as $key => $args) {
                $args = is_array($args) ? $args : [];
                $tree[$group][(string) $key] = array_merge($defaultArgs, $args);
            }
        }
        return $tree;
    }

    /**
     * 从主题目录 app/setup.php 加载设置项并写入数据库
     *
     * setup.php 需 return 数组，格式为 tab => [ 选项名 => 参数 ]。用于兼容旧逻辑或预填默认值。
     *
     * @param string $themeDir 主题根目录
     * @param string|null $themeName 主题名，空则用目录名
     * @return void
     */
    private static function loadThemeSetupFile(string $themeDir, ?string $themeName = null): void
    {
        $themeDirResolved = realpath(rtrim($themeDir, '/\\'));
        if ($themeDirResolved === false) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Theme] loadThemeSetupFile: themeDir not found: ' . $themeDir);
            }
            return;
        }
        $setupFile = $themeDirResolved . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'setup.php';
        if (!file_exists($setupFile)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Theme] loadThemeSetupFile: setup.php not found: ' . $setupFile);
            }
            return;
        }
        if (!defined('ANON_ALLOWED_ACCESS')) {
            define('ANON_ALLOWED_ACCESS', true);
        }
        $schema = include $setupFile;
        if (!is_array($schema)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Theme] loadThemeSetupFile: setup.php did not return array, got: ' . gettype($schema));
            }
            return;
        }
        $name = $themeName !== null && $themeName !== '' ? $themeName : basename($themeDirResolved);
        Anon_Theme_Options::registerFromSchema($schema, strtolower($name));
        Anon_Cms_Options::clearCache();
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log('[Theme] loadThemeSetupFile: registered schema for theme ' . $name . ', keys: ' . count($schema));
        }
    }

    /**
     * 获取 app 目录绝对路径，不依赖 CWD，基于当前文件位置
     * Theme.php 在 server/core/Modules/Cms/Theme/，上溯 4 级为 server，app 为 server/app
     * @return string
     */
    private static function getAppDirAbsolute(): string
    {
        $serverDir = dirname(__DIR__, 4);
        $appDir = $serverDir . DIRECTORY_SEPARATOR . 'app';
        $resolved = realpath($appDir);
        return $resolved !== false ? $resolved : rtrim($appDir, DIRECTORY_SEPARATOR);
    }

    /**
     * 查找主题目录并解析为绝对路径
     *
     * 使用基于 Theme 文件位置的 app 绝对路径，避免 CWD 导致找不到主题目录。
     *
     * @param string $themeName 主题名
     * @return string|null 主题目录绝对路径，未找到为 null
     */
    private static function findThemeDirectory(string $themeName): ?string
    {
        $themesBase = self::getAppDirAbsolute() . DIRECTORY_SEPARATOR . 'Theme';
        $found = Anon_Cms::findDirectoryCaseInsensitive($themesBase . DIRECTORY_SEPARATOR, $themeName);
        if ($found === null) {
            return null;
        }
        $resolved = realpath(rtrim($found, '/\\'));
        return $resolved !== false ? $resolved . DIRECTORY_SEPARATOR : null;
    }

    /**
     * 确保指定主题的设置项已加载
     *
     * 从主题目录 app/setup.php 读取并注册；存储 key 为主题名小写，主题名不区分大小写。
     *
     * @param string $themeName 主题名，任意大小写
     * @return void
     */
    public static function ensureThemeOptionsLoaded(string $themeName): void
    {
        if ($themeName === '' || $themeName === null) {
            return;
        }
        $themeDir = self::findThemeDirectory($themeName);
        if ($themeDir === null) {
            return;
        }
        $keyName = strtolower(basename(rtrim($themeDir, '/\\')));
        
        // 检查 options 表中是否存在该主题的值
        // 使用一个特殊对象作为默认值来判断是否存在
        $missing = new stdClass();
        $val = Anon_Cms_Options::get("theme:{$keyName}", $missing);
        
        if ($val !== $missing) {
            return;
        }
        
        self::loadThemeSetupFile($themeDir, $keyName);
    }

    /**
     * 获取用于接口的主题名，小写，与 options 存储一致
     *
     * @param string $themeName 请求或配置中的主题名，任意大小写
     * @return string 小写主题名，未找到目录则返回原值小写
     */
    public static function getCanonicalThemeName(string $themeName): string
    {
        if ($themeName === '' || $themeName === null) {
            return strtolower((string) $themeName);
        }
        $themeDir = self::findThemeDirectory($themeName);
        if ($themeDir === null) {
            return strtolower($themeName);
        }
        return strtolower(basename(rtrim($themeDir, '/\\')));
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
     * 渲染模板
     * @param string $templateName 模板名
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
            $view = new Anon_Cms_Theme_View($data);

            $isErrorTemplate = (strtolower($templateName) === 'error');
            if ($isErrorTemplate) {
                $GLOBALS['_anon_rendering_error_page'] = true;
            }
            ob_start();
            try {
                $view->render($templatePath);
            } catch (Error $e) {
                ob_end_clean();
                if ($isErrorTemplate) {
                    $GLOBALS['_anon_rendering_error_page'] = false;
                }
                self::handleFatalError($e);
            } catch (Throwable $e) {
                ob_end_clean();
                if ($isErrorTemplate) {
                    $GLOBALS['_anon_rendering_error_page'] = false;
                }
                if (self::isFatalError($e)) {
                    self::handleFatalError($e);
                } else {
                    throw $e;
                }
            }
            $content = ob_get_clean();
            if ($isErrorTemplate) {
                $GLOBALS['_anon_rendering_error_page'] = false;
            }

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
     * @param string $templateName 模板名
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
     * @param bool|string|null $forceNoCacheOrType true 表示强制禁止缓存，或传资源类型；不指定则按文件后缀自动判断
     * @param array $attributes 额外属性
     * @return string 如果只返回 URL，否则返回空字符串
     */
    public static function assets(string $path, $forceNoCacheOrType = null, array $attributes = []): string
    {
        $path = ltrim($path, '/');
        
        $forceNoCache = false;
        $type = null;
        
        if (is_bool($forceNoCacheOrType)) {
            $forceNoCache = $forceNoCacheOrType;
        } elseif (is_string($forceNoCacheOrType)) {
            $type = $forceNoCacheOrType;
        }
        
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
        
        // 添加缓存参数
        $cacheParam = self::getAssetCacheParam();
        if ($forceNoCache) {
            // 强制禁止缓存
            if ($cacheParam === '') {
                $cacheParam = '?nocache=1';
            } else {
                $cacheParam .= '&nocache=1';
            }
        }
        $urlWithCache = $url . $cacheParam;

        if ($type === 'css' || $type === 'js') {
            if ($type === 'css') {
                $rel = $attributes['rel'] ?? 'stylesheet';
                $media = isset($attributes['media']) ? ' media="' . htmlspecialchars($attributes['media']) . '"' : '';
                echo '<link rel="' . htmlspecialchars($rel) . '" href="' . htmlspecialchars($urlWithCache) . '"' . $media . '>' . "\n";
            } elseif ($type === 'js') {
                $defer = isset($attributes['defer']) ? ' defer' : '';
                $async = isset($attributes['async']) ? ' async' : '';
                echo '<script src="' . htmlspecialchars($urlWithCache) . '"' . $defer . $async . '></script>' . "\n";
            }
            return '';
        }
        
        if ($type === 'favicon' || $type === 'icon') {
            $rel = $type === 'favicon' ? 'icon' : 'icon';
            echo '<link rel="' . $rel . '" href="' . htmlspecialchars($urlWithCache) . '" type="' . htmlspecialchars($mimeType) . '">' . "\n";
            return '';
        }

        if (!empty($attributes)) {
            return '';
        }

        return $urlWithCache;
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
    private static $assetCacheVersion = null;
    private static $assetCacheMode = null;

    /**
     * 获取资源缓存参数
     * @param bool|null $dev 是否为开发模式，null 表示自动检测
     * @return string 缓存参数字符串
     */
    public static function getAssetCacheParam(?bool $dev = null): string
    {
        // 如果已设置缓存模式，使用已设置的值
        if (self::$assetCacheMode !== null) {
            $dev = self::$assetCacheMode === 'dev';
        } elseif ($dev === null) {
            $dev = Anon_Cms_Options::get('theme:dev_mode', false);
        }

        if ($dev) {
            return '?nocache=1';
        }

        if (self::$assetCacheVersion === null) {
            $themeVersion = self::info('version');
            self::$assetCacheVersion = ($themeVersion !== null && $themeVersion !== '') ? (string)$themeVersion : '1';
        }
        return '?ver=' . self::$assetCacheVersion;
    }

    /**
     * 设置资源缓存模式
     * @param string|null $mode 缓存模式，'dev' 表示开发模式，'prod' 表示生产模式，null 表示自动检测
     * @return void
     */
    public static function setAssetCacheMode(?string $mode): void
    {
        self::$assetCacheMode = $mode;
    }

    /**
     * 设置资源缓存版本号
     * @param string|null $version 版本号，null 表示使用默认版本号
     * @return void
     */
    public static function setAssetCacheVersion(?string $version): void
    {
        self::$assetCacheVersion = $version;
    }

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
            
            if (Anon_Cms::isDir($itemPath)) {
                self::scanAssetsDirectory($itemPath, $prefix . '/' . $item);
                continue;
            }
            
            if (!Anon_Cms::isFile($itemPath)) {
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
            $componentsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'app/components');
            
            if ($componentsDir === null) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log('[Anon Theme components] 静态调用: 组件目录未找到: themeDir=' . $themeDir . ', 查找 app/components');
                }
                self::outputComponentError("组件目录未找到: app/components");
                return;
            }
            
            $pathParts = explode(DIRECTORY_SEPARATOR, $componentPath);
            $componentName = array_pop($pathParts);
            $componentDir = $componentsDir;
            
            foreach ($pathParts as $part) {
                $foundDir = Anon_Cms::findDirectoryCaseInsensitive($componentDir, $part);
                if ($foundDir === null) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        error_log('[Anon Theme components] 静态调用: 组件子目录未找到: componentPath=' . $componentPath . ', part=' . $part . ', componentDir=' . $componentDir);
                    }
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
            
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Anon Theme components] 静态调用: 组件文件未找到: componentPath=' . $componentPath . ', componentName=' . $componentName . ', componentDir=' . $componentDir);
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
                echo '<title>' . htmlspecialchars($seo['title']) . '</title>' . "\n";
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
        if (self::$allThemesCache !== null) {
            return self::$allThemesCache;
        }
        
        $themesDir = Anon_Main::APP_DIR . 'Theme/';
        $themes = [];
        
        if (!Anon_Cms::isDir($themesDir)) {
            self::$allThemesCache = $themes;
            return $themes;
        }
        
        $items = Anon_Cms::scanDirectory($themesDir);
        if ($items === null) {
            self::$allThemesCache = $themes;
            return $themes;
        }
        
        foreach ($items as $item) {
            $themePath = $themesDir . $item;
            if (!Anon_Cms::isDir($themePath)) {
                continue;
            }
            
            $infoFile = null;
            $themeItems = Anon_Cms::scanDirectory($themePath);
            if ($themeItems !== null) {
                foreach ($themeItems as $themeItem) {
                    if (strtolower($themeItem) === 'package.json') {
                        $infoFile = $themePath . DIRECTORY_SEPARATOR . $themeItem;
                        break;
                    }
                }
            }

            $themeInfo = [];
            if ($infoFile && Anon_Cms::fileExists($infoFile)) {
                $jsonContent = file_get_contents($infoFile);
                if ($jsonContent !== false) {
                    $decoded = json_decode($jsonContent, true);
                    if (is_array($decoded)) {
                        if (isset($decoded['anon']) && is_array($decoded['anon'])) {
                            $themeInfo = array_merge($decoded, $decoded['anon']);
                        } else {
                            $themeInfo = $decoded;
                        }
                    }
                }
            }
            
            $screenshot = '';
            if (!empty($themeInfo['screenshot'])) {
                $screenshotFile = $themePath . DIRECTORY_SEPARATOR . $themeInfo['screenshot'];
                if (Anon_Cms::fileExists($screenshotFile)) {
                    $screenshot = '/anon/static/cms/theme/' . $item . '/screenshot';
                }
            }
            
            $themes[] = [
                'name' => $item,
                'displayName' => $themeInfo['displayName'] ?? ($themeInfo['name'] ?? $item),
                'description' => $themeInfo['description'] ?? '',
                'author' => $themeInfo['author'] ?? '',
                'version' => $themeInfo['version'] ?? '',
                'url' => $themeInfo['homepage'] ?? ($themeInfo['url'] ?? ''),
                'screenshot' => $screenshot,
            ];
        }
        
        self::$allThemesCache = $themes;
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
            $infoFile = Anon_Cms::findFileCaseInsensitive($themeDir, 'package', ['json', 'php', 'html', 'htm']);
            
            $themeInfo = [];
            if ($infoFile !== null && Anon_Cms::fileExists($infoFile)) {
                $jsonContent = file_get_contents($infoFile);
                if ($jsonContent !== false) {
                    $decoded = json_decode($jsonContent, true);
                    if (is_array($decoded)) {
                        if (isset($decoded['anon']) && is_array($decoded['anon'])) {
                            $themeInfo = array_merge($decoded, $decoded['anon']);
                        } else {
                            $themeInfo = $decoded;
                        }
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
     * 渲染 Markdown 内容为 HTML
     * 会自动移除内容开头的 <!--markdown--> 标记
     * @param string $content
     * @return string
     */
    public static function markdown(string $content): string
    {
        $content = ltrim($content);
        if (strpos($content, '<!--markdown-->') === 0) {
            $content = substr($content, strlen('<!--markdown-->'));
        }
        $content = ltrim($content);
        if ($content === '') {
            return '';
        }

        require_once Anon_Main::WIDGETS_DIR . 'Parsedown.php';

        $parser = new Parsedown();
        $parser->setSafeMode(true);
        $parser->setBreaksEnabled(true);

        return $parser->text($content);
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
        
        // 保存设置项定义
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

/**
 * 主题视图对象
 * 在模板中通过 $this 调用主题方法和读取渲染数据
 */
class Anon_Cms_Theme_View
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
        // 处理 null 和非字符串值，转换为空字符串
        if ($text === null) {
            return '';
        }
        return Anon_Cms_Theme::escape((string)$text, $flags);
    }

    /**
     * 渲染 Markdown
     * @param string $content
     * @return string
     */
    public function markdown(string $content): string
    {
        return Anon_Cms_Theme::markdown($content);
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

        $themeDir = Anon_Cms_Theme::getThemeDir();
        $componentsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'app/components');
        if ($componentsDir === null) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Anon Theme components] 组件目录未找到: themeDir=' . $themeDir . ', 查找 app/components');
            }
            return;
        }

        $pathParts = explode(DIRECTORY_SEPARATOR, $componentPath);
        $componentName = array_pop($pathParts);
        $componentDir = $componentsDir;

        foreach ($pathParts as $part) {
            $foundDir = Anon_Cms::findDirectoryCaseInsensitive($componentDir, $part);
            if ($foundDir === null) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log('[Anon Theme components] 组件子目录未找到: componentPath=' . $componentPath . ', part=' . $part . ', componentDir=' . $componentDir);
                }
                return;
            }
            $componentDir = $foundDir;
        }

        $componentFile = Anon_Cms::findFileCaseInsensitive($componentDir, $componentName);
        if ($componentFile === null) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log('[Anon Theme components] 组件文件未找到: componentPath=' . $componentPath . ', componentName=' . $componentName . ', componentDir=' . $componentDir);
            }
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
        return Anon_Cms_Theme::assets($path, $forceNoCacheOrType, $attributes);
    }

    /**
     * 输出页面头部 meta
     * @param array $overrides
     * @return void
     */
    public function headMeta(array $overrides = []): void
    {
        Anon_Cms_Theme::headMeta($overrides);
    }

    /**
     * 输出页面底部 meta
     * @return void
     */
    public function footMeta(): void
    {
        Anon_Cms_Theme::footMeta();
    }

    /**
     * 输出片段
     * @param string $partialName
     * @param array $data
     * @return void
     */
    public function partial(string $partialName, array $data = []): void
    {
        $themeDir = Anon_Cms_Theme::getThemeDir();
        $partialsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'partials');
        if ($partialsDir === null) {
            return;
        }

        $partialPath = Anon_Cms::findFileCaseInsensitive($partialsDir, $partialName);
        if ($partialPath === null) {
            return;
        }

        $this->child($data)->render($partialPath);
    }

    /**
     * 获取文章数据对象，仅查询，不存在时不渲染错误页，用于在输出 head 前判断文章是否存在
     * @return Anon_Cms_Post|null
     */
    public function postIfExists(): ?Anon_Cms_Post
    {
        $data = Anon_Cms::getPostIfExists();
        return $data ? new Anon_Cms_Post($data) : null;
    }

    public function post(): ?Anon_Cms_Post
    {
        $data = Anon_Cms::getPost();
        return $data ? new Anon_Cms_Post($data) : null;
    }

    /**
     * 获取页面数据对象，仅查询，不存在时不渲染错误页，用于在输出 head 前判断页面是否存在
     * @return Anon_Cms_Post|null
     */
    public function pageIfExists(): ?Anon_Cms_Post
    {
        $data = Anon_Cms::getPageIfExists();
        return $data ? new Anon_Cms_Post($data) : null;
    }

    /**
     * 获取页面数据对象
     * @return Anon_Cms_Post|null
     */
    public function page(): ?Anon_Cms_Post
    {
        $data = Anon_Cms::getPage();
        return $data ? new Anon_Cms_Post($data) : null;
    }

    /**
     * 文章列表缓存
     * @var array
     */
    private static $postsCache = [];

    /**
     * 分页器实例
     * @var Anon_Cms_Paginator|null
     */
    private $paginator = null;

    /**
     * 获取最新文章列表
     * @param int $pageSize 每页数量
     * @param int|null $page 当前页码
     * @return Anon_Cms_Post[]
     */
    public function posts(int $pageSize = 10, ?int $page = null): array
    {
        if ($pageSize <= 0) {
            $pageSize = 10;
            $db = Anon_Database::getInstance();
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
                $result[] = new Anon_Cms_Post($postData);
            }
            return $result;
        }

        // 分页逻辑
        $pageSize = max(1, min(100, $pageSize));
        
        // 获取当前页码
        if ($page === null) {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        } else {
            $page = max(1, $page);
        }

        $cacheKey = 'posts_' . $pageSize . '_' . $page;
        if (isset(self::$postsCache[$cacheKey])) {
            $cached = self::$postsCache[$cacheKey];
            $this->paginator = $cached['paginator'];
            return $cached['posts'];
        }

        $db = Anon_Database::getInstance();
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
            $result[] = new Anon_Cms_Post($postData);
        }

        $this->paginator = new Anon_Cms_Paginator($page, $pageSize, $total, $totalPages);
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

    /**
     * 获取选项代理，主题内默认优先级 theme > plugin > system，可传参指定优先级或输出方式
     * @return Anon_Cms_Options_Proxy
     */
    public function options(): Anon_Cms_Options_Proxy
    {
        return new Anon_Cms_Options_Proxy('theme', null, Anon_Cms_Theme::getCurrentTheme());
    }

    /**
     * 生成永久链接
     * @param Anon_Cms_Post|array|null $post 文章或页面对象
     * @return string
     */
    public function permalink($post = null): string
    {
        // 如果没有提供 post，尝试从当前上下文获取
        if ($post === null) {
            $post = $this->post();
            if ($post === null) {
                $post = $this->page();
            }
        }

        // 如果仍然没有 post，返回空字符串
        if ($post === null) {
            return '';
        }

        // 如果是数组，转换为 Anon_Cms_Post 对象
        if (is_array($post)) {
            $post = new Anon_Cms_Post($post);
        }

        // 获取 post 类型
        $type = $post->type();
        if (empty($type)) {
            return '';
        }

        // 获取路由配置
        $routesValue = Anon_Cms_Options::get('routes', '');
        $routes = [];
        
        if (is_array($routesValue)) {
            $routes = $routesValue;
        } elseif (is_string($routesValue) && !empty($routesValue)) {
            $decoded = json_decode($routesValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $routes = $decoded;
            }
        }

        // 根据类型查找对应的路由模板
        $routePattern = null;
        
        // 优先查找精确匹配的路由
        foreach ($routes as $pattern => $template) {
            // 检查模板是否匹配当前类型
            if ($template === $type) {
                $routePattern = $pattern;
                break;
            }
        }
        
        // 如果没有找到，尝试使用类型映射
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

        // 如果没有找到匹配的路由，使用默认规则
        if ($routePattern === null) {
            if ($type === 'post') {
                $routePattern = '/post/{id}';
            } elseif ($type === 'page') {
                $routePattern = '/{slug}';
            } else {
                return '';
            }
        }

        // 替换路由参数
        $url = $routePattern;
        
        // 替换 {id}（文章/页面 ID）
        if (strpos($url, '{id}') !== false) {
            $id = $post->id();
            $url = str_replace('{id}', $id, $url);
        }
        
        // 替换 {slug}（文章/页面 slug）
        if (strpos($url, '{slug}') !== false) {
            $slug = $post->slug();
            $url = str_replace('{slug}', urlencode($slug), $url);
        }
        
        // 替换 {category}（分类 slug，需要从分类中获取）
        if (strpos($url, '{category}') !== false) {
            $categoryId = $post->categoryId();
            if ($categoryId) {
                // TODO: 需要实现获取分类 slug 的方法
                // 暂时使用分类 ID
                $url = str_replace('{category}', $categoryId, $url);
            } else {
                $url = str_replace('{category}', '', $url);
            }
        }
        
        // 替换 {directory}（多级分类，暂时使用分类 slug）
        if (strpos($url, '{directory}') !== false) {
            $categoryId = $post->categoryId();
            if ($categoryId) {
                // TODO: 需要实现获取多级分类路径的方法
                $url = str_replace('{directory}', $categoryId, $url);
            } else {
                $url = str_replace('{directory}', '', $url);
            }
        }
        
        // 替换日期相关参数
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
        

        // 确保 URL 以 / 开头
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }

        return $url;
    }

}

/**
 * 统一选项代理，合并插件、主题、系统三源
 * 插件内默认顺序 plugin > theme > system，主题内默认 theme > plugin > system
 */
class Anon_Cms_Options_Proxy
{
    /** @var string 上下文 plugin 或 theme */
    private $context;

    /** @var string|null 插件 slug */
    private $pluginSlug;

    /** @var string|null 主题名 */
    private $themeName;

    public function __construct(string $context, ?string $pluginSlug = null, ?string $themeName = null)
    {
        $this->context = $context;
        $this->pluginSlug = $pluginSlug;
        $this->themeName = $themeName;
    }

    /**
     * 解析优先级得到源顺序
     * @param string|null $priority plugin 或 theme 或 system，null 时按上下文
     * @return string[] 源顺序
     */
    private function order(?string $priority): array
    {
        if ($priority === 'plugin') {
            return ['plugin', 'theme', 'system'];
        }
        if ($priority === 'theme') {
            return ['theme', 'plugin', 'system'];
        }
        if ($priority === 'system') {
            return ['system'];
        }
        return $this->context === 'plugin'
            ? ['plugin', 'theme', 'system']
            : ['theme', 'plugin', 'system'];
    }

    /**
     * 获取选项值
     * 第三参可为 bool 或 string：bool 表示是否 echo，string 且为 plugin/theme/system 表示优先级；不传时默认 echo
     * @param string $name 选项名
     * @param mixed $default 默认值
     * @param bool|string|null $outputOrPriority true 先 echo 再返回，false 仅返回；或传 plugin/theme/system 指定优先级并 echo
     * @param string|null $priority 第四参时有效，指定优先级
     * @return mixed
     */
    public function get(string $name, $default = null, $outputOrPriority = true, ?string $priority = null)
    {
        $output = true;
        $resolvedPriority = null;
        if (func_num_args() >= 3) {
            $third = $outputOrPriority;
            if (is_string($third) && in_array($third, ['plugin', 'theme', 'system'], true)) {
                $resolvedPriority = $third;
                $output = func_num_args() >= 4 ? (bool) $priority : true;
            } else {
                $output = (bool) $third;
                $resolvedPriority = func_num_args() >= 4 ? $priority : null;
            }
        }
        $order = $this->order($resolvedPriority);
        $value = $default;

        foreach ($order as $source) {
            if ($source === 'plugin' && $this->pluginSlug !== null && $this->pluginSlug !== '') {
                $all = Anon_System_Plugin::getPluginOptions($this->pluginSlug);
                if (array_key_exists($name, $all)) {
                    $value = $all[$name];
                    break;
                }
            }
            if ($source === 'theme') {
                $themeName = $this->themeName !== null && $this->themeName !== ''
                    ? $this->themeName
                    : (class_exists('Anon_Cms_Theme') ? Anon_Cms_Theme::getCurrentTheme() : '');
                if ($themeName !== '') {
                    $all = Anon_Theme_Options::all($themeName);
                    if (array_key_exists($name, $all)) {
                        $value = $all[$name];
                        break;
                    }
                }
            }
            if ($source === 'system') {
                $value = Anon_Cms_Options::get($name, $default);
                break;
            }
        }

        if ($output) {
            echo $value;
        }
        return $value;
    }

    /**
     * 设置选项值，写入系统 options 表
     * @param string $name 选项名
     * @param mixed $value 选项值
     * @return bool
     */
    public function set(string $name, $value): bool
    {
        return Anon_Cms_Options::set($name, $value);
    }
}

/**
 * 分页器类
 * 提供类似 Typecho 的分页功能
 */
class Anon_Cms_Paginator
{
    /**
     * @var int 当前页码
     */
    private $currentPage;

    /**
     * @var int 每页数量
     */
    private $pageSize;

    /**
     * @var int 总记录数
     */
    private $total;

    /**
     * @var int 总页数
     */
    private $totalPages;

    /**
     * @param int $currentPage 当前页码
     * @param int $pageSize 每页数量
     * @param int $total 总记录数
     * @param int $totalPages 总页数
     */
    public function __construct(int $currentPage, int $pageSize, int $total, int $totalPages)
    {
        $this->currentPage = $currentPage;
        $this->pageSize = $pageSize;
        $this->total = $total;
        $this->totalPages = $totalPages;
    }

    /**
     * 获取当前页码
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 获取每页数量
     * @return int
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * 获取总记录数
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 获取总页数
     * @return int
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * 判断是否有上一页
     * @return bool
     */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * 判断是否有下一页
     * @return bool
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * 获取上一页页码
     * @return int
     */
    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    /**
     * 获取下一页页码
     * @return int
     */
    public function nextPage(): int
    {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /**
     * 生成分页链接
     * @param int $page 页码
     * @return string
     */
    public function pageLink(int $page): string
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedUrl = parse_url($currentUrl);
        $path = $parsedUrl['path'] ?? '/';
        $query = [];
        
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }
        
        // 更新页码
        if ($page <= 1) {
            unset($query['page']);
        } else {
            $query['page'] = $page;
        }
        
        // 构建 URL
        $url = $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }

    /**
     * 获取所有页码数组
     * @param int $range 当前页前后显示的页码数量
     * @return array
     */
    public function getPageNumbers(int $range = 2): array
    {
        $pages = [];
        $start = max(1, $this->currentPage - $range);
        $end = min($this->totalPages, $this->currentPage + $range);
        
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        return $pages;
    }
}

/**
 * 文章/页面对象类
 * 提供类似 Typecho 的链式调用 API
 */
class Anon_Cms_Post
{
    /**
     * @var array 文章数据
     */
    private $data;

    /**
     * @param array $data 文章数据数组
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 获取文章 ID
     * @return int
     */
    public function id(): int
    {
        return (int)($this->data['id'] ?? 0);
    }

    /**
     * 获取标题
     * @return string
     */
    public function title(): string
    {
        return (string)($this->data['title'] ?? '');
    }

    /**
     * 获取内容
     * @return string
     */
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }

    /**
     * 获取摘要
     * @param int $length 长度
     * @return string
     */
    public function excerpt(int $length = 150): string
    {
        $content = $this->content();
        if (empty($content)) {
            return '';
        }

        // 移除 HTML 标签
        $text = strip_tags($content);
        
        // 移除 Markdown 标记
        $text = preg_replace('/<!--markdown-->/', '', $text);
        
        // 移除 Markdown 语法符号
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/`[^`]*`/u', ' ', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/(^|\s)#+\s+/u', ' ', $text);
        $text = preg_replace('/[*_~>#-]{1,3}/u', ' ', $text);
        
        // 清理多余空白
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        
        // 截取指定长度
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }

    /**
     * 获取 Slug
     * @return string
     */
    public function slug(): string
    {
        return (string)($this->data['slug'] ?? '');
    }

    /**
     * 获取类型
     * @return string
     */
    public function type(): string
    {
        return (string)($this->data['type'] ?? '');
    }

    /**
     * 获取状态
     * @return string
     */
    public function status(): string
    {
        return (string)($this->data['status'] ?? '');
    }

    /**
     * 获取创建时间
     * @return int
     */
    public function created(): int
    {
        $createdAt = $this->data['created_at'] ?? null;
        if (is_string($createdAt)) {
            return (int)strtotime($createdAt);
        }
        return (int)$createdAt;
    }

    /**
     * 获取创建时间 格式化
     * @param string $format 格式，默认 'Y-m-d H:i:s'
     * @return string
     */
    public function date(string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = $this->created();
        return $timestamp > 0 ? date($format, $timestamp) : '';
    }

    /**
     * 获取更新时间
     * @return int
     */
    public function modified(): int
    {
        $updatedAt = $this->data['updated_at'] ?? null;
        if (is_string($updatedAt)) {
            return (int)strtotime($updatedAt);
        }
        return (int)$updatedAt;
    }

    /**
     * 获取分类 ID
     * @return int|null
     */
    public function category(): ?int
    {
        $categoryId = $this->data['category_id'] ?? null;
        return $categoryId && $categoryId > 0 ? (int)$categoryId : null;
    }

    /**
     * 获取原始数据
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * 获取字段值
     * @param string $key 字段名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return $default;
    }

    /**
     * 检查字段是否存在
     * @param string $key 字段名
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}

