<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => false,
];

try {
    $config = Anon_System_Config::getConfig();
    Anon_Http_Response::success($config, '获取配置信息成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
