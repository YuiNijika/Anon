<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空'
    ]);
    
    $inputData = Anon_RequestHelper::getInput();
    
    if (class_exists('Anon_Captcha') && Anon_Captcha::isEnabled()) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码不能为空', [], 400);
        }
        
        if (!Anon_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码错误', [], 400);
        }
        
        Anon_Captcha::clear();
    }
    
    Anon_Check::startSessionIfNotStarted();
    
    $username = $data['username'];
    $password = $data['password'];
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = Anon_Database::getInstance();
    $user = $db->getUserInfoByName($username);
    
    if (!$user || !password_verify($password, $user['password'])) {
        // 记录登录失败
        $db->logLogin(null, $username, false, '用户名或密码错误');
        Anon_ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    session_regenerate_id(true);
    
    $userId = (int)$user['uid'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['name'];
    
    Anon_Check::setAuthCookies($userId, $user['name'], $rememberMe);
    
    $token = Anon_RequestHelper::generateUserToken($userId, $user['name'], $rememberMe);
    
    // 记录登录成功
    $db->logLogin($userId, $user['name'], true, '登录成功');
    
    $userInfo = $db->getUserInfo($userId);
    
    $userData = [
        'user_id' => $userId,
        'username' => $user['name'],
        'display_name' => $userInfo['display_name'] ?? $user['name'],
        'email' => $user['email'],
        'avatar' => $userInfo['avatar'] ?? '',
        'logged_in' => true,
        'token' => $token ?? ''
    ];
    
    Anon_ResponseHelper::success($userData, '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '登录处理过程中发生错误');
}
