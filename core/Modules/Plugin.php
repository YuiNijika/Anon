<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 插件系统
 * 类似 WordPress 的插件机制，支持插件扫描、加载、激活/停用
 */
class Anon_Plugin
{
    /**
     * 插件目录
     */
    const PLUGIN_DIR = __DIR__ . '/../../app/Plugin/';

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
        Anon_Hook::do_action('plugin_system_before_init');

        // 获取已激活的插件列表
        self::$activePlugins = self::getActivePlugins();

        // 扫描并加载插件
        self::scanAndLoadPlugins();

        self::$initialized = true;

        // 执行插件系统初始化后钩子
        Anon_Hook::do_action('plugin_system_after_init', self::$loadedPlugins);
    }

    /**
     * 检查插件系统是否启用
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (class_exists('Anon_Env') && Anon_Env::isInitialized()) {
            return Anon_Env::get('app.plugins.enabled', true);
        }
        return true; // 默认启用
    }

    /**
     * 扫描插件目录并加载已激活的插件
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
        Anon_Hook::do_action('plugin_before_scan');

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

            // 读取插件元数据
            $meta = self::readPluginMeta($pluginFile);
            if (!$meta) {
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::warn("Failed to read plugin meta", ['plugin' => $dir, 'file' => $pluginFile]);
                }
                continue;
            }

            $pluginSlug = self::getPluginSlug($dir, $meta);

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

        // 执行插件扫描后钩子
        Anon_Hook::do_action('plugin_after_scan', self::$loadedPlugins);
    }

    /**
     * 读取插件元数据
     * 使用简单直接的方法：先加载文件，然后读取常量
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

            // 使用正则表达式提取常量定义
            // 匹配 const Anon_PluginMeta = [...];
            if (!preg_match('/const\s+Anon_PluginMeta\s*=\s*\[(.*?)\];/s', $content, $matches)) {
                return null;
            }

            $arrayContent = $matches[1];
            
            // 解析数组内容，提取键值对
            $meta = [];
            
            // 匹配 'key' => 'value' 或 "key" => "value"
            // 使用更精确的正则表达式
            $pattern = "/(?:['\"])([a-zA-Z_][a-zA-Z0-9_]*)(?:['\"])\s*=>\s*(?:['\"])([^'\"]*)(?:['\"])/";
            if (preg_match_all($pattern, $arrayContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $key = $match[1];
                    $value = $match[2];
                    $meta[$key] = $value;
                }
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
     * 获取已激活的插件列表
     * @return array 已激活的插件标识符列表
     */
    private static function getActivePlugins(): array
    {
        if (class_exists('Anon_Env') && Anon_Env::isInitialized()) {
            $active = Anon_Env::get('app.plugins.active', []);
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
        Anon_Hook::do_action('plugin_before_load', $pluginSlug, $meta);

        try {
            // 加载插件文件
            require_once $pluginFile;

            // 获取插件类名（从目录名生成，不区分大小写）
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
            Anon_Hook::do_action('plugin_after_load', $pluginSlug, $meta);
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
            Anon_Hook::do_action('plugin_load_error', $pluginSlug, $e);
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
