<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/View.php';
require_once __DIR__ . '/OptionsProxy.php';
require_once __DIR__ . '/Paginator.php';
require_once __DIR__ . '/Post.php';
require_once __DIR__ . '/SetupHelper.php';

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
                $themeName = self::getCurrentTheme();
                $codeContext = new class($themeName) {
                    private $themeName;
                    private $optionsProxy;

                    public function __construct(string $themeName)
                    {
                        $this->themeName = $themeName;
                    }

                    public function options(?string $name = null, $default = null, $outputOrPriority = false, ?string $priority = null)
                    {
                        if ($this->optionsProxy === null) {
                            $this->optionsProxy = new Anon_Cms_Options_Proxy('theme', null, $this->themeName);
                        }
                        if ($name === null) {
                            return $this->optionsProxy;
                        }
                        try {
                            $result = $this->optionsProxy->get($name, $default, $outputOrPriority, $priority);

                            if (Anon_Debug::isEnabled()) {
                                Anon_Debug::debug('[Theme Code Context] options() 调用结果', [
                                    'theme' => $this->themeName,
                                    'option_name' => $name,
                                    'default' => $default,
                                    'outputOrPriority' => $outputOrPriority,
                                    'priority' => $priority,
                                    'result' => $result,
                                    'result_type' => gettype($result),
                                    'result_is_null' => is_null($result),
                                    'result_is_empty' => empty($result),
                                    'result_is_object' => is_object($result),
                                    'result_class' => is_object($result) ? get_class($result) : null
                                ]);
                            }

                            if (is_object($result)) {
                                if ($result instanceof Anon_Cms_Options_Proxy) {
                                    if (Anon_Debug::isEnabled()) {
                                        Anon_Debug::error('[Theme Code Context] options() 返回了代理对象', [
                                            'theme' => $this->themeName,
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
                                    if (Anon_Debug::isEnabled()) {
                                        Anon_Debug::warn('[Theme Code Context] options() 返回了对象，已转换为字符串', [
                                            'theme' => $this->themeName,
                                            'option_name' => $name,
                                            'result_type' => get_class($result),
                                            'converted_value' => $stringValue
                                        ]);
                                    }
                                    return $stringValue;
                                }
                                if (Anon_Debug::isEnabled()) {
                                    Anon_Debug::error('[Theme Code Context] options() 返回了无法转换的对象', [
                                        'theme' => $this->themeName,
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
                            if (Anon_Debug::isEnabled()) {
                                Anon_Debug::error('[Theme Code Context] options() 发生异常', [
                                    'theme' => $this->themeName,
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
                };

                $code = file_get_contents($codeFile);
                if ($code !== false) {
                    $code = preg_replace('/^<\?php\s*/', '', $code);
                    $executeCode = function () use ($code) {
                        eval($code);
                    };
                    $executeCode->call($codeContext);
                }
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

        $helper = new Anon_Cms_Theme_Setup_Helper($themeName);
        $raw = $helper->loadSetupFile($setupFile);

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
            Anon_Debug::warn('[Theme] loadThemeSetupFile: themeDir not found', ['themeDir' => $themeDir]);
            return;
        }
        $setupFile = $themeDirResolved . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'setup.php';
        if (!file_exists($setupFile)) {
            Anon_Debug::warn('[Theme] loadThemeSetupFile: setup.php not found', ['setupFile' => $setupFile]);
            return;
        }
        if (!defined('ANON_ALLOWED_ACCESS')) {
            define('ANON_ALLOWED_ACCESS', true);
        }

        $name = $themeName !== null && $themeName !== '' ? $themeName : basename($themeDirResolved);
        $helper = new Anon_Cms_Theme_Setup_Helper($name);
        $schema = $helper->loadSetupFile($setupFile);

        if (!is_array($schema)) {
            Anon_Debug::warn('[Theme] loadThemeSetupFile: setup.php did not return array', ['got' => gettype($schema)]);
            return;
        }
        Anon_Theme_Options::registerFromSchema($schema, strtolower($name));
        Anon_Cms_Options::clearCache();
        Anon_Debug::info('[Theme] loadThemeSetupFile: registered schema for theme', ['theme' => $name, 'keys' => count($schema)]);
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
            return '';
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
                return '';
            }
            throw $e;
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

            if (!empty($data)) {
                foreach (['uid', 'name', 'id', 'slug'] as $key) {
                    if (isset($data[$key])) {
                        $GLOBALS[$key] = $data[$key];
                    }
                }
            }

            Anon_Cms::startPageLoad();

            if (strtolower($templateName) === 'user') {
                $resolved = self::resolveUserTemplate();
                if ($resolved === null) {
                    http_response_code(404);
                    self::render('error', ['code' => 404, 'message' => '页面不存在']);
                    exit;
                }
                $templateName = $resolved;
            }

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
                Anon_Cms_Theme_View::clearRenderedComponents();
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
     * 解析用户页模板，有 user 或 author 模板则返回对应名，否则返回 null
     * @return string|null user、author 或 null
     */
    public static function resolveUserTemplate(): ?string
    {
        if (self::findTemplate('user') !== null) {
            return 'user';
        }
        if (self::findTemplate('author') !== null) {
            return 'author';
        }
        return null;
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

        $cacheParam = self::getAssetCacheParam();
        if ($forceNoCache) {
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
     * 仅返回主题资源 URL，不输出标签，供 theme()->themeUrl() 等调用
     * @param string $path 资源相对路径，如 style.css、js/main.js
     * @param bool $forceNoCache 是否追加禁缓存参数
     * @return string 绝对路径 URL，无站点前缀；资源不存在或未注册时返回空字符串
     */
    public static function getAssetUrl(string $path, bool $forceNoCache = false): string
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return '';
        }
        if (!self::$assetsRegistered) {
            self::registerAssets();
        }
        $themeDir = self::getThemeDir();
        $assetsDir = Anon_Cms::findDirectoryCaseInsensitive($themeDir, 'assets');
        if ($assetsDir === null) {
            return '';
        }
        $fileExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $typeDirs = self::getTypeDirs();
        $typeDir = $typeDirs[$fileExt] ?? 'files';
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $url = '/assets/' . $typeDir . '/' . $fileName;
        $cacheParam = self::getAssetCacheParam();
        if ($forceNoCache) {
            $cacheParam = ($cacheParam === '' ? '?' : $cacheParam . '&') . 'nocache=1';
        }
        return $url . $cacheParam;
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
     * @return string 缓存参数字符串，生产环境为 ?ver=主题版本号，版本号变更时浏览器会重新拉取
     */
    public static function getAssetCacheParam(?bool $dev = null): string
    {
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
                    Anon_Debug::warn('[Anon Theme components] 静态调用: 组件目录未找到', ['themeDir' => $themeDir, 'lookup' => 'app/components']);
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
                    Anon_Debug::warn('[Anon Theme components] 静态调用: 组件子目录未找到', ['componentPath' => $componentPath, 'part' => $part, 'componentDir' => $componentDir]);
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

            Anon_Debug::warn('[Anon Theme components] 静态调用: 组件文件未找到', ['componentPath' => $componentPath, 'componentName' => $componentName, 'componentDir' => $componentDir]);
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
        $charset = Anon_Cms_Options::get('charset', 'UTF-8');
        echo '<meta charset="' . htmlspecialchars((string) $charset) . '">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        try {
            $seo = Anon_Cms_PageMeta::getSeo($overrides);

            if (!empty($seo['title'])) {
                echo '<title>' . htmlspecialchars($seo['title']) . '</title>' . "\n";
            } else {
                self::title();
            }
            self::meta($seo);
            echo '<script src="/anon/static/vue"></script>' . "\n";
            echo '<script src="/anon/static/comments"></script>' . "\n";
            Anon_System_Hook::do_action('theme_head');
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
            return '';
        } catch (Throwable $e) {
            if (self::isFatalError($e)) {
                self::handleFatalError($e);
                return '';
            }
            throw $e;
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

        $settings = Anon_Cms_Options::get($optionName, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $defaultArgs = [
            'type' => 'text',
            'label' => $key,
            'description' => '',
            'default' => '',
            'sanitize_callback' => null,
            'validate_callback' => null,
        ];

        $args = array_merge($defaultArgs, $args);

        if (!isset($settings[$key])) {
            $settings[$key] = $args['default'];
        }

        $registeredSettings = Anon_Cms_Options::get("theme:{$themeName}:settings", []);
        if (!is_array($registeredSettings)) {
            $registeredSettings = [];
        }
        $registeredSettings[$key] = $args;
        Anon_Cms_Options::set("theme:{$themeName}:settings", $registeredSettings);

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

        $registeredSettings = Anon_Cms_Options::get("theme:{$themeName}:settings", []);
        if (isset($registeredSettings[$key])) {
            $settingDef = $registeredSettings[$key];

            if (isset($settingDef['validate_callback']) && is_callable($settingDef['validate_callback'])) {
                $valid = call_user_func($settingDef['validate_callback'], $value);
                if ($valid === false) {
                    return false;
                }
            }

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
