<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/SettingsBasic.php';
require_once __DIR__ . '/SettingsTheme.php';
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/Tags.php';
require_once __DIR__ . '/Attachments.php';
require_once __DIR__ . '/Posts.php';

class Anon_Cms_Admin
{
    /**
     * 获取 CMS 路由前缀
     * 根据 apiPrefix 配置动态生成，如果 apiPrefix 为空则使用 /anon
     * @return string
     */
    public static function getRoutePrefix(): string
    {
        $apiPrefix = Anon_Cms_Options::get('apiPrefix', '');
        // 如果 apiPrefix 为空，使用 /anon 作为默认值
        $prefix = empty($apiPrefix) ? '/anon' : ($apiPrefix[0] === '/' ? $apiPrefix : '/' . $apiPrefix);
        return $prefix . '/cms/admin';
    }

    /**
     * 初始化管理模块
     * @return void
     */
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
        
        Anon_System_Config::addRoute(self::getRoutePrefix() . $path, $handler, $meta);
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
     * 注册所有管理后台相关的 API 路由
     * @return void
     */
    public static function initRoutes()
    {
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

        self::addRoute('/settings/basic', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_SettingsBasic::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_SettingsBasic::save();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        Anon_Cms_Admin_SettingsTheme::initStaticRoutes();

        self::addRoute('/settings/theme', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_SettingsTheme::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_SettingsTheme::switch();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
        ]);

        self::addRoute('/settings/theme-options', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_SettingsTheme::getOptions();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_SettingsTheme::saveOptions();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        self::addRoute('/metas/categories', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Categories::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Categories::create();
            } elseif ($requestMethod === 'PUT') {
                Anon_Cms_Admin_Categories::update();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Categories::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/metas/tags', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Tags::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Tags::create();
            } elseif ($requestMethod === 'PUT') {
                Anon_Cms_Admin_Tags::update();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Tags::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        Anon_Cms_Admin_Attachments::initStaticRoutes();

        self::addRoute('/attachments', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Attachments::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Attachments::upload();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Attachments::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/posts', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $data = Anon_Http_Request::getInput();
                $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
                if ($id > 0) {
                    Anon_Cms_Admin_Posts::getOne($id);
                } else {
                    Anon_Cms_Admin_Posts::getList();
                }
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Posts::create();
            } elseif ($requestMethod === 'PUT') {
                Anon_Cms_Admin_Posts::update();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Posts::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);
    }
}
