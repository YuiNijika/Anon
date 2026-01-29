<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 主题设置管理类
 * 处理主题设置相关的功能
 */
class Anon_Cms_Admin_SettingsTheme
{
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
            
            if ($infoFile === null && $themeItems !== null) {
                foreach ($themeItems as $themeItem) {
                    if (strtolower($themeItem) === 'info.json') {
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
     * @return void
     */
    public static function getOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $themeName = isset($data['theme']) ? trim($data['theme']) : null;
            
            if ($themeName === null || $themeName === '') {
                $themeName = Anon_Cms_Options::get('theme', 'default');
            }
            
            $schema = Anon_Theme_Options::schema($themeName);
            $values = Anon_Theme_Options::all($themeName);
            
            Anon_Http_Response::success([
                'theme' => $themeName,
                'schema' => $schema,
                'values' => $values,
            ], '获取主题设置项成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存主题设置项
     * @return void
     */
    public static function saveOptions()
    {
        try {
            $data = Anon_Http_Request::getInput();
            
            if (empty($data)) {
                Anon_Http_Response::error('请求数据不能为空', 400);
                return;
            }
            
            $themeName = isset($data['theme']) ? trim($data['theme']) : null;
            if ($themeName === null || $themeName === '') {
                $themeName = Anon_Cms_Options::get('theme', 'default');
            }
            
            $values = isset($data['values']) && is_array($data['values']) ? $data['values'] : [];
            
            if (empty($values)) {
                Anon_Http_Response::error('设置值不能为空', 400);
                return;
            }
            
            /**
             * 验证并保存设置项
             */
            $schema = Anon_Theme_Options::schema($themeName);
            $errors = [];
            $validatedValues = [];
            
            foreach ($values as $key => $value) {
                if (!isset($schema[$key])) {
                    continue;
                }
                
                $def = $schema[$key];
                
                /**
                 * 执行验证回调
                 */
                if (isset($def['validate_callback']) && is_callable($def['validate_callback'])) {
                    $valid = call_user_func($def['validate_callback'], $value);
                    if ($valid === false) {
                        $label = $def['label'] ?? $key;
                        $errors[] = "设置项 {$label} 验证失败";
                        continue;
                    }
                }
                
                /**
                 * 执行清理回调
                 */
                if (isset($def['sanitize_callback']) && is_callable($def['sanitize_callback'])) {
                    $value = call_user_func($def['sanitize_callback'], $value);
                }
                
                $validatedValues[$key] = $value;
            }
            
            if (!empty($errors)) {
                Anon_Http_Response::error(implode('; ', $errors), 400);
                return;
            }
            
            if (empty($validatedValues)) {
                Anon_Http_Response::error('没有有效的设置项', 400);
                return;
            }
            
            $result = Anon_Theme_Options::setMany($validatedValues, $themeName);
            
            if (!$result) {
                Anon_Http_Response::error('保存主题设置项失败', 500);
                return;
            }
            
            Anon_Cms_Options::clearCache();
            
            Anon_Http_Response::success([
                'theme' => $themeName,
                'values' => $validatedValues,
            ], '保存主题设置项成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

