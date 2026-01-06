<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 插件系统
 * 类似 WordPress 的插件机制，支持插件扫描、加载、激活/停用
 */
class Anon_System_Plugin
{
    /**
     * 插件目录
     */
    const PLUGIN_DIR = __DIR__ . '/../../../app/Plugin/';

    /**
     * 已加载的插件
     * @var array
     */
    private static $loadedPlugins = [];

    /**
     * 已激活的插件列表（从配置读取）
     * @var array
     */
    private static $activePlugins = [];

    /**
     * 是否已初始化
     * @var bool
     */
    private static $initialized = false;

    /**
     * 插件扫描结果缓存
     * @var array|null
     */
    private static $scanCache = null;

    /**
     * 初始化插件系统
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // 检查插件系统是否启用
        if (!self::isEnabled()) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::info('Plugin system is disabled');
            }
            self::$initialized = true;
            return;
        }

        // 执行插件系统初始化前钩子
        Anon_System_Hook::do_action('plugin_system_before_init');

        // 获取已激活的插件列表
        self::$activePlugins = self::getActivePlugins();

        // 扫描并加载插件
        self::scanAndLoadPlugins();

        self::$initialized = true;

        // 执行插件系统初始化后钩子
        Anon_System_Hook::do_action('plugin_system_after_init', self::$loadedPlugins);
    }

    /**
     * 检查插件系统是否启用
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.plugins.enabled', true);
        }
        return true; // 默认启用
    }

    /**
     * 扫描插件目录并加载已激活的插件
     * 使用缓存减少文件系统操作
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

        // 执行插件扫描前钩子
        Anon_System_Hook::do_action('plugin_before_scan');

        // 尝试加载缓存的扫描结果
        $cachedPlugins = self::loadScanCache($pluginDir);
        
        if ($cachedPlugins !== null) {
            // 使用缓存的扫描结果
            foreach ($cachedPlugins as $pluginData) {
                $pluginSlug = $pluginData['slug'];
                
                // 检查插件是否已激活
                if (!self::isPluginActive($pluginSlug)) {
                    continue;
                }

                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::info("Loading plugin (cached): {$pluginData['meta']['name']}", [
                        'slug' => $pluginSlug,
                        'version' => $pluginData['meta']['version'] ?? 'N/A'
                    ]);
                }

                // 加载插件
                self::loadPlugin($pluginSlug, $pluginData['file'], $pluginData['meta'], $pluginData['dir']);
            }
        } else {
            // 执行完整扫描
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
                
                // 缓存数据只保留必要信息
                $scannedPlugins[] = [
                    'slug' => $pluginSlug,
                    'dir' => $dir,
                    'file' => $pluginFile,
                    'meta' => $meta
                ];

                // 检查插件是否已激活
                if (!self::isPluginActive($pluginSlug)) {
                    continue;
                }

                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::info("Loading plugin: {$meta['name']}", [
                        'slug' => $pluginSlug,
                        'version' => $meta['version'] ?? 'N/A'
                    ]);
                }

                // 加载插件
                self::loadPlugin($pluginSlug, $pluginFile, $meta, $dir);
            }
            
            // 保存扫描结果到缓存
            self::saveScanCache($pluginDir, $scannedPlugins);
        }

        // 执行插件扫描后钩子
        Anon_System_Hook::do_action('plugin_after_scan', self::$loadedPlugins);
    }

    /**
     * 加载插件扫描缓存
     * @param string $pluginDir 插件目录
     * @return array|null 缓存的插件列表，不存在或过期返回 null
     */
    private static function loadScanCache(string $pluginDir): ?array
    {
        // 调试模式下禁用缓存
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        if ($isDebug) {
            return null;
        }

        try {
            $cached = Anon_Cache::get('plugin_scan_list');
            
            if (!is_array($cached) || !isset($cached['plugins']) || !isset($cached['file_count'])) {
                return null;
            }

            // 简单检查插件目录是否有变化，仅检查文件数量
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
     * 保存插件扫描缓存
     * @param string $pluginDir 插件目录
     * @param array $plugins 扫描到的插件列表
     */
    private static function saveScanCache(string $pluginDir, array $plugins): void
    {
        // 调试模式下不保存缓存
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        if ($isDebug) {
            return;
        }

        try {
            // 统计插件文件数量用于快速验证
            $fileCount = count(glob($pluginDir . '*/Index.php'));
            
            $cacheData = [
                'file_count' => $fileCount,
                'plugins' => $plugins
            ];

            // 使用框架缓存系统，1小时过期
            Anon_Cache::set('plugin_scan_list', $cacheData, 3600);
        } catch (Throwable $e) {
            // 缓存失败不影响业务
        }
    }

    /**
     * 清除插件扫描缓存
     */
    public static function clearScanCache(): void
    {
        Anon_Cache::delete('plugin_scan_list');
        
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::info('Plugin scan cache cleared');
        }
    }

    /**
     * 读取插件元数据
     * 从文件头注释中解析元数据
     * @param string $pluginFile 插件文件路径
     * @return array|null 插件元数据，失败返回 null
     */
    private static function readPluginMeta(string $pluginFile): ?array
    {
        try {
            // 读取文件内容
            $content = file_get_contents($pluginFile);
            if ($content === false) {
                return null;
            }

            $meta = [];
            
            // 从文件头注释中提取元数据
            // 支持格式：Plugin Name: xxx 或 Plugin Name:xxx
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
            
            // 验证必需的字段
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
     * 将 PHP 数组语法安全转换为 JSON 格式
     * 参考 Router.php 的 convertPhpArrayToJson 方法
     * @param string $phpArrayStr PHP 数组字符串
     * @return string|null JSON 字符串，转换失败返回 null
     */
    private static function convertPhpArrayToJson(string $phpArrayStr): ?string
    {
        // 移除注释和多余空白
        $phpArrayStr = preg_replace('/\/\*.*?\*\//s', '', $phpArrayStr);
        $phpArrayStr = preg_replace('/\/\/.*$/m', '', $phpArrayStr);
        $phpArrayStr = trim($phpArrayStr);
        
        // 验证只包含安全的字符和结构
        $unsafePattern = '/(\$|function\s*\(|eval\s*\(|exec\s*\(|system\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|fwrite\s*\(|unlink\s*\(|include\s*\(|require\s*\()/i';
        
        if (preg_match($unsafePattern, $phpArrayStr)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin meta contains unsafe code", ['content' => substr($phpArrayStr, 0, 100)]);
            }
            return null;
        }
        
        // 验证基本结构：应该包含数组括号和键值对（支持多行）
        if (!preg_match('/\[[\s\S]*\]/', $phpArrayStr)) {
            if (defined('ANON_DEBUG') && ANON_DEBUG) {
                Anon_Debug::debug("Plugin meta is not a valid array structure", ['content' => substr($phpArrayStr, 0, 200)]);
            }
            return null;
        }
        
        // 将 PHP 数组语法转换为 JSON
        // 使用与 Router.php 相同的简单方法，但改进以支持 URL
        // 步骤1: 单引号转双引号（使用非贪婪匹配，支持 URL 中的 //）
        $jsonStr = preg_replace("/(['\"])(.*?)\\1/s", '"$2"', $phpArrayStr);
        
        // 步骤2: 处理 => 转换为 :
        $jsonStr = preg_replace('/\s*=>\s*/', ':', $jsonStr);
        
        // 步骤3: 处理未加引号的键名（在 => 转换之后）
        $jsonStr = preg_replace('/([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '"$1":', $jsonStr);
        
        // 步骤4: PHP 布尔值和 null
        $jsonStr = preg_replace('/\btrue\b/i', 'true', $jsonStr);
        $jsonStr = preg_replace('/\bfalse\b/i', 'false', $jsonStr);
        $jsonStr = preg_replace('/\bnull\b/i', 'null', $jsonStr);
        
        // 验证 JSON 格式
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
     * 获取已激活的插件列表
     * @return array 已激活的插件标识符列表
     */
    private static function getActivePlugins(): array
    {
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            $active = Anon_System_Env::get('app.plugins.active', []);
            // 空数组表示所有插件都激活
            if (empty($active)) {
                return []; // 返回空数组表示激活所有
            }
            return array_map('strtolower', $active);
        }
        return []; // 默认激活所有插件
    }

    /**
     * 检查插件是否已激活
     * @param string $pluginSlug 插件标识符
     * @return bool
     */
    private static function isPluginActive(string $pluginSlug): bool
    {
        // 如果 activePlugins 为空，表示所有插件都激活
        if (empty(self::$activePlugins)) {
            return true;
        }
        return in_array(strtolower($pluginSlug), self::$activePlugins, true);
    }

    /**
     * 获取插件标识符
     * @param string $dir 插件目录名
     * @param array $meta 插件元数据
     * @return string 插件标识符
     */
    private static function getPluginSlug(string $dir, array $meta): string
    {
        // 优先使用目录名作为标识符
        return strtolower($dir);
    }

    /**
     * 加载插件
     * @param string $pluginSlug 插件标识符
     * @param string $pluginFile 插件文件路径
     * @param array $meta 插件元数据
     * @param string $dirName 插件目录名（原始大小写）
     */
    private static function loadPlugin(string $pluginSlug, string $pluginFile, array $meta, string $dirName): void
    {
        // 执行插件加载前钩子
        Anon_System_Hook::do_action('plugin_before_load', $pluginSlug, $meta);

        try {
            // 检查插件是否已加载
            if (isset(self::$loadedPlugins[$pluginSlug])) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::debug("Plugin already loaded, skipping", ['plugin' => $pluginSlug]);
                }
                return;
            }

            // 加载插件文件
            require_once $pluginFile;

            // 获取插件类名
            $className = self::getPluginClassName($dirName);
            $pluginInstance = null;

            // 不区分大小写查找类
            $actualClassName = self::findClassCaseInsensitive($className);
            if ($actualClassName === null) {
                $actualClassName = $className;
            }

            // 如果插件类存在，调用 init 方法
            if ($actualClassName && class_exists($actualClassName)) {
                $className = $actualClassName;
                try {
                    // 检查是否有静态 init 方法
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

            // 记录已加载的插件
            self::$loadedPlugins[$pluginSlug] = [
                'slug' => $pluginSlug,
                'file' => $pluginFile,
                'meta' => $meta,
                'class' => $className,
                'instance' => $pluginInstance,
                'loaded_at' => microtime(true)
            ];

            // 执行插件加载后钩子
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

            // 执行插件加载错误钩子
            Anon_System_Hook::do_action('plugin_load_error', $pluginSlug, $e);
        }
    }

    /**
     * 根据插件目录名获取类名（不区分大小写）
     * @param string $dirName 插件目录名
     * @return string 类名
     */
    private static function getPluginClassName(string $dirName): string
    {
        // 将插件目录名转换为类名，不区分大小写
        // 例如: HelloWorld -> Anon_Plugin_Helloworld（会通过 findClassCaseInsensitive 匹配到 HelloWorld）
        // 例如: helloworld -> Anon_Plugin_Helloworld
        // 例如: my-plugin -> Anon_Plugin_Myplugin
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
     * 查找类名（不区分大小写）
     * @param string $className 类名
     * @return string|null 找到的类名，未找到返回 null
     */
    private static function findClassCaseInsensitive(string $className): ?string
    {
        // 获取所有已定义的类
        $allClasses = get_declared_classes();
        
        // 不区分大小写查找
        $lowerClassName = strtolower($className);
        foreach ($allClasses as $declaredClass) {
            if (strtolower($declaredClass) === $lowerClassName) {
                return $declaredClass;
            }
        }
        
        return null;
    }

    /**
     * 获取已激活的插件列表
     * @return array
     */
    public static function getLoadedPlugins(): array
    {
        return self::$loadedPlugins;
    }

    /**
     * 获取指定插件的信息
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
