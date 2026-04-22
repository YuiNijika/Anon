<?php

use Anon\Modules\HttpRequestHelper;
use Anon\Modules\AuthCaptcha;
use Anon\Modules\HttpResponseHelper;
use Anon\ModulesCheck;
use Anon\Modules\DatabaseDatabase;
use Anon\ModulesCommon;
use Anon\Modules\System\CacheCache;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    
    $data = RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空'
    ]);
    
    $inputData = RequestHelper::getInput();
    
    if (Captcha::isEnabled()) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($inputData['captcha'] ?? '')) {
            ResponseHelper::error('验证码不能为空', [], 400);
        }
        
        if (!Captcha::verify($inputData['captcha'] ?? '')) {
            ResponseHelper::error('验证码错误', [], 400);
        }
        
        Captcha::clear();
    }
    
    Check::startSessionIfNotStarted();
    
    $username = $data['username'];
    $password = $data['password'];
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = Database::getInstance();
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
        $cacheKey = 'login_fail_' . Common::GetClientIp();
        $failCount = Cache::get($cacheKey) ?? 0;
        $failCount++;
        Cache::set($cacheKey, $failCount, 900); // 15分钟
        
        if ($failCount >= 5) {
            ResponseHelper::error('登录失败次数过多，请15分钟后重试', [], 429);
        }
        
        ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    // 登录成功清除失败计数
    $cacheKey = 'login_fail_' . Common::GetClientIp();
    Cache::delete($cacheKey);
    
    session_regenerate_id(true);
    
    $userId = (int)$user['uid'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['name'];
    
    Check::setAuthCookies($userId, $user['name'], $rememberMe);
    
    $token = RequestHelper::generateUserToken($userId, $user['name'], $rememberMe);
    
    // 记录登录成功
    $db->logLogin($userId, $user['name'], true, '登录成功');
    
    $userInfo = $db->getUserInfo($userId);
    
    $userData = [
        'token' => $token ?? '',
        'user' => [
            'uid' => $userId,
            'name' => $user['name'],
            'email' => $user['email'] ?? null,
        ],
    ];
    
    ResponseHelper::success($userData, '登录成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '登录处理过程中发生错误');
}
