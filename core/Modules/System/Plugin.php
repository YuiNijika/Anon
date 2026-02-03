<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 插件系统
 */
class Anon_System_Plugin
{
    const PLUGIN_DIR = __DIR__ . '/../../../app/Plugin/';

    /**
     * @var array 已加载插件
     */
    private static $loadedPlugins = [];

    /**
     * @var array 已激活插件
     */
    private static $activePlugins = [];

    /**
     * @var bool 初始化状态
     */
    private static $initialized = false;

    /**
     * @var array|null 扫描缓存
     */
    private static $scanCache = null;

    /**
     * 初始化系统
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        if (!self::isEnabled()) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::info('Plugin system is disabled');
            }
            self::$initialized = true;
            return;
        }

        Anon_System_Hook::do_action('plugin_system_before_init');

        self::$activePlugins = self::getActivePlugins();

        self::scanAndLoadPlugins();

        self::$initialized = true;

        Anon_System_Hook::do_action('plugin_system_after_init', self::$loadedPlugins);
    }

    /**
     * 检查启用状态
     * @return bool
     */
    public static function isEnabled(): bool
    {
        $appMode = defined('ANON_APP_MODE') ? ANON_APP_MODE : Anon_System_Env::get('app.mode', 'api');

        // CMS 模式
        if ($appMode === 'cms') {
            return true;
        }

        // API 模式
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.plugins.enabled', true);
        }

