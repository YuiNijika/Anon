<?php
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
        Anon_Http_Response::success($data, $message, $httpCode);
    }

    /**
     * 错误响应
     * @param string $message 错误消息
     * @param mixed $data 额外数据
     * @param int $httpCode HTTP状态码
     */
    public static function error($message = '操作失败', $data = null, $httpCode = 400)
    {
        $mode = Anon_System_Env::get('app.mode', 'api');

        // 如果是 API 模式，或者请求头要求 JSON，保持现状
        if ($mode === 'api' || Anon_Http_Request::wantsJson()) {
            Anon_Http_Response::error($message, $data, $httpCode);
            return;
        }

        // 如果是 CMS 模式，且不是 AJAX 请求，则渲染错误页面
        if ($mode === 'cms') {
            try {
                Anon_Cms_Theme::render('Error', [
                    'code' => $httpCode,
                    'message' => $message,
                    'data' => $data
                ]);
            } catch (Error $e) {
                // 严重错误：使用系统级错误页面
                if (class_exists('Anon_Cms_Theme_FatalError')) {
                    Anon_Cms_Theme_FatalError::render(
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        get_class($e)
                    );
                } else {
                    Anon_Common::Header($httpCode);
                    echo "<h1>出错了 ($httpCode)</h1><p>" . htmlspecialchars($message) . "</p>";
                }
            } catch (Throwable $e) {
                // 检查是否是严重错误
                $errorMessage = $e->getMessage();
                $isFatal = strpos($errorMessage, 'Call to undefined') !== false ||
                    strpos($errorMessage, 'not found') !== false ||
                    strpos($errorMessage, 'Class') !== false;

                if ($isFatal && class_exists('Anon_Cms_Theme_FatalError')) {
                    Anon_Cms_Theme_FatalError::render(
                        $errorMessage,
                        $e->getFile(),
                        $e->getLine(),
                        get_class($e)
                    );
                } else {
                    // 如果主题模板不存在，使用默认错误页面
                    Anon_Common::Header($httpCode);
                    echo "<h1>出错了 ($httpCode)</h1><p>" . htmlspecialchars($message) . "</p>";
                }
            }
            exit;
        }

        // 默认回退到 API 响应
        Anon_Http_Response::error($message, $data, $httpCode);
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
        Anon_Http_Response::paginated($data, $pagination, $message, $httpCode);
    }

    /**
     * 未授权
     * @param string $message 消息
     * @param array $data 额外数据
     * @throws Anon_UnauthorizedException
     */
    public static function unauthorized($message = '未授权访问', array $data = [])
    {
        throw new Anon_UnauthorizedException($message, $data);
    }

    /**
     * 禁止访问
     * @param string $message 消息
     * @param array $data 额外数据
     * @throws Anon_ForbiddenException
     */
    public static function forbidden($message = '禁止访问', array $data = [])
    {
        throw new Anon_ForbiddenException($message, $data);
    }

    /**
     * 未找到
     * @param string $message 消息
     * @param array $data 额外数据
     * @throws Anon_NotFoundException
     */
    public static function notFound($message = '资源未找到', array $data = [])
    {
        throw new Anon_NotFoundException($message, $data);
    }

    /**
     * 服务器错误
     * @param string $message 消息
     * @param mixed $data 额外数据
     */
    public static function serverError($message = '服务器内部错误', $data = null)
    {
        Anon_Http_Response::serverError($message, $data);
    }

    /**
     * 验证错误
     * @param string $message 消息
     * @param mixed $errors 错误详情
     */
    public static function validationError($message = '参数验证失败', $errors = null)
    {
        Anon_Http_Response::validationError($message, $errors);
    }

    /**
     * 获取请求参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return Anon_Http_Request::get($key, $default);
    }

    /**
     * 获取POST参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function post(string $key, $default = null)
    {
        return Anon_Http_Request::post($key, $default);
    }

    /**
     * 获取GET参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getParam(string $key, $default = null)
    {
        return Anon_Http_Request::getParam($key, $default);
    }

    /**
     * 获取请求输入
     * @return array
     */
    public static function input(): array
    {
        return Anon_Http_Request::getInput();
    }

    /**
     * 验证必需参数
     * @param array $rules 验证规则 ['key' => '错误消息']
     * @return array 验证通过的数据
     */
    public static function validate(array $rules): array
    {
        return Anon_Http_Request::validate($rules);
    }

    /**
     * 要求特定HTTP方法
     * @param string|array $methods 允许的方法
     * @return bool
     */
    public static function requireMethod($methods): bool
    {
        return Anon_Http_Request::requireMethod($methods);
    }

    /**
     * 获取当前请求方法
     * @return string
     */
    public static function method(): string
    {
        return Anon_Http_Request::method();
    }

    /**
     * 是否为POST请求
     * @return bool
     */
    public static function isPost(): bool
    {
        return Anon_Http_Request::isPost();
    }

    /**
     * 是否为GET请求
     * @return bool
     */
    public static function isGet(): bool
    {
        return Anon_Http_Request::isGet();
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
        Anon_System_Config::addRoute($path, $handler, $meta);
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
        Anon_System_Config::addStaticRoute($route, $filePath, $mimeType, $cacheTime, $compress);
    }

    /**
     * 设置HTTP响应头
     * @param int $code HTTP状态码
     * @param bool $response 是否输出响应
     * @param bool $cors 是否启用CORS
     */
    public static function header($code = 200, $response = true, $cors = true)
    {
        Anon_Common::Header($code, $response, $cors);
    }

    /**
     * 要求登录
     */
    public static function requireLogin()
    {
        Anon_Common::RequireLogin();
    }

    /**
     * 是否已登录
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return Anon_Common::isLoggedIn();
    }

    /**
     * 登出
     */
    public static function logout()
    {
        Anon_Common::logout();
    }

    /**
     * 获取当前用户ID
     * @return int|null
     */
    public static function userId()
    {
        return Anon_Http_Request::getUserId();
    }

    /**
     * 获取当前用户信息
     * @return array
     */
    public static function user()
    {
        return Anon_Http_Request::requireAuth();
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
        return Anon_System_Hook::add_action($hook, $callback, $priority, $acceptedArgs);
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
        return Anon_System_Hook::add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * 执行动作钩子
     * @param string $hook 钩子名称
     * @param mixed ...$args 参数
     */
    public static function doAction(string $hook, ...$args)
    {
        Anon_System_Hook::do_action($hook, ...$args);
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
        return Anon_System_Hook::apply_filters($hook, $value, ...$args);
    }

    /**
     * 获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function config(string $key, $default = null)
    {
        return Anon_System_Env::get($key, $default);
    }

    /**
     * 调试信息
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function debug(string $message, array $context = [])
    {
        Anon_Debug::debug($message, $context);
    }

    /**
     * 信息日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function info(string $message, array $context = [])
    {
        Anon_Debug::info($message, $context);
    }

    /**
     * 警告日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function warn(string $message, array $context = [])
    {
        Anon_Debug::warn($message, $context);
    }

    /**
     * 错误日志
     * @param string $message 消息
     * @param array $context 上下文
     */
    public static function logError(string $message, array $context = [])
    {
        Anon_Debug::error($message, $context);
    }


    /**
     * 获取数据库查询构建器
     * @param string $table 表名
     * @return Anon_QueryBuilder
     */
    public static function db(string $table)
    {
        return Anon_Database::getInstance()->db($table);
    }

    /**
     * 获取客户端IP
     * @return string
     */
    public static function ip(): string
    {
        return Anon_Common::GetClientIp();
    }
}
