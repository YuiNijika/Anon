<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Token 验证类
 * 用于防止 API 被刷，支持签名验证和时间戳验证
 */
class Anon_Token
{
    /**
     * 生成 Token
     * @param array $data 要包含在 token 中的数据
     * @param int $expire 过期时间秒数，默认 3600 秒即1小时
     * @return string Token 字符串
     */
    public static function generate(array $data = [], int $expire = 3600): string
    {
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
            // 如果时间戳差异超过5分钟，记录警告但不拒绝 允许时钟偏差
            $timestampDiff = abs(time() - $payload['timestamp']);
            if ($timestampDiff > 300) {
                // 记录时间戳异常，但不拒绝 Token允许时钟偏差
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    error_log("Token timestamp diff: {$timestampDiff} seconds");
                }
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
        
        // 使用更安全的密钥来源 优先使用配置的密钥 避免依赖可伪造的 $_SERVER
        $serverKey = defined('ANON_SECRET_KEY') ? ANON_SECRET_KEY : (defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : 'anon_default_key_change_in_production');
        
        $secretData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'username' => $username,
            'server_key' => $serverKey
        ];
        
        return hash('sha256', json_encode($secretData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 检查是否应该记录详细错误信息
     * @return bool
     */
    private static function shouldLogDetailedErrors(): bool
    {
        if (class_exists('Anon_Env') && Anon_Env::isInitialized()) {
            return Anon_Env::get('app.debug.logDetailedErrors', false);
        }
        return false;
    }

    /**
     * 检查是否启用 Token 验证
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (Anon_Env::isInitialized()) {
            return Anon_Env::get('app.token.enabled', false);
        }
        return defined('ANON_TOKEN_ENABLED') ? ANON_TOKEN_ENABLED : false;
    }

    /**
     * 检查是否启用Token刷新
     * @return bool
     */
    public static function isRefreshEnabled(): bool
    {
        if (Anon_Env::isInitialized()) {
            return Anon_Env::get('app.token.refresh', false);
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
        
        if (Anon_Env::isInitialized()) {
            $whitelist = Anon_Env::get('app.token.whitelist', []);
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

