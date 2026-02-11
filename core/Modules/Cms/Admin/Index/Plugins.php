<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 插件管理类
 */
class Anon_Cms_Admin_Plugins
{
    /**
     * 插件初始化
     */
    public static function init()
    {
    }

    /**
     * 获取插件列表
     */
    public static function get()
    {
        try {
            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            if (!is_dir($pluginDir)) {
                Anon_Http_Response::success(['list' => []], '获取插件列表成功');
                return;
            }

            $activePlugins = self::getActivePlugins();
            $plugins = [];
            $dirs = scandir($pluginDir);

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;

                $pluginPath = $pluginDir . $dir;
                if (!is_dir($pluginPath)) continue;
                if (Anon_System_Plugin::getPluginMainFile($pluginPath) === null) continue;

                $meta = Anon_System_Plugin::readPluginMetaForDir($pluginPath);
                if (!$meta || empty($meta['name'])) {
                    $meta = [
                        'name' => $dir,
                        'description' => '',
                        'version' => '',
                        'author' => '',
                        'url' => '',
                        'mode' => 'api',
                        'settings' => [],
                    ];
                }

                $slug = strtolower($dir);
                $plugins[] = [
                    'slug' => $slug,
                    'dir' => $dir,
                    'name' => $meta['name'] ?? $dir,
                    'description' => $meta['description'] ?? '',
                    'version' => $meta['version'] ?? '',
                    'author' => $meta['author'] ?? '',
                    'url' => $meta['url'] ?? '',
                    'mode' => $meta['mode'] ?? 'api',
                    'active' => in_array($slug, $activePlugins, true),
                    'pages' => self::getPluginPagesList($slug, $pluginPath),
                ];
            }

            Anon_Http_Response::success(['list' => $plugins], '获取插件列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 激活插件
     */
    public static function activate()
    {
        try {
            $input = Anon_Http_Request::getInput();
            $slug = trim((string)($input['slug'] ?? ''));
            if (empty($slug)) {
                Anon_Http_Response::error('插件标识符不能为空', 400);
                return;
            }

            $activePlugins = self::getActivePlugins();
            $lowerSlug = strtolower($slug);

            if (in_array($lowerSlug, $activePlugins, true)) {
                Anon_Http_Response::success(['slug' => $slug], '插件已激活');
                return;
            }

            $activePlugins[] = $lowerSlug;
            self::setActivePlugins($activePlugins);
            Anon_System_Plugin::clearScanCache();
            Anon_System_Plugin::reloadPlugin($slug);

            Anon_Http_Response::success(['slug' => $slug], '激活插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 停用插件
     */
    public static function deactivate()
    {
        try {
            $input = Anon_Http_Request::getInput();
            $slug = trim((string)($input['slug'] ?? ''));
            if (empty($slug)) {
                Anon_Http_Response::error('插件标识符不能为空', 400);
                return;
            }

            $activePlugins = self::getActivePlugins();
            $lowerSlug = strtolower($slug);
            $key = array_search($lowerSlug, $activePlugins, true);

            if ($key === false) {
                Anon_Http_Response::success(['slug' => $slug], '插件已停用');
                return;
            }

            unset($activePlugins[$key]);
            self::setActivePlugins(array_values($activePlugins));
            Anon_System_Plugin::clearScanCache();
            Anon_System_Plugin::deactivatePlugin($slug);

            Anon_Http_Response::success(['slug' => $slug], '停用插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除插件
     */
    public static function delete()
    {
        try {
            $input = Anon_Http_Request::getInput();
            $slug = trim((string)($input['slug'] ?? ''));
            if (empty($slug)) {
                Anon_Http_Response::error('插件标识符不能为空', 400);
                return;
            }

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $pluginPath = $pluginDir . $slug;

            if (!is_dir($pluginPath)) {
                Anon_Http_Response::error('插件不存在', 404);
                return;
            }

            if (in_array(strtolower($slug), self::getActivePlugins(), true)) {
                Anon_Http_Response::error('请先停用插件再删除', 400);
                return;
            }

            self::deleteDirectory($pluginPath);
            Anon_System_Plugin::clearScanCache();

            Anon_Http_Response::success(['slug' => $slug], '删除插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 上传插件
     */
    public static function upload()
    {
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Anon_Http_Response::error('文件上传失败', 400);
                return;
            }

            $file = $_FILES['file'];
            if (!preg_match('/\.(zip)$/i', $file['name'])) {
                Anon_Http_Response::error('只支持 ZIP 格式的插件包', 400);
                return;
            }

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            if (!is_dir($pluginDir)) mkdir($pluginDir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                Anon_Http_Response::error('无法打开 ZIP 文件', 400);
                return;
            }

            $extractDir = sys_get_temp_dir() . '/anon_plugin_' . uniqid();
            if (!mkdir($extractDir, 0755, true) || !$zip->extractTo($extractDir)) {
                $zip->close();
                if (is_dir($extractDir)) self::deleteDirectory($extractDir);
                Anon_Http_Response::error('解压 ZIP 文件失败', 500);
                return;
            }
            $zip->close();

            $overwrite = !empty($_POST['overwrite']);
            $pluginName = self::detectPluginName($extractDir, pathinfo($file['name'], PATHINFO_FILENAME));

            if (!$pluginName) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('无效的插件包结构', 400);
                return;
            }

            // 处理目录移动和覆盖逻辑
            $targetDir = $pluginDir . $pluginName;
            $sourceDir = $extractDir . (is_dir($extractDir . '/' . $pluginName) ? '/' . $pluginName : '');

            // 读取新版本信息
            $meta = Anon_System_Plugin::readPluginMetaForDir($sourceDir);
            $newVersion = $meta['version'] ?? '';

            if (is_dir($targetDir) && !$overwrite) {
                $existingMeta = Anon_System_Plugin::readPluginMetaForDir($targetDir);
                $existingVersion = $existingMeta['version'] ?? '';

                self::deleteDirectory($extractDir);
                Anon_Http_Response::success([
                    'needConfirm' => true,
                    'name' => $pluginName,
                    'slug' => strtolower($pluginName),
                    'existingVersion' => $existingVersion,
                    'newVersion' => $newVersion,
                    'upgrade' => version_compare($newVersion, $existingVersion, '>'),
                ], '插件已存在，请确认是否覆盖');
                return;
            }

            if (is_dir($targetDir)) self::deleteDirectory($targetDir);

            if (!rename($sourceDir, $targetDir)) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('移动插件文件失败', 500);
                return;
            }

            self::deleteDirectory($extractDir);
            Anon_System_Plugin::clearScanCache();
            Anon_Http_Response::success(['slug' => strtolower($pluginName)], '上传插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取插件自定义页面配置
     */
    public static function getPage()
    {
        try {
            $slug = trim((string)($_GET['slug'] ?? ''));
            $page = trim((string)($_GET['page'] ?? ''));

            if ($slug === '' || $page === '') {
                Anon_Http_Response::error('参数缺失', 400);
                return;
            }

            $config = self::loadPluginPagesConfig($slug);
            if (!isset($config[$page])) {
                Anon_Http_Response::error('页面不存在', 404);
                return;
            }

            Anon_Http_Response::success($config[$page], '获取页面配置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 插件自定义页面通用操作接口
     */
    public static function pageAction()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $slug = trim((string)($data['slug'] ?? ''));
            $page = trim((string)($data['page'] ?? ''));
            $action = trim((string)($data['action'] ?? ''));
            $actionData = $data['data'] ?? [];

            if ($slug === '' || $page === '' || $action === '') {
                Anon_Http_Response::error('参数缺失', 400);
                return;
            }

            $instance = self::getPluginInstance($slug);
            $config = self::loadPluginPagesConfig($slug, $instance);

            // 优先检查页面配置中的 handler
            if (isset($config[$page]['handler']) && is_callable($config[$page]['handler'])) {
                $result = call_user_func($config[$page]['handler'], $action, $actionData);
                if ($result !== null) {
                    Anon_Http_Response::success($result, '操作成功');
                    return;
                }
            }

            // 调用插件实例处理页面操作
            if ($instance && method_exists($instance, 'handlePageAction')) {
                $result = $instance->handlePageAction($page, $action, $actionData);
                if ($result !== null) {
                    Anon_Http_Response::success($result, '操作成功');
                    return;
                }
            }

            Anon_Http_Response::error('未处理的操作', 400);
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取插件设置项
     */
    public static function getOptions()
    {
        try {
            $slug = trim((string)($_GET['slug'] ?? ''));
            if ($slug === '') {
                Anon_Http_Response::error('插件标识不能为空', 400);
                return;
            }

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
            if (!$resolvedDir) {
                Anon_Http_Response::error('插件不存在', 404);
                return;
            }

            $schema = self::getPluginSettingsSchema(strtolower($slug), $pluginDir . $resolvedDir);
            $values = self::getStoredOptionsValues($slug, $schema);

            Anon_Http_Response::success([
                'slug' => strtolower($slug),
                'schema' => $schema,
                'values' => $values,
            ]);
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存插件设置项
     */
    public static function saveOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $slug = trim((string)($data['slug'] ?? ''));
            if ($slug === '') {
                Anon_Http_Response::error('插件标识不能为空', 400);
                return;
            }

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
            if (!$resolvedDir) {
                Anon_Http_Response::error('插件不存在', 404);
                return;
            }

            $slugLower = strtolower($slug);
            $schema = self::getPluginSettingsSchema($slugLower, $pluginDir . $resolvedDir);
            $submitted = $data['values'] ?? $data;
            unset($submitted['slug']);

            // 读取现有值以保留未提交的字段
            $currentValues = self::getStoredOptionsValues($slug, $schema);
            $displayOnlyTypes = ['badge', 'divider', 'alert', 'notice', 'alert_dialog', 'content', 'heading', 'accordion', 'result', 'card', 'description_list', 'table', 'tooltip', 'tag'];
            $finalValues = [];

            foreach ($schema as $key => $def) {
                if (!is_array($def)) continue;
                if (in_array($def['type'] ?? 'text', $displayOnlyTypes, true)) continue;

                $val = $submitted[$key] ?? $currentValues[$key] ?? $def['default'] ?? null;

                if (isset($def['sanitize_callback']) && is_callable($def['sanitize_callback'])) {
                    $val = call_user_func($def['sanitize_callback'], $val);
                }
                $finalValues[$key] = $val;
            }

            $db = Anon_Database::getInstance();
            $storageKey = 'plugin:' . $slugLower;
            $valueStr = json_encode($finalValues, JSON_UNESCAPED_UNICODE);

            $exists = $db->db('options')->where('name', $storageKey)->count() > 0;
            $ok = $exists
                ? $db->db('options')->where('name', $storageKey)->update(['value' => $valueStr])
                : $db->db('options')->insert(['name' => $storageKey, 'value' => $valueStr]);

            if (!$ok) {
                Anon_Http_Response::error('写入数据库失败', 500);
                return;
            }

            Anon_Cms_Options::clearCache();
            Anon_Http_Response::success([
                'slug' => $slugLower,
                'schema' => $schema,
                'values' => $finalValues,
            ], '保存成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    // --- 私有辅助方法 ---

    private static function getActivePlugins(): array
    {
        $active = Anon_Cms_Options::get('plugins:active', []);
        if (is_string($active)) $active = json_decode($active, true) ?: [];
        return array_map('strtolower', $active);
    }

    private static function setActivePlugins(array $plugins): void
    {
        Anon_Cms_Options::set('plugins:active', $plugins);
        Anon_Cms_Options::clearCache();
    }

    private static function getPluginInstance(string $slug)
    {
        $className = Anon_System_Plugin::getPluginClassNameFromSlug($slug);
        if (!$className) return null;

        if (!class_exists($className, false)) {
            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
            if ($resolvedDir) {
                $mainFile = Anon_System_Plugin::getPluginMainFile($pluginDir . $resolvedDir);
                if ($mainFile) include_once $mainFile;
            }
        }

        if (class_exists($className)) {
            try {
                return new $className($slug);
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    private static function loadPluginPagesConfig(string $slug, $instance = null)
    {
        if (!$instance) $instance = self::getPluginInstance($slug);

        // 优先使用实例方法
        if ($instance && method_exists($instance, 'getPages')) {
            $pages = $instance->getPages();
            return is_array($pages) ? $pages : [];
        }

        // 降级：直接加载 pages.php
        $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
        $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
        if (!$resolvedDir) return [];

        $pagesFile = $pluginDir . $resolvedDir . '/app/pages.php';
        if (file_exists($pagesFile)) {
            $plugin = $instance;
            $pages = include $pagesFile;
            return is_array($pages) ? $pages : [];
        }

        return [];
    }

    private static function getPluginPagesList(string $slug, string $pluginPath): array
    {
        $pages = [];
        $instance = self::getPluginInstance($slug);
        $config = self::loadPluginPagesConfig($slug, $instance);

        foreach ($config as $pageSlug => $pageConfig) {
            $pages[] = [
                'slug' => $pageSlug,
                'title' => $pageConfig['title'] ?? $pageSlug,
            ];
        }
        return $pages;
    }

    private static function getStoredOptionsValues(string $slug, array $schema): array
    {
        $storageKey = 'plugin:' . strtolower($slug);
        $db = Anon_Database::getInstance();
        $row = $db->db('options')->where('name', $storageKey)->first();

        $storedValues = [];
        if ($row && !empty($row['value'])) {
            $decoded = json_decode($row['value'], true);
            if (is_array($decoded)) $storedValues = $decoded;
        }

        $values = [];
        foreach ($schema as $key => $def) {
            if (!is_array($def)) continue;
            $values[$key] = $storedValues[$key] ?? $def['default'] ?? null;
        }
        return $values;
    }

    private static function getPluginSettingsSchema(string $slugLower, string $pluginPath): array
    {
        $className = Anon_System_Plugin::getPluginClassNameFromSlug($slugLower);

        // 尝试加载类
        if ((!$className || !class_exists($className)) && file_exists($pluginPath)) {
            $mainFile = Anon_System_Plugin::getPluginMainFile($pluginPath);
            if ($mainFile) {
                require_once $mainFile;
                $className = Anon_System_Plugin::getPluginClassNameFromSlug($slugLower);
            }
        }

        if ($className && class_exists($className)) {
            foreach (['getSettingsSchema', 'options'] as $method) {
                if (method_exists($className, $method)) {
                    try {
                        $ref = new ReflectionMethod($className, $method);
                        $schema = $ref->isStatic()
                            ? call_user_func([$className, $method])
                            : call_user_func([new $className($slugLower), $method]);

                        if (is_array($schema)) return $schema;
                    } catch (Throwable $e) {
                    }
                }
            }
        }
        return [];
    }

    private static function detectPluginName(string $extractDir, string $zipBasename): ?string
    {
        // 检查是否有单一顶层目录
        $entries = array_diff(scandir($extractDir), ['.', '..']);
        foreach ($entries as $entry) {
            if (is_dir($extractDir . '/' . $entry) && file_exists($extractDir . '/' . $entry . '/Index.php')) {
                return $entry;
            }
        }

        // 检查根目录是否有 Index.php
        if (file_exists($extractDir . '/Index.php')) {
            return preg_match('/^[a-zA-Z0-9_-]+$/', $zipBasename) ? $zipBasename : ('plugin_' . uniqid());
        }

        return null;
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
