<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 插件管理类
 */
class Anon_Cms_Admin_Plugins
{
    /**
     * 获取插件列表
     * @return void
     */
    public static function get()
    {
        try {
            // 使用 Anon_Main::APP_DIR 获取插件目录路径
            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            if (!is_dir($pluginDir)) {
                Anon_Http_Response::success(['list' => []], '获取插件列表成功');
                return;
            }

            $activePlugins = self::getActivePlugins();
            $plugins = [];
            $dirs = scandir($pluginDir);

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $pluginPath = $pluginDir . $dir;
                if (!is_dir($pluginPath)) {
                    continue;
                }

                if (Anon_System_Plugin::getPluginMainFile($pluginPath) === null) {
                    continue;
                }

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

                $pluginSlug = strtolower($dir);
                $isActive = in_array($pluginSlug, $activePlugins, true);

                $plugins[] = [
                    'slug' => $pluginSlug,
                    'dir' => $dir,
                    'name' => $meta['name'] ?? $dir,
                    'description' => $meta['description'] ?? '',
                    'version' => $meta['version'] ?? '',
                    'author' => $meta['author'] ?? '',
                    'url' => $meta['url'] ?? '',
                    'mode' => $meta['mode'] ?? 'api',
                    'active' => $isActive,
                ];
            }

            Anon_Http_Response::success(['list' => $plugins], '获取插件列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 激活插件
     * @return void
     */
    public static function activate()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $slug = isset($data['slug']) ? trim($data['slug']) : '';

            if (empty($slug)) {
                Anon_Http_Response::error('插件标识符不能为空', 400);
                return;
            }

            $activePlugins = self::getActivePlugins();
            if (in_array(strtolower($slug), $activePlugins, true)) {
                Anon_Http_Response::success(['slug' => $slug], '插件已激活');
                return;
            }

            $activePlugins[] = strtolower($slug);
            self::setActivePlugins($activePlugins);

            Anon_System_Plugin::clearScanCache();
            
            // 重新加载插件系统以加载新激活的插件
            Anon_System_Plugin::reloadPlugin($slug);

            Anon_Http_Response::success(['slug' => $slug], '激活插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 停用插件
     * @return void
     */
    public static function deactivate()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $slug = isset($data['slug']) ? trim($data['slug']) : '';

            if (empty($slug)) {
                Anon_Http_Response::error('插件标识符不能为空', 400);
                return;
            }

            $activePlugins = self::getActivePlugins();
            $slugLower = strtolower($slug);
            $key = array_search($slugLower, $activePlugins, true);
            if ($key === false) {
                Anon_Http_Response::success(['slug' => $slug], '插件已停用');
                return;
            }

            unset($activePlugins[$key]);
            $activePlugins = array_values($activePlugins);
            self::setActivePlugins($activePlugins);

            Anon_System_Plugin::clearScanCache();
            Anon_System_Plugin::deactivatePlugin($slug);

            Anon_Http_Response::success(['slug' => $slug], '停用插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除插件
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $slug = isset($data['slug']) ? trim($data['slug']) : '';

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

            $activePlugins = self::getActivePlugins();
            $slugLower = strtolower($slug);
            if (in_array($slugLower, $activePlugins, true)) {
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
     * @return void
     */
    public static function upload()
    {
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Anon_Http_Response::error('文件上传失败', 400);
                return;
            }

            $file = $_FILES['file'];
            $fileName = $file['name'];
            $tmpPath = $file['tmp_name'];

            if (!preg_match('/\.(zip)$/i', $fileName)) {
                Anon_Http_Response::error('只支持 ZIP 格式的插件包', 400);
                return;
            }

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            if (!is_dir($pluginDir)) {
                mkdir($pluginDir, 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                Anon_Http_Response::error('无法打开 ZIP 文件', 400);
                return;
            }

            $extractDir = sys_get_temp_dir() . '/anon_plugin_' . uniqid();
            if (!mkdir($extractDir, 0755, true)) {
                $zip->close();
                Anon_Http_Response::error('无法创建临时目录', 500);
                return;
            }

            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('解压 ZIP 文件失败', 500);
                return;
            }

            $zip->close();

            $pluginName = null;
            $entries = scandir($extractDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $entryPath = $extractDir . '/' . $entry;
                if (is_dir($entryPath)) {
                    $pluginName = $entry;
                    break;
                }
            }

            $pluginIndexFile = null;
            if ($pluginName) {
                $pluginIndexFile = $extractDir . '/' . $pluginName . '/Index.php';
                if (!file_exists($pluginIndexFile)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('插件必须包含 Index.php 文件', 400);
                    return;
                }
                $meta = Anon_System_Plugin::readPluginMeta($pluginIndexFile);
                if (!$meta) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('无法读取插件元数据', 400);
                    return;
                }
                $targetDir = $pluginDir . $pluginName;
                if (is_dir($targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('插件已存在', 400);
                    return;
                }
                if (!rename($extractDir . '/' . $pluginName, $targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('移动插件文件失败', 500);
                    return;
                }
            } else {
                $pluginIndexFile = $extractDir . '/Index.php';
                if (!file_exists($pluginIndexFile)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('ZIP 根目录未找到 Index.php，或请使用含单一插件目录的 ZIP', 400);
                    return;
                }
                $meta = Anon_System_Plugin::readPluginMeta($pluginIndexFile);
                if (!$meta) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('无法读取插件元数据', 400);
                    return;
                }
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $pluginName = preg_match('/^[a-zA-Z0-9_-]+$/', $baseName) ? $baseName : ('plugin_' . uniqid());
                $targetDir = $pluginDir . $pluginName;
                if (is_dir($targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('插件已存在', 400);
                    return;
                }
                if (!mkdir($targetDir, 0755, true)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('无法创建插件目录', 500);
                    return;
                }
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $src = $extractDir . '/' . $entry;
                    $dst = $targetDir . '/' . $entry;
                    if (!rename($src, $dst)) {
                        self::deleteDirectory($extractDir);
                        self::deleteDirectory($targetDir);
                        Anon_Http_Response::error('移动插件文件失败', 500);
                        return;
                    }
                }
            }

            self::deleteDirectory($extractDir);
            Anon_System_Plugin::clearScanCache();

            Anon_Http_Response::success(['slug' => strtolower($pluginName)], '上传插件成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取插件设置项。schema 来自插件入口文件中的 options 方法；values 来自 options 表 plugin:slug。
     */
    public static function getOptions()
    {
        try {
            $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
            if ($slug === '') {
                Anon_Http_Response::error('插件标识不能为空', 400);
                return;
            }
            $slugLower = strtolower($slug);
            $storageKey = 'plugin:' . $slugLower;

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
            if ($resolvedDir === null) {
                Anon_Http_Response::error('插件不存在', 404);
                return;
            }
            $pluginPath = $pluginDir . $resolvedDir;
            $schema = self::getPluginSettingsSchema($slugLower, $pluginPath);

            $db = Anon_Database::getInstance();
            $row = $db->db('options')->where('name', $storageKey)->first();
            $values = [];
            if ($row && isset($row['value']) && $row['value'] !== '' && $row['value'] !== null) {
                $v = $row['value'];
                if (is_string($v) && (substr($v, 0, 1) === '{' || substr($v, 0, 1) === '[')) {
                    $dec = json_decode($v, true);
                    if (is_array($dec)) {
                        $values = $dec;
                    }
                }
            }
            foreach ($schema as $key => $def) {
                if (!is_array($def)) {
                    continue;
                }
                if (!array_key_exists($key, $values)) {
                    $values[$key] = $def['default'] ?? null;
                }
            }
            Anon_Http_Response::success([
                'slug' => $slugLower,
                'schema' => $schema,
                'values' => $values,
            ]);
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存插件设置项。options 表 name = plugin:插件名，value 为 JSON。
     */
    public static function saveOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            if (!is_array($data)) {
                $data = [];
            }
            $slug = isset($data['slug']) ? trim((string)$data['slug']) : '';
            if ($slug === '') {
                Anon_Http_Response::error('插件标识不能为空', 400);
                return;
            }
            $slugLower = strtolower($slug);
            $storageKey = 'plugin:' . $slugLower;

            $pluginDir = Anon_Main::APP_DIR . 'Plugin/';
            $resolvedDir = Anon_System_Plugin::resolvePluginDir($pluginDir, $slug);
            if ($resolvedDir === null) {
                Anon_Http_Response::error('插件不存在', 404);
                return;
            }
            $pluginPath = $pluginDir . $resolvedDir;
            $schema = self::getPluginSettingsSchema($slugLower, $pluginPath);

            $submitted = isset($data['values']) && is_array($data['values']) ? $data['values'] : $data;
            unset($submitted['slug']);

            $db = Anon_Database::getInstance();
            $row = $db->db('options')->where('name', $storageKey)->first();
            $currentDbValues = [];
            if ($row && isset($row['value']) && $row['value'] !== '' && $row['value'] !== null) {
                $v = $row['value'];
                if (is_string($v) && (substr($v, 0, 1) === '{' || substr($v, 0, 1) === '[')) {
                    $dec = json_decode($v, true);
                    if (is_array($dec)) {
                        $currentDbValues = $dec;
                    }
                }
            }
            $finalValues = [];
            foreach ($schema as $key => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $val = array_key_exists($key, $submitted) ? $submitted[$key]
                    : (array_key_exists($key, $currentDbValues) ? $currentDbValues[$key] : ($def['default'] ?? null));
                if (isset($def['sanitize_callback']) && is_callable($def['sanitize_callback'])) {
                    $val = call_user_func($def['sanitize_callback'], $val);
                }
                $finalValues[$key] = $val;
            }
            $valueStr = json_encode($finalValues, JSON_UNESCAPED_UNICODE);
            if ($row && isset($row['name'])) {
                $ok = $db->db('options')->where('name', $storageKey)->update(['value' => $valueStr]);
            } else {
                $ok = $db->db('options')->insert(['name' => $storageKey, 'value' => $valueStr]);
            }
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

    /**
     * 从插件入口文件 getSettingsSchema 或 options 方法获取设置 schema，不依赖 package.json
     * @param string $slugLower 插件标识符小写
     * @param string $pluginPath 插件目录绝对路径
     * @return array
     */
    private static function getPluginSettingsSchema(string $slugLower, string $pluginPath): array
    {
        $pluginInfo = Anon_System_Plugin::getPlugin($slugLower);
        if ($pluginInfo && isset($pluginInfo['class']) && is_string($pluginInfo['class'])) {
            $className = $pluginInfo['class'];
            if (method_exists($className, 'getSettingsSchema')) {
                $schema = call_user_func([$className, 'getSettingsSchema']);
                return is_array($schema) ? $schema : [];
            }
            if (method_exists($className, 'options')) {
                $schema = call_user_func([$className, 'options']);
                return is_array($schema) ? $schema : [];
            }
        }
        $mainFile = Anon_System_Plugin::getPluginMainFile($pluginPath);
        if ($mainFile === null) {
            return [];
        }
        require_once $mainFile;
        $className = Anon_System_Plugin::getPluginClassNameFromSlug($slugLower);
        if ($className === null || !class_exists($className)) {
            return [];
        }
        if (method_exists($className, 'getSettingsSchema')) {
            $schema = call_user_func([$className, 'getSettingsSchema']);
            return is_array($schema) ? $schema : [];
        }
        if (method_exists($className, 'options')) {
            $schema = call_user_func([$className, 'options']);
            return is_array($schema) ? $schema : [];
        }
        return [];
    }

    /**
     * 获取已激活的插件列表
     * @return array
     */
    private static function getActivePlugins(): array
    {
        $active = Anon_Cms_Options::get('plugins:active', []);
        if (is_string($active)) {
            $active = json_decode($active, true);
            if (!is_array($active)) {
                $active = [];
            }
        }
        return array_map('strtolower', $active);
    }

    /**
     * 设置已激活的插件列表
     * @param array $plugins 插件列表
     * @return void
     */
    private static function setActivePlugins(array $plugins): void
    {
        Anon_Cms_Options::set('plugins:active', $plugins);
        Anon_Cms_Options::clearCache();
    }

    /**
     * 递归删除目录
     * @param string $dir 目录路径
     * @return void
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

