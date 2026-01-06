<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 请求处理助手类
 * 提供统一的请求方法检查、输入获取、验证等功能
 */
class Anon_Http_Request
{
    /**
     * 缓存的原始输入数据
     * @var array|null
     */
    private static $cachedInput = null;

    /**
     * 是否已读取输入
     * @var bool
     */
    private static $inputRead = false;

    /**
     * XSS 过滤后的输入数据
     * @var array|null
     */
    private static $filteredInput = null;

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
            Anon_Http_Response::methodNotAllowed($methodsStr);
            return false;
        }
        
        return true;
    }

    /**
     * 获取请求输入数据，支持JSON和表单数据
     * @param bool $raw 是否获取原始未过滤的数据
     * @return array 解析后的数据数组
     */
    public static function getInput(bool $raw = false): array
    {
        if (!$raw && self::$filteredInput !== null) {
            return self::$filteredInput;
        }

        if (self::$inputRead && self::$cachedInput !== null) {
            return self::$cachedInput;
        }

        $input = file_get_contents('php://input');
        self::$inputRead = true;
        
        // 请求体大小限制默认2MB
        $maxSize = 2097152;
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            $maxSize = Anon_System_Env::get('app.request.maxBodySize', 2097152);
        }
        
        if (strlen($input) > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 2);
            throw new RuntimeException("请求体过大，最大允许 {$maxSizeMB}MB");
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = null;
        }
        
        if (!$data) {
            $data = $_POST;
        }
        
        $data = $data ?: [];
        
        $data = Anon_System_Hook::apply_filters('request_input', $data);
        
        self::$cachedInput = $data;
        
        return $data;
    }

    /**
     * 设置过滤后的输入数据
     * @param array $data 过滤后的数据
     */
    public static function setFilteredInput(array $data): void
    {
        self::$filteredInput = $data;
    }

    /**
     * 获取原始未过滤的输入数据
     * @return array
     */
    public static function getRawInput(): array
    {
        return self::getInput(true);
    }

    /**
     * 重置输入缓存
     */
    public static function resetInput(): void
    {
        self::$cachedInput = null;
        self::$filteredInput = null;
        self::$inputRead = false;
    }

    /**
     * 从 GET 或 POST 获取请求参数
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
            Anon_Http_Response::validationError($message);
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
            Anon_Http_Response::validationError('参数验证失败', $errors);
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
     * 从会话或 Cookie 获取当前用户ID
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
     * 获取需要登录的当前用户信息
     * @return array 用户信息数组
     */
    public static function requireAuth(): array
    {
        if (!Anon_Check::isLoggedIn()) {
            Anon_Http_Response::unauthorized('请先登录');
        }
        
        $userId = self::getUserId();
        if (!$userId) {
            Anon_Http_Response::unauthorized('用户未登录');
        }
        
        $db = Anon_Database::getInstance();
        $userInfo = $db->getUserInfo($userId);
        
        if (!$userInfo) {
            Anon_Http_Response::unauthorized('用户不存在');
        }
        
        return $userInfo;
    }

    /**
     * 生成用户 Token
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param bool|null $rememberMe 是否记住我，null 时自动从 cookie 判断
     * @return string|null Token 字符串，未启用时返回 null
     */
    public static function generateUserToken(int $userId, string $username, ?bool $rememberMe = null): ?string
    {
        if (!Anon_Auth_Token::isEnabled()) {
            return null;
        }

        Anon_Check::startSessionIfNotStarted();
        $sessionId = session_id();

        if ($rememberMe === null) {
            $rememberMe = isset($_COOKIE['user_id']) && !empty($_COOKIE['user_id']);
        }

        $expire = $rememberMe ? 86400 * 30 : 3600;

        return Anon_Auth_Token::generate([
            'user_id' => $userId,
            'username' => $username,
            'session_id' => $sessionId,
            'login_time' => time()
        ], $expire);
    }

    /**
     * 智能获取或生成用户 Token
     * 根据 refresh 配置决定是返回现有 Token 还是生成新 Token
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param bool|null $rememberMe 是否记住我，null 时自动从 cookie 判断
     * @return string|null Token 字符串，未启用时返回 null
     */
    public static function getUserToken(int $userId, string $username, ?bool $rememberMe = null): ?string
    {
        if (!Anon_Auth_Token::isEnabled()) {
            return null;
        }

        // 如果启用了Token刷新，总是生成新Token
        if (Anon_Auth_Token::isRefreshEnabled()) {
            return self::generateUserToken($userId, $username, $rememberMe);
        }

        // 如果未启用刷新，检查是否有有效的现有 Token
        $existingToken = Anon_Auth_Token::getTokenFromRequest();
        if (!empty($existingToken)) {
            $payload = Anon_Auth_Token::verify($existingToken);
            // 如果现有 Token 有效，返回现有 Token
            if ($payload !== false && isset($payload['data']['user_id']) && (int)$payload['data']['user_id'] === $userId) {
                return $existingToken;
            }
        }

        // 如果没有有效 Token，生成新 Token
        return self::generateUserToken($userId, $username, $rememberMe);
    }

    /**
     * 验证 API Token 防止 API 被刷
     * @param bool $throwException 验证失败时是否抛出异常，默认 true
     * @param bool $skip 是否跳过验证，默认 false
     * @return bool 验证成功返回 true，失败返回 false 或抛出异常
     */
    public static function requireToken(bool $throwException = true, bool $skip = false): bool
    {
        // 如果明确要求跳过验证，直接返回
        if ($skip) {
            return true;
        }
        
        // 检查是否启用Token验证
        if (!Anon_Auth_Token::isEnabled()) {
            return true; // 未启用则直接通过
        }

        // 获取当前请求路径
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = strstr($path, '?', true) ?: $path;
        
        // 去除前端代理的 /apiService 前缀
        if (strpos($path, '/apiService') === 0) {
            $path = substr($path, strlen('/apiService'));
        }
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        // 检查是否在白名单中
        if (Anon_Auth_Token::isWhitelisted($path)) {
            return true;
        }

        // 验证 Token
        $token = Anon_Auth_Token::getTokenFromRequest();
        if (empty($token)) {
            // 如果用户已通过 Session/Cookie 登录，允许降级使用 Session 验证，仅限 Debug API
            $isDebugApi = strpos($path, '/anon/debug/api/') === 0;
            if ($isDebugApi && Anon_Check::isLoggedIn()) {
                // Debug API 允许已登录用户通过 Session 访问以保持向后兼容
                return true;
            }
            
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_Http_Response::forbidden('Token 验证失败，未提供 API Token');
            }
            return false;
        }

        $payload = Anon_Auth_Token::verify($token);
        if ($payload === false) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_Http_Response::forbidden('Token 验证失败，请提供有效的 API Token');
            }
            return false;
        }

        // 如果 Token 包含用户信息，自动设置登录状态
        if (isset($payload['data']['user_id'])) {
            Anon_Check::startSessionIfNotStarted();
            
            // 从 Token 中恢复用户登录状态
            $userId = (int)$payload['data']['user_id'];
            $username = $payload['data']['username'] ?? '';
            
            // 设置会话信息
            $_SESSION['user_id'] = $userId;
            if (!empty($username)) {
                $_SESSION['username'] = $username;
            }
            
            // 支持多设备登录，不强制验证 session_id
            if (isset($payload['data']['session_id'])) {
                $currentSessionId = session_id();
            }

            // 如果启用了 Token 刷新，生成新 Token 并添加到响应头
            if (Anon_Auth_Token::isRefreshEnabled()) {
                // 根据 Token 过期时间判断是否为"记住我"
                // 如果过期时间超过24小时，视为记住我
                $expireTime = $payload['expire'] ?? 0;
                $tokenLifetime = $expireTime - ($payload['timestamp'] ?? time());
                $rememberMe = $tokenLifetime > 86400; // 超过24小时视为记住我
                
                $newToken = self::generateUserToken($userId, $username, $rememberMe);
                
                if ($newToken !== null) {
                    // 将新 Token 添加到响应头，客户端需要更新
                    if (!headers_sent()) {
                        header('X-New-Token: ' . $newToken);
                    }
                }
            }
        }

        return true;
    }
}

