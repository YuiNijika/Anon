<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    $userInfo = Anon_Http_Request::requireAuth();
    
    Anon_Http_Response::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '获取用户信息发生错误');
}
