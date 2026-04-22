<?php
namespace Anon\Modules\Cms\AdminIndex;
use Anon\Main;


use Anon\Modules\Cms\ThemeOptions as ThemeOptions;







use Exception;
use ZipArchive;
use Index;

use Anon\Modules\Cms\Theme\Theme;
use Anon\Modules\Cms\Cms;
use Anon\Modules\Database\Database;
use Anon\Modules\Debug;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Config;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 主题管理类
 */
class Themes
{
    /**
     * 上传主题
     * @return void
     */
    public static function upload()
    {
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                ResponseHelper::error('文件上传失败', null, 400);
                return;
            }

            $file = $_FILES['file'];
            $fileName = $file['name'];
            $tmpPath = $file['tmp_name'];

            if (!preg_match('/\.(zip)$/i', $fileName)) {
                ResponseHelper::error('只支持 ZIP 格式的主题包', null, 400);
                return;
            }

            $themeDir = Main::APP_DIR . 'Theme/';
            if (!is_dir($themeDir)) {
                mkdir($themeDir, 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                ResponseHelper::error('无法打开 ZIP 文件', null, 400);
                return;
            }

            $extractDir = sys_get_temp_dir() . '/anon_theme_' . uniqid();
            if (!mkdir($extractDir, 0755, true)) {
                $zip->close();
                ResponseHelper::error('无法创建临时目录', null, 500);
                return;
            }

            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                self::deleteDirectory($extractDir);
                ResponseHelper::error('解压 ZIP 文件失败', null, 500);
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
                    ResponseHelper::error('主题已存在', null, 400);
                    return;
                }
                if (!rename($extractDir . '/' . $themeName, $targetDir)) {
                    self::deleteDirectory($extractDir);
                    ResponseHelper::error('移动主题文件失败', null, 500);
                    return;
                }
            } else {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $themeName = preg_match('/^[a-zA-Z0-9_-]+$/', $baseName) ? $baseName : ('theme_' . uniqid());
                $targetDir = $themeDir . $themeName;
                if (is_dir($targetDir)) {
                    self::deleteDirectory($extractDir);
                    ResponseHelper::error('主题已存在', null, 400);
                    return;
                }
                if (!mkdir($targetDir, 0755, true)) {
                    self::deleteDirectory($extractDir);
                    ResponseHelper::error('无法创建主题目录', null, 500);
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
                        ResponseHelper::error('移动主题文件失败', null, 500);
                        return;
                    }
                }
            }

            self::deleteDirectory($extractDir);
            Options::clearCache();

            ResponseHelper::success(['name' => $themeName], '上传主题成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 删除主题
     * @return void
     */
    public static function delete()
    {
        try {
            $data = RequestHelper::getInput();
            $themeName = isset($data['name']) ? trim($data['name']) : '';

            if (empty($themeName)) {
                ResponseHelper::error('主题名称不能为空', null, 400);
                return;
            }

            $currentTheme = Options::get('theme', 'default');
            if ($themeName === $currentTheme) {
                ResponseHelper::error('不能删除当前使用的主题', null, 400);
                return;
            }

            $themeDir = Main::APP_DIR . 'Theme/';
            $themePath = $themeDir . $themeName;

            if (!is_dir($themePath)) {
                ResponseHelper::error('主题不存在', null, 404);
                return;
            }

            self::deleteDirectory($themePath);
            Options::clearCache();

            ResponseHelper::success(['name' => $themeName], '删除主题成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 获取主题列表
     * @return void
     */
    public static function get()
    {
        try {
            $currentTheme = Options::get('theme', 'default');
            $allThemes = Theme::getAllThemes();

            ResponseHelper::success([
                'current' => $currentTheme,
                'themes' => $allThemes,
            ], '获取主题列表成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 切换主题
     * @return void
     */
    public static function switch()
    {
        try {
            $data = RequestHelper::getInput();

            if (empty($data) || !isset($data['theme'])) {
                ResponseHelper::error('主题名称不能为空', null, 400);
                return;
            }

            $themeName = trim($data['theme']);
            if (empty($themeName)) {
                ResponseHelper::error('主题名称不能为空', null, 400);
                return;
            }

            $allThemes = Theme::getAllThemes();
            $themeExists = false;
            foreach ($allThemes as $theme) {
                if ($theme['name'] === $themeName) {
                    $themeExists = true;
                    break;
                }
            }

            if (!$themeExists) {
                ResponseHelper::error('主题不存在', null, 400);
                return;
            }

            Options::set('theme', $themeName);
            Theme::ensureThemeOptionsLoaded($themeName);
            Options::clearCache();

            ResponseHelper::success([
                'theme' => $themeName,
            ], '切换主题成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
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
            $data = RequestHelper::getInput();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'GET' && !empty($_GET)) {
                $data = is_array($data) ? array_merge($data, $_GET) : $_GET;
            }
            $themeName = isset($data['theme']) ? trim((string) $data['theme']) : null;
            if ($themeName === null || $themeName === '') {
                $themeName = Options::get('theme', 'default');
            }
            $canonicalTheme = Theme::getCanonicalThemeName($themeName);
            Theme::ensureThemeOptionsLoaded($themeName);
            $schema = Theme::getSchemaFromSetupFile($canonicalTheme);
            $stored = ThemeOptions::all($canonicalTheme);
            $values = [];
            foreach ($schema as $groupItems) {
                if (!is_array($groupItems)) {
                    continue;
                }
                foreach ($groupItems as $key => $def) {
                    $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : ($def['default'] ?? null);
                }
            }
            ResponseHelper::success([
                'theme' => $canonicalTheme,
                'schema' => $schema,
                'values' => $values,
            ], '获取主题设置项成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 保存主题设置项
     * 在 options 表内按 name = theme:当前主题名 判断：有则更新 value，无则用 setup 默认值插入。
     */
    public static function saveOptions()
    {
        try {
            $data = RequestHelper::getInput();
            if (!is_array($data)) {
                $data = [];
            }

            $themeName = isset($data['theme']) ? trim((string)$data['theme']) : Options::get('theme', 'default');
            $canonicalTheme = Theme::getCanonicalThemeName($themeName);
            $storageKey = 'theme:' . strtolower($canonicalTheme);

            $schema = Theme::getSchemaFromSetupFile($canonicalTheme);
            if (empty($schema)) {
                ResponseHelper::error('当前主题无设置定义(setup.php)', null, 400);
                return;
            }

            $submitted = isset($data['values']) && is_array($data['values']) ? $data['values'] : $data;
            unset($submitted['theme']);

            $db = Database::getInstance();
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

            /** 仅展示、不参与存储的选项类型，与前端 DISPLAY_ONLY_TYPES 一致 */
            $displayOnlyTypes = ['badge', 'divider', 'alert', 'notice', 'alert_dialog', 'content', 'heading', 'accordion', 'result', 'card', 'description_list', 'table', 'tooltip', 'tag'];
            $finalValues = [];
            $errors = [];
            foreach ($schema as $items) {
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $key => $def) {
                    $type = isset($def['type']) ? (string) $def['type'] : 'text';
                    if (in_array($type, $displayOnlyTypes, true)) {
                        continue;
                    }
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
                ResponseHelper::error(implode('; ', $errors), null, 400);
                return;
            }

            $valueStr = json_encode($finalValues, JSON_UNESCAPED_UNICODE);

            if ($row && isset($row['name'])) {
                $ok = $db->db('options')->where('name', $storageKey)->update(['value' => $valueStr]);
            } else {
                $ok = $db->db('options')->insert(['name' => $storageKey, 'value' => $valueStr]);
            }

            // 注意：update 返回影响的行数（0 或更多），false 表示错误
            // 如果数据相同，affected_rows 为 0，这不是错误
            if ($ok === false) {
                // 获取详细的数据库错误信息
                $dbError = 'Unknown';
                if (method_exists($db, 'error')) {
                    $dbError = $db->error();
                }
                if (method_exists($db, 'getLastError')) {
                    $dbError = $db->getLastError();
                }
                
                $debugInfo = [
                    'storageKey' => $storageKey,
                    'operation' => ($row && isset($row['name'])) ? 'update' : 'insert',
                    'valueLength' => strlen($valueStr),
                    'dbError' => $dbError,
                    'finalValues' => $finalValues,
                ];
                
                // 记录详细错误到 debug 日志
                Debug::debug('主题设置保存失败', $debugInfo);
                
                ResponseHelper::error('写入数据库失败', null, 500);
                return;
            }
            Options::clearCache();
            ResponseHelper::success([
                'theme' => $canonicalTheme,
                'schema' => $schema,
                'values' => $finalValues,
            ], '保存成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 初始化主题静态路由
     * @return void
     */
    public static function initStaticRoutes()
    {
        $screenshotCache = null;
        $nullSvgPath = __DIR__ . '/../../../../Static/img/null.svg';

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

            $themesDir = Main::APP_DIR . 'Theme/';
            $themePath = Cms::findDirectoryCaseInsensitive($themesDir, $themeName);

            if ($themePath === null || !is_dir($themePath)) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }

            $infoFile = null;
            $themeItems = Cms::scanDirectory($themePath);
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
                $screenshotFile = Cms::findFileCaseInsensitive($themePath, pathinfo($screenshotFileName, PATHINFO_FILENAME), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']);
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

        Config::addStaticRoute(
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

        Config::addStaticRoute(
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
