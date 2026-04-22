<?php

use Anon\Modules\SystemConfig;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => false,
];

try {
    $config = Config::getConfig();
    ResponseHelper::success($config, '获取配置信息成功');
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
