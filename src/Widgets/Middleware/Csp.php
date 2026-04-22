<?php
namespace Anon\Widgets\Middleware;


use Middleware;
use Anon\Modules\System\Env;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Csp
{
    /**
     * 执行 CSP 策略
     * @param array $data 请求数据
     * @return array
     */
    public static function handle($data)
    {
        if (!Env::get('app.base.security.csp.enabled', false)) {
            return $data;
        }
        
        $policy = Env::get('app.base.security.csp.policy', "default-src 'self'");
        $reportOnly = Env::get('app.base.security.csp.reportOnly', false);
        $reportUri = Env::get('app.base.security.csp.reportUri', '');
        
        $headerName = $reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        header("{$headerName}: {$policy}");
        
        // 报告 URI
        if ($reportUri && !$reportOnly) {
            header("Report-To: {\"group\":\"csp-endpoint\",\"max_age\":10886400,\"endpoints\":[{\"url\":\"{$reportUri}\"}]}");
        }
        
        return $data;
    }
}
