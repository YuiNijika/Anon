<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => '请先登录以获取 Token',
    'method' => 'GET',
];

try {
    
    $isLoggedIn = Anon_Check::isLoggedIn();
    $token = '';
    
    if ($isLoggedIn) {
        $userId = Anon_Http_Request::getUserId();
        $username = $_SESSION['username'] ?? '';
        if ($userId && $username) {
            $token = Anon_Http_Request::getUserToken($userId, $username);
        }
    }
    
    $message = $token ? '获取 Token 成功' : '用户未登录，无法获取 Token';
    
    Anon_Http_Response::success([
        'token' => $token,
    ], $message);
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '获取 Token 时发生错误');
}
