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
    
    // 登录时总是生成新 Token
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
        'refresh' => false, // 是否在验证后自动刷新 Token
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

**配置说明**：
- `enabled`: 是否启用 Token 验证
- `refresh`: 是否在验证后自动刷新 Token
  - `true`: 每次验证成功后生成新 Token，通过响应头 `X-New-Token` 返回
  - `false`: 不刷新，Token 保持到过期（推荐用于多设备场景）
- `whitelist`: Token 验证白名单路由

### 生成 Token

**推荐使用 `getUserToken()`**（智能获取或生成）：

```php
// 智能获取或生成 Token（根据 refresh 配置决定）
// - refresh = false: 如果已有有效 Token，返回现有 Token；否则生成新 Token
// - refresh = true: 总是生成新 Token
$token = Anon_RequestHelper::getUserToken($userId, $username, $rememberMe);
```

**直接生成新 Token**（登录时使用）：

```php
// 总是生成新 Token（登录时使用）
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);
```

**手动生成 Token**（不推荐，除非特殊需求）：

```php
$token = Anon_Token::generate(['user_id' => 1], 3600); // 1小时
$token = Anon_Token::generate(['user_id' => 1], 86400 * 30); // 30天
```

**方法选择指南**：

- `getUserToken()`: 用于获取用户信息、获取 Token 等场景，会根据配置智能处理
- `generateUserToken()`: 用于登录等需要强制生成新 Token 的场景

**使用示例**：

```php
// 获取用户信息时使用 getUserToken()
// server/app/Router/User/Info.php
try {
    $userInfo = Anon_RequestHelper::requireAuth();
    
    // 智能获取或生成 Token（根据 refresh 配置决定）
    $token = Anon_RequestHelper::getUserToken((int)$userInfo['uid'], $userInfo['name']);
    if ($token !== null) {
        $userInfo['token'] = $token;
    }
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '获取用户信息发生错误');
}
```

### 验证 Token

Token 验证自动在路由执行前进行，验证失败返回 403。

**特性**：

- Token 验证通过后，如果包含用户信息，系统自动设置登录状态
- 每个登录会话都有独立的 Token
- Token 只能从 HTTP Header 获取：`X-API-Token` 或 `Authorization: Bearer`
- 如果启用了 `refresh`，验证成功后会在响应头返回新 Token：`X-New-Token`

**Token 刷新机制**：

当 `token.refresh` 设置为 `true` 时：
- 每次 Token 验证成功后，自动生成新的 Token
- 新 Token 通过响应头 `X-New-Token` 返回给客户端
- 客户端需要检查并更新本地存储的 Token
- 旧 Token 仍然有效，直到过期

**适用场景**：
- `refresh: false`（默认）：多设备登录、Web 应用（推荐）
- `refresh: true`：单设备应用、移动 App、高安全要求场景

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