        return true;
    }

    /**
     * 扫描并加载
     */
    private static function scanAndLoadPlugins(): void
    {
        $pluginDir = self::PLUGIN_DIR;

        if (!is_dir($pluginDir)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::warn("Plugin directory not found: {$pluginDir}");
            }
            return;
        }

        Anon_System_Hook::do_action('plugin_before_scan');

        $cachedPlugins = self::loadScanCache($pluginDir);

        if ($cachedPlugins !== null) {
            foreach ($cachedPlugins as $pluginData) {
                $pluginSlug = $pluginData['slug'];

                if (!self::isPluginActive($pluginSlug)) {
                    continue;
                }

                if (!self::shouldLoadPlugin($pluginData['meta'])) {
                    continue;
                }

                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::info("Loading plugin (cached): {$pluginData['meta']['name']}", [
                        'slug' => $pluginSlug,
                        'version' => $pluginData['meta']['version'] ?? 'N/A'
                    ]);
                }

                self::loadPlugin($pluginSlug, $pluginData['file'], $pluginData['meta'], $pluginData['dir']);
            }
        } else {
            $scannedPlugins = [];
            $dirs = scandir($pluginDir);

            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                $pluginDirs = array_filter($dirs, function ($dir) {
                    return $dir !== '.' && $dir !== '..';
                });
                Anon_Debug::debug("Scanning plugin directory", [
                    'path' => $pluginDir,
                    'found' => count($pluginDirs) . ' plugin(s)'
                ]);
            }

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $pluginPath = $pluginDir . $dir;
                if (!is_dir($pluginPath)) {
                    continue;
                }

                $pluginFile = self::getPluginMainFile($pluginPath);
                if ($pluginFile === null) {
                    continue;
                }

                $meta = self::readPluginMetaForDir($pluginPath);
                if (!$meta) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        Anon_Debug::warn("Failed to read plugin meta", ['plugin' => $dir, 'path' => $pluginPath]);
                    }
                    continue;
                }

                $pluginSlug = self::getPluginSlug($dir, $meta);

                $scannedPlugins[] = [
                    'slug' => $pluginSlug,
                    'dir' => $dir,
                    'file' => $pluginFile,
                    'meta' => $meta
                ];

                if (!self::isPluginActive($pluginSlug)) {
                    continue;
                }

                if (!self::shouldLoadPlugin($meta)) {
                    continue;
                }

                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::info("Loading plugin: {$meta['name']}", [
                        'slug' => $pluginSlug,
                        'version' => $meta['version'] ?? 'N/A'
                    ]);
                }

                self::loadPlugin($pluginSlug, $pluginFile, $meta, $dir);
            }

            self::saveScanCache($pluginDir, $scannedPlugins);
        }

        Anon_System_Hook::do_action('plugin_after_scan', self::$loadedPlugins);
    }

    /**
     * 加载扫描缓存
     * @param string $pluginDir 插件目录
     * @return array|null
     */
    private static function loadScanCache(string $pluginDir): ?array
    {
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        if ($isDebug) {
            return null;
        }

        try {
            $cached = Anon_Cache::get('plugin_scan_list');

            if (!is_array($cached) || !isset($cached['plugins']) || !isset($cached['file_count'])) {
                return null;
            }

            $currentFileCount = count(glob($pluginDir . '*/Index.php'));
            if ($currentFileCount !== $cached['file_count']) {
                Anon_Cache::delete('plugin_scan_list');
                return null;
            }

            return $cached['plugins'];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 保存扫描缓存
     * @param string $pluginDir 插件目录
     * @param array $plugins 插件列表
     */
    private static function saveScanCache(string $pluginDir, array $plugins): void
    {
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        if ($isDebug) {
            return;
        }

        try {
            $fileCount = count(glob($pluginDir . '*/Index.php'));

            $cacheData = [
                'file_count' => $fileCount,
                'plugins' => $plugins
            ];

            Anon_Cache::set('plugin_scan_list', $cacheData, 3600);
        } catch (Throwable $e) {
            // 忽略
        }
    }

    /**
     * 清除扫描缓存
     */
    public static function clearScanCache(): void
    {
        Anon_Cache::delete('plugin_scan_list');

        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::info('Plugin scan cache cleared');
        }
    }

    /**
     * 从 package.json 读取元数据
     * @param string $pluginPath 插件目录绝对路径
     * @return array|null
     */
    public static function readPluginMetaFromPackageJson(string $pluginPath): ?array
    {
        $file = $pluginPath . '/package.json';
        if (!is_file($file)) {
            return null;
        }
        try {
            $json = file_get_contents($file);
            if ($json === false) {
                return null;
            }
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['name'])) {
                return null;
            }
            $meta = [
                'name' => trim((string)$data['name']),
                'description' => isset($data['description']) ? trim((string)$data['description']) : '',
                'version' => isset($data['version']) ? trim((string)$data['version']) : '',
                'author' => isset($data['author']) ? trim((string)$data['author']) : '',
                'url' => isset($data['url']) ? trim((string)$data['url']) : (isset($data['homepage']) ? trim((string)$data['homepage']) : ''),
                'mode' => 'api',
                'settings' => isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : [],
            ];
            if (isset($data['anon']) && is_array($data['anon'])) {
                if (isset($data['anon']['mode'])) {
                    $m = strtolower(trim((string)$data['anon']['mode']));
                    if (in_array($m, ['api', 'cms', 'auto'], true)) {
                        $meta['mode'] = $m;
                    }
                }
                if (isset($data['anon']['settings']) && is_array($data['anon']['settings'])) {
                    $meta['settings'] = $data['anon']['settings'];
                }
            }
            return $meta;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 插件目录下主入口文件路径，不区分大小写
     * @param string $pluginPath 插件目录绝对路径
     * @return string|null Index.php 的完整路径，未找到返回 null
     */
    public static function getPluginMainFile(string $pluginPath): ?string
    {
        if (!is_dir($pluginPath)) {
            return null;
        }
        $files = scandir($pluginPath);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (strtolower($f) === 'index.php') {
                return $pluginPath . '/' . $f;
            }
        }
        return null;
    }

    /**
     * 根据 slug 解析插件目录名，不区分大小写
     * @param string $pluginDir 插件根目录
     * @param string $slug 插件标识符
     * @return string|null 实际目录名，未找到返回 null
     */
    public static function resolvePluginDir(string $pluginDir, string $slug): ?string
    {
        if (!is_dir($pluginDir)) {
            return null;
        }
        $slugLower = strtolower($slug);
        $dirs = scandir($pluginDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            if (!is_dir($pluginDir . $dir)) {
                continue;
            }
            if (strtolower($dir) === $slugLower) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * 从插件目录读取元数据，优先 package.json，否则从主入口文件注释读取
     * @param string $pluginPath 插件目录绝对路径
     * @return array|null
     */
    public static function readPluginMetaForDir(string $pluginPath): ?array
    {
        $meta = self::readPluginMetaFromPackageJson($pluginPath);
        if ($meta !== null) {
            return $meta;
        }
        $mainFile = self::getPluginMainFile($pluginPath);
        if ($mainFile !== null) {
            return self::readPluginMeta($mainFile);
        }
        return null;
    }

    /**
     * 从主入口文件注释读取元数据
     * @param string $pluginFile 插件文件
     * @return array|null
     */
    public static function readPluginMeta(string $pluginFile): ?array
    {
        try {
            $content = file_get_contents($pluginFile);
            if ($content === false) {
                return null;
            }

            $meta = [];
            if (preg_match('/^\s*\*\s*Name:\s*(.+)$/im', $content, $matches)) {
                $meta['name'] = trim($matches[1]);
            } elseif (preg_match('/^\s*\*\s*Plugin Name:\s*(.+)$/im', $content, $matches)) {
                $meta['name'] = trim($matches[1]);
            }

            if (preg_match('/^\s*\*\s*Description:\s*(.+)$/im', $content, $matches)) {
                $meta['description'] = trim($matches[1]);
            } elseif (preg_match('/^\s*\*\s*Plugin Description:\s*(.+)$/im', $content, $matches)) {
                $meta['description'] = trim($matches[1]);
            }

            if (preg_match('/^\s*\*\s*Version:\s*(.+)$/im', $content, $matches)) {
                $meta['version'] = trim($matches[1]);
            }

            if (preg_match('/^\s*\*\s*Author:\s*(.+)$/im', $content, $matches)) {
                $meta['author'] = trim($matches[1]);
            }

            if (preg_match('/^\s*\*\s*URI:\s*(.+)$/im', $content, $matches)) {
                $meta['url'] = trim($matches[1]);
            } elseif (preg_match('/^\s*\*\s*Plugin URI:\s*(.+)$/im', $content, $matches)) {
                $meta['url'] = trim($matches[1]);
            }

            if (preg_match('/^\s*\*\s*Mode:\s*(.+)$/im', $content, $matches)) {
                $mode = strtolower(trim($matches[1]));
                if (in_array($mode, ['api', 'cms', 'auto'], true)) {
                    $meta['mode'] = $mode;
                } else {
                    $meta['mode'] = 'api'; // 默认值
                }
            } else {
                $meta['mode'] = 'api'; // 默认值
            }

            if (empty($meta['name'])) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::warn("Plugin meta missing 'name' field in {$pluginFile}");
                }
                return null;
            }

            return $meta;
        } catch (Throwable $e) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::error("Error reading plugin meta from {$pluginFile}: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * PHP数组转JSON
     * @param string $phpArrayStr PHP数组字符串
     * @return string|null
     */
    private static function convertPhpArrayToJson(string $phpArrayStr): ?string
    {
        $phpArrayStr = preg_replace('/\/\*.*?\*\//s', '', $phpArrayStr);
        $phpArrayStr = preg_replace('/\/\/.*$/m', '', $phpArrayStr);
        $phpArrayStr = trim($phpArrayStr);

        $unsafePattern = '/(\$|function\s*\(|eval\s*\(|exec\s*\(|system\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|fwrite\s*\(|unlink\s*\(|include\s*\(|require\s*\()/i';

        if (preg_match($unsafePattern, $phpArrayStr)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin meta contains unsafe code", ['content' => substr($phpArrayStr, 0, 100)]);
            }
            return null;
        }

        if (!preg_match('/\[[\s\S]*\]/', $phpArrayStr)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin meta is not a valid array structure", ['content' => substr($phpArrayStr, 0, 200)]);
            }
            return null;
        }

        $jsonStr = preg_replace("/(['\"])(.*?)\\1/s", '"$2"', $phpArrayStr);
        $jsonStr = preg_replace('/\s*=>\s*/', ':', $jsonStr);
        $jsonStr = preg_replace('/([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '"$1":', $jsonStr);

        $jsonStr = preg_replace('/\btrue\b/i', 'true', $jsonStr);
        $jsonStr = preg_replace('/\bfalse\b/i', 'false', $jsonStr);
        $jsonStr = preg_replace('/\bnull\b/i', 'null', $jsonStr);

        $testDecode = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($testDecode)) {
            return json_encode($testDecode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::debug("Plugin meta JSON conversion failed", [
                'error' => json_last_error_msg(),
                'original' => substr($phpArrayStr, 0, 200),
                'converted' => substr($jsonStr, 0, 200)
            ]);
        }

        return null;
    }

    /**
     * 获取激活列表
     * @return array
     */
    private static function getActivePlugins(): array
    {
        $appMode = defined('ANON_APP_MODE') ? ANON_APP_MODE : Anon_System_Env::get('app.mode', 'api');

        // CMS 模式下从 options 表读取
        if ($appMode === 'cms' && class_exists('Anon_Cms_Options')) {
            $active = Anon_Cms_Options::get('plugins:active', []);
            if (is_string($active)) {
                $active = json_decode($active, true);
                if (!is_array($active)) {
                    $active = [];
                }
            }
            return array_map('strtolower', $active);
        }

        // API 模式下从 useApp.php 读取
        if (Anon_System_Env::isInitialized()) {
            $active = Anon_System_Env::get('app.plugins.active', []);
            if (empty($active)) {
                return [];
            }
            return array_map('strtolower', $active);
        }

        return [];
    }

    /**
     * 检查激活状态
     * @param string $pluginSlug 插件标识符
     * @return bool
     */
    private static function isPluginActive(string $pluginSlug): bool
    {
        if (empty(self::$activePlugins)) {
            return true;
        }
        return in_array(strtolower($pluginSlug), self::$activePlugins, true);
    }

    /**
     * 判断是否应该加载插件
     * @param array $meta 插件元数据
     * @return bool
     */
    private static function shouldLoadPlugin(array $meta): bool
    {
        $pluginMode = strtolower($meta['mode'] ?? 'api');
        $appMode = Anon_System_Env::get('app.mode', 'api');

        // auto 模式
        if ($pluginMode === 'auto') {
            return true; // auto 模式总是加载
        }

        // api 模式
        if ($pluginMode === 'api') {
            return $appMode === 'api';
        }

        // cms 模式
        if ($pluginMode === 'cms') {
            return $appMode === 'cms';
        }

        // 默认不加载
        return false;
    }

    /**
     * 获取标识符
     * @param string $dir 目录名
     * @param array $meta 元数据
     * @return string
     */
    private static function getPluginSlug(string $dir, array $meta): string
    {
        return strtolower($dir);
    }

    /**
     * 加载插件
     * @param string $pluginSlug 插件标识符
     * @param string $pluginFile 文件路径
     * @param array $meta 元数据
     * @param string $dirName 目录名
     */
    private static function loadPlugin(string $pluginSlug, string $pluginFile, array $meta, string $dirName): void
    {
        Anon_System_Hook::do_action('plugin_before_load', $pluginSlug, $meta);

        try {
            if (isset(self::$loadedPlugins[$pluginSlug])) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::debug("Plugin already loaded, skipping", ['plugin' => $pluginSlug]);
                }
                return;
            }

            require_once $pluginFile;

            $className = self::getPluginClassName($dirName);
            $pluginInstance = null;

            $actualClassName = self::findClassCaseInsensitive($className);
            if ($actualClassName === null) {
                $actualClassName = $className;
            }

            if ($actualClassName && class_exists($actualClassName)) {
                $className = $actualClassName;
                try {
                    if (is_subclass_of($className, 'Anon_Plugin_Base')) {
                        $instance = new $className($pluginSlug);
                        if (method_exists($instance, 'init')) {
                            $instance->init();
                        }
                        $pluginInstance = $instance;
                        if (defined('ANON_DEBUG') && ANON_DEBUG) {
                            Anon_Debug::debug("Plugin initialized (instance)", ['class' => $className]);
                        }
                    } elseif (method_exists($className, 'init')) {
                        $className::init();
                        if (defined('ANON_DEBUG') && ANON_DEBUG) {
                            Anon_Debug::debug("Plugin initialized", ['class' => $className]);
                        }
                        $pluginInstance = $className;
                    } else {
                        if (defined('ANON_DEBUG') && ANON_DEBUG) {
                            Anon_Debug::warn("Plugin class has no init() method", ['class' => $className]);
                        }
                        $pluginInstance = $className;
                    }
                } catch (Throwable $e) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        Anon_Debug::error("Plugin initialization failed", [
                            'plugin' => $pluginSlug,
                            'class' => $className,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                }
            } else {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::warn("Plugin class not found", [
                        'plugin' => $pluginSlug,
                        'expected' => $className,
                        'dir' => $dirName
                    ]);
                }
            }

            self::$loadedPlugins[$pluginSlug] = [
                'slug' => $pluginSlug,
                'file' => $pluginFile,
                'meta' => $meta,
                'class' => $className,
                'instance' => $pluginInstance,
                'loaded_at' => microtime(true)
            ];

            Anon_System_Hook::do_action('plugin_after_load', $pluginSlug, $meta);
        } catch (Throwable $e) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::error("Failed to load plugin", [
                    'plugin' => $pluginSlug,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }

            Anon_System_Hook::do_action('plugin_load_error', $pluginSlug, $e);
        }
    }

    /**
     * 获取类名
     * @param string $dirName 目录名
     * @return string
     */
    private static function getPluginClassName(string $dirName): string
    {
        $parts = preg_split('/[-_]/', $dirName);
        $className = 'Anon_Plugin_';
        foreach ($parts as $part) {
            if (strlen($part) > 0) {
                $className .= ucfirst(strtolower($part));
            }
        }
        return $className;
    }

    /**
     * 根据 slug 获取插件类名，用于未加载时读取设置 schema
     * @param string $slug 插件标识符
     * @return string|null
     */
    public static function getPluginClassNameFromSlug(string $slug): ?string
    {
        $resolvedDir = self::resolvePluginDir(self::PLUGIN_DIR, $slug);
        return $resolvedDir !== null ? self::getPluginClassName($resolvedDir) : null;
    }

    /**
     * 查找类
     * @param string $className 类名
     * @return string|null
     */
    private static function findClassCaseInsensitive(string $className): ?string
    {
        $allClasses = get_declared_classes();

        $lowerClassName = strtolower($className);
        foreach ($allClasses as $declaredClass) {
            if (strtolower($declaredClass) === $lowerClassName) {
                return $declaredClass;
            }
        }

        return null;
    }

    /**
     * 获取加载列表
     * @return array
     */
    public static function getLoadedPlugins(): array
    {
        return self::$loadedPlugins;
    }

    /**
     * 获取插件信息
     * @param string $pluginSlug 插件标识符
     * @return array|null
     */
    public static function getPlugin(string $pluginSlug): ?array
    {
        return self::$loadedPlugins[strtolower($pluginSlug)] ?? null;
    }

    /**
     * 获取插件选项，从 options 表 plugin:slug 的 value 解析为数组
     * @param string $pluginSlug 插件标识符
     * @return array
     */
    public static function getPluginOptions(string $pluginSlug): array
    {
        $slug = strtolower($pluginSlug);
        $name = 'plugin:' . $slug;
        $raw = null;
        if (class_exists('Anon_Cms_Options')) {
            $raw = Anon_Cms_Options::get($name, []);
        } else {
            $db = Anon_Database::getInstance();
            $row = $db->db('options')->where('name', $name)->first();
            if ($row && isset($row['value']) && $row['value'] !== '' && $row['value'] !== null) {
                $dec = json_decode($row['value'], true);
                $raw = is_array($dec) ? $dec : [];
            } else {
                $raw = [];
            }
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * 激活插件
     * @param string $pluginSlug 插件标识符
     * @return bool
     */
    public static function activatePlugin(string $pluginSlug): bool
    {
        $pluginInfo = self::getPlugin($pluginSlug);
        if (!$pluginInfo) {
            return false;
        }

        if ($pluginInfo && isset($pluginInfo['class']) && class_exists($pluginInfo['class'])) {
            $className = $pluginInfo['class'];
            if (method_exists($className, 'activate')) {
                try {
                    $className::activate();
                    return true;
                } catch (Throwable $e) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        Anon_Debug::error("Failed to activate plugin {$pluginSlug}: " . $e->getMessage());
                    }
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * 停用插件
     * @param string $pluginSlug 插件标识符
     * @return bool
     */
    public static function deactivatePlugin(string $pluginSlug): bool
    {
        $pluginInfo = self::getPlugin($pluginSlug);
        if (!$pluginInfo) {
            return false;
        }

        if ($pluginInfo && isset($pluginInfo['class']) && class_exists($pluginInfo['class'])) {
            $className = $pluginInfo['class'];
            if (method_exists($className, 'deactivate')) {
                try {
                    $className::deactivate();
                    return true;
                } catch (Throwable $e) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        Anon_Debug::error("Failed to deactivate plugin {$pluginSlug}: " . $e->getMessage());
                    }
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * 重新加载单个插件
     * @param string $pluginSlug 插件标识符
     * @return bool
     */
    public static function reloadPlugin(string $pluginSlug): bool
    {
        // 更新激活列表
        self::$activePlugins = self::getActivePlugins();

        $pluginDir = self::PLUGIN_DIR;
        $resolvedDir = self::resolvePluginDir($pluginDir, $pluginSlug);
        if ($resolvedDir === null) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::warn("Plugin directory not found", ['slug' => $pluginSlug]);
            }
            return false;
        }
        $pluginPath = $pluginDir . $resolvedDir;
        $pluginFile = self::getPluginMainFile($pluginPath);
        if ($pluginFile === null) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::warn("Plugin main file not found", ['slug' => $pluginSlug, 'path' => $pluginPath]);
            }
            return false;
        }

        $meta = self::readPluginMetaForDir($pluginPath);
        if (!$meta || empty($meta['name'])) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::warn("Failed to read plugin meta", ['slug' => $pluginSlug]);
            }
            return false;
        }

        // 按模式匹配检查插件是否应加载
        if (!self::shouldLoadPlugin($meta)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin mode mismatch, skipping", ['slug' => $pluginSlug, 'mode' => $meta['mode'] ?? 'api']);
            }
            return false;
        }

        // 检查插件是否已激活
        if (!self::isPluginActive($pluginSlug)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin not active, skipping", ['slug' => $pluginSlug]);
            }
            return false;
        }

        // 加载插件
        self::loadPlugin($pluginSlug, $pluginFile, $meta, $resolvedDir);

        // 调用激活方法
        return self::activatePlugin($pluginSlug);
    }

    /**
     * 获取当前应用模式
     * @return string 'api' 或 'cms'
     */
    public static function getAppMode(): string
    {
        return defined('ANON_APP_MODE') ? ANON_APP_MODE : Anon_System_Env::get('app.mode', 'api');
    }

    /**
     * 判断是否为 API 模式
     * @return bool
     */
    public static function isApiMode(): bool
    {
        return self::getAppMode() === 'api';
    }

    /**
     * 判断是否为 CMS 模式
     * @return bool
     */
    public static function isCmsMode(): bool
    {
        return self::getAppMode() === 'cms';
    }
}

