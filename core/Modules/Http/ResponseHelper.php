<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 响应助手
 */
class Anon_Http_Response {
    
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
            error_log($logMessage);
        }
    }
    
    /**
     * 清理空值
     * @param mixed $data 原始数据
     * @return mixed
     */
    private static function cleanNullValues($data) {
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
    public static function success($data = null, $message = '操作成功', $httpCode = 200) {
        Anon_System_Hook::do_action('response_before_success', $data, $message, $httpCode);
        $data = Anon_System_Hook::apply_filters('response_data', $data);
        $message = Anon_System_Hook::apply_filters('response_message', $message);
        
        http_response_code($httpCode);
        
        $response = [
            'code' => 200,
            'message' => $message,
            'data' => self::cleanNullValues($data)
        ];
        
        $response = Anon_System_Hook::apply_filters('response_success', $response);
        
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
    public static function error($message = '操作失败', $data = null, $httpCode = 400) {
        Anon_System_Hook::do_action('response_before_error', $message, $data, $httpCode);
        $message = Anon_System_Hook::apply_filters('response_error_message', $message);
        
        http_response_code($httpCode);
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = [
            'code' => $httpCode,
            'message' => $message,
            'data' => self::cleanNullValues($data)
        ];
        
        $response = Anon_System_Hook::apply_filters('response_error', $response);
        
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
    public static function paginated($data, $pagination, $message = '获取数据成功', $httpCode = 200) {
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
    public static function methodNotAllowed($allowedMethods = '') {
        $message = '请求方法不被允许';
        if (!empty($allowedMethods)) {
            $message .= '，允许的方法：' . $allowedMethods;
        }
        self::error($message, [], 405);
    }
    
    /**
     * 发送验证失败
     * @param string $message 消息
     * @param array $errors 错误详情
     */
    public static function validationError($message = '参数验证失败', $errors = null) {
        self::error($message, $errors ?? [], 422);
    }
    
    /**
     * 发送未授权
     * @param string $message 消息
     */
    public static function unauthorized($message = '未授权访问') {
        self::error($message, [], 401);
    }
    
    /**
     * 发送禁止访问
     * @param string $message 消息
     */
    public static function forbidden($message = '禁止访问') {
        self::error($message, [], 403);
    }
    
    /**
     * 发送未找到
     * @param string $message 消息
     */
    public static function notFound($message = '资源未找到') {
        self::error($message, [], 404);
    }
    
    /**
     * 发送服务器错误
     * @param string $message 消息
     * @param mixed $data 开发详情
     */
    public static function serverError($message = '服务器内部错误', $data = null) {
        // 记录日志
        if ($data !== null) {
            error_log('Server Error: ' . $message . ' - Data: ' . json_encode($data));
        } else {
            error_log('Server Error: ' . $message);
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
        $message = $customMessage ?: $exception->getMessage();
        
        // 记录异常
        error_log('Exception handled: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        
        // 设置状态码
        $httpCode = 500;
        $data = [];
        
        if ($exception instanceof Anon_System_Exception) {
            // 框架异常
            $httpCode = $exception->getHttpCode();
            $data = $exception->getData();
        } elseif ($exception instanceof Anon_UnauthorizedException) {
            $httpCode = 401;
        } elseif ($exception instanceof Anon_ForbiddenException) {
            $httpCode = 403;
        } elseif ($exception instanceof Anon_NotFoundException) {
            $httpCode = 404;
        } elseif ($exception instanceof Anon_ValidationException) {
            $httpCode = 422;
            $data = $exception->getData();
        } elseif ($exception instanceof InvalidArgumentException) {
            $httpCode = 400;
        }
        
        // 检查详细日志
        $logDetailed = false;
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            $logDetailed = Anon_System_Env::get('app.debug.logDetailedErrors', false);
        }
        
        // 返回详细信息
        if ($logDetailed && empty($data)) {
            $data = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG && empty($data)) {
            // 返回简化信息
            $data = [
                'type' => get_class($exception)
            ];
        }
        
        self::error($message, $data, $httpCode);
    }
}