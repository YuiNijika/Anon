<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Token 验证类
 * 用于防止 API 被刷，支持签名验证和时间戳验证
 */
class Anon_Auth_Token
{
    /**
     * Token 验证结果缓存
     * 对已验证的 Token 暂存于内存缓存，避免重复计算验证逻辑
     * @var array
     */
    private static $verificationCache = [];

    /**
     * 生成 Token
     * @param array $data 要包含在 token 中的数据
     * @param int|null $expire 过期时间秒数，如果为 null 则根据操作类型自动设置（敏感操作 60 秒，非敏感操作 300 秒）
     * @param bool $isSensitive 是否为敏感操作（如删除、修改密码等），默认 false
     * @return string Token 字符串
     */
    public static function generate(array $data = [], ?int $expire = null, bool $isSensitive = false): string
    {
        // 如果没有指定过期时间，根据操作类型自动设置
        if ($expire === null) {
            $expire = $isSensitive ? 60 : 300; // 敏感操作 1 分钟，非敏感操作 5 分钟
        }
        
        $timestamp = time();
        $expireTime = $timestamp + $expire;
        $nonce = bin2hex(random_bytes(16));

        $payload = [
            'data' => $data,
            'timestamp' => $timestamp,
            'expire' => $expireTime,
            'nonce' => $nonce
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $secret = self::generateSecret($data);
        $signature = hash_hmac('sha256', $payloadJson, $secret);

        $token = base64_encode($payloadJson . '.' . $signature);

        return $token;
    }

    /**
     * 验证 Token
     * @param string|null $token Token 字符串，如果为 null 则从请求中获取
     * @return array|false 验证成功返回 payload 数据，失败返回 false
     */
    public static function verify(?string $token = null)
    {
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }

        if (empty($token)) {
            return false;
        }

        // 检查缓存
        $cacheKey = 'token_' . hash('sha256', $token);
        if (isset(self::$verificationCache[$cacheKey])) {
            $cached = self::$verificationCache[$cacheKey];
            // 检查缓存是否过期
            if ($cached['expire'] > time()) {
                return $cached['payload'];
            } else {
                // 缓存已过期，清除
                unset(self::$verificationCache[$cacheKey]);
            }
        }

        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                return false;
            }

            $parts = explode('.', $decoded, 2);
            if (count($parts) !== 2) {
                return false;
            }

            list($payloadJson, $signature) = $parts;

            $payload = json_decode($payloadJson, true);
            if (!is_array($payload) || !isset($payload['expire']) || !isset($payload['data'])) {
                return false;
            }

            if (time() > $payload['expire']) {
                return false;
            }

