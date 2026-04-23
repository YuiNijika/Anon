<?php
namespace Anon\Modules\Http;



use Anon\Modules\System\Exception as SystemException;

use Throwable;
use Http;
use Anon\Modules\Debug;
use Anon\Modules\System\Env;
use Exception;
use Anon\Modules\System\Hook;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 响应助手
 */
class ResponseHelper
{

    /**
     * 调试输出
     * @param array $response 响应数据
     * @param string $type 类型
     */
    private static function logToConsole(array $response, string $type = 'success'): void
    {
        $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
        if (!$isDebug) {
            return;
        }

        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $logMessage = sprintf(
            "[%s] %s %s\n%s: %s\n",
            date('Y-m-d H:i:s'),
            $method,
            $uri,
            strtoupper($type),
            $json
        );

        if (php_sapi_name() === 'cli') {
            file_put_contents('php://stderr', $logMessage);
        } else {
            Debug::debug($logMessage);
        }
    }

    /**
     * 清理空值
     * @param mixed $data 原始数据
     * @return mixed
     */
    private static function cleanNullValues($data)
    {
        if ($data === null) {
            return [];
        }

        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $cleaned[$key] = self::cleanNullValues($value);
            }
            return $cleaned;
        }

        return $data;
    }

    /**
     * 发送成功
     * @param mixed $data 数据
     * @param string $message 消息
     * @param int $httpCode 状态码
     */
    public static function success($data = null, $message = '操作成功', $httpCode = 200)
    {
        Hook::do_action('response_before_success', $data, $message, $httpCode);
        $data = Hook::apply_filters('response_data', $data);
        $message = Hook::apply_filters('response_message', $message);

        http_response_code($httpCode);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'code' => 200,
            'message' => $message,
            'data' => self::cleanNullValues($data)
        ];

        $response = Hook::apply_filters('response_success', $response);

        self::logToConsole($response, 'success');

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    /**
     * 发送失败
     * @param string $message 消息
     * @param mixed $data 数据
     * @param int $httpCode 状态码
     */
    public static function error($message = '操作失败', $data = null, $httpCode = 400)
    {
        Hook::do_action('response_before_error', $message, $data, $httpCode);
        $message = Hook::apply_filters('response_error_message', $message);

        http_response_code($httpCode);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'code' => $httpCode,
            'message' => $message,
            'data' => self::cleanNullValues($data)
        ];

        $response = Hook::apply_filters('response_error', $response);

        self::logToConsole($response, 'error');

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    /**
     * 发送分页
     * @param array $data 数据列表
     * @param array $pagination 分页信息
     * @param string $message 消息
     * @param int $httpCode 状态码
     */
    public static function paginated($data, $pagination, $message = '获取数据成功', $httpCode = 200)
    {
        http_response_code($httpCode);

        $response = [
            'code' => $httpCode,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination
        ];

        self::logToConsole($response, 'success');

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    /**
     * 发送方法不允许
     * @param string $allowedMethods 允许方法
     */
    public static function methodNotAllowed($allowedMethods = '')
    {
        $message = '请求方法不被允许';
        if ($allowedMethods !== '') {
            $message .= '，允许的方法：' . $allowedMethods;
        }
        self::error($message, [], 405);
    }

    /**
     * 发送验证失败
     * @param string $message 消息
     * @param array $errors 错误详情
     */
    public static function validationError($message = '参数验证失败', $errors = null)
    {
        self::error($message, $errors ?? [], 422);
    }

    /**
     * 发送未授权
     * @param string $message 消息
     */
    public static function unauthorized($message = '未授权访问')
    {
        self::error($message, [], 401);
    }

    /**
     * 发送禁止访问
     * @param string $message 消息
     */
    public static function forbidden($message = '禁止访问')
    {
        self::error($message, [], 403);
    }

    /**
     * 发送未找到
     * @param string $message 消息
     */
    public static function notFound($message = '资源未找到')
    {
        self::error($message, [], 404);
    }

    /**
     * 发送服务器错误
     * @param string $message 消息
     * @param mixed $data 开发详情
     */
    public static function serverError($message = '服务器内部错误', $data = null)
    {
        // 记录日志
        if ($data !== null) {
            Debug::error('Server Error', ['message' => $message, 'data' => $data]);
        } else {
            Debug::error('Server Error', ['message' => $message]);
        }

        // 生产环境隐藏详情
        $isDevelopment = defined('ANON_DEBUG') && ANON_DEBUG;
        $responseData = $isDevelopment ? $data : [];

        self::error($message, $responseData, 500);
    }

    /**
     * 处理异常
     * @param Throwable $exception 异常
     * @param string $customMessage 自定义消息
     */
    public static function handleException(Throwable $exception, ?string $customMessage = null): void
    {
        // 记录异常到 debug log (始终记录)
        Debug::error('Exception handled', [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);

        // 检查是否为连接异常(数据库/Redis)
        $isConnectionError = (
            $exception instanceof \Anon\Modules\Database\ConnectionException ||
            ($exception instanceof \RuntimeException && strpos($exception->getMessage(), 'Redis') !== false)
        );

        if ($isConnectionError) {
            // 连接异常：生成 ErrorId 并使用 error() 方法
            $errorId = bin2hex(random_bytes(8));
            
            if ($exception instanceof \Anon\Modules\Database\ConnectionException) {
                $message = '数据库连接失败';
            } else {
                $message = 'Redis 连接失败';
            }

            self::error($message, ['error_id' => $errorId], 503);
        }

        // 其他异常：正常处理
        // 为数据库连接异常提供友好消息
        if ($exception instanceof \Anon\Modules\Database\ConnectionException) {
            $message = $customMessage ?: $exception->getFriendlyMessage();
        } else {
            $message = $customMessage ?: $exception->getMessage();
        }

        // 设置状态码
        $httpCode = 500;
        $data = [];

        $httpCode = SystemException::resolveHttpCode($exception);
        $data = SystemException::resolveData($exception);

        // 框架异常
        $logDetailed = false;
        if (Env::isInitialized()) {
            $logDetailed = Env::get('app.debug.logDetailedErrors', false);
        }

        // 检查详细日志
        if ($logDetailed && empty($data)) {
            $data = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG && empty($data)) {
            // 返回详细信息
            $data = [
                'type' => get_class($exception)
            ];
        }

        self::error($message, $data, $httpCode);
    }
}
