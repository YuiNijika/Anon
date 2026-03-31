<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CSP 内容安全策略中间件
 */
class Anon_Widget_Middleware_Csp
{
    /**
     * 执行 CSP 策略
     * @param array $data 请求数据
     * @return array
     */
    public static function handle($data)
    {
        if (!Anon_System_Env::get('app.security.csp.enabled', false)) {
            return $data;
        }
        
        $policy = Anon_System_Env::get('app.security.csp.policy', "default-src 'self'");
        $reportOnly = Anon_System_Env::get('app.security.csp.reportOnly', false);
        $reportUri = Anon_System_Env::get('app.security.csp.reportUri', '');
        
        // CSP 响应头
        $headerName = $reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        header("{$headerName}: {$policy}");
        
        // 报告 URI
        if ($reportUri && !$reportOnly) {
            header("Report-To: {\"group\":\"csp-endpoint\",\"max_age\":10886400,\"endpoints\":[{\"url\":\"{$reportUri}\"}]}");
        }
        
        return $data;
    }
}
