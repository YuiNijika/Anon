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
        'avatar' => 'https://www.cravatar.cn/avatar',
    ],
];
```

## 数据库操作

### 基本使用

```php
$db = new Anon_Database();

// 用户
$db->addUser('admin', 'admin@example.com', 'password', 'admin');
$db->getUserInfo(1);
$db->getUserInfoByName('admin');
$db->isUserAdmin(1);
$db->updateUserGroup(1, 'admin');
```

### 数据库类命名规则

#### Repository 类

- **类名格式**：`Anon_Database_{Name}Repository`
- **文件位置**：`server/app/Database/{Name}.php`
- **必须继承**：`Anon_Database_Connection`

示例：

```php
// server/app/Database/User.php
class Anon_Database_UserRepository extends Anon_Database_Connection
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function getUserInfo($uid)
    {
        return $this->db('users')
            ->select(['uid', 'name', 'email'])
            ->where('uid', '=', (int)$uid)
            ->first();
    }
}
```

#### Service 类

- **类名格式**：`Anon_Database_{Name}Service`
- **文件位置**：`server/app/Database/{Name}.php`
- **必须继承**：`Anon_Database_Connection`

示例：

```php
// server/app/Database/Avatar.php
class Anon_Database_AvatarService extends Anon_Database_Connection
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function buildAvatar($email)
    {
        $hash = md5(strtolower(trim($email)));
        return "https://www.cravatar.cn/avatar/{$hash}?s=640&d=retro";
    }
}
```

#### 访问方式

系统会自动发现并实例化所有 Repository 和 Service 类，支持多种访问方式：

```php
$db = new Anon_Database();

// 方式1：属性访问（推荐）
$db->userRepository->getUserInfo(1);
$db->avatarService->buildAvatar('user@example.com');

// 方式2：大驼峰命名
$db->UserRepository->getUserInfo(1);

// 方式3：完整类名
$db->Anon_Database_UserRepository->getUserInfo(1);

// 方式4：方法自动转发（推荐）
// 方法名以 get/is/add/update/delete 开头时，自动解析到对应的 Repository 或 Service
$db->getUserInfo(1);  // 自动转发到 UserRepository::getUserInfo()
$db->isUserAdmin(1);  // 自动转发到 UserRepository::isUserAdmin()
```

#### QueryBuilder 使用

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
    ->select(['uid', 'name'])
    ->where('uid', '=', 1)
    ->first();

// 插入
$id = $db->db('users')
    ->insert([
        'name' => 'admin',
        'email' => 'admin@example.com',
        'password' => password_hash('password', PASSWORD_BCRYPT)
    ])
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

// 计数
$count = $db->db('users')
    ->count()
    ->where('`group`', '=', 'admin')
    ->scalar();

// 存在检查
$exists = $db->db('users')
    ->exists()
    ->where('email', '=', 'user@example.com')
    ->scalar();
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

## 通用方法

```php
// 设置 HTTP 响应头
Anon_Common::Header(); // 默认：200, JSON响应, CORS
Anon_Common::Header(404); // 404错误，JSON响应，CORS
Anon_Common::Header(200, false); // 200，不设置JSON响应头，CORS
Anon_Common::Header(200, true, false); // 200，JSON响应，不设置CORS

// 获取系统信息
$systemInfo = Anon_Common::SystemInfo();

// 获取客户端IP
$clientIp = Anon_Common::GetClientIp();
```

## 响应处理

```php
// 成功响应
Anon_ResponseHelper::success($data, '操作成功');
Anon_ResponseHelper::success($data, '操作成功', 201); // 自定义HTTP状态码

// 失败响应
Anon_ResponseHelper::error('错误消息');
Anon_ResponseHelper::error('错误消息', $data, 400);

// 分页响应
Anon_ResponseHelper::paginated($data, $pagination, '获取数据成功');

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
