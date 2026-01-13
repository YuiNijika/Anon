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
                $pluginDirs = array_filter($dirs, function($dir) {
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

                $pluginFile = $pluginPath . '/Index.php';
                if (!file_exists($pluginFile)) {
                    continue;
                }

                $meta = self::readPluginMeta($pluginFile);
                if (!$meta) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        Anon_Debug::warn("Failed to read plugin meta", ['plugin' => $dir, 'file' => $pluginFile]);
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
     * 读取元数据
     * @param string $pluginFile 插件文件
     * @return array|null
     */
    private static function readPluginMeta(string $pluginFile): ?array
    {
        try {
            $content = file_get_contents($pluginFile);
            if ($content === false) {
                return null;
            }

            $meta = [];
            
            if (preg_match('/Plugin Name:\s*(.+)/i', $content, $matches)) {
                $meta['name'] = trim($matches[1]);
            }
            
            if (preg_match('/Plugin Description:\s*(.+)/i', $content, $matches)) {
                $meta['description'] = trim($matches[1]);
            }
            
            if (preg_match('/Version:\s*(.+)/i', $content, $matches)) {
                $meta['version'] = trim($matches[1]);
            }
            
            if (preg_match('/Author:\s*(.+)/i', $content, $matches)) {
                $meta['author'] = trim($matches[1]);
            }
            
            if (preg_match('/Plugin URI:\s*(.+)/i', $content, $matches)) {
                $meta['url'] = trim($matches[1]);
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
                    if (method_exists($className, 'init')) {
                        $className::init();
                        if (defined('ANON_DEBUG') && ANON_DEBUG) {
                            Anon_Debug::debug("Plugin initialized", ['class' => $className]);
                        }
                    } else {
                        if (defined('ANON_DEBUG') && ANON_DEBUG) {
                            Anon_Debug::warn("Plugin class has no init() method", ['class' => $className]);
                        }
                    }
                    $pluginInstance = $className;
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
}
