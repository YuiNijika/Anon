<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Common::Header();

try {
    Anon_RequestHelper::requireMethod('GET');
    
    // 生成验证码
    $result = Anon_Captcha::generate();
    
    // 保存验证码到 session
    Anon_Captcha::saveToSession($result['code']);
    
    // 返回 base64 图片
    Anon_ResponseHelper::success([
        'image' => $result['image']
    ], '获取验证码成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '生成验证码时发生错误');
}

