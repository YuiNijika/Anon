# 用户认证

## 登录检查

```php
if (Anon_Check::isLoggedIn()) {
    // 已登录
}
```

## 获取当前用户

```php
// 获取用户 ID
$userId = Anon_RequestHelper::getUserId();

// 获取完整用户信息（需要登录）
$userInfo = Anon_RequestHelper::requireAuth();
```

## 设置认证 Cookie

```php
Anon_Check::setAuthCookies($userId, $username, $rememberMe);
```

## 登出

```php
Anon_Check::logout();
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
    
    $db = new Anon_Database();
    $user = $db->getUserInfoByName($data['username']);
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        Anon_ResponseHelper::unauthorized('用户名或密码错误');
    }
    
    Anon_Check::startSessionIfNotStarted();
    $_SESSION['user_id'] = (int)$user['uid'];
    Anon_Check::setAuthCookies((int)$user['uid'], $user['name']);
    
    $token = Anon_RequestHelper::generateUserToken((int)$user['uid'], $user['name']);
    
    Anon_ResponseHelper::success([
        'user_id' => (int)$user['uid'],
        'username' => $user['name'],
        'token' => $token,
    ], '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

## Token 验证

### 配置

```php
// server/app/useApp.php
'app' => [
    'token' => [
        'enabled' => true,
        'whitelist' => [
            '/auth/login',
            '/auth/logout',
            '/auth/check-login',
            '/auth/token',
            '/auth/captcha'
        ],
    ],
],
```

### 生成 Token

```php
// 推荐：生成用户 Token
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);

// 手动生成 Token
$token = Anon_Token::generate(['user_id' => 1], 3600); // 1小时
$token = Anon_Token::generate(['user_id' => 1], 86400 * 30); // 30天
```

### 验证 Token

Token 验证自动在路由执行前进行，验证失败返回 403。

**特性**：

- Token 验证通过后，如果包含用户信息，系统自动设置登录状态
- 每个登录会话都有独立的 Token
- Token 只能从 HTTP Header 获取：`X-API-Token` 或 `Authorization: Bearer`

### 手动验证

```php
Anon_RequestHelper::requireToken();

$payload = Anon_Token::verify();
if ($payload) {
    $userId = $payload['data']['user_id'] ?? null;
}
```

### 白名单

支持精确匹配和通配符：

- 精确匹配：`/api/public`
- 通配符：`/api/public/*`

---

[← 返回文档首页](../README.md)

