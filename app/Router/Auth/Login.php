<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空'
    ]);
    
    $inputData = Anon_Http_Request::getInput();
    
    if (class_exists('Anon_Captcha') && Anon_Auth_Captcha::isEnabled()) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($inputData['captcha'] ?? '')) {
            Anon_Http_Response::error('验证码不能为空', [], 400);
        }
        
        if (!Anon_Auth_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_Http_Response::error('验证码错误', [], 400);
        }
        
        Anon_Auth_Captcha::clear();
    }
    
    Anon_Check::startSessionIfNotStarted();
    
    $username = $data['username'];
    $password = $data['password'];
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = Anon_Database::getInstance();
    $user = $db->getUserInfoByName($username);
    
    // 始终验证密码避免时序攻击
    $passwordValid = false;
    if ($user && isset($user['password'])) {
        $passwordValid = password_verify($password, $user['password']);
    } else {
        // 用户不存在时也执行密码验证增加处理时间
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuv');
    }
    
    if (!$user || !$passwordValid) {
        $db->logLogin(null, $username, false, '用户名或密码错误');
        
        // 检查登录失败次数限制
        $cacheKey = 'login_fail_' . Anon_Common::GetClientIp();
        $failCount = Anon_System_Cache::get($cacheKey) ?? 0;
        $failCount++;
        Anon_System_Cache::set($cacheKey, $failCount, 900); // 15分钟
        
        if ($failCount >= 5) {
            Anon_Http_Response::error('登录失败次数过多，请15分钟后重试', [], 429);
        }
        
        Anon_Http_Response::unauthorized('用户名或密码错误');
    }
    
    // 登录成功清除失败计数
    $cacheKey = 'login_fail_' . Anon_Common::GetClientIp();
    Anon_System_Cache::delete($cacheKey);
    
    session_regenerate_id(true);
    
    $userId = (int)$user['uid'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['name'];
    
    Anon_Check::setAuthCookies($userId, $user['name'], $rememberMe);
    
    $token = Anon_Http_Request::generateUserToken($userId, $user['name'], $rememberMe);
    
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
    
    Anon_Http_Response::success($userData, '登录成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '登录处理过程中发生错误');
}
