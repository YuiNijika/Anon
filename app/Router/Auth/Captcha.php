<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => [
        'enabled' => false,
        'time' => 0, 
    ],
];

try {
    
    // 生成验证码
    $result = Anon_Auth_Captcha::generate();
    
    // 保存验证码到 session
    Anon_Auth_Captcha::saveToSession($result['code']);
    
    // 返回验证码图片
    Anon_Http_Response::success([
        'image' => $result['image']
    ], '获取验证码成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '生成验证码时发生错误');
}

