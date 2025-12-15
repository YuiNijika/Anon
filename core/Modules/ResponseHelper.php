<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 统一JSON响应格式
 * 提供标准化的API响应格式：success、message、data
 */
class Anon_ResponseHelper {
    
    /**
     * 输出 JSON 响应到控制台（调试用）
     * @param array $response 响应数据
     * @param string $type 响应类型（success/error）
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
     * 发送成功响应
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $httpCode HTTP状态码，默认200
     */
    public static function success($data = null, $message = '操作成功', $httpCode = 200) {
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('response_before_success', $data, $message, $httpCode);
            $data = Anon_Hook::apply_filters('response_data', $data);
            $message = Anon_Hook::apply_filters('response_message', $message);
        }
        
        http_response_code($httpCode);
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (class_exists('Anon_Hook')) {
            $response = Anon_Hook::apply_filters('response_success', $response);
        }
        
        self::logToConsole($response, 'success');
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    /**
     * 发送失败响应
     * @param string $message 错误消息
     * @param mixed $data 额外的错误数据（可选）
     * @param int $httpCode HTTP状态码，默认400
     */
    public static function error($message = '操作失败', $data = null, $httpCode = 400) {
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('response_before_error', $message, $data, $httpCode);
            $message = Anon_Hook::apply_filters('response_error_message', $message);
        }
        
        http_response_code($httpCode);
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (class_exists('Anon_Hook')) {
            $response = Anon_Hook::apply_filters('response_error', $response);
        }
        
        self::logToConsole($response, 'error');
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    /**
     * 发送分页数据响应
     * @param array $data 数据列表
     * @param array $pagination 分页信息
     * @param string $message 响应消息
     * @param int $httpCode HTTP状态码，默认200
     */
    public static function paginated($data, $pagination, $message = '获取数据成功', $httpCode = 200) {
        http_response_code($httpCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination
        ];
        
        self::logToConsole($response, 'success');
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    /**
     * 发送方法不允许响应
     * @param string $allowedMethods 允许的方法列表
     */
    public static function methodNotAllowed($allowedMethods = '') {
        $message = '请求方法不被允许';
        if (!empty($allowedMethods)) {
            $message .= '，允许的方法：' . $allowedMethods;
        }
        self::error($message, null, 405);
    }
    
    /**
     * 发送参数验证失败响应
     * @param string $message 验证失败消息
     * @param array $errors 具体的验证错误（可选）
     */
    public static function validationError($message = '参数验证失败', $errors = null) {
        self::error($message, $errors, 422);
    }
    
    /**
     * 发送未授权响应
     * @param string $message 未授权消息
     */
    public static function unauthorized($message = '未授权访问') {
        self::error($message, null, 401);
    }
    
    /**
     * 发送禁止访问响应
     * @param string $message 禁止访问消息
     */
    public static function forbidden($message = '禁止访问') {
        self::error($message, null, 403);
    }
    
    /**
     * 发送资源未找到响应
     * @param string $message 未找到消息
     */
    public static function notFound($message = '资源未找到') {
        self::error($message, null, 404);
    }
    
    /**
     * 发送服务器内部错误响应
     * @param string $message 错误消息
     * @param mixed $data 错误详情（开发环境可用）
     */
    public static function serverError($message = '服务器内部错误', $data = null) {
        // 记录错误日志
        if ($data !== null) {
            error_log('Server Error: ' . $message . ' - Data: ' . json_encode($data));
        } else {
            error_log('Server Error: ' . $message);
        }
        
        // 生产环境不返回具体错误信息
        $isDevelopment = defined('ANON_DEBUG') && ANON_DEBUG;
        $responseData = $isDevelopment ? $data : null;
        
        self::error($message, $responseData, 500);
    }
    
    /**
     * 处理异常并发送错误响应
     * @param Exception $exception 异常对象
     * @param string $customMessage 自定义错误消息（可选）
     */
    public static function handleException($exception, $customMessage = null) {
        $message = $customMessage ?: $exception->getMessage();
        
        // 记录异常日志
        error_log('Exception handled: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        
        // 根据异常类型返回不同的HTTP状态码
        $httpCode = 500;
        if ($exception instanceof InvalidArgumentException) {
            $httpCode = 400;
        } elseif ($exception instanceof UnauthorizedAccessException) {
            $httpCode = 401;
        } elseif ($exception instanceof NotFoundException) {
            $httpCode = 404;
        }
        
        $isDevelopment = defined('ANON_DEBUG') && ANON_DEBUG;
        $data = $isDevelopment ? [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ] : null;
        
        self::error($message, $data, $httpCode);
    }
}