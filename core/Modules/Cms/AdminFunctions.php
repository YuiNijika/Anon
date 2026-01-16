<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin
{
    const CMS_ROUTE_PREFIX = '/anon/cms/admin';

    public static function init()
    {
        self::initRoutes();
    }

    /**
     * 添加管理路由
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     * @param array $meta 路由元数据
     */
    public static function addRoute(string $path, callable $handler, array $meta = [])
    {
        // 默认需要管理员权限
        if (!isset($meta['requireAdmin'])) {
            $meta['requireAdmin'] = '需要管理员权限';
        }
        
        // 设置requireAdmin时自动添加requireLogin
        if (!empty($meta['requireAdmin'])) {
            $meta['requireLogin'] = true;
        }
        
        Anon_System_Config::addRoute(self::CMS_ROUTE_PREFIX . $path, $handler, $meta);
    }

    /**
     * 检查用户是否已登录
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return Anon_Check::isLoggedIn();
    }

    /**
     * 检查当前用户是否为管理员
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $userId = Anon_Http_Request::getUserId();
        if (!$userId) {
            return false;
        }
        
        $db = Anon_Database::getInstance();
        return $db->isUserAdmin($userId);
    }


    /**
     * 初始化路由
     */
    public static function initRoutes()
    {
        // Token 接口
        self::addRoute('/auth/token', function () {
            try {
                $userId = Anon_Http_Request::getUserId();
                $username = $_SESSION['username'] ?? '';
                
                if (!$userId || !$username) {
                    Anon_Http_Response::unauthorized('用户未登录，无法获取 Token');
                    return;
                }
                
                $token = Anon_Http_Request::getUserToken($userId, $username);
                
                Anon_Http_Response::success([
                    'token' => $token,
                ], '获取 Token 成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e, '获取 Token 时发生错误');
            }
        }, [
            'requireAdmin' => false,
            'requireLogin' => '请先登录以获取 Token',
            'method' => 'GET',
            'token' => false,
        ]);

        // 检查登录状态接口
        self::addRoute('/auth/check-login', function () {
            try {
                $isLoggedIn = Anon_Check::isLoggedIn();
                $message = $isLoggedIn ? '用户已登录' : '用户未登录';
                
                Anon_Http_Response::success([
                    'loggedIn' => $isLoggedIn,
                    'logged_in' => $isLoggedIn,
                ], $message);
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e, '检查登录状态时发生错误');
            }
        }, [
            'requireAdmin' => false,
            'requireLogin' => false,
            'method' => 'GET',
            'token' => false,
        ]);

        // 获取用户信息接口
        self::addRoute('/user/info', function () {
            try {
                $userInfo = Anon_Http_Request::requireAuth();
                
                Anon_Http_Response::success($userInfo, '获取用户信息成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e, '获取用户信息时发生错误');
            }
        }, [
            'requireLogin' => true,
            'method' => 'GET',
            'token' => true,
        ]);

        // 获取配置信息接口
        self::addRoute('/config', function () {
            try {
                $config = Anon_System_Config::getConfig();
                Anon_Http_Response::success($config, '获取配置信息成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'requireAdmin' => false,
            'requireLogin' => false,
            'method' => 'GET',
            'token' => false,
        ]);

        // 统计接口
        self::addRoute('/statistics', function () {
            try {
                $statistics = Anon_Cms_Statistics::getAll();
                $statistics['attachments_size'] = Anon_Cms_Statistics::getAttachmentsSize();
                $statistics['total_views'] = Anon_Cms_Statistics::getTotalViews();
                
                Anon_Http_Response::success($statistics, '获取统计数据成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        // 基本设置
        self::addRoute('/settings/basic', function () {
            try {
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                
                if ($requestMethod === 'GET') {
                    $uploadAllowedTypesValue = Anon_Cms_Options::get('upload_allowed_types', '');
                    $uploadAllowedTypes = [];
                    
                    // 如果已经是数组则直接使用，否则尝试解析JSON字符串
                    if (is_array($uploadAllowedTypesValue)) {
                        $uploadAllowedTypes = $uploadAllowedTypesValue;
                    } elseif (is_string($uploadAllowedTypesValue) && !empty($uploadAllowedTypesValue)) {
                        $decoded = json_decode($uploadAllowedTypesValue, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $uploadAllowedTypes = $decoded;
                        }
                    }
                    
                    // 新格式为空时从旧字段读取
                    if (empty($uploadAllowedTypes)) {
                        $uploadAllowedTypes = [
                            'image' => Anon_Cms_Options::get('upload_allowed_image', 'gif,jpg,jpeg,png,tiff,bmp,webp,avif'),
                            'media' => Anon_Cms_Options::get('upload_allowed_media', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv'),
                            'document' => Anon_Cms_Options::get('upload_allowed_document', 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'),
                            'other' => Anon_Cms_Options::get('upload_allowed_other', ''),
                        ];
                    }
                    
                    $settings = [
                        'title' => Anon_Cms_Options::get('title', ''),
                        'description' => Anon_Cms_Options::get('description', ''),
                        'keywords' => Anon_Cms_Options::get('keywords', ''),
                        'allow_register' => Anon_Cms_Options::get('allow_register', '0') === '1',
                        'api_prefix' => Anon_Cms_Options::get('apiPrefix', '/api'),
                        'api_enabled' => Anon_Cms_Options::get('api_enabled', '0') === '1',
                        'upload_allowed_types' => $uploadAllowedTypes,
                    ];
                    
                    Anon_Http_Response::success($settings, '获取基本设置成功');
                } elseif ($requestMethod === 'POST') {
                    $data = Anon_Http_Request::getInput();
                    
                    if (empty($data)) {
                        Anon_Http_Response::error('请求数据不能为空', 400);
                        return;
                    }

                    $siteName = isset($data['title']) ? trim($data['title']) : '';
                    $siteDescription = isset($data['description']) ? trim($data['description']) : '';
                    $keywords = isset($data['keywords']) ? trim($data['keywords']) : '';
                    $allowRegister = isset($data['allow_register']) ? ($data['allow_register'] ? '1' : '0') : '0';
                    $apiPrefix = isset($data['api_prefix']) ? trim($data['api_prefix']) : '/api';
                    $apiEnabled = isset($data['api_enabled']) ? ($data['api_enabled'] ? '1' : '0') : '0';
                    
                    $uploadAllowedTypes = [];
                    if (isset($data['upload_allowed_types']) && is_array($data['upload_allowed_types'])) {
                        $uploadAllowedTypes = $data['upload_allowed_types'];
                    } else {
                        // 从旧格式字段读取
                        $uploadAllowedTypes = [
                            'image' => isset($data['upload_allowed_image']) ? trim($data['upload_allowed_image']) : '',
                            'media' => isset($data['upload_allowed_media']) ? trim($data['upload_allowed_media']) : '',
                            'document' => isset($data['upload_allowed_document']) ? trim($data['upload_allowed_document']) : '',
                            'other' => isset($data['upload_allowed_other']) ? trim($data['upload_allowed_other']) : '',
                        ];
                    }

                    if (empty($siteName)) {
                        Anon_Http_Response::error('站点名称不能为空', 400);
                        return;
                    }

                    if (empty($apiPrefix) || $apiPrefix[0] !== '/') {
                        Anon_Http_Response::error('API 前缀必须以 / 开头', 400);
                        return;
                    }

                    Anon_Cms_Options::set('title', $siteName);
                    Anon_Cms_Options::set('description', $siteDescription);
                    Anon_Cms_Options::set('keywords', $keywords);
                    Anon_Cms_Options::set('allow_register', $allowRegister);
                    Anon_Cms_Options::set('apiPrefix', $apiPrefix);
                    Anon_Cms_Options::set('api_enabled', $apiEnabled);
                    Anon_Cms_Options::set('upload_allowed_types', json_encode($uploadAllowedTypes, JSON_UNESCAPED_UNICODE));
                    
                    Anon_Http_Response::success([
                        'title' => $siteName,
                        'description' => $siteDescription,
                        'keywords' => $keywords,
                        'allow_register' => $allowRegister === '1',
                        'api_prefix' => $apiPrefix,
                        'api_enabled' => $apiEnabled === '1',
                        'upload_allowed_types' => $uploadAllowedTypes,
                    ], '保存设置成功');
                } else {
                    Anon_Http_Response::error('不支持的请求方法', 405);
                }
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        // 注册主题截图静态路由
        $screenshotCache = null;
        $nullSvgPath = __DIR__ . '/../../Static/img/null.svg';
        
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
            if (!is_array($decoded) || empty($decoded['screenshot'])) {
                $screenshotCache = $nullSvgPath;
                return $nullSvgPath;
            }
            
            $screenshotFileName = $decoded['screenshot'];
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

        // 主题设置
        self::addRoute('/settings/theme', function () {
            try {
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                
                if ($requestMethod === 'GET') {
                    $currentTheme = Anon_Cms_Options::get('theme', 'default');
                    $allThemes = Anon_Cms_Theme::getAllThemes();
                    
                    Anon_Http_Response::success([
                        'current' => $currentTheme,
                        'themes' => $allThemes,
                    ], '获取主题列表成功');
                } elseif ($requestMethod === 'POST') {
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
                } else {
                    Anon_Http_Response::error('不支持的请求方法', 405);
                }
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => ['GET', 'POST'],
        ]);
    }
}