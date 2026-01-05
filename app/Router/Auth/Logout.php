<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'POST',
];

try {
    Anon_Check::logout();
    
    Anon_Http_Response::success([], '登出成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '登出过程中发生错误');
}