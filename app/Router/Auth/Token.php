<?php

use Anon\ModulesCheck;
use Anon\Modules\HttpRequestHelper;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => '请先登录以获取 Token',
    'method' => 'GET',
];

try {
    
    $isLoggedIn = Check::isLoggedIn();
    $token = '';
    
    if ($isLoggedIn) {
        $userId = RequestHelper::getUserId();
        $username = $_SESSION['username'] ?? '';
        if ($userId && $username) {
            $token = RequestHelper::getUserToken($userId, $username);
        }
    }
    
    $message = $token ? '获取 Token 成功' : '用户未登录，无法获取 Token';
    
    ResponseHelper::success([
        'token' => $token,
    ], $message);
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '获取 Token 时发生错误');
}
