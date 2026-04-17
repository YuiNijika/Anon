<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

require_once __DIR__ . '/RESTful/Post.php';
require_once __DIR__ . '/RESTful/Category.php';
require_once __DIR__ . '/RESTful/Tag.php';

class Anon_Cms_RESTful 
{
    /**
     * 获取 RESTful API 路由前缀
     * @return string
     */
    public static function getRoutePrefix(): string
    {
        return Anon_System_ApiPrefix::get() . '/restful/v1';
    }

    /**
     * 添加路由
     * @param string $path
     * @param callable $handler
     * @param array $meta
     * @return void
     */
    private static function addRoute(string $path, callable $handler, array $meta = [])
    {
        Anon_System_Config::addRoute($path, $handler, $meta);
    }

    /**
     * 初始化 RESTful API 路由
     * @return void
     */
    public static function init()
    {
        $routePrefix = self::getRoutePrefix();
        
        self::addRoute($routePrefix . '/posts', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Post::index();
            } elseif ($requestMethod === 'POST') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Post::store();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/posts/batch', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Post::batchDestroy();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/posts/{id}', function ($params) {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                Anon_Http_Response::error('文章 ID 无效', 400);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Post::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Post::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Post::destroy($id);
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/*', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            Anon_Http_Response::error('API 端点不存在', 404);
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/categories', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Category::index();
            } elseif ($requestMethod === 'POST') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Category::store();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/categories/batch', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Category::batchDestroy();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/categories/{id}', function ($params) {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                Anon_Http_Response::error('分类 ID 无效', 400);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Category::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Category::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Category::destroy($id);
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/tags', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Tag::index();
            } elseif ($requestMethod === 'POST') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Tag::store();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/tags/batch', function () {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Tag::batchDestroy();
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/tags/{id}', function ($params) {
            if (!Anon_Cms_Options::get('api_enabled', '0')) {
                Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
                return;
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                Anon_Http_Response::error('标签 ID 无效', 400);
                return;
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Anon_Http_Request::getToken()) {
                    Anon_Http_Response::unauthorized('需要 Token 认证');
                    return;
                }
                Anon_Cms_RESTful_Tag::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Tag::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Anon_Cms_Admin::isAdmin()) {
                    Anon_Http_Response::error('需要管理员权限', 403);
                    return;
                }
                Anon_Cms_RESTful_Tag::destroy($id);
            } else {
                Anon_Http_Response::error('不支持的请求方法', 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
    }
}