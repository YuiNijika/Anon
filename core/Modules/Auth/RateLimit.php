<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 防刷限制模块
 * 支持基于IP、设备指纹等多种限制方式
 */
class Anon_Auth_RateLimit
{
    /**
     * 获取客户端IP
     * @return string
     */
    public static function getClientIp(): string
    {
        return Anon_Common::GetClientIp() ?? '0.0.0.0';
    }

    /**
     * 生成设备指纹
     * @return string
     */
    public static function generateDeviceFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            self::getClientIp(),
        ];
        
        $fingerprint = implode('|', $components);
        return hash('sha256', $fingerprint);
    }

    /**
     * 检查是否超过限制
     * @param string $key 限制键（如 'register_ip', 'register_device'）
     * @param int $maxAttempts 最大尝试次数
     * @param int $windowSeconds 时间窗口（秒）
     * @return array ['allowed' => bool, 'remaining' => int, 'resetAt' => int]
     */
    public static function checkLimit(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $cacheKey = "ratelimit:{$key}";
        
        $data = Anon_Cache::get($cacheKey, [
            'count' => 0,
            'resetAt' => time() + $windowSeconds
        ]);
        
        $now = time();
        
        // 如果时间窗口已过期，重置计数
        if ($now >= $data['resetAt']) {
            $data = [
                'count' => 0,
                'resetAt' => $now + $windowSeconds
            ];
        }
        
        $remaining = max(0, $maxAttempts - $data['count'] - 1);
        $allowed = $data['count'] < $maxAttempts;
        
        if ($allowed) {
            $data['count']++;
            Anon_Cache::set($cacheKey, $data, $windowSeconds);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'resetAt' => $data['resetAt'],
            'count' => $data['count']
        ];
    }

    /**
     * 检查注册限制（IP + 设备指纹）
     * @param array $config 配置数组
     * @return array ['allowed' => bool, 'message' => string, 'remaining' => int, 'resetAt' => int]
     */
    public static function checkRegisterLimit(array $config = []): array
    {
        $defaultConfig = [
            'ip' => [
                'enabled' => true,
                'maxAttempts' => 5,
                'windowSeconds' => 3600, // 1小时
            ],
            'device' => [
                'enabled' => true,
                'maxAttempts' => 3,
                'windowSeconds' => 3600, // 1小时
            ],
        ];
        
        $config = array_merge($defaultConfig, $config);
        
        // 检查IP限制
        if ($config['ip']['enabled']) {
            $ip = self::getClientIp();
            $ipKey = "register_ip:" . hash('sha256', $ip);
            $ipLimit = self::checkLimit($ipKey, $config['ip']['maxAttempts'], $config['ip']['windowSeconds']);
            
            if (!$ipLimit['allowed']) {
                $resetTime = date('Y-m-d H:i:s', $ipLimit['resetAt']);
                return [
                    'allowed' => false,
                    'message' => "IP注册次数已达上限，请于 {$resetTime} 后重试",
                    'remaining' => 0,
                    'resetAt' => $ipLimit['resetAt'],
                    'type' => 'ip'
                ];
            }
        }
        
        // 检查设备指纹限制
        if ($config['device']['enabled']) {
            $deviceFingerprint = self::generateDeviceFingerprint();
            $deviceKey = "register_device:" . $deviceFingerprint;
            $deviceLimit = self::checkLimit($deviceKey, $config['device']['maxAttempts'], $config['device']['windowSeconds']);
            
            if (!$deviceLimit['allowed']) {
                $resetTime = date('Y-m-d H:i:s', $deviceLimit['resetAt']);
                return [
                    'allowed' => false,
                    'message' => "设备注册次数已达上限，请于 {$resetTime} 后重试",
                    'remaining' => 0,
                    'resetAt' => $deviceLimit['resetAt'],
                    'type' => 'device'
                ];
            }
        }
        
        // 返回允许状态和剩余次数
        $ipRemaining = $config['ip']['enabled'] ? $ipLimit['remaining'] : PHP_INT_MAX;
        $deviceRemaining = $config['device']['enabled'] ? $deviceLimit['remaining'] : PHP_INT_MAX;
        $remaining = min($ipRemaining, $deviceRemaining);
        
        return [
            'allowed' => true,
            'message' => '允许注册',
            'remaining' => $remaining,
            'resetAt' => max(
                $config['ip']['enabled'] ? $ipLimit['resetAt'] : 0,
                $config['device']['enabled'] ? $deviceLimit['resetAt'] : 0
            ),
            'type' => 'success'
        ];
    }

    /**
     * 清除限制记录
     * @param string $key 限制键
     * @return bool
     */
    public static function clearLimit(string $key): bool
    {
        return Anon_Cache::delete("ratelimit:{$key}");
    }

    /**
     * 清除IP限制
     * @param string|null $ip IP地址，null表示当前IP
     * @return bool
     */
    public static function clearIpLimit(?string $ip = null): bool
    {
        $ip = $ip ?? self::getClientIp();
        return self::clearLimit("register_ip:" . hash('sha256', $ip));
    }

    /**
     * 清除设备指纹限制
     * @param string|null $fingerprint 设备指纹，null表示当前设备
     * @return bool
     */
    public static function clearDeviceLimit(?string $fingerprint = null): bool
    {
        $fingerprint = $fingerprint ?? self::generateDeviceFingerprint();
        return self::clearLimit("register_device:" . $fingerprint);
    }
}

