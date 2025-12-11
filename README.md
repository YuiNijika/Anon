# Anon Framework

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
            'router' => true,
        ],
    ],
];
```

## 数据库操作

```php
$db = new Anon_Database();

// 用户
$db->addUser('admin', 'admin@example.com', 'password', 'admin');
$db->getUserInfo(1);
$db->getUserInfoByName('admin');
$db->isUserAdmin(1);
$db->updateUserGroup(1, 'admin');
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

### API 端点示例

```http
POST   http://localhost:8080/auth/login
POST   http://localhost:8080/auth/logout
GET    http://localhost:8080/auth/check-login
GET    http://localhost:8080/user/info
```

## 路由处理

```php
// server/app/Router/Auth/Login.php
Anon_Common::Header();

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
    
    Anon_ResponseHelper::success([
        'user_id' => (int)$user['uid'],
        'username' => $user['name'],
        'email' => $user['email'],
    ], '登录成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

## 请求处理

```php
// 检查请求方法
Anon_RequestHelper::requireMethod('POST');

// 获取输入
$data = Anon_RequestHelper::getInput();
$username = Anon_RequestHelper::get('username', 'default');

// 验证必需参数
$data = Anon_RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);

// 要求登录
$userInfo = Anon_RequestHelper::requireAuth();
```

## 响应处理

```php
// 成功响应
Anon_ResponseHelper::success($data, '操作成功');

// 错误响应
Anon_ResponseHelper::error('错误消息', null, 400);
Anon_ResponseHelper::unauthorized('未授权');
Anon_ResponseHelper::validationError('参数验证失败');
Anon_ResponseHelper::notFound('资源未找到');

// 处理异常
Anon_ResponseHelper::handleException($e, '自定义错误消息');
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

在 `server/app/Code.php` 中添加自定义代码（类似 WordPress 的 `functions.php`）：

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

## 错误处理

```php
Anon_Config::addErrorHandler(404, function () {
    Anon_ResponseHelper::notFound('页面不存在');
});

Anon_Config::addErrorHandler(500, function () {
    Anon_ResponseHelper::serverError('服务器错误');
});
```
