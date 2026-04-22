<?php
namespace Anon\Modules\Cms\Admin;


use Anon\Modules\CmsOptions as CmsOptions;






use Exception;


use Navbar;
use Statistics;
use Basic;
use Permission;
use Page;
use Themes;
use Categories;
use Tags;
use Posts;
use Users;
use Comments;
use Plugins;
use Store;

use Anon\Modules\Check;
use Options;
use Anon\Modules\Database\Database;
use Anon\Modules\Debug;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use ApiPrefix;
use Anon\Modules\System\Config;
use AccessLog;
use Attachment;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Admin
{
    /**
     * 获取 CMS 路由前缀
     * 根据 apiPrefix 配置动态生成，如果 apiPrefix 为空则使用 /anon
     * @return string
     */
    public static function getRoutePrefix(): string
    {
        // 使用统一的 API前缀管理
        return ApiPrefix::get() . '/cms/admin';
    }

    /**
     * 初始化管理模块
     * @return void
     */
    public static function init()
    {
        Debug::info("Initializing CMS Admin with route prefix: " . self::getRoutePrefix());
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
    
        // 设置 requireAdmin 时自动添加 requireLogin
        if (!empty($meta['requireAdmin'])) {
            $meta['requireLogin'] = true;
        }
    
        $routePrefix = self::getRoutePrefix();
            
        // 在配置的 API前缀下注册路由
        Config::addRoute($routePrefix . $path, $handler, $meta);
            
        // 同时在根路径下注册一份，确保兼容性
        Config::addRoute('/cms/admin' . $path, $handler, $meta);
    }

    /**
     * 检查用户是否已登录
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return Check::isLoggedIn();
    }

    /**
     * 检查当前用户是否为管理员
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $userId = RequestHelper::getUserId();
        if (!$userId) {
            return false;
        }

        $db = Database::getInstance();
        return $db->isUserAdmin($userId);
    }

    /**
     * 初始化路由
     * 注册所有管理后台相关的 API 路由
     * @return void
     */
    public static function initRoutes()
    {
        $routePrefix = self::getRoutePrefix();
        Debug::info("Registering CMS Admin routes with prefix: {$routePrefix}");
        
        self::addRoute('/navbar', function () {
            UINavbar::get();
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/statistics', function () {
            try {
                $statistics = IndexStatistics::getAll();
                $statistics['attachments_size'] = IndexStatistics::getAttachmentsSize();
                $statistics['total_views'] = IndexStatistics::getTotalViews();
                $statistics['views_trend'] = IndexStatistics::getViewsTrend(7);

                ResponseHelper::success($statistics, '获取统计数据成功');
            } catch (Exception $e) {
                ResponseHelper::error($e->getMessage(), null, 500);
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

                $trend = IndexStatistics::getViewsTrend($days);

                ResponseHelper::success($trend, '获取访问趋势数据成功');
            } catch (Exception $e) {
                ResponseHelper::error($e->getMessage(), null, 500);
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

                $result = AccessLog::getLogs($options);

                ResponseHelper::success($result, '获取访问日志成功');
            } catch (Exception $e) {
                ResponseHelper::error($e->getMessage(), null, 500);
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

                $stats = AccessLog::getStatistics($options);

                ResponseHelper::success($stats, '获取访问统计成功');
            } catch (Exception $e) {
                ResponseHelper::error($e->getMessage(), null, 500);
            }
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/settings/basic', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                SettingsBasic::get();
            } elseif ($requestMethod === 'POST') {
                SettingsBasic::save();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        self::addRoute('/settings/permission', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                SettingsPermission::get();
            } elseif ($requestMethod === 'POST') {
                SettingsPermission::save();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        self::addRoute('/settings/page', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                SettingsPage::get();
            } elseif ($requestMethod === 'POST') {
                SettingsPage::save();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        // 通用系统配置读写接口
        self::addRoute('/settings/system', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                // 获取系统配置
                $keys = isset($_GET['keys']) ? $_GET['keys'] : [];
                
                // 确保 keys 是数组
                if (!is_array($keys)) {
                    // 如果是逗号分隔的字符串，拆分为数组
                    if (is_string($keys) && strpos($keys, ',') !== false) {
                        $keys = explode(',', $keys);
                    } else {
                        $keys = [$keys];
                    }
                }
                
                $result = [];
                foreach ($keys as $key) {
                    $key = trim($key);
                    if (!empty($key)) {
                        $result[$key] = CmsOptions::get($key, null);
                    }
                }
                ResponseHelper::success($result, '获取系统配置成功');
            } elseif ($requestMethod === 'POST') {
                // 保存系统配置
                $data = RequestHelper::getInput();
                if (empty($data)) {
                    ResponseHelper::error('请求数据不能为空', null, 400);
                    return;
                }
                foreach ($data as $key => $value) {
                    CmsOptions::set($key, $value);
                }
                CmsOptions::clearCache();
                ResponseHelper::success($data, '保存系统配置成功');
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        IndexThemes::initStaticRoutes();

        self::addRoute('/settings/theme', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                IndexThemes::get();
            } elseif ($requestMethod === 'POST') {
                IndexThemes::switch();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
        ]);

        self::addRoute('/settings/theme-options', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                IndexThemes::getOptions();
            } elseif ($requestMethod === 'POST') {
                IndexThemes::saveOptions();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        self::addRoute('/metas/categories', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                ManageCategories::get();
            } elseif ($requestMethod === 'POST') {
                ManageCategories::create();
            } elseif ($requestMethod === 'PUT') {
                ManageCategories::update();
            } elseif ($requestMethod === 'DELETE') {
                ManageCategories::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/metas/tags', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                ManageTags::get();
            } elseif ($requestMethod === 'POST') {
                ManageTags::create();
            } elseif ($requestMethod === 'PUT') {
                ManageTags::update();
            } elseif ($requestMethod === 'DELETE') {
                ManageTags::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/attachments', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                Attachment::handleGetList();
            } elseif ($requestMethod === 'POST') {
                Attachment::handleUpload();
            } elseif ($requestMethod === 'DELETE') {
                Attachment::handleDelete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/posts', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $data = RequestHelper::getInput();
                $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
                if ($id > 0) {
                    ManagePosts::getOne($id);
                } else {
                    ManagePosts::getList();
                }
            } elseif ($requestMethod === 'POST') {
                ManagePosts::create();
            } elseif ($requestMethod === 'PUT') {
                ManagePosts::update();
            } elseif ($requestMethod === 'DELETE') {
                ManagePosts::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/users', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                ManageUsers::get();
            } elseif ($requestMethod === 'POST') {
                ManageUsers::create();
            } elseif ($requestMethod === 'PUT') {
                ManageUsers::update();
            } elseif ($requestMethod === 'DELETE') {
                ManageUsers::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/comments', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                ManageComments::getList();
            } elseif ($requestMethod === 'PUT') {
                ManageComments::update();
            } elseif ($requestMethod === 'DELETE') {
                ManageComments::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/plugins', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                IndexPlugins::get();
            } elseif ($requestMethod === 'POST') {
                IndexPlugins::upload();
            } elseif ($requestMethod === 'PUT') {
                $data = RequestHelper::getInput();
                $action = isset($data['action']) ? $data['action'] : '';
                if ($action === 'activate') {
                    IndexPlugins::activate();
                } elseif ($action === 'deactivate') {
                    IndexPlugins::deactivate();
                } else {
                    ResponseHelper::error('无效的操作', null, 400);
                }
            } elseif ($requestMethod === 'DELETE') {
                IndexPlugins::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => true,
        ]);

        self::addRoute('/plugins/options', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                IndexPlugins::getOptions();
            } elseif ($requestMethod === 'POST') {
                IndexPlugins::saveOptions();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => true,
        ]);

        self::addRoute('/plugins/page', function () {
            IndexPlugins::getPage();
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/plugins/page/action', function () {
            IndexPlugins::pageAction();
        }, [
            'method' => 'POST',
            'token' => true,
        ]);

        self::addRoute('/themes', function () {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'POST') {
                IndexThemes::upload();
            } elseif ($requestMethod === 'DELETE') {
                IndexThemes::delete();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['POST', 'DELETE'],
            'token' => true,
        ]);

        // 商店路由
        self::addRoute('/store/check-version', function () {
            ManageStore::checkVersion();
        }, [
            'method' => 'GET',
            'token' => true,
        ]);

        self::addRoute('/store/download', function () {
            ManageStore::download();
        }, [
            'method' => 'POST',
            'token' => true,
        ]);
    }
}
