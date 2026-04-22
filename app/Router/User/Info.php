<?php

use Anon\Modules\HttpRequestHelper;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    $userInfo = RequestHelper::requireAuth();
    
    ResponseHelper::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '获取用户信息发生错误');
}
