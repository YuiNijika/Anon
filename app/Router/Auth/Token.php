<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Common::Header();

try {
    Anon_RequestHelper::requireMethod('GET');
    
    $userInfo = Anon_RequestHelper::requireAuth();
    $token = Anon_RequestHelper::generateUserToken((int)$userInfo['uid'], $userInfo['name']);
    
    if ($token === null) {
        Anon_ResponseHelper::success(['token_enabled' => false], 'Token验证未启用');
    } else {
        Anon_ResponseHelper::success(['token' => $token, 'token_enabled' => true], '获取Token成功');
    }
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '获取Token时发生错误');
}

