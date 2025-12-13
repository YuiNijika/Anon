<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Common::Header();

try {
    Anon_RequestHelper::requireMethod('POST');
    
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空'
    ]);
    
    // 获取原始输入数据
    $inputData = Anon_RequestHelper::getInput();
    
    // 如果启用验证码，验证验证码
    if (class_exists('Anon_Captcha') && Anon_Captcha::isEnabled()) {
        if (empty($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码不能为空', null, 400);
        }
        
        if (!Anon_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码错误', null, 400);
        }
        
        // 验证成功后清除验证码
        Anon_Captcha::clear();
    }
    
    $username = $data['username'];
    $password = $data['password'];
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = new Anon_Database();
    $user = $db->getUserInfoByName($username);
    
    if (!$user || !password_verify($password, $user['password'])) {
        Anon_ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    Anon_Check::startSessionIfNotStarted();
    session_regenerate_id(true);
    
    $userId = (int)$user['uid'];
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['name'];
    
    Anon_Check::setAuthCookies($userId, $user['name'], $rememberMe);
    
    $token = Anon_RequestHelper::generateUserToken($userId, $user['name'], $rememberMe);
    
    $userData = [
        'user_id' => $userId,
        'username' => $user['name'],
        'email' => $user['email'],
        'logged_in' => true
    ];
    
    if ($token !== null) {
        $userData['token'] = $token;
    }
    
    Anon_ResponseHelper::success($userData, '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '登录处理过程中发生错误');
}
