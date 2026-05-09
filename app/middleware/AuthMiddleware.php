<?php

namespace Anon\Middleware;

use Anon\Core\Http\Request;
use Anon\Core\Http\Response;

class AuthMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        // 模拟拦截未授权的请求
        $token = $request->input('token');
        if (empty($token)) {
            return Response::error('Unauthorized: Missing token', 401);
        }

        // 继续传递给下一个中间件或路由目标
        $response = $next($request);

        // 可以在这里对响应进行后置处理
        $response->setHeader('X-Powered-By', 'Anon-Next-Auth');

        return $response;
    }
}
