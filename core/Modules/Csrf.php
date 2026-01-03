<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CSRF 防护模块
 * 用于防止跨站请求伪造攻击
 */
class Anon_Csrf
{
    /**
     * 生成 CSRF Token
     * @return string Token 字符串
     */
    public static function generateToken(): string
    {
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($token === null) {
            // 从请求中获取 Token，支持 header 和 body
            $token = self::getTokenFromRequest();
        }
        
        if (empty($token)) {
            if ($throwException) {
                Anon_Common::Header(403);
                Anon_ResponseHelper::forbidden('CSRF Token 未提供');
            }
            return false;
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
        
        return true;
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

