<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
    'token' => false,
];

try {
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空',
        'password' => '密码不能为空'
    ]);
    
    $inputData = Anon_RequestHelper::getInput();
    
    // 验证码检查
    if (class_exists('Anon_Captcha') && Anon_Captcha::isEnabled()) {
        if (empty($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码不能为空', [], 400);
        }
        
        if (!Anon_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码错误', [], 400);
        }
        
        Anon_Captcha::clear();
    }
    
    // 防刷限制检查
    $rateLimitConfig = Anon_Env::get('app.rateLimit.register', []);
    $rateLimitResult = Anon_RateLimit::checkRegisterLimit($rateLimitConfig);
    
    if (!$rateLimitResult['allowed']) {
        Anon_ResponseHelper::error($rateLimitResult['message'], [
            'remaining' => $rateLimitResult['remaining'],
            'resetAt' => $rateLimitResult['resetAt'],
            'type' => $rateLimitResult['type']
        ], 429);
    }
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    $displayName = trim($inputData['display_name'] ?? '');
    
    // 验证用户名格式
    if (strlen($username) < 3 || strlen($username) > 20) {
        Anon_ResponseHelper::error('用户名长度必须在3-20个字符之间', [], 400);
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        Anon_ResponseHelper::error('用户名只能包含字母、数字和下划线', [], 400);
    }
    
    // 验证显示名字长度
    if (!empty($displayName) && strlen($displayName) > 255) {
        Anon_ResponseHelper::error('显示名字长度不能超过255个字符', [], 400);
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Anon_ResponseHelper::error('邮箱格式不正确', [], 400);
    }
    
    // 验证密码强度
    if (strlen($password) < 6) {
        Anon_ResponseHelper::error('密码长度至少6个字符', [], 400);
    }
    
    $db = new Anon_Database();
    
    // 检查用户名是否已存在
    if ($db->getUserInfoByName($username)) {
        Anon_ResponseHelper::error('用户名已存在', [], 400);
    }
    
    // 检查邮箱是否已存在
    if ($db->getUserInfoByEmail($email)) {
        Anon_ResponseHelper::error('邮箱已被注册', [], 400);
    }
    
    // 加密密码
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // 创建用户，displayName 为空时使用 null（数据库会使用默认值）
    $success = $db->addUser($username, $email, $hashedPassword, 'user', empty($displayName) ? null : $displayName);
    
    if (!$success) {
        Anon_ResponseHelper::error('注册失败，请稍后重试', [], 500);
    }
    
    // 获取新创建的用户信息
    $newUser = $db->getUserInfoByName($username);
    
    // 注册成功后可选清除限制，根据需求决定
    // Anon_RateLimit::clearIpLimit();
    // Anon_RateLimit::clearDeviceLimit();
    
    Anon_ResponseHelper::success([
        'username' => $username,
        'display_name' => $newUser['display_name'] ?? $username,
        'email' => $email,
        'avatar' => $newUser['avatar'] ?? '',
        'remaining' => $rateLimitResult['remaining'] ?? 0
    ], '注册成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '注册处理过程中发生错误');
}

