<?php

use Anon\Modules\AuthCaptcha;
use Anon\Modules\HttpResponseHelper;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
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
    $result = Captcha::generate();
    
    // 保存验证码到 session
    Captcha::saveToSession($result['code']);
    
    // 返回验证码图片
    ResponseHelper::success([
        'image' => $result['image']
    ], '获取验证码成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '生成验证码时发生错误');
}

