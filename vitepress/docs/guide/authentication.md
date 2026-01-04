# 用户认证

一句话：检查登录状态、获取用户信息、生成和验证Token。

## 检查登录状态

```php
// 检查是否已登录
$isLoggedIn = Anon_Check::isLoggedIn();
// 自动检查 Session 和 Cookie，如果 Cookie 有效会自动恢复 Session

// 用户注销
Anon_Check::logout();

// 设置认证 Cookie
Anon_Check::setAuthCookies($userId, $username, $rememberMe);
// $rememberMe: true=30天, false=会话结束
// Cookie 自动设置顶级域名，支持跨子域名共享

// 清除认证 Cookie
Anon_Check::clearAuthCookies();

// 启动会话
Anon_Check::startSessionIfNotStarted();
```

## 获取当前用户

```php
// 获取用户ID（从会话或Cookie）
$userId = Anon_RequestHelper::getUserId();
// 返回: int|null

// 获取完整用户信息（未登录自动返回401）
$userInfo = Anon_RequestHelper::requireAuth();
// 返回: ['uid' => 1, 'name' => 'admin', 'email' => '...', ...]
```

## 登录示例

```php
// server/app/Router/Auth/Login.php
try {
    Anon_RequestHelper::requireMethod('POST');
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    $inputData = Anon_RequestHelper::getInput();
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = Anon_Database::getInstance();
    $user = $db->getUserInfoByName($data['username']);
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        Anon_ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    Anon_Check::startSessionIfNotStarted();
    $_SESSION['user_id'] = (int)$user['uid'];
    $_SESSION['username'] = $user['name'];
    
    Anon_Check::setAuthCookies((int)$user['uid'], $user['name'], $rememberMe);
    
    // 登录时总是生成新Token
    $token = Anon_RequestHelper::generateUserToken((int)$user['uid'], $user['name'], $rememberMe);
    
    Anon_ResponseHelper::success([
        'user_id' => (int)$user['uid'],
        'username' => $user['name'],
        'token' => $token ?? '',
    ], '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

## 注册示例

```php
// server/app/Router/Auth/Register.php
try {
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空',
        'password' => '密码不能为空'
    ]);
    
    // 验证码检查（如果启用）
    if (class_exists('Anon_Captcha') && Anon_Captcha::isEnabled()) {
        $inputData = Anon_RequestHelper::getInput();
        if (empty($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码不能为空', null, 400);
        }
        if (!Anon_Captcha::verify($inputData['captcha'] ?? '')) {
            Anon_ResponseHelper::error('验证码错误', null, 400);
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
    
    // 验证用户名格式
    if (strlen($username) < 3 || strlen($username) > 20) {
        Anon_ResponseHelper::error('用户名长度必须在3-20个字符之间', null, 400);
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        Anon_ResponseHelper::error('用户名只能包含字母、数字和下划线', null, 400);
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Anon_ResponseHelper::error('邮箱格式不正确', null, 400);
    }
    
    // 验证密码强度
    if (strlen($password) < 6) {
        Anon_ResponseHelper::error('密码长度至少6个字符', null, 400);
    }
    
    $db = Anon_Database::getInstance();
    
    // 检查用户名是否已存在
    if ($db->getUserInfoByName($username)) {
        Anon_ResponseHelper::error('用户名已存在', null, 400);
    }
    
    // 检查邮箱是否已存在
    if ($db->getUserInfoByEmail($email)) {
        Anon_ResponseHelper::error('邮箱已被注册', null, 400);
    }
    
    // 加密密码并创建用户
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $success = $db->addUser($username, $email, $hashedPassword, 'user');
    
    if (!$success) {
        Anon_ResponseHelper::error('注册失败，请稍后重试', null, 500);
    }
    
    Anon_ResponseHelper::success([
        'username' => $username,
        'email' => $email,
        'remaining' => $rateLimitResult['remaining']
    ], '注册成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '注册处理过程中发生错误');
}
```

## Token 配置

```php
// server/app/useApp.php
'app' => [
    'token' => [
        'enabled' => true,                    // 是否启用Token验证
        'refresh' => false,                   // 是否自动刷新Token
        'whitelist' => [                      // Token验证白名单
            '/auth/login',
            '/auth/logout',
            '/auth/check-login',
            '/auth/token',
            '/auth/captcha'
        ],
    ],
],
```

**配置说明：**
- `enabled`: 是否启用Token验证
- `refresh`: 是否在验证后自动刷新Token
  - `true`: 每次验证成功后生成新Token，通过响应头`X-New-Token`返回
  - `false`: 不刷新，Token保持到过期（推荐多设备场景）
- `whitelist`: Token验证白名单路由

## 生成 Token

### 智能获取或生成（推荐）

```php
// 根据refresh配置决定：有有效Token就返回，没有就生成
$token = Anon_RequestHelper::getUserToken($userId, $username, $rememberMe);
```

### 强制生成新Token（登录时使用）

```php
// 登录时总是生成新Token
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);
```

### 手动生成（不推荐）

```php
$token = Anon_Token::generate(['user_id' => 1], 3600);        // 1小时
$token = Anon_Token::generate(['user_id' => 1], 86400 * 30);  // 30天
```

## 验证 Token

Token验证自动在路由执行前进行，验证失败返回403。

**特性：**
- Token验证通过后，如果包含用户信息，系统自动设置登录状态
- 每个登录会话都有独立的Token
- Token只能从HTTP Header获取：`X-API-Token` 或 `Authorization: Bearer`
- 如果启用了`refresh`，验证成功后会在响应头返回新Token：`X-New-Token`

### Token刷新机制

当`token.refresh`设置为`true`时：
- 每次Token验证成功后，自动生成新的Token
- 新Token通过响应头`X-New-Token`返回给客户端
- 客户端需要检查并更新本地存储的Token
- 旧Token仍然有效，直到过期

**适用场景：**
- `refresh: false`（默认）：多设备登录、Web应用推荐
- `refresh: true`：单设备应用、移动App、高安全要求场景

### 手动验证

```php
// 要求Token（无效自动返回403）
Anon_RequestHelper::requireToken();

// 手动验证Token
$payload = Anon_Token::verify();
if ($payload) {
    $userId = $payload['data']['user_id'] ?? null;
}

// 从请求中获取Token
$token = Anon_Token::getTokenFromRequest();
// 从Header: X-API-Token 或 Authorization: Bearer
```

## 使用示例

```php
// 获取用户信息时使用getUserToken
// server/app/Router/User/Info.php
try {
    $userInfo = Anon_RequestHelper::requireAuth();
    
    // 根据refresh配置决定智能获取或生成Token
    $token = Anon_RequestHelper::getUserToken((int)$userInfo['uid'], $userInfo['name']);
    if ($token !== null) {
        $userInfo['token'] = $token;
    }
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '获取用户信息发生错误');
}
```

## 跨端登录状态共享

系统支持"一端登录，多端都有状态"的功能，用户在一个客户端登录并选择"记住我"后，其他客户端访问 API 时会自动获得登录状态。

### 工作原理

1. **Cookie 自动设置**
   - 登录时设置认证 Cookie（`user_id` 和 `username`）
   - Cookie 自动设置顶级域名（如 `.example.com`），支持跨子域名共享
   - 选择"记住我"时，Cookie 有效期 30 天；否则为会话 Cookie

2. **自动验证和恢复**
   - `Anon_Check::isLoggedIn()` 会自动检查 Cookie
   - 如果 Cookie 有效，自动恢复 Session，用户自动登录
   - Cookie 验证会查询数据库确保用户存在且用户名匹配

3. **跨域支持**
   - 跨域请求时，Cookie 的 `SameSite` 自动设置为 `None`（需要 HTTPS）
   - CORS 配置自动设置 `Access-Control-Allow-Credentials: true`
   - 前端需要设置 `credentials: 'include'`（前端已自动配置）

### Cookie 配置

Cookie 自动根据环境配置：

- **同域名/子域名**：`SameSite=Lax`，支持跨子域名（如 `app.example.com` 和 `api.example.com`）
- **跨域 HTTPS**：`SameSite=None`，`Secure=true`，支持完全跨域
- **Domain 设置**：自动设置为顶级域名（如 `.example.com`），IP 地址使用完整域名

### 前端配置

前端已自动配置 `credentials: 'include'`，确保 Cookie 自动发送：

```typescript
// Vue / React / Next.js / Nuxt
const response = await fetch('/api/endpoint', {
  credentials: 'include'  // 自动发送 Cookie
});
```

### 使用场景

1. **同一域名下的不同子域名**
   - 用户在 `app.example.com` 登录
   - 访问 `api.example.com` 时自动获得登录状态

2. **跨域请求（需要 HTTPS）**
   - 用户在 `https://app.example.com` 登录
   - 从 `https://mobile.example.com` 访问 API 时自动获得登录状态

3. **不同端口**
   - 同一域名下的不同端口自动共享 Cookie

### 安全说明

- Cookie 使用 `HttpOnly` 标志，防止 JavaScript 访问
- Cookie 验证时会查询数据库，确保用户存在且有效
- 跨域时要求 HTTPS，确保传输安全
- 支持通过 Hook 自定义 Cookie 选项：`auth_cookie_options`

### 示例

```php
// 登录时设置记住我
$rememberMe = true;
Anon_Check::setAuthCookies($userId, $username, $rememberMe);

// 其他客户端访问 API 时
$isLoggedIn = Anon_Check::isLoggedIn();  // 自动从 Cookie 恢复登录状态
if ($isLoggedIn) {
    $userId = Anon_RequestHelper::getUserId();  // 自动获取用户ID
}
```

## 白名单

支持精确匹配和通配符：
- 精确匹配：`/api/public`
- 通配符：`/api/public/*`

