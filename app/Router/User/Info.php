<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Common::Header();

try {
    Anon_RequestHelper::requireMethod('GET');
    
    $userInfo = Anon_RequestHelper::requireAuth();
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '获取用户信息发生错误');
}
