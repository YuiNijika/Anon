<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CSRF防护
 */
class Anon_Auth_Csrf
{
    /**
     * @var bool|null 无状态
     */
    private static $statelessEnabled = null;

    /**
     * 检查无状态
     * @return bool
     */
    private static function isStatelessEnabled(): bool
    {
        if (self::$statelessEnabled === null) {
            if (Anon_System_Env::isInitialized()) {
                self::$statelessEnabled = Anon_System_Env::get('app.security.csrf.stateless', true);
            } else {
                self::$statelessEnabled = defined('ANON_CSRF_STATELESS') ? ANON_CSRF_STATELESS : true;
            }
        }
        return self::$statelessEnabled;
    }

    /**
     * 获取密钥
     * @return string
     */
    private static function getSecretKey(): string
    {
        if (defined('ANON_APP_KEY') && !empty(ANON_APP_KEY)) {
            return hash('sha256', ANON_APP_KEY . '_csrf');
        }

        if (Anon_System_Env::isInitialized()) {
            $appKey = Anon_System_Env::get('app.key');
            if (!empty($appKey)) {
                return hash('sha256', $appKey . '_csrf');
            }
        }

        Anon_Debug::warn('Security Warning: ANON_APP_KEY not configured!');
            
        return hash('sha256', 'anon_default_insecure_key_csrf');
    }

    /**
     * 生成Token
     * @return string
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
     * 生成无状态Token
     * @return string
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
     * 获取Token
     * @return string|null
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * 验证Token
     * @param string|null $token
     * @param bool $throwException
     * @return bool
     */
    public static function verify(?string $token = null, bool $throwException = true): bool
    {
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }
        
        if (empty($token)) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_Http_Response::forbidden('CSRF Token 未提供');
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
                Anon_Http_Response::forbidden('CSRF Token 已过期，请刷新页面重试');
            }
            return false;
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_Http_Response::forbidden('CSRF Token 验证失败');
            }
            return false;
        }
        
        self::refreshToken();
        
        return true;
    }

    /**
     * 验证无状态Token
     * @param string $token
     * @param bool $throwException
     * @return bool
     */
    private static function verifyStatelessToken(string $token, bool $throwException = true): bool
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_Http_Response::forbidden('CSRF Token 格式错误');
                }
                return false;
            }

            $parts = explode('.', $decoded, 2);
            if (count($parts) !== 2) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_Http_Response::forbidden('CSRF Token 格式错误');
                }
                return false;
            }

            list($payload, $signature) = $parts;

            $expectedSignature = hash_hmac('sha256', $payload, self::getSecretKey());
            if (!hash_equals($expectedSignature, $signature)) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_Http_Response::forbidden('CSRF Token 验证失败');
                }
                return false;
            }

            $data = json_decode(base64_decode($payload, true), true);
            if (!is_array($data) || !isset($data['timestamp']) || !isset($data['nonce'])) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_Http_Response::forbidden('CSRF Token 数据错误');
                }
                return false;
            }

            $maxAge = 7200;
            if (time() - $data['timestamp'] > $maxAge) {
                if ($throwException) {
                    Anon_Common::Header(403);
                    Anon_Http_Response::forbidden('CSRF Token 已过期，请刷新页面重试');
                }
                return false;
            }

            if (session_status() === PHP_SESSION_ACTIVE && isset($data['session_id'])) {
                $currentSessionId = session_id();
                if ($currentSessionId && $data['session_id'] !== $currentSessionId) {
                    if ($throwException) {
                        Anon_Common::Header(403);
                        Anon_Http_Response::forbidden('CSRF Token Session 不匹配');
                    }
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_Http_Response::forbidden('CSRF Token 验证异常');
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
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }
        
        $inputData = Anon_Http_Request::getInput();
        if (isset($inputData['_csrf_token'])) {
            return $inputData['_csrf_token'];
        }
        
        if (isset($_GET['_csrf_token'])) {
            return $_GET['_csrf_token'];
        }
        
        return null;
    }
    
    /**
     * 刷新Token
     * @return string
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
     * 清除Token
     * @return void
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
     * 检查启用
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.security.csrf.enabled', true);
        }
        return defined('ANON_CSRF_ENABLED') ? ANON_CSRF_ENABLED : true;
    }
    
    /**
     * 检查是否需要验证
     * @param string|null $method
     * @return bool
     */
    public static function requiresVerification(?string $method = null): bool
    {
        if ($method === null) {
            $method = Anon_Http_Request::method();
        }
        
        $method = strtoupper($method);
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        return !in_array($method, $safeMethods);
    }
}

