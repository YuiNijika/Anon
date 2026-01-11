<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 速率限制
 */
class Anon_Auth_RateLimit
{
    /**
     * 获取IP
     * @return string
     */
    public static function getClientIp(): string
    {
        return Anon_Common::GetClientIp() ?? '0.0.0.0';
    }

    /**
     * 生成指纹
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
     * 检查限制
     * @param string $key 键
     * @param int $maxAttempts 最大尝试
     * @param int $windowSeconds 窗口秒数
     * @return array
     */
    public static function checkLimit(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $cacheKey = "ratelimit:{$key}";
        
        $data = Anon_Cache::get($cacheKey, [
            'count' => 0,
            'resetAt' => time() + $windowSeconds
        ]);
        
        $now = time();
        
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
     * 检查注册限制
     * @param array $config 配置
     * @return array
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
     * 清除限制
     * @param string $key 键
     * @return bool
     */
    public static function clearLimit(string $key): bool
    {
        return Anon_Cache::delete("ratelimit:{$key}");
    }

    /**
     * 清除IP限制
     * @param string|null $ip
     * @return bool
     */
    public static function clearIpLimit(?string $ip = null): bool
    {
        $ip = $ip ?? self::getClientIp();
        return self::clearLimit("register_ip:" . hash('sha256', $ip));
    }

    /**
     * 清除设备限制
     * @param string|null $fingerprint
     * @return bool
     */
    public static function clearDeviceLimit(?string $fingerprint = null): bool
    {
        $fingerprint = $fingerprint ?? self::generateDeviceFingerprint();
        return self::clearLimit("register_device:" . $fingerprint);
    }
}

