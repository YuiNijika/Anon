<?php

use Anon\ModulesCheck;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    
    $isLoggedIn = Check::isLoggedIn();
    $message = $isLoggedIn ? '用户已登录' : '用户未登录';
    
    ResponseHelper::success([
        'loggedIn' => $isLoggedIn,
        'logged_in' => $isLoggedIn,
    ], $message);
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '检查登录状态时发生错误');
}