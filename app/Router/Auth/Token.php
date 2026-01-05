<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
    'cache' => [
        'enabled' => false,
        'time' => 0, 
    ],
];

try {
    $userInfo = Anon_Http_Request::requireAuth();
    
    $token = Anon_Http_Request::getUserToken((int)$userInfo['uid'], $userInfo['name']);
    
    if ($token === null) {
        Anon_Http_Response::success(['token_enabled' => false], 'Token验证未启用');
    } else {
        Anon_Http_Response::success(['token' => $token, 'token_enabled' => true], '获取Token成功');
    }
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '获取Token时发生错误');
}