/**
 * 插件基类，继承后统一通过 $this 调用：$this->options()、$this->user()、$this->theme()
 * 默认选项优先级 plugin > theme > system
 */
abstract class Anon_Plugin_Base
{
    /** @var string 插件 slug */
    protected $slug;

    public function __construct(string $pluginSlug = '')
    {
        $this->slug = $pluginSlug !== '' ? strtolower($pluginSlug) : self::slugFromClass(get_class($this));
    }

    /**
     * 从类名推导 slug，例 Anon_Plugin_HelloWorld 得 helloworld
     * @param string $class 类名
     * @return string
     */
    protected static function slugFromClass(string $class): string
    {
        if (strpos($class, 'Anon_Plugin_') === 0) {
            $name = substr($class, strlen('Anon_Plugin_'));
            return strtolower(preg_replace('/[-_]/', '', $name));
        }
        return strtolower($class);
    }

    /**
     * 选项代理，默认优先级 plugin > theme > system
     * @return Anon_Cms_Options_Proxy|null
     */
    public function options()
    {
        if (!class_exists('Anon_Cms_Options_Proxy')) {
            return null;
        }
        return new Anon_Cms_Options_Proxy('plugin', $this->slug, null);
    }

    /**
     * 当前登录用户对象，未登录为 null
     * @return Anon_Cms_User|null
     */
    public function user()
    {
        if (!class_exists('Anon_Cms') || !method_exists('Anon_Cms', 'getCurrentUser')) {
            return null;
        }
        $user = Anon_Cms::getCurrentUser();
        return $user !== null && class_exists('Anon_Cms_User') ? new Anon_Cms_User($user) : null;
    }

    /**
     * 主题辅助对象，仅读当前主题名与主题选项
     * @return Anon_Cms_Theme_Helper|null
     */
    public function theme()
    {
        if (!class_exists('Anon_Cms_Theme_Helper') || !class_exists('Anon_Cms_Theme')) {
            return null;
        }
        return new Anon_Cms_Theme_Helper(Anon_Cms_Theme::getCurrentTheme());
    }
}
