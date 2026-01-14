<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 请求助手
 */
class Anon_Http_Request
{
    /**
     * @var array|null 输入缓存
     */
    private static $cachedInput = null;

    /**
     * @var bool 读取状态
     */
    private static $inputRead = false;

    /**
     * @var array|null 过滤输入
     */
    private static $filteredInput = null;

    /**
     * 检查方法
     * @param string|array $allowedMethods 允许方法
     * @return bool
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
     * 设置原始输入
     * @param string $rawInput
     */
    public static function setRawInput(string $rawInput): void
    {
        self::$inputRead = true;
        // 模拟缓存
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = null;
        }
        if (!$data) {
            $data = $_POST;
        }
        self::$cachedInput = $data ?: [];
    }

    /**
     * 获取输入
     * @param bool $raw 是否获取原始数据
     * @return array
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
        
        $maxSize = 2097152;
        if (Anon_System_Env::isInitialized()) {
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
     * 设置过滤输入
     * @param array $data 过滤后数据
     */
    public static function setFilteredInput(array $data): void
    {
        self::$filteredInput = $data;
    }

    /**
     * 获取原始输入
     * @return array
     */
    public static function getRawInput(): array
    {
        return self::getInput(true);
    }

    /**
     * 重置缓存
     */
    public static function resetInput(): void
    {
        self::$cachedInput = null;
        self::$filteredInput = null;
        self::$inputRead = false;
    }

    /**
     * 获取参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * 获取POST参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * 获取GET参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * 获取必需参数
     * @param string $key 参数名
     * @param string|null $errorMessage 错误消息
     * @return mixed
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
     * 验证参数
     * @param array $required 必需参数
     * @param array|null $data 数据数组
     * @return array
     */
    public static function validate(array $required, ?array $data = null): array
    {
        if ($data === null) {
            $data = self::getInput();
        }
        
        $errors = [];
        $validated = [];
        
        foreach ($required as $key => $message) {
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
     * 获取方法
     * @return string
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * 检查POST
     * @return bool
     */
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * 检查GET
     * @return bool
     */
    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /**
     * 检查请求是否期望 JSON 响应
     * @return bool
     */
    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        if (empty($accept)) {
            return false;
        }
        
        // 检查 Accept 头是否包含 application/json
        return strpos($accept, 'application/json') !== false || 
               strpos($accept, 'text/json') !== false;
    }

    /**
     * 获取用户ID
     * @return int|null
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
     * 获取用户信息
     * @return array
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
     * 生成Token
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param bool|null $rememberMe 记住我
     * @return string|null
     */
    public static function generateUserToken(int $userId, string $username, ?bool $rememberMe = null): ?string
    {
        // 确保 Token 模块已加载
        if (!class_exists('Anon_Auth_Token')) {
            Anon_Loader::loadOptionalModules('token');
        }
        
        if (!class_exists('Anon_Auth_Token') || !Anon_Auth_Token::isEnabled()) {
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
     * 获取或生成Token
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param bool|null $rememberMe 记住我
     * @return string|null
     */
    public static function getUserToken(int $userId, string $username, ?bool $rememberMe = null): ?string
    {
        // 确保 Token 模块已加载
        if (!class_exists('Anon_Auth_Token')) {
            Anon_Loader::loadOptionalModules('token');
        }
        
        if (!class_exists('Anon_Auth_Token') || !Anon_Auth_Token::isEnabled()) {
            return null;
        }

        // 强制刷新Token
        if (Anon_Auth_Token::isRefreshEnabled()) {
            return self::generateUserToken($userId, $username, $rememberMe);
        }

        // 检查现有Token
        $existingToken = Anon_Auth_Token::getTokenFromRequest();
        if (!empty($existingToken)) {
            $payload = Anon_Auth_Token::verify($existingToken);
            // 返回有效Token
            if ($payload !== false && isset($payload['data']['user_id']) && (int)$payload['data']['user_id'] === $userId) {
                return $existingToken;
            }
        }

        // 生成新Token
        return self::generateUserToken($userId, $username, $rememberMe);
    }

    /**
     * 验证Token
     * @param bool $throwException 是否抛出异常
     * @param bool $skip 是否跳过
     * @return bool
     */
    public static function requireToken(bool $throwException = true, bool $skip = false): bool
    {
        // 跳过验证
        if ($skip) {
            return true;
        }
        
        // 确保 Token 模块已加载
        if (!class_exists('Anon_Auth_Token')) {
            Anon_Loader::loadOptionalModules('token');
        }
        
        // 如果类仍然不存在，说明 Token 模块未启用，直接返回 true
        if (!class_exists('Anon_Auth_Token')) {
            return true;
        }
        
        // 检查启用
        if (!Anon_Auth_Token::isEnabled()) {
            return true;
        }

        // 获取路径
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = strstr($path, '?', true) ?: $path;
        
        // 去除前缀
        if (strpos($path, '/apiService') === 0) {
            $path = substr($path, strlen('/apiService'));
        }
        
        // 去除 CMS 模式下的 API 前缀
        $mode = Anon_System_Env::get('app.mode', 'api');
        if ($mode === 'cms' && class_exists('Anon_Cms_Options')) {
            $apiPrefix = Anon_Cms_Options::get('apiPrefix', '/api');
            $apiPrefix = rtrim($apiPrefix, '/');
            if (!empty($apiPrefix) && $apiPrefix !== '/' && strpos($path, $apiPrefix) === 0) {
                $path = substr($path, strlen($apiPrefix));
            }
        }
        
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        // 检查白名单（先检查原始路径，再检查去除前缀后的路径）
        if (Anon_Auth_Token::isWhitelisted($path)) {
            return true;
        }
        
        // 如果原始路径与去除前缀后的路径不同，也检查原始路径
        $originalPath = parse_url($requestUri, PHP_URL_PATH);
        $originalPath = strstr($originalPath, '?', true) ?: $originalPath;
        if ($originalPath !== $path && Anon_Auth_Token::isWhitelisted($originalPath)) {
            return true;
        }

        // 验证Token
        $token = Anon_Auth_Token::getTokenFromRequest();
        if (empty($token)) {
            // 调试接口降级验证
            $isDebugApi = strpos($path, '/anon/debug/api/') === 0;
            if ($isDebugApi && Anon_Check::isLoggedIn()) {
                // 允许Session访问
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

        // 设置登录状态
        if (isset($payload['data']['user_id'])) {
            Anon_Check::startSessionIfNotStarted();
            
            // 恢复登录
            $userId = (int)$payload['data']['user_id'];
            $username = $payload['data']['username'] ?? '';
            
            // 设置会话
            $_SESSION['user_id'] = $userId;
            if (!empty($username)) {
                $_SESSION['username'] = $username;
            }
            
            // 支持多端登录
            if (isset($payload['data']['session_id'])) {
                $currentSessionId = session_id();
            }

            // 刷新Token
            if (Anon_Auth_Token::isRefreshEnabled()) {
                // 判断记住我
                // 超过24小时视为记住我
                $expireTime = $payload['expire'] ?? 0;
                $tokenLifetime = $expireTime - ($payload['timestamp'] ?? time());
                $rememberMe = $tokenLifetime > 86400; 
                
                $newToken = self::generateUserToken($userId, $username, $rememberMe);
                
                if ($newToken !== null) {
                    // 设置新Token头
                    if (!headers_sent()) {
                        header('X-New-Token: ' . $newToken);
                    }
                }
            }
        }

        return true;
    }
}

