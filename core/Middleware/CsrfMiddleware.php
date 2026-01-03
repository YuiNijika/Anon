<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CSRF 防护中间件
 * 自动验证 CSRF Token
 */
class Anon_CsrfMiddleware implements Anon_MiddlewareInterface
{
    /**
     * @var array 排除的路由（不需要 CSRF 验证）
     */
    private $excludedRoutes;
    
    /**
     * 构造函数
     * @param array $excludedRoutes 排除的路由列表
     */
    public function __construct(array $excludedRoutes = [])
    {
        $this->excludedRoutes = $excludedRoutes;
    }
    
    /**
     * 处理请求
     * @param mixed $request 请求对象
     * @param callable $next 下一个中间件
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        // 检查是否启用 CSRF
        if (!Anon_Csrf::isEnabled()) {
            return $next($request);
        }
        
        // 检查是否需要验证
        if (!Anon_Csrf::requiresVerification()) {
            return $next($request);
        }
        
        // 检查是否在排除列表中
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        foreach ($this->excludedRoutes as $excluded) {
            if (strpos($path, $excluded) === 0) {
                return $next($request);
            }
        }
        
        // 验证 CSRF Token
        Anon_Csrf::verify(null, true);
        
        // 继续处理请求
        return $next($request);
    }
    
    /**
     * 创建 CSRF 中间件实例（便捷方法）
     * @param array $excludedRoutes 排除的路由列表
     * @return Anon_CsrfMiddleware
     */
    public static function make(array $excludedRoutes = []): self
    {
        return new self($excludedRoutes);
    }
}

