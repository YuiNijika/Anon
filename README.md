# Anon Framework

[配套前端](https://github.com/YuiNijika/AnonClient)

## 配置

```php
// server/env.php
return [
    'system' => [
        'db' => [
            'host' => 'localhost',
            'port' => 3306,
            'prefix' => 'puxt_',
            'user' => 'root',
            'password' => 'root',
            'database' => 'puxt',
            'charset' => 'utf8mb4',
        ],
        'installed' => true,
    ],
    'app' => [
        'debug' => [
            'global' => false,
            'router' => false,
        ],
        'avatar' => 'https://www.cravatar.cn/avatar',
        'token' => [
            'enabled' => true,
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token'
            ],
        ],
    ],
];
```

## 数据库操作

### 基本使用

```php
$db = new Anon_Database();

// 用户操作
$db->addUser('admin', 'admin@example.com', 'password', 'admin');
$db->getUserInfo(1);
$db->getUserInfoByName('admin');
$db->isUserAdmin(1);
$db->updateUserGroup(1, 'admin');
```

### 类命名规则

**Repository 类**：`Anon_Database_{Name}Repository`  
**Service 类**：`Anon_Database_{Name}Service`  
**文件位置**：`server/app/Database/{Name}.php`  
**必须继承**：`Anon_Database_Connection`

### 访问方式

```php
$db = new Anon_Database();

// 属性访问
$db->userRepository->getUserInfo(1);
$db->avatarService->buildAvatar('user@example.com');

// 方法自动转发
$db->getUserInfo(1);  // 自动转发到 UserRepository
$db->isUserAdmin(1);  // 自动转发到 UserRepository
```

### QueryBuilder

```php
// 查询
$users = $db->db('users')
    ->select(['uid', 'name', 'email'])
    ->where('uid', '>', 10)
    ->orderBy('uid', 'DESC')
    ->limit(10)
    ->get();

// 单条查询
$user = $db->db('users')
    ->where('uid', '=', 1)
    ->first();

// 插入
$id = $db->db('users')
    ->insert(['name' => 'admin', 'email' => 'admin@example.com'])
    ->execute();

// 更新
$affected = $db->db('users')
    ->update(['email' => 'new@example.com'])
    ->where('uid', '=', 1)
    ->execute();

// 删除
$affected = $db->db('users')
    ->delete()
    ->where('uid', '=', 1)
    ->execute();
```

## 路由

### 配置路由

```php
// server/app/App.php
return [
    'auth' => [
        'login' => [
            'view' => 'Auth/Login',
            'useLoginCheck' => false,
        ],
    ],
];
```

### 动态注册路由

```php
Anon_Config::addRoute('/api/test', function () {
    Anon_ResponseHelper::success(['ok' => true]);
});
```

### API 端点

```
GET    /anon/common/config
GET    /anon/common/system
GET    /anon/common/client-ip
POST   /auth/login
POST   /auth/logout
GET    /auth/check-login
GET    /auth/token
GET    /user/info
```

## 路由处理示例

```php
// server/app/Router/Auth/Login.php
try {
    Anon_RequestHelper::requireMethod('POST');
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空'
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
        'email' => $user['email'],
        'token' => $token,
    ], '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

## 通用方法

```php
// HTTP 响应头
Anon_Common::Header();              // 200, JSON, CORS
Anon_Common::Header(404);            // 404, JSON, CORS
Anon_Common::Header(200, false);    // 200, 非JSON, CORS
Anon_Common::Header(200, true, false); // 200, JSON, 非CORS

// 系统信息
$systemInfo = Anon_Common::SystemInfo();
$clientIp = Anon_Common::GetClientIp();
```

## 响应处理

```php
// 成功响应
Anon_ResponseHelper::success($data, '操作成功');
Anon_ResponseHelper::success($data, '操作成功', 201);

// 错误响应
Anon_ResponseHelper::error('错误消息');
Anon_ResponseHelper::error('错误消息', $data, 400);

// 状态码响应
Anon_ResponseHelper::unauthorized('未授权访问');
Anon_ResponseHelper::forbidden('禁止访问');
Anon_ResponseHelper::notFound('资源未找到');
Anon_ResponseHelper::serverError('服务器内部错误');
Anon_ResponseHelper::methodNotAllowed('GET, POST');
Anon_ResponseHelper::validationError('参数验证失败', $errors);

// 处理异常
Anon_ResponseHelper::handleException($e, '自定义错误消息');
```

## 请求处理

```php
// 检查请求方法
Anon_RequestHelper::requireMethod('POST');
Anon_RequestHelper::requireMethod(['GET', 'POST']);

// 获取输入
$data = Anon_RequestHelper::getInput();
$username = Anon_RequestHelper::get('username', 'default');

// 验证必需参数
$data = Anon_RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);

// 用户认证
$userId = Anon_RequestHelper::getUserId();
$userInfo = Anon_RequestHelper::requireAuth();

// Token 生成
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);
$token = Anon_RequestHelper::generateUserToken($userId, $username); // 自动判断

// Token 验证
Anon_RequestHelper::requireToken();
Anon_RequestHelper::requireToken(false); // 不抛出异常
```

## 认证

```php
// 检查登录
if (Anon_Check::isLoggedIn()) {
    // 已登录
}

// 设置认证 Cookie
Anon_Check::setAuthCookies($userId, $username, $rememberMe);

// 登出
Anon_Check::logout();
```

## Token 验证

### 配置

```php
'app' => [
    'token' => [
        'enabled' => true,
        'whitelist' => [
            '/api/public/*',
        ],
    ],
],
```

**注意**：

- Token 密钥基于用户会话自动生成，无需手动配置
- 默认白名单：`/anon/install`、`/anon/common/*`、`/auth/login`、`/auth/logout`、`/auth/check-login`、`/auth/token`

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

## 配置接口

前端通过 `/anon/common/config` 获取配置：

```http
GET /anon/common/config
```

响应：

```json
{
  "success": true,
  "message": "获取配置信息成功",
  "data": {
    "token": true
  }
}
```

前端根据 `data.token` 决定是否在请求头中携带 Token。

## 调试

```php
// 日志
Anon_Debug::log('INFO', '消息');
Anon_Debug::log('ERROR', '错误');

// 性能
Anon_Debug::performance('操作名', microtime(true));

// SQL
Anon_Debug::query('SELECT * FROM users', ['id' => 1], 0.12);

// Web 控制台
// http://localhost:8080/anon/debug/console
```

## 自定义代码

在 `server/app/Code.php` 中添加自定义代码：

```php
// 注册钩子
Anon_Hook::add_action('router_before_init', function () {
    Anon_Debug::info('路由初始化前');
});

// 注册自定义路由
Anon_Config::addRoute('/api/custom', function () {
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 注册错误处理器
Anon_Config::addErrorHandler(404, function () {
    Anon_ResponseHelper::notFound('页面不存在');
});
```

## 钩子

```php
// 动作钩子
Anon_Hook::add_action('user_login', function ($user) {
    // 用户登录后执行
});
Anon_Hook::do_action('user_login', $user);

// 过滤器钩子
Anon_Hook::add_filter('content_filter', function ($content) {
    return str_replace('bad', '***', $content);
});
$filtered = Anon_Hook::apply_filters('content_filter', $content);
```
