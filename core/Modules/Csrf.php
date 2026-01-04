<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CSRF 防护模块
 * 用于防止跨站请求伪造攻击
 * 支持无状态设计，基于 HMAC 的 Token 无需存储在 Session 中
 */
class Anon_Csrf
{
    /**
     * 是否使用无状态 Token
     * 无状态 Token 基于 HMAC，无需存储在 Session 中，减少锁竞争
     * @var bool
     */
    private static $statelessEnabled = null;

    /**
     * 检查是否启用无状态 Token
     * @return bool
     */
    private static function isStatelessEnabled(): bool
    {
        if (self::$statelessEnabled === null) {
            if (Anon_Env::isInitialized()) {
                self::$statelessEnabled = Anon_Env::get('app.security.csrf.stateless', true);
            } else {
                self::$statelessEnabled = defined('ANON_CSRF_STATELESS') ? ANON_CSRF_STATELESS : true;
            }
        }
        return self::$statelessEnabled;
    }

    /**
     * 获取 CSRF 密钥
     * @return string
     */
    private static function getSecretKey(): string
    {
        $key = defined('ANON_SECRET_KEY') ? ANON_SECRET_KEY : (defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : 'anon_default_key_change_in_production');
        return hash('sha256', $key . 'csrf');
    }

    /**
     * 生成 CSRF Token
     * 优先使用无状态 Token，基于 HMAC 无需存储在 Session 中
     * @return string Token 字符串
     */
    public static function generateToken(): string
    {
        if (self::isStatelessEnabled()) {
            return self::generateStatelessToken();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * 生成无状态 CSRF Token
     * 基于 HMAC 的 Token，包含时间戳和随机数，无需存储在 Session 中
     * @return string Token 字符串
     */
    private static function generateStatelessToken(): string
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $sessionId = session_id() ?: 'anonymous';
        
        $data = [
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'session_id' => $sessionId
        ];
        
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, self::getSecretKey());
        
        return base64_encode($payload . '.' . $signature);
    }
    
    /**
     * 获取当前 CSRF Token
     * @return string|null Token 字符串，如果不存在则返回 null
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * 验证 CSRF Token
     * @param string|null $token 要验证的 Token，如果为 null 则从请求中获取
     * @param bool $throwException 验证失败时是否抛出异常
     * @return bool 验证成功返回 true，失败返回 false
     */
    public static function verify(?string $token = null, bool $throwException = true): bool
    {
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }
        
        if (empty($token)) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_ResponseHelper::forbidden('CSRF Token 未提供');
            }
            return false;
        }

        if (self::isStatelessEnabled()) {
            return self::verifyStatelessToken($token, $throwException);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_ResponseHelper::forbidden('CSRF Token 已过期，请刷新页面重试');
            }
            return false;
        }
        
        // 使用 hash_equals 防止时序攻击
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_ResponseHelper::forbidden('CSRF Token 验证失败');
            }
            return false;
        }
        
        // 验证通过后立即失效，生成新 Token（一次性使用）
        self::refreshToken();
        
        return true;
    }

    /**
     * 验证无状态 CSRF Token
     * @param string $token Token 字符串
     * @param bool $throwException 验证失败时是否抛出异常
     * @return bool 验证成功返回 true，失败返回 false
     */
    private static function verifyStatelessToken(string $token, bool $throwException = true): bool
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_ResponseHelper::forbidden('CSRF Token 格式错误');
                }
                return false;
            }

            $parts = explode('.', $decoded, 2);
            if (count($parts) !== 2) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_ResponseHelper::forbidden('CSRF Token 格式错误');
                }
                return false;
            }

            list($payload, $signature) = $parts;

            // 验证签名
            $expectedSignature = hash_hmac('sha256', $payload, self::getSecretKey());
            if (!hash_equals($expectedSignature, $signature)) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_ResponseHelper::forbidden('CSRF Token 验证失败');
                }
                return false;
            }

            // 解析 payload
            $data = json_decode(base64_decode($payload, true), true);
            if (!is_array($data) || !isset($data['timestamp']) || !isset($data['nonce'])) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_ResponseHelper::forbidden('CSRF Token 数据错误');
                }
                return false;
            }

            // 验证时间戳，Token 有效期 2 小时
            $maxAge = 7200;
            if (time() - $data['timestamp'] > $maxAge) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_ResponseHelper::forbidden('CSRF Token 已过期，请刷新页面重试');
                }
                return false;
            }

            // 验证 Session ID 匹配（如果存在 Session）
            if (session_status() === PHP_SESSION_ACTIVE && isset($data['session_id'])) {
                $currentSessionId = session_id();
                if ($currentSessionId && $data['session_id'] !== $currentSessionId) {
                    if ($throwException) {
                        Anon_Common::Header(403);
                        Anon_ResponseHelper::forbidden('CSRF Token Session 不匹配');
                    }
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_ResponseHelper::forbidden('CSRF Token 验证异常');
            }
            return false;
        }
    }
    
    /**
     * 从请求中获取 CSRF Token
     * 支持从以下位置获取：
     * 1. HTTP Header: X-CSRF-Token
     * 2. POST 参数: _csrf_token
     * 3. GET 参数: _csrf_token
     * @return string|null Token 字符串，如果不存在则返回 null
     */
    public static function getTokenFromRequest(): ?string
    {
        // 优先从 Header 获取
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }
        
        // 从 POST 参数获取
        $inputData = Anon_RequestHelper::getInput();
        if (isset($inputData['_csrf_token'])) {
            return $inputData['_csrf_token'];
        }
        
        // 从 GET 参数获取
        if (isset($_GET['_csrf_token'])) {
            return $_GET['_csrf_token'];
        }
        
        return null;
    }
    
    /**
     * 刷新 CSRF Token
     * @return string 新的 Token 字符串
     */
    public static function refreshToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 清除 CSRF Token
     */
    public static function clearToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }
    
    /**
     * 检查是否启用 CSRF 防护
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (Anon_Env::isInitialized()) {
            return Anon_Env::get('app.security.csrf.enabled', true);
        }
        return defined('ANON_CSRF_ENABLED') ? ANON_CSRF_ENABLED : true;
    }
    
    /**
     * 检查请求方法是否需要 CSRF 验证
     * 默认只验证 POST、PUT、PATCH、DELETE 等修改性请求
     * @param string|null $method HTTP 方法，如果为 null 则从请求中获取
     * @return bool
     */
    public static function requiresVerification(?string $method = null): bool
    {
        if ($method === null) {
            $method = Anon_RequestHelper::method();
        }
        
        $method = strtoupper($method);
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        return !in_array($method, $safeMethods);
    }
}

