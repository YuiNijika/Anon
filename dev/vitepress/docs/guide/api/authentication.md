# 用户认证

一句话：检查登录状态、获取用户信息、生成和验证Token。

## 检查登录状态

```php
<?php
use Anon\Modules\Check;

// 检查用户登录状态
$isLoggedIn = Check::isLoggedIn();
// 自动检查会话和Cookie,Cookie有效时自动恢复会话

// 用户注销登录
Check::logout();

// 设置认证Cookie
Check::setAuthCookies($userId, $username, $rememberMe);
// 记住登录状态时Cookie有效期30天,否则随会话结束
// Cookie自动设置顶级域名,支持跨子域名共享

// 清除认证Cookie
Check::clearAuthCookies();

// 启动会话
Check::startSessionIfNotStarted();
```

## 获取当前用户

```php
<?php
use Anon\Modules\Http\RequestHelper;

// 从会话或Cookie获取用户ID
$userId = RequestHelper::getUserId();
// 返回整数类型用户ID或空值

// 获取完整用户信息，未登录时自动返回401错误
$userInfo = RequestHelper::requireAuth();
// 返回包含用户信息的关联数组
```

## 登录示例

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\Database\Database;
use Anon\Modules\Check;

// app/Router/Auth/Login.php
try {
    RequestHelper::requireMethod('POST');
    $data = RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    $inputData = RequestHelper::getInput();
    $rememberMe = filter_var($inputData['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $db = Database::getInstance();
    $user = $db->getUserInfoByName($data['username']);
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    Check::startSessionIfNotStarted();
    $_SESSION['user_id'] = (int)$user['uid'];
    $_SESSION['username'] = $user['name'];
    
    Check::setAuthCookies((int)$user['uid'], $user['name'], $rememberMe);
    
    // 登录时总是生成新Token
    $token = RequestHelper::generateUserToken((int)$user['uid'], $user['name'], $rememberMe);
    
    ResponseHelper::success([
        'user_id' => (int)$user['uid'],
        'username' => $user['name'],
        'token' => $token ?? '',
    ], '登录成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

## 注册示例

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\Database\Database;
use Anon\Modules\Auth\Captcha;
use Anon\Modules\Auth\RateLimit;
use Anon\Modules\System\Env;

// app/Router/Auth/Register.php
try {
    $data = RequestHelper::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空',
        'password' => '密码不能为空'
    ]);
    
    // 验证码检查（如果启用）
    if (class_exists('Captcha') && Captcha::isEnabled()) {
        $inputData = RequestHelper::getInput();
        if (empty($inputData['captcha'] ?? '')) {
            ResponseHelper::error('验证码不能为空', null, 400);
        }
        if (!Captcha::verify($inputData['captcha'] ?? '')) {
            ResponseHelper::error('验证码错误', null, 400);
        }
        Captcha::clear();
    }
    
    // 防刷限制检查
    $rateLimitConfig = Env::get('app.rateLimit.register', []);
    $rateLimitResult = RateLimit::checkRegisterLimit($rateLimitConfig);
    
    if (!$rateLimitResult['allowed']) {
        ResponseHelper::error($rateLimitResult['message'], [
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
        ResponseHelper::error('用户名长度必须在3-20个字符之间', null, 400);
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        ResponseHelper::error('用户名只能包含字母、数字和下划线', null, 400);
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ResponseHelper::error('邮箱格式不正确', null, 400);
    }
    
    // 验证密码强度
    if (strlen($password) < 6) {
        ResponseHelper::error('密码长度至少6个字符', null, 400);
    }
    
    $db = Database::getInstance();
    
    // 检查用户名是否已存在
    if ($db->getUserInfoByName($username)) {
        ResponseHelper::error('用户名已存在', null, 400);
    }
    
    // 检查邮箱是否已存在
    if ($db->getUserInfoByEmail($email)) {
        ResponseHelper::error('邮箱已被注册', null, 400);
    }
    
    // 加密密码并创建用户
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $success = $db->addUser($username, $email, $hashedPassword, 'user');
    
    if (!$success) {
        ResponseHelper::error('注册失败，请稍后重试', null, 500);
    }
    
    ResponseHelper::success([
        'username' => $username,
        'email' => $email,
        'remaining' => $rateLimitResult['remaining']
    ], '注册成功');
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '注册处理过程中发生错误');
}
```

## Token 配置

```php
// app/useApp.php
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

## Token 接口

系统提供了专门的 Token 获取接口 `/auth/token`，用于获取当前登录用户的 Token。

### 接口说明

- **路径**: `/auth/token`
- **方法**: `GET`
- **需要登录**: 是（`requireLogin: true`）
- **返回**: `{ "code": 200, "data": { "token": "..." }, "message": "获取 Token 成功" }`

### 使用场景

1. **前端检查登录状态后获取 Token**
   - 先调用 `/auth/check-login` 检查登录状态
   - 如果已登录，再调用 `/auth/token` 获取 Token
   - 将 Token 保存到 Cookie 或 localStorage

2. **Token 刷新**
   - 当 Token 即将过期时，前端可以调用此接口获取新的 Token
   - 如果用户已登录，会返回新的 Token

### 示例

```php
<?php
use Anon\Modules\Check;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

// app/Router/Auth/Token.php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    $isLoggedIn = Check::isLoggedIn();
    $token = '';
    
    if ($isLoggedIn) {
        $userId = RequestHelper::getUserId();
        $username = $_SESSION['username'] ?? '';
        if ($userId && $username) {
            $token = RequestHelper::getUserToken($userId, $username);
        }
    }
    
    $message = $token ? '获取 Token 成功' : '用户未登录，无法获取 Token';
    
    ResponseHelper::success([
        'token' => $token,
    ], $message);
    
} catch (Exception $e) {
    ResponseHelper::handleException($e, '获取 Token 时发生错误');
}
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
<?php
use Anon\Modules\Http\RequestHelper;

// 根据refresh配置决定：有有效Token就返回，没有就生成
$token = RequestHelper::getUserToken($userId, $username, $rememberMe);
```

### 强制生成新Token（登录时使用）

```php
<?php
use Anon\Modules\Http\RequestHelper;

// 登录时总是生成新Token
$token = RequestHelper::generateUserToken($userId, $username, $rememberMe);
```

### 手动生成（不推荐）

```php
<?php
use Anon\Modules\Auth\Token;

$token = Token::generate(['user_id' => 1], 3600);        // 1小时
$token = Token::generate(['user_id' => 1], 86400 * 30);  // 30天
```

## 验证 Token

Token验证自动在路由执行前进行，验证失败返回403。

**特性：**

- Token验证通过后，如果包含用户信息，系统自动设置登录状态
- 每个登录会话都有独立的Token
- Token只能从HTTP Header获取：`X-API-Token` 或 `Authorization: Bearer`
- 如果启用了`refresh`，验证成功后会在响应头返回新Token：`X-New-Token`

### Token刷新机制

当`app.token.refresh`设置为`true`时：

- 每次Token验证成功后自动生成新Token
- 新Token通过响应头`X-New-Token`返回客户端
- 客户端需要检查并更新本地存储Token
- 旧Token仍然有效直到过期

**适用场景：**

- `app.token.refresh: false`：多设备登录和Web应用推荐使用
- `app.token.refresh: true`：单设备应用移动App和高安全要求场景使用

### 手动验证

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Auth\Token;

// 要求Token(无效自动返回403)
RequestHelper::requireToken();

// 手动验证Token
$payload = Token::verify();
if ($payload) {
    $userId = $payload['data']['user_id'] ?? null;
}

// 从请求中获取Token
$token = Token::getTokenFromRequest();
// 从Header: X-API-Token 或 Authorization: Bearer
```

## 使用示例

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

// 获取用户信息时使用getUserToken
// app/Router/User/Info.php
try {
    $userInfo = RequestHelper::requireAuth();
    
    // 根据refresh配置决定智能获取或生成Token
    $token = RequestHelper::getUserToken((int)$userInfo['uid'], $userInfo['name']);
    if ($token !== null) {
        $userInfo['token'] = $token;
    }
    
    ResponseHelper::success($userInfo, '获取用户信息成功');
} catch (Exception $e) {
    ResponseHelper::handleException($e, '获取用户信息发生错误');
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
   - `Check::isLoggedIn()` 会自动检查 Cookie
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
<?php
use Anon\Modules\Check;
use Anon\Modules\Http\RequestHelper;

// 登录时设置记住我
$rememberMe = true;
Check::setAuthCookies($userId, $username, $rememberMe);

// 其他客户端访问 API 时
$isLoggedIn = Check::isLoggedIn();  // 自动从 Cookie 恢复登录状态
if ($isLoggedIn) {
    $userId = RequestHelper::getUserId();  // 自动获取用户ID
}
```

## 白名单

支持精确匹配和通配符：

- 精确匹配：`/api/public`
- 通配符：`/api/public/*`
