<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 请求处理助手类
 * 提供统一的请求方法检查、输入获取、验证等功能
 */
class Anon_RequestHelper
{
    /**
     * 检查请求方法是否匹配
     * @param string|array $allowedMethods 允许的请求方法，可以是字符串或数组
     * @return bool 如果请求方法匹配返回true，否则返回false并发送错误响应
     */
    public static function requireMethod($allowedMethods): bool
    {
        $methods = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($requestMethod, $methods)) {
            $methodsStr = implode(', ', $methods);
            Anon_ResponseHelper::methodNotAllowed($methodsStr);
            return false;
        }
        
        return true;
    }

    /**
     * 获取请求输入数据（支持 JSON 和表单数据）
     * @return array 解析后的数据数组
     */
    public static function getInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        return $data ?: [];
    }

    /**
     * 获取请求参数（从 GET 或 POST）
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed 参数值
     */
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * 获取必需的参数
     * @param string $key 参数名
     * @param string|null $errorMessage 错误消息
     * @return mixed 参数值
     */
    public static function require(string $key, ?string $errorMessage = null)
    {
        $value = self::get($key);
        
        if ($value === null || $value === '') {
            $message = $errorMessage ?: "参数 {$key} 不能为空";
            Anon_ResponseHelper::validationError($message);
        }
        
        return $value;
    }

    /**
     * 验证必需参数
     * @param array $required 必需参数列表 ['key' => '错误消息', ...] 或 ['key1', 'key2', ...]
     * @param array|null $data 数据数组，默认从请求获取
     * @return array 验证后的数据
     */
    public static function validate(array $required, ?array $data = null): array
    {
        if ($data === null) {
            $data = self::getInput();
        }
        
        $errors = [];
        $validated = [];
        
        foreach ($required as $key => $message) {
            // 如果 $key 是数字索引，则 $message 是参数名
            if (is_numeric($key)) {
                $paramName = $message;
                $errorMsg = "参数 {$paramName} 不能为空";
            } else {
                $paramName = $key;
                $errorMsg = $message;
            }
            
            if (!isset($data[$paramName]) || $data[$paramName] === '') {
                $errors[$paramName] = $errorMsg;
            } else {
                $validated[$paramName] = $data[$paramName];
            }
        }
        
        if (!empty($errors)) {
            Anon_ResponseHelper::validationError('参数验证失败', $errors);
        }
        
        return $validated;
    }

    /**
     * 获取当前请求方法
     * @return string 请求方法
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * 检查是否为 POST 请求
     * @return bool
     */
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * 检查是否为 GET 请求
     * @return bool
     */
    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /**
     * 获取当前用户ID（从会话或Cookie）
     * @return int|null 用户ID，未登录返回null
     */
    public static function getUserId(): ?int
    {
        Anon_Check::startSessionIfNotStarted();
        
        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        
        if (!empty($_COOKIE['user_id'])) {
            return (int)$_COOKIE['user_id'];
        }
        
        return null;
    }

    /**
     * 获取当前用户信息（需要登录）
     * @return array 用户信息数组
     */
    public static function requireAuth(): array
    {
        if (!Anon_Check::isLoggedIn()) {
            Anon_ResponseHelper::unauthorized('请先登录');
        }
        
        $userId = self::getUserId();
        if (!$userId) {
            Anon_ResponseHelper::unauthorized('用户未登录');
        }
        
        $db = new Anon_Database();
        $userInfo = $db->getUserInfo($userId);
        
        if (!$userInfo) {
            Anon_ResponseHelper::unauthorized('用户不存在');
        }
        
        return $userInfo;
    }
}

