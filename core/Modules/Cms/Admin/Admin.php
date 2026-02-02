<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/Settings/Basic.php';
require_once __DIR__ . '/Settings/Permission.php';
require_once __DIR__ . '/Manage/Categories.php';
require_once __DIR__ . '/Manage/Tags.php';
require_once __DIR__ . '/Manage/Attachments.php';
require_once __DIR__ . '/Manage/Posts.php';
require_once __DIR__ . '/Manage/Users.php';
require_once __DIR__ . '/Index/Statistics.php';
require_once __DIR__ . '/Index/Plugins.php';
require_once __DIR__ . '/Index/Themes.php';
require_once __DIR__ . '/UI/Navbar.php';

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
        self::addRoute('/navbar', function () {
            Anon_Cms_Admin_UI_Navbar::get();
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/statistics', function () {
            try {
                $statistics = Anon_Cms_Statistics::getAll();
                $statistics['attachments_size'] = Anon_Cms_Statistics::getAttachmentsSize();
                $statistics['total_views'] = Anon_Cms_Statistics::getTotalViews();
                $statistics['views_trend'] = Anon_Cms_Statistics::getViewsTrend(7);
                
                Anon_Http_Response::success($statistics, '获取统计数据成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/statistics/views-trend', function () {
            try {
                $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
                if (!in_array($days, [7, 14, 30])) {
                    $days = 7;
                }
                
                $trend = Anon_Cms_Statistics::getViewsTrend($days);
                
                Anon_Http_Response::success($trend, '获取访问趋势数据成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/statistics/access-logs', function () {
            try {
                $options = [];
                
                // 分页参数
                if (isset($_GET['page'])) {
                    $options['page'] = (int)$_GET['page'];
                }
                if (isset($_GET['page_size'])) {
                    $options['page_size'] = (int)$_GET['page_size'];
                }
                
                // 筛选参数
                if (isset($_GET['ip']) && !empty($_GET['ip'])) {
                    $options['ip'] = trim($_GET['ip']);
                }
                if (isset($_GET['path']) && !empty($_GET['path'])) {
                    $options['path'] = trim($_GET['path']);
                }
                if (isset($_GET['type']) && !empty($_GET['type'])) {
                    $options['type'] = trim($_GET['type']);
                }
                if (isset($_GET['user_agent']) && !empty($_GET['user_agent'])) {
                    $options['user_agent'] = trim($_GET['user_agent']);
                }
                if (isset($_GET['status_code']) && !empty($_GET['status_code'])) {
                    $options['status_code'] = (int)$_GET['status_code'];
                }
                if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                    $options['start_date'] = trim($_GET['start_date']);
                }
                if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                    $options['end_date'] = trim($_GET['end_date']);
                }
                
                $result = Anon_Cms_AccessLog::getLogs($options);
                
                Anon_Http_Response::success($result, '获取访问日志成功');
            } catch (Exception $e) {
                Anon_Http_Response::handleException($e);
            }
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/statistics/access-stats', function () {
            try {
                $options = [];
                
                // 筛选参数
                if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                    $options['start_date'] = trim($_GET['start_date']);
                }
                if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                    $options['end_date'] = trim($_GET['end_date']);
                }
                if (isset($_GET['ip']) && !empty($_GET['ip'])) {
                    $options['ip'] = trim($_GET['ip']);
                }
                if (isset($_GET['path']) && !empty($_GET['path'])) {
                    $options['path'] = trim($_GET['path']);
                }
                if (isset($_GET['type']) && !empty($_GET['type'])) {
                    $options['type'] = trim($_GET['type']);
                }
                
                $stats = Anon_Cms_AccessLog::getStatistics($options);
                
                Anon_Http_Response::success($stats, '获取访问统计成功');
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

        self::addRoute('/settings/permission', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_SettingsPermission::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_SettingsPermission::save();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);


        Anon_Cms_Admin_Themes::initStaticRoutes();

        self::addRoute('/settings/theme', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Themes::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Themes::switch();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
        ]);

        self::addRoute('/settings/theme-options', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Themes::getOptions();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Themes::saveOptions();
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

        self::addRoute('/users', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Users::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Users::create();
            } elseif ($requestMethod === 'PUT') {
                Anon_Cms_Admin_Users::update();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Users::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/plugins', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Anon_Cms_Admin_Plugins::get();
            } elseif ($requestMethod === 'POST') {
                Anon_Cms_Admin_Plugins::upload();
            } elseif ($requestMethod === 'PUT') {
                $data = Anon_Http_Request::getInput();
                $action = isset($data['action']) ? $data['action'] : '';
                if ($action === 'activate') {
                    Anon_Cms_Admin_Plugins::activate();
                } elseif ($action === 'deactivate') {
                    Anon_Cms_Admin_Plugins::deactivate();
                } else {
                    Anon_Http_Response::error('无效的操作', 400);
                }
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Plugins::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/themes', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'POST') {
                Anon_Cms_Admin_Themes::upload();
            } elseif ($requestMethod === 'DELETE') {
                Anon_Cms_Admin_Themes::delete();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['POST', 'DELETE'],
            'token' => true,
        ]);
    }
}
