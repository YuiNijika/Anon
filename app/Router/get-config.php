<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'token' => false,
];

try {
    $config = [
        'token' => Anon_Auth_Token::isEnabled(),
        'captcha' => Anon_Auth_Captcha::isEnabled(),
        'csrfToken' => class_exists('Anon_Csrf') ? Anon_Auth_Csrf::generateToken() : null
    ];
    Anon_Http_Response::success($config, '获取配置信息成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
