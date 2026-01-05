<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XSS 过滤中间件
 */
class Anon_XssFilterMiddleware implements Anon_MiddlewareInterface
{
    /**
     * @var bool 是否移除 HTML 标签
     */
    private $stripHtml;
    
    /**
     * @var array 跳过的字段列表
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
        $filterOptions = [
            'stripHtml' => $this->stripHtml,
            'skipFields' => $this->skipFields
        ];
        
        if (!empty($_POST)) {
            $_POST = Anon_Security_Security::filterInput($_POST, $filterOptions);
        }
        
        if (!empty($_GET)) {
            $_GET = Anon_Security_Security::filterInput($_GET, $filterOptions);
        }
        
        // 过滤JSON输入
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $jsonInput = Anon_Http_Request::getInput();
            if (!empty($jsonInput) && is_array($jsonInput)) {
                $filteredInput = Anon_Security_Security::filterInput($jsonInput, $filterOptions);
                Anon_Http_Request::setFilteredInput($filteredInput);
            }
        }
        
        return $next($request);
    }
    
    /**
     * 创建中间件实例
     * @param bool $stripHtml 是否移除 HTML 标签
     * @param array $skipFields 跳过的字段列表
     * @return Anon_XssFilterMiddleware
     */
    public static function make(bool $stripHtml = true, array $skipFields = []): self
    {
        return new self($stripHtml, $skipFields);
    }
}

