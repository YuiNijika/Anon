<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XSS 过滤中间件
 * 自动过滤请求数据中的 XSS 代码
 */
class Anon_XssFilterMiddleware implements Anon_MiddlewareInterface
{
    /**
     * @var bool 是否移除 HTML 标签
     */
    private $stripHtml;
    
    /**
     * @var array 跳过的字段列表（不进行过滤）
     */
    private $skipFields;
    
    /**
     * 构造函数
     * @param bool $stripHtml 是否移除 HTML 标签
     * @param array $skipFields 跳过的字段列表
     */
    public function __construct(bool $stripHtml = true, array $skipFields = [])
    {
        $this->stripHtml = $stripHtml;
        $this->skipFields = $skipFields;
    }
    
    /**
     * 处理请求
     * @param mixed $request 请求对象
     * @param callable $next 下一个中间件
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        // 过滤 POST 数据
        if (!empty($_POST)) {
            $_POST = Anon_Security::filterInput($_POST, [
                'stripHtml' => $this->stripHtml,
                'skipFields' => $this->skipFields
            ]);
        }
        
        // 过滤 GET 数据
        if (!empty($_GET)) {
            $_GET = Anon_Security::filterInput($_GET, [
                'stripHtml' => $this->stripHtml,
                'skipFields' => $this->skipFields
            ]);
        }
        
        // 继续处理请求
        return $next($request);
    }
    
    /**
     * 创建 XSS 过滤中间件实例（便捷方法）
     * @param bool $stripHtml 是否移除 HTML 标签
     * @param array $skipFields 跳过的字段列表
     * @return Anon_XssFilterMiddleware
     */
    public static function make(bool $stripHtml = true, array $skipFields = []): self
    {
        return new self($stripHtml, $skipFields);
    }
}