            try {
                $secret = self::generateSecret($payload['data']);
                $expectedSignature = hash_hmac('sha256', $payloadJson, $secret);
                if (!hash_equals($expectedSignature, $signature)) {
                    // 根据配置决定是否记录详细错误
                    $logDetailed = self::shouldLogDetailedErrors();
                    if ($logDetailed) {
                        error_log("Token signature mismatch. Expected: " . substr($expectedSignature, 0, 16) . "... Got: " . substr($signature, 0, 16) . "...");
                    } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
                        error_log("Token signature mismatch");
                    }
                    return false;
                }
            } catch (Exception $e) {
                // 根据配置决定是否记录详细错误
                $logDetailed = self::shouldLogDetailedErrors();
                if ($logDetailed) {
                    error_log("Token secret generation failed: " . $e->getMessage());
                } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log("Token secret generation failed");
                }
                return false;
            }

            // 时间戳验证
            // 如果时间戳差异超过2分钟，拒绝 Token 防止重放攻击
            $timestampDiff = abs(time() - $payload['timestamp']);
            $maxTimestampDiff = 120; // 2分钟时间窗口
            
            if ($timestampDiff > $maxTimestampDiff) {
                // 记录时间戳异常并拒绝 Token
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log("Token timestamp diff too large: {$timestampDiff} seconds, rejected");
                }
                return false;
            }

            // 缓存验证结果，有效期设置为 Token 剩余有效期的 80%，避免缓存过期时间超过 Token 本身
            $remainingTime = $payload['expire'] - time();
            $cacheExpire = time() + (int)($remainingTime * 0.8);
            if ($cacheExpire > time()) {
                self::$verificationCache[$cacheKey] = [
                    'payload' => $payload,
                    'expire' => $cacheExpire
                ];
            }

            return $payload;
        } catch (Exception $e) {
            // 根据配置决定是否记录详细错误
            $logDetailed = self::shouldLogDetailedErrors();
            if ($logDetailed) {
                error_log("Token verification error: " . $e->getMessage());
            } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
                error_log("Token verification error");
            }
            return false;
        }
    }

    /**
     * 从请求中获取 Token
     * 仅支持从 HTTP Header 获取，不支持 URL 参数和 POST 数据
     * @return string|null Token 字符串
     */
    public static function getTokenFromRequest(): ?string
    {
        // 优先从 Header 获取
        $headers = getallheaders();
        if ($headers) {
            if (isset($headers['X-API-Token'])) {
                return trim($headers['X-API-Token']);
            }
            if (isset($headers['x-api-token'])) {
                return trim($headers['x-api-token']);
            }
        }

        // 兼容环境getallheaders不可用时从$_SERVER获取
        if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
            return trim($_SERVER['HTTP_X_API_TOKEN']);
        }

        // 从 Authorization Header 获取 Bearer token
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * 基于用户会话信息生成密钥
     * @param array $data Token 数据
     * @return string 密钥
     */
    private static function generateSecret(array $data): string
    {
        $sessionId = $data['session_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $username = $data['username'] ?? '';
        
        if (!$sessionId) {
            throw new RuntimeException('无法生成 Token：缺少会话ID');
        }
        
        // 获取安全密钥
        $serverKey = self::getSecretKey();
        
        $secretData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'username' => $username,
            'server_key' => $serverKey
        ];
        
        return hash('sha256', json_encode($secretData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取服务器密钥
     * Token签名已包含session_id，此密钥作为额外保护层
     * @return string
     */
    private static function getSecretKey(): string
    {
        // 使用数据库密码作为服务器标识
        if (defined('ANON_DB_PASSWORD') && !empty(ANON_DB_PASSWORD)) {
            return ANON_DB_PASSWORD;
        }

        return 'anon_default_key';
    }

    /**
     * 检查是否应该记录详细错误信息
     * @return bool
     */
    private static function shouldLogDetailedErrors(): bool
    {
        if (class_exists('Anon_Env') && Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.debug.logDetailedErrors', false);
        }
        return false;
    }

    /**
     * 检查是否启用 Token 验证
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.token.enabled', false);
        }
        return defined('ANON_TOKEN_ENABLED') ? ANON_TOKEN_ENABLED : false;
    }

    /**
     * 检查是否启用Token刷新
     * @return bool
     */
    public static function isRefreshEnabled(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.token.refresh', false);
        }
        return false;
    }

    /**
     * 获取不需要 Token 验证的路由白名单
     * @return array 路由路径数组
     */
    public static function getWhitelist(): array
    {
        $defaultWhitelist = [
            '/anon/ciallo',
            '/anon/install',
            '/anon/common/*',
            '/anon/debug/login',
            '/anon/debug/console',
        ];
        
        if (Anon_System_Env::isInitialized()) {
            $whitelist = Anon_System_Env::get('app.token.whitelist', []);
            if (is_array($whitelist) && !empty($whitelist)) {
                return array_merge($defaultWhitelist, $whitelist);
            }
        } elseif (defined('ANON_TOKEN_WHITELIST') && is_array(ANON_TOKEN_WHITELIST) && !empty(ANON_TOKEN_WHITELIST)) {
            return array_merge($defaultWhitelist, ANON_TOKEN_WHITELIST);
        }
        
        return $defaultWhitelist;
    }

    /**
     * 检查路由是否在白名单中
     * @param string $route 路由路径
     * @return bool
     */
    public static function isWhitelisted(string $route): bool
    {
        $whitelist = self::getWhitelist();
        if (empty($whitelist)) {
            return false;
        }

        foreach ($whitelist as $pattern) {
            // 支持精确匹配和通配符匹配
            if ($pattern === $route) {
                return true;
            }
            // 支持通配符，如 /api/public/*
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
                if (preg_match($regex, $route)) {
                    return true;
                }
            }
        }

        return false;
    }
}

