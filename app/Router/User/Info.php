<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    $userInfo = Anon_RequestHelper::requireAuth();
    
    $token = Anon_RequestHelper::getUserToken((int)$userInfo['uid'], $userInfo['name']);
    if ($token !== null) {
        $userInfo['token'] = $token;
    }
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '获取用户信息发生错误');
}
