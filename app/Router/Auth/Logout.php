<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Common::Header();

try {
    Anon_RequestHelper::requireMethod('POST');
    
    Anon_Check::logout();
    
    Anon_ResponseHelper::success(null, '登出成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '登出过程中发生错误');
}