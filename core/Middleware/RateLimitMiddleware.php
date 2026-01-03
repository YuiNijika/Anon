<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 接口限流中间件
 * 用于限制 API 请求频率
 */
class Anon_RateLimitMiddleware implements Anon_MiddlewareInterface
{
    /**
     * @var int 最大请求次数
     */
    private $maxAttempts;
    
    /**
     * @var int 时间窗口（秒）
     */
    private $windowSeconds;
    
    /**
     * @var string 限流键前缀
     */
    private $keyPrefix;
    
    /**
     * @var bool 是否基于 IP
     */
    private $useIp;
    
    /**
     * @var bool 是否基于用户 ID
     */
    private $useUserId;
    
    /**
     * 构造函数
     * @param int $maxAttempts 最大请求次数
     * @param int $windowSeconds 时间窗口（秒）
     * @param string $keyPrefix 限流键前缀
     * @param array $options 选项
     *   - 'useIp' => bool 是否基于 IP（默认 true）
     *   - 'useUserId' => bool 是否基于用户 ID（默认 false）
     */
    public function __construct(int $maxAttempts, int $windowSeconds, string $keyPrefix = 'api', array $options = [])
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->keyPrefix = $keyPrefix;
        $this->useIp = $options['useIp'] ?? true;
        $this->useUserId = $options['useUserId'] ?? false;
    }
    
    /**
     * 处理请求
     * @param mixed $request 请求对象
     * @param callable $next 下一个中间件
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        // 生成限流键
        $key = $this->generateKey();
        
        // 检查限流
        $limit = Anon_RateLimit::checkLimit($key, $this->maxAttempts, $this->windowSeconds);
        
        if (!$limit['allowed']) {
            // 设置响应头
            header('X-RateLimit-Limit: ' . $this->maxAttempts);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $limit['resetAt']);
            header('Retry-After: ' . ($limit['resetAt'] - time()));
            
            Anon_Common::Header(429);
            Anon_ResponseHelper::error(
                '请求过于频繁，请稍后再试',
                [
                    'limit' => $this->maxAttempts,
                    'remaining' => 0,
                    'resetAt' => $limit['resetAt'],
                    'retryAfter' => $limit['resetAt'] - time()
                ],
                429
            );
        }
        
        // 设置响应头
        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . $limit['remaining']);
        header('X-RateLimit-Reset: ' . $limit['resetAt']);
        
        // 继续处理请求
        return $next($request);
    }
    
    /**
     * 生成限流键
     * @return string
     */
    private function generateKey(): string
    {
        $parts = [$this->keyPrefix];
        
        // 添加 IP
        if ($this->useIp) {
            $ip = Anon_RateLimit::getClientIp();
            $parts[] = 'ip:' . md5($ip);
        }
        
        // 添加用户 ID
        if ($this->useUserId) {
            $userId = Anon_RequestHelper::getUserId();
            if ($userId) {
                $parts[] = 'user:' . $userId;
            }
        }
        
        // 添加路由路径
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $parts[] = 'path:' . md5($path);
        
        return implode(':', $parts);
    }
    
    /**
     * 创建限流中间件实例（便捷方法）
     * @param int $maxAttempts 最大请求次数
     * @param int $windowSeconds 时间窗口（秒）
     * @param string $keyPrefix 限流键前缀
     * @param array $options 选项
     * @return Anon_RateLimitMiddleware
     */
    public static function make(int $maxAttempts, int $windowSeconds, string $keyPrefix = 'api', array $options = []): self
    {
        return new self($maxAttempts, $windowSeconds, $keyPrefix, $options);
    }
}

