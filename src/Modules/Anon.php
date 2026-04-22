<?php
namespace Anon\Modules;

use Modules;
use Anon\Modules\Debug;
use Anon\Modules\Common;
use Anon\Modules\Database\Database;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Env;
use Anon\Modules\System\Hook;
use Anon\Modules\System\Config;
use Anon\Modules\Cms\Theme\OptionsProxy;
use Anon\Modules\Cms\Theme\Theme;
use FatalError;
use QueryBuilder;
use Error;
use Throwable;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon
{
    /**
     * 成功响应
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $httpCode HTTP状态码
     */
    public static function success($data = null, $message = '操作成功', $httpCode = 200)
    {
        ResponseHelper::success($data, $message, $httpCode);
    }

    /**
     * 错误响应
     * @param string $message 错误消息
     * @param mixed $data 额外数据
     * @param int $httpCode HTTP状态码
     */
    public static function error($message = '操作失败', $data = null, $httpCode = 400)
    {
        $mode = Env::get('app.mode', 'api');

        // 如果是 API 模式，或者请求头要求 JSON，保持现状
        if ($mode === 'api' || RequestHelper::wantsJson()) {
            ResponseHelper::error($message, $data, $httpCode);
            return;
        }

        // 如果是 CMS 模式，且不是 AJAX 请求，则渲染错误页面
        if ($mode === 'cms') {
            try {
                Theme::render('Error', [
                    'code' => $httpCode,
                    'message' => $message,
                    'data' => $data
                ]);
            } catch (Error $e) {
                // 严重错误：使用系统级错误页面
                FatalError::render(
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    get_class($e)
                );
            } catch (Throwable $e) {
                // 检查是否是严重错误
                $errorMessage = $e->getMessage();
                $isFatal = strpos($errorMessage, 'Call to undefined') !== false ||
                    strpos($errorMessage, 'not found') !== false ||
                    strpos($errorMessage, 'Class') !== false;

                if ($isFatal) {
                    FatalError::render(
                        $errorMessage,
                        $e->getFile(),
                        $e->getLine(),
                        get_class($e)
                    );
                } else {
                    // 如果主题模板不存在，使用默认错误页面
                    Common::Header($httpCode);
                    echo "<h1>出错了 ($httpCode)</h1><p>" . htmlspecialchars($message) . "</p>";
                }
            }
            exit;
        }

        // 默认回退到 API 响应
        ResponseHelper::error($message, $data, $httpCode);
    }

    /**
     * 分页响应
     * @param mixed $data 数据
     * @param array $pagination 分页信息
     * @param string $message 消息
     * @param int $httpCode HTTP状态码
     */
    public static function paginated($data, $pagination, $message = '获取数据成功', $httpCode = 200)
    {
        ResponseHelper::paginated($data, $pagination, $message, $httpCode);
    }

    /**
     * 未授权
     * @param string $message 消息
     * @param array $data 额外数据
     */
    public static function unauthorized($message = '未授权访问', array $data = [])
    {
        ResponseHelper::unauthorized($message);
    }

    /**
     * 禁止访问
     * @param string $message 消息
     * @param array $data 额外数据
     */
    public static function forbidden($message = '禁止访问', array $data = [])
    {
        ResponseHelper::forbidden($message);
    }

    /**
     * 未找到
     * @param string $message 消息
     * @param array $data 额外数据
     */
    public static function notFound($message = '资源未找到', array $data = [])
    {
        ResponseHelper::notFound($message);
    }

    /**
     * 服务器错误
     * @param string $message 消息
     * @param mixed $data 额外数据
     */
    public static function serverError($message = '服务器内部错误', $data = null)
    {
        ResponseHelper::serverError($message, $data);
    }

    /**
     * 验证错误
     * @param string $message 消息
     * @param mixed $errors 错误详情
     */
    public static function validationError($message = '参数验证失败', $errors = null)
    {
        ResponseHelper::validationError($message, $errors);
    }

    /**
     * 获取请求参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return RequestHelper::get($key, $default);
    }

    /**
     * 获取POST参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function post(string $key, $default = null)
    {
        return RequestHelper::post($key, $default);
    }

    /**
     * 获取GET参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getParam(string $key, $default = null)
    {
        return RequestHelper::getParam($key, $default);
    }

    /**
     * 获取请求输入
     * @return array
     */
    public static function input(): array
    {
        return RequestHelper::getInput();
    }

    /**
     * 验证必需参数
     * @param array $rules 验证规则 ['key' => '错误消息']
     * @return array 验证通过的数据
     */
    public static function validate(array $rules): array
    {
        return RequestHelper::validate($rules);
    }

    /**
     * 要求特定HTTP方法
     * @param string|array $methods 允许的方法
     * @return bool
     */
    public static function requireMethod($methods): bool
    {
        return RequestHelper::requireMethod($methods);
    }

    /**
     * 获取当前请求方法
     * @return string
     */
    public static function method(): string
    {
        return RequestHelper::method();
    }

    /**
     * 是否为POST请求
     * @return bool
     */
    public static function isPost(): bool
    {
        return RequestHelper::isPost();
    }

    /**
     * 是否为GET请求
     * @return bool
     */
    public static function isGet(): bool
    {
        return RequestHelper::isGet();
    }

    /**
     * 添加路由
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     */
    /**
     * 注册路由
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     * @param array $meta 路由元数据，可选
     */
    public static function route(string $path, callable $handler, array $meta = [])
    {
        Config::addRoute($path, $handler, $meta);
    }

    /**
     * 添加静态文件路由
     * @param string $route 路由路径
     * @param string $filePath 文件路径
     * @param string $mimeType MIME类型
     * @param int $cacheTime 缓存时间，单位为秒
     * @param bool $compress 是否压缩
     */
    public static function staticRoute(string $route, string $filePath, string $mimeType, int $cacheTime = 31536000, bool $compress = true)
    {
        Config::addStaticRoute($route, $filePath, $mimeType, $cacheTime, $compress);
    }

    /**
     * 设置HTTP响应头
     * @param int $code HTTP状态码
     * @param bool $response 是否输出响应
     * @param bool $cors 是否启用CORS
     */
    public static function header($code = 200, $response = true, $cors = true)
    {
        Common::Header($code, $response, $cors);
    }

    /**
     * 要求登录
     */
    public static function requireLogin()
    {
        Common::RequireLogin();
    }

    /**
     * 是否已登录
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return Common::isLoggedIn();
    }

    /**
     * 登出
     */
    public static function logout()
    {
        Common::logout();
    }

    /**
     * 获取当前用户ID
     * @return int|null
     */
    public static function userId()
    {
        return RequestHelper::getUserId();
    }

    /**
     * 获取当前用户信息
     * @return array
     */
    public static function user()
    {
        return RequestHelper::requireAuth();
    }

    /**
     * 添加动作钩子
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $acceptedArgs 接受参数数量
     * @return bool
     */
    public static function action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return Hook::add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * 添加过滤器钩子
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $acceptedArgs 接受参数数量
     * @return bool
     */
    public static function filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return Hook::add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * 执行动作钩子
     * @param string $hook 钩子名称
     * @param mixed ...$args 参数
     */
    public static function doAction(string $hook, ...$args)
    {
        Hook::do_action($hook, ...$args);
    }

    /**
     * 应用过滤器钩子
     * @param string $hook 钩子名称
     * @param mixed $value 要过滤的值
     * @param mixed ...$args 额外参数
     * @return mixed
     */
    public static function applyFilter(string $hook, $value, ...$args)
    {
        return Hook::apply_filters($hook, $value, ...$args);
    }

    /**
     * 获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function config(string $key, $default = null)
    {
        return Env::get($key, $default);
    }

    /**
     * 调试信息
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function debug(string $message, array $context = [])
    {
        Debug::debug($message, $context);
    }

    /**
     * 信息日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function info(string $message, array $context = [])
    {
        Debug::info($message, $context);
    }

    /**
     * 警告日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function warn(string $message, array $context = [])
    {
        Debug::warn($message, $context);
    }

    /**
     * 错误日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function logError(string $message, array $context = [])
    {
        Debug::error($message, $context);
    }

    /**
     * 获取数据库查询构建器
     * @param string $table 表名
     * @return QueryBuilder
     */
    public static function db(string $table)
    {
        return Database::getInstance()->db($table);
    }

    /**
     * 获取客户端IP
     * @return string
     */
    public static function ip(): string
    {
        return Common::GetClientIp();
    }

    /**
     * 获取插件选项代理
     * @param string $slug 插件标识符
     * @return Anon_Cms_Options_Proxy|null
     */
    public static function options(string $slug)
    {
        if (!class_exists(OptionsProxy::class)) {
            return null;
        }
        return new OptionsProxy('plugin', $slug, null);
    }
}
