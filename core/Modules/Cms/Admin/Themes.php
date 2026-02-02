<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 主题管理，含上传删除、设置获取与保存
 */
class Anon_Cms_Admin_Themes
{
    /**
     * 上传主题
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
                Anon_Http_Response::error('只支持 ZIP 格式的主题包', 400);
                return;
            }

            $themeDir = Anon_Main::APP_DIR . 'Theme/';
            if (!is_dir($themeDir)) {
                mkdir($themeDir, 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                Anon_Http_Response::error('无法打开 ZIP 文件', 400);
                return;
            }

            $extractDir = sys_get_temp_dir() . '/anon_theme_' . uniqid();
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

            $themeName = null;
            $entries = scandir($extractDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $entryPath = $extractDir . '/' . $entry;
                if (is_dir($entryPath)) {
                    $themeName = $entry;
                    break;
                }
            }

            $targetDir = null;
            if ($themeName) {
                $targetDir = $themeDir . $themeName;
                if (is_dir($targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('主题已存在', 400);
                    return;
                }
                if (!rename($extractDir . '/' . $themeName, $targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('移动主题文件失败', 500);
                    return;
                }
            } else {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $themeName = preg_match('/^[a-zA-Z0-9_-]+$/', $baseName) ? $baseName : ('theme_' . uniqid());
                $targetDir = $themeDir . $themeName;
                if (is_dir($targetDir)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('主题已存在', 400);
                    return;
                }
                if (!mkdir($targetDir, 0755, true)) {
                    self::deleteDirectory($extractDir);
                    Anon_Http_Response::error('无法创建主题目录', 500);
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
                        Anon_Http_Response::error('移动主题文件失败', 500);
                        return;
                    }
                }
            }

            self::deleteDirectory($extractDir);
            Anon_Cms_Options::clearCache();

            Anon_Http_Response::success(['name' => $themeName], '上传主题成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除主题
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $themeName = isset($data['name']) ? trim($data['name']) : '';

            if (empty($themeName)) {
                Anon_Http_Response::error('主题名称不能为空', 400);
                return;
            }

            $currentTheme = Anon_Cms_Options::get('theme', 'default');
            if ($themeName === $currentTheme) {
                Anon_Http_Response::error('不能删除当前使用的主题', 400);
                return;
            }

            $themeDir = Anon_Main::APP_DIR . 'Theme/';
            $themePath = $themeDir . $themeName;

            if (!is_dir($themePath)) {
                Anon_Http_Response::error('主题不存在', 404);
                return;
            }

            self::deleteDirectory($themePath);
            Anon_Cms_Options::clearCache();

            Anon_Http_Response::success(['name' => $themeName], '删除主题成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取主题列表
     * @return void
     */
    public static function get()
    {
        try {
            $currentTheme = Anon_Cms_Options::get('theme', 'default');
            $allThemes = Anon_Cms_Theme::getAllThemes();
            
            Anon_Http_Response::success([
                'current' => $currentTheme,
                'themes' => $allThemes,
            ], '获取主题列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 切换主题
     * @return void
     */
    public static function switch()
    {
        try {
            $data = Anon_Http_Request::getInput();
            
            if (empty($data) || !isset($data['theme'])) {
                Anon_Http_Response::error('主题名称不能为空', 400);
                return;
            }
            
            $themeName = trim($data['theme']);
            if (empty($themeName)) {
                Anon_Http_Response::error('主题名称不能为空', 400);
                return;
            }
            
            $allThemes = Anon_Cms_Theme::getAllThemes();
            $themeExists = false;
            foreach ($allThemes as $theme) {
                if ($theme['name'] === $themeName) {
                    $themeExists = true;
                    break;
                }
            }
            
            if (!$themeExists) {
                Anon_Http_Response::error('主题不存在', 400);
                return;
            }
            
            Anon_Cms_Options::set('theme', $themeName);
            Anon_Cms_Theme::ensureThemeOptionsLoaded($themeName);
            Anon_Cms_Options::clearCache();

            Anon_Http_Response::success([
                'theme' => $themeName,
            ], '切换主题成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取主题设置项
     *
     * schema 从主题目录 app/setup.php 读取；values 从 options 表读取。options 表中 name 列为 theme:{主题名}、theme:{主题名}:settings，首次获取或首次切换主题时自动写入默认值。
     *
     * @return void
     */
    public static function getOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'GET' && !empty($_GET)) {
                $data = is_array($data) ? array_merge($data, $_GET) : $_GET;
            }
            $themeName = isset($data['theme']) ? trim((string) $data['theme']) : null;
            if ($themeName === null || $themeName === '') {
                $themeName = Anon_Cms_Options::get('theme', 'default');
            }
            $canonicalTheme = Anon_Cms_Theme::getCanonicalThemeName($themeName);
            Anon_Cms_Theme::ensureThemeOptionsLoaded($themeName);
            $schema = Anon_Cms_Theme::getSchemaFromSetupFile($canonicalTheme);
            $stored = Anon_Theme_Options::all($canonicalTheme);
            $values = [];
            foreach ($schema as $groupItems) {
                if (!is_array($groupItems)) {
                    continue;
                }
                foreach ($groupItems as $key => $def) {
                    $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : ($def['default'] ?? null);
                }
            }
            Anon_Http_Response::success([
                'theme' => $canonicalTheme,
                'schema' => $schema,
                'values' => $values,
            ], '获取主题设置项成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存主题设置项
     *
     * schema 从主题 setup 文件读取用于校验与清理，仅将 values 写入数据库。
     *
     * @return void
     */
    /**
     * 保存主题设置项
     * 在 options 表内按 name = theme:当前主题名 判断：有则更新 value，无则用 setup 默认值插入。
     */
    public static function saveOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            if (!is_array($data)) {
                $data = [];
            }

            $themeName = isset($data['theme']) ? trim((string)$data['theme']) : Anon_Cms_Options::get('theme', 'default');
            $canonicalTheme = Anon_Cms_Theme::getCanonicalThemeName($themeName);
            $storageKey = 'theme:' . strtolower($canonicalTheme);

            $schema = Anon_Cms_Theme::getSchemaFromSetupFile($canonicalTheme);
            if (empty($schema)) {
                Anon_Http_Response::error('当前主题无设置定义(setup.php)', 400);
                return;
            }

            $submitted = isset($data['values']) && is_array($data['values']) ? $data['values'] : $data;
            unset($submitted['theme']);

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
            $errors = [];
            foreach ($schema as $items) {
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $key => $def) {
                    $val = array_key_exists($key, $submitted) ? $submitted[$key]
                        : (array_key_exists($key, $currentDbValues) ? $currentDbValues[$key] : ($def['default'] ?? null));

                    if (isset($def['validate_callback']) && is_callable($def['validate_callback']) && call_user_func($def['validate_callback'], $val) === false) {
                        $errors[] = ($def['label'] ?? $key) . ' 格式错误';
                        continue;
                    }
                    if (isset($def['sanitize_callback']) && is_callable($def['sanitize_callback'])) {
                        $val = call_user_func($def['sanitize_callback'], $val);
                    }
                    $finalValues[$key] = $val;
                }
            }
            if (!empty($errors)) {
                Anon_Http_Response::error(implode('; ', $errors), 400);
                return;
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
                'theme' => $canonicalTheme,
                'schema' => $schema,
                'values' => $finalValues,
            ], '保存成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 初始化主题静态路由
     * @return void
     */
    public static function initStaticRoutes()
    {
        $screenshotCache = null;
        $nullSvgPath = __DIR__ . '/../../../Static/img/null.svg';
        
        /**
         * 获取主题截图文件路径
         * @return string|null 文件路径，失败返回 null
         */
        $getScreenshotFile = function () use (&$screenshotCache, $nullSvgPath) {
            if ($screenshotCache !== null) {
                return $screenshotCache;
            }
            
            $themeName = $_GET['themeName'] ?? '';
            
            if (empty($themeName)) {
                $requestPath = $_SERVER['REQUEST_URI'] ?? '';
                $requestPath = preg_replace('#/+#', '/', $requestPath);
                if (preg_match('#/anon/static/cms/theme/([^/]+)/screenshot#', $requestPath, $matches)) {
                    $themeName = $matches[1];
                }
            }
            
            $themeName = trim($themeName, '/ ');
            
            if (empty($themeName)) {
                $requestPath = $_SERVER['REQUEST_URI'] ?? '';
                $requestPath = preg_replace('#/+#', '/', $requestPath);
                $parts = explode('/', trim($requestPath, '/'));
                $themeIndex = array_search('theme', $parts);
                if ($themeIndex !== false && isset($parts[$themeIndex + 1])) {
                    $themeName = $parts[$themeIndex + 1];
                }
            }
            
            if (empty($themeName)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $themesDir = Anon_Main::APP_DIR . 'Theme/';
            $themePath = Anon_Cms::findDirectoryCaseInsensitive($themesDir, $themeName);
            
            if ($themePath === null || !is_dir($themePath)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
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
            
            if (!$infoFile || !file_exists($infoFile)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $jsonContent = file_get_contents($infoFile);
            if ($jsonContent === false) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $decoded = json_decode($jsonContent, true);
            if (!is_array($decoded)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $themeInfo = $decoded;
            if (isset($decoded['anon']) && is_array($decoded['anon'])) {
                $themeInfo = array_merge($decoded, $decoded['anon']);
            }
            
            if (empty($themeInfo['screenshot'])) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $screenshotFileName = $themeInfo['screenshot'];
            $screenshotFile = $themePath . DIRECTORY_SEPARATOR . $screenshotFileName;
            
            if (!file_exists($screenshotFile)) {
                $screenshotFile = Anon_Cms::findFileCaseInsensitive($themePath, pathinfo($screenshotFileName, PATHINFO_FILENAME), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']);
            }
            
            if (!$screenshotFile || !file_exists($screenshotFile) || !is_readable($screenshotFile)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $screenshotCache = $screenshotFile;
            return $screenshotFile;
        };
        
        /**
         * 获取主题截图文件的 MIME 类型
         * @return string MIME 类型
         */
        $getMimeType = function () use ($getScreenshotFile) {
            $screenshotFile = $getScreenshotFile();
            if (!$screenshotFile) {
                return 'image/svg+xml';
            }
            
            $ext = strtolower(pathinfo($screenshotFile, PATHINFO_EXTENSION));
            $mimeTypes = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
            ];
            return $mimeTypes[$ext] ?? 'image/svg+xml';
        };
        
        Anon_System_Config::addStaticRoute(
            '/anon/static/cms/theme/{themeName}/screenshot',
            $getScreenshotFile,
            $getMimeType,
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

        Anon_System_Config::addStaticRoute(
            '/anon/static/img/null',
            $nullSvgPath,
            'image/svg+xml',
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

