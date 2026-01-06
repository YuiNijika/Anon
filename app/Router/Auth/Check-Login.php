<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    
    $isLoggedIn = Anon_Check::isLoggedIn();
    $message = $isLoggedIn ? '用户已登录' : '用户未登录';
    
    Anon_Http_Response::success([
        'loggedIn' => $isLoggedIn,
        'logged_in' => $isLoggedIn,
    ], $message);
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '检查登录状态时发生错误');
}