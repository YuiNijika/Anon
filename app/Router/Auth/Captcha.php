<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    
    // 生成验证码
    $result = Anon_Captcha::generate();
    
    // 保存验证码到 session
    Anon_Captcha::saveToSession($result['code']);
    
    // 返回验证码图片
    Anon_ResponseHelper::success([
        'image' => $result['image']
    ], '获取验证码成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '生成验证码时发生错误');
}

