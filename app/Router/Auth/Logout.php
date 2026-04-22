<?php

use Anon\ModulesCheck;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'POST',
];

try {
    Check::logout();
    
    ResponseHelper::success([], '登出成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '登出过程中发生错误');
}