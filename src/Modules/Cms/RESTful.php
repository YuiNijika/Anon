<?php
namespace Anon\Modules\Cms;









use Anon\Modules\Cms\Cms;
use Token;
use Admin;
use Category;
use Post;
use Tag;
use Anon\Modules\Http\ResponseHelper;
use ApiPrefix;
use Anon\Modules\System\Config;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class RESTful
{
    /**
     * 获取 RESTful API 路由前缀
     * @return string
     */
    public static function getRoutePrefix(): string
    {
        return ApiPrefix::get() . '/restful/v1';
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
        Config::addRoute($path, $handler, $meta);
    }

    /**
     * 初始化 RESTful API 路由
     * @return void
     */
    public static function init()
    {
        $routePrefix = self::getRoutePrefix();
        
        self::addRoute($routePrefix . '/posts', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Post::index();
            } elseif ($requestMethod === 'POST') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Post::store();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/posts/batch', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Post::batchDestroy();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/posts/{id}', function ($params) {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                ResponseHelper::error('文章 ID 无效', null, 400);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Post::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Post::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Post::destroy($id);
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/*', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            ResponseHelper::error('API 端点不存在', null, 404);
        }, [
            'method' => ['GET', 'POST', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/categories', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Category::index();
            } elseif ($requestMethod === 'POST') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Category::store();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/categories/batch', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Category::batchDestroy();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/categories/{id}', function ($params) {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                ResponseHelper::error('分类 ID 无效', null, 400);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Category::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Category::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Category::destroy($id);
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/tags', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Tag::index();
            } elseif ($requestMethod === 'POST') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Tag::store();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'POST'],
            'token' => false,
        ]);
        
        self::addRoute($routePrefix . '/tags/batch', function () {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'DELETE';
            if ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Tag::batchDestroy();
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['DELETE'],
            'token' => true,
        ]);
        
        self::addRoute($routePrefix . '/tags/{id}', function ($params) {
            if (!Options::get('api_enabled', '0')) {
                ResponseHelper::error('RESTful API 未启用，请在后台设置中启用', null, 403);
            }
            
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                ResponseHelper::error('标签 ID 无效', null, 400);
            }
            
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($requestMethod === 'GET') {
                $tokenRequired = Options::get('restful_api_token_required', '1');
                if ($tokenRequired === '1' && !Token::getTokenFromRequest()) {
                    ResponseHelper::unauthorized('需要 Token 认证');
                }
                Tag::show($id);
            } elseif ($requestMethod === 'PUT') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Tag::update($id);
            } elseif ($requestMethod === 'DELETE') {
                if (!Admin::isAdmin()) {
                    ResponseHelper::error('需要管理员权限', null, 403);
                }
                Tag::destroy($id);
            } else {
                ResponseHelper::error('不支持的请求方法', null, 405);
            }
        }, [
            'method' => ['GET', 'PUT', 'DELETE'],
            'token' => false,
        ]);
    }
}
