<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
    'token' => false,
];

try {
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空',
        'password' => '密码不能为空'
    ]);
    
    $inputData = Anon_Http_Request::getInput();
    
    // 验证码检查
    if (Anon_Auth_Captcha::isEnabled()) {
        if (empty($inputData['captcha'] ?? '')) {
            Anon_Http_Response::error('验证码不能为空', [], 400);
        }
        
        if (!Anon_Auth_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_Http_Response::error('验证码错误', [], 400);
        }
        
        Anon_Auth_Captcha::clear();
    }
    
    // 防刷限制检查
    $rateLimitConfig = Anon_System_Env::get('app.rateLimit.register', []);
    $rateLimitResult = Anon_Auth_RateLimit::checkRegisterLimit($rateLimitConfig);
    
    if (!$rateLimitResult['allowed']) {
        Anon_Http_Response::error($rateLimitResult['message'], [
            'remaining' => $rateLimitResult['remaining'],
            'resetAt' => $rateLimitResult['resetAt'],
            'type' => $rateLimitResult['type']
        ], 429);
    }
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    $displayName = trim($inputData['display_name'] ?? '');
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    // 验证用户名格式
    if (strlen($username) < 3 || strlen($username) > 20) {
        Anon_Http_Response::error('用户名长度必须在3-20个字符之间', [], 400);
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        Anon_Http_Response::error('用户名只能包含字母、数字和下划线', [], 400);
    }
    
    // 验证显示名字长度
    if (!empty($displayName) && strlen($displayName) > 255) {
        Anon_Http_Response::error('显示名字长度不能超过255个字符', [], 400);
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Anon_Http_Response::error('邮箱格式不正确', [], 400);
    }
    
    // 验证密码强度
    $passwordErrors = Anon_Utils_Validate::passwordStrength($password);
    if (!empty($passwordErrors)) {
        Anon_Http_Response::error('密码不符合要求', ['requirements' => $passwordErrors], 400);
    }
    
    $db = Anon_Database::getInstance();
    
    // 检查用户名是否已存在
    if ($db->getUserInfoByName($username)) {
        Anon_Http_Response::error('用户名已存在', [], 400);
    }
    
    // 检查邮箱是否已存在
    if ($db->getUserInfoByEmail($email)) {
        Anon_Http_Response::error('邮箱已被注册', [], 400);
    }
    
    // 加密密码
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // 创建用户，displayName 为空时使用 null，数据库会使用默认值
    $success = $db->addUser($username, $email, $hashedPassword, 'user', empty($displayName) ? null : $displayName);
    
    if (!$success) {
        Anon_Http_Response::error('注册失败，请稍后重试', [], 500);
    }
    
    // 获取新创建的用户信息
    $newUser = $db->getUserInfoByName($username);
    
    if (!$newUser) {
        Anon_Http_Response::error('注册成功但获取用户信息失败', [], 500);
    }
    
    // 注册成功后自动登录
    Anon_Check::startSessionIfNotStarted();
    session_regenerate_id(true);
    
    $userId = (int)$newUser['uid'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $newUser['name'];
    
    // 设置认证 cookies
    Anon_Check::setAuthCookies($userId, $newUser['name'], $rememberMe);
    
    // 生成 token
    $token = Anon_Http_Request::generateUserToken($userId, $newUser['name'], $rememberMe);
    
    // 注册成功后可选清除限制，根据需求决定
    // Anon_Auth_RateLimit::clearIpLimit();
    // Anon_Auth_RateLimit::clearDeviceLimit();
    
    Anon_Http_Response::success([
        'token' => $token ?? '',
        'user' => [
            'uid' => $userId,
            'name' => $newUser['name'],
            'email' => $newUser['email'] ?? null,
            'display_name' => $newUser['display_name'] ?? $username,
            'avatar' => $newUser['avatar'] ?? '',
        ],
        'remaining' => $rateLimitResult['remaining'] ?? 0
    ], '注册成功');
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '注册处理过程中发生错误');
}

