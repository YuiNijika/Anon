<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Token认证
 */
class Anon_Auth_Token
{
    /**
     * @var array 验证缓存
     */
    private static $verificationCache = [];

    /**
     * 生成Token
     * @param array $data 数据
     * @param int|null $expire 过期时间
     * @param bool $isSensitive 敏感操作
     * @return string
     */
    public static function generate(array $data = [], ?int $expire = null, bool $isSensitive = false): string
    {
        if ($expire === null) {
            $expire = $isSensitive ? 60 : 300;
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
     * 验证Token
     * @param string|null $token
     * @return array|false
     */
    public static function verify(?string $token = null)
    {
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }

        if (empty($token)) {
            return false;
        }

        $cacheKey = 'token_' . hash('sha256', $token);
        if (isset(self::$verificationCache[$cacheKey])) {
            $cached = self::$verificationCache[$cacheKey];
            if ($cached['expire'] > time()) {
                return $cached['payload'];
            } else {
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
                    $logDetailed = self::shouldLogDetailedErrors();
                    if ($logDetailed) {
                        error_log("Token signature mismatch. Expected: " . substr($expectedSignature, 0, 16) . "... Got: " . substr($signature, 0, 16) . "...");
                    } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
                        error_log("Token signature mismatch");
                    }
                    return false;
                }
            } catch (Exception $e) {
                $logDetailed = self::shouldLogDetailedErrors();
                if ($logDetailed) {
                    error_log("Token secret generation failed: " . $e->getMessage());
                } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log("Token secret generation failed");
                }
                return false;
            }

            // 检查仅在启用 refresh 时进行严格检查，防止重放攻击
            // 未启用 refresh 时，只要 token 未过期即可使用
            if (self::isRefreshEnabled()) {
                $timestampDiff = abs(time() - $payload['timestamp']);
                $maxTimestampDiff = 120; // 2分钟时间窗口
                
                if ($timestampDiff > $maxTimestampDiff) {
                    if (defined('ANON_DEBUG') && ANON_DEBUG) {
                        error_log("Token timestamp diff too large: {$timestampDiff} seconds, rejected");
                    }
                    return false;
                }
            }

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
     * 获取Token
     * @return string|null
     */
    public static function getTokenFromRequest(): ?string
    {
        $headers = getallheaders();
        if ($headers) {
            if (isset($headers['X-API-Token'])) {
                return trim($headers['X-API-Token']);
            }
            if (isset($headers['x-api-token'])) {
                return trim($headers['x-api-token']);
            }
        }

        if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
            return trim($_SERVER['HTTP_X_API_TOKEN']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * 生成密钥
     * @param array $data
     * @return string
     */
    private static function generateSecret(array $data): string
    {
        $sessionId = $data['session_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $username = $data['username'] ?? '';
        
        if (!$sessionId) {
            throw new RuntimeException('无法生成 Token：缺少会话ID');
        }
        
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
     * @return string
     */
    private static function getSecretKey(): string
    {
        if (defined('ANON_APP_KEY') && !empty(ANON_APP_KEY)) {
            return ANON_APP_KEY;
        }

        if (Anon_System_Env::isInitialized()) {
            $appKey = Anon_System_Env::get('app.key');
            if (!empty($appKey)) {
                return $appKey;
            }
        }

        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            error_log('Security Warning: ANON_APP_KEY not configured!');
        }

        return 'anon_default_insecure_key';
    }

    /**
     * 检查详细日志
     * @return bool
     */
    private static function shouldLogDetailedErrors(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.debug.logDetailedErrors', false);
        }
        return false;
    }

    /**
     * 检查启用
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
     * 检查刷新
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
     * 获取白名单
     * @return array
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
     * 检查白名单
     * @param string $route
     * @return bool
     */
    public static function isWhitelisted(string $route): bool
    {
        $whitelist = self::getWhitelist();
        if (empty($whitelist)) {
            return false;
        }

        foreach ($whitelist as $pattern) {
            if ($pattern === $route) {
                return true;
            }
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

