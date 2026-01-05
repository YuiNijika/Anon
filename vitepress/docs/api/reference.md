
## 类名变更说明

框架已重构，类名已更新为更清晰的命名。旧类名仍然可用，但建议使用新类名。

### 新类名（推荐使用）

```php
// Http 模块
Anon_Http_Request::validate([...]);
Anon_Http_Response::success($data);
Anon_Http_Router::handle();

// Auth 模块
Anon_Auth_Token::generate([...]);
Anon_Auth_Csrf::generate();
```

### 旧类名（仍然可用）

```php
// 旧代码仍然可以正常工作
Anon_Http_Request::validate([...]);
Anon_Http_Response::success($data);
Anon_Auth_Token::generate([...]);
```

### 兼容机制

通过 `core/Compatibility.php` 自动创建类别名，旧代码无需修改即可继续工作。


﻿# API 参考文档

一句话：所有核心模块的公共方法调用参考，快速查找可用方法。

## 📋 目录

- [请求处理](#请求处理)
- [响应处理](#响应处理)
- [用户认证](#用户认证)
- [Token 管理](#token-管理)
- [数据库操作](#数据库操作)
- [钩子系统](#钩子系统)
- [Widget 组件](#widget-组件)
- [权限系统](#权限系统)
- [缓存系统](#缓存系统)
- [容器系统](#容器系统)
- [中间件](#中间件)
- [调试工具](#调试工具)
- [控制台工具](#控制台工具)
- [防刷限制](#防刷限制)
- [安全功能](#安全功能)
- [工具类](#工具类)
- [配置管理](#配置管理)
- [通用功能](#通用功能)

---

## 请求处理

### Anon_Http_Request

#### 获取请求数据

```php
// 获取支持JSON和表单数据的请求输入数据
$data = Anon_Http_Request::getInput();
// 返回：['key' => 'value', ...]

// 从GET或POST获取请求参数
$value = Anon_Http_Request::get('key', 'default');
$value = Anon_Http_Request::get('username'); // 不存在返回null

// 获取POST参数
$value = Anon_Http_Request::post('key', 'default');

// 获取GET参数
$value = Anon_Http_Request::getParam('key', 'default');
```

#### 验证请求

```php
// 验证必需参数
$data = Anon_Http_Request::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);
// 验证失败自动返回400错误

// 要求特定HTTP方法
Anon_Http_Request::requireMethod('POST');
Anon_Http_Request::requireMethod(['POST', 'PUT']);

// 检查请求方法
$method = Anon_Http_Request::method(); // 'GET'、'POST'等
$isPost = Anon_Http_Request::isPost();
$isGet = Anon_Http_Request::isGet();
```

#### 用户认证

```php
// 从会话或Cookie获取当前用户ID
$userId = Anon_Http_Request::getUserId();
// 返回：int|null

// 获取需要登录的当前用户信息
$userInfo = Anon_Http_Request::requireAuth();
// 未登录自动返回401错误
// 返回：['uid' => 1, 'name' => 'admin', 'email' => '...', ...]

// 验证API Token防止API被刷
Anon_Http_Request::requireToken();
// Token无效自动返回403错误
```

#### Token 生成

```php
// 根据refresh配置决定智能获取或生成Token
$token = Anon_Http_Request::getUserToken($userId, $username, $rememberMe);
// refresh为false时如果已有有效Token则返回现有Token，否则生成新Token
// refresh为true时总是生成新Token

// 登录时总是生成新Token
$token = Anon_Http_Request::generateUserToken($userId, $username, $rememberMe);
```

---

## 响应处理

### Anon_Http_Response

#### 成功响应

```php
// 基本成功响应
Anon_Http_Response::success($data, '操作成功', 200);
Anon_Http_Response::success(['id' => 1, 'name' => 'test'], '创建成功');

// 分页响应
Anon_Http_Response::paginated($data, $pagination, '获取数据成功', 200);
// $pagination = ['page' => 1, 'per_page' => 10, 'total' => 100]
```

#### 错误响应

```php
// 基本错误响应
Anon_Http_Response::error('操作失败', $data, 400);

// 验证错误
Anon_Http_Response::validationError('参数验证失败', $errors);
// $errors = ['field1' => '错误消息1', 'field2' => '错误消息2']

// 未授权，返回401
Anon_Http_Response::unauthorized('请先登录');

// 禁止访问，返回403
Anon_Http_Response::forbidden('权限不足');

// 未找到，返回404
Anon_Http_Response::notFound('资源不存在');

// 服务器错误，返回500
Anon_Http_Response::serverError('服务器内部错误', $data);
```

#### 异常处理

```php
try {
    // 业务逻辑
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '操作时发生错误');
    // 自动根据异常类型返回合适的HTTP状态码
}
```

---

## 用户认证

### Anon_Check

```php
// 检查用户是否已登录
$isLoggedIn = Anon_Check::isLoggedIn();
// 返回：bool
// 自动检查 Session 和 Cookie，Cookie 有效时自动恢复 Session

// 用户注销
Anon_Check::logout();

// 设置认证Cookie
Anon_Check::setAuthCookies($userId, $username, $rememberMe);
// $userId: 用户ID
// $username: 用户名
// $rememberMe: true=30天，false=会话结束
// Cookie 自动设置顶级域名，支持跨子域名共享登录状态

// 清除认证Cookie
Anon_Check::clearAuthCookies();

// 如果会话未启动，则启动会话
Anon_Check::startSessionIfNotStarted();
```

### Anon_Common

```php
// 设置HTTP响应头
Anon_Common::Header(200, true, true);
// 参数：HTTP状态码，是否设置JSON响应头，是否设置CORS头

// 要求登录，未登录返回401
Anon_Common::RequireLogin();

// 获取系统信息
$info = Anon_Common::SystemInfo();
// 返回：['system' => [...], 'copyright' => [...]]

// 获取客户端真实IP
$ip = Anon_Common::GetClientIp();
// 返回：string|null
```

---

## Token 管理

### Anon_Auth_Token

```php
// 生成Token
$token = Anon_Auth_Token::generate(['user_id' => 1], 3600, false);
// 参数：数据数组，过期时间秒数（null则自动设置），是否为敏感操作
// 敏感操作默认60秒，非敏感操作默认300秒

// 验证Token
$payload = Anon_Auth_Token::verify($token);
// 返回：false|array
// 成功返回：['data' => [...], 'timestamp' => ..., 'expire' => ..., 'nonce' => ...]

// 从请求中获取Token
$token = Anon_Auth_Token::getTokenFromRequest();
// 从Header的X-API-Token或Authorization Bearer获取

// 检查Token是否启用
$enabled = Anon_Auth_Token::isEnabled();

// 检查是否启用刷新
$refreshEnabled = Anon_Auth_Token::isRefreshEnabled();

// 获取白名单
$whitelist = Anon_Auth_Token::getWhitelist();

// 检查路由是否在白名单
$isWhitelisted = Anon_Auth_Token::isWhitelisted('/auth/login');
```

---

## 数据库操作

### Anon_Database

```php
// 推荐使用单例模式获取数据库实例
$db = Anon_Database::getInstance();

// 推荐使用QueryBuilder
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

// 批量插入
$inserted = $db->batchInsert('users', $data, 1000);

// 批量更新
$updated = $db->batchUpdate('users', $data, 'id', 1000);

// 不推荐执行原始SQL，除非必要
$result = $db->query('SELECT * FROM users WHERE id = 1');

// 准备预处理语句
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [1]);

// 自动发现并访问Repository/Service
$user = $db->userRepository->getUserInfo(1);
$avatar = $db->avatarService->getAvatarUrl(1);
```

### Anon_Database_QueryBuilder

```php
$query = new Anon_Database_QueryBuilder($connection, 'users');

// SELECT查询
$users = $query->select(['id', 'name', 'email'])
    ->where('status', '=', 'active')
    ->where('age', '>', 18)
    ->orWhere('vip', '=', 1)
    ->whereIn('id', [1, 2, 3])
    ->whereNull('deleted_at')
    ->whereNotNull('email')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->offset(0)
    ->get();

// 单条记录
$user = $query->where('id', '=', 1)->first();

// 插入
$id = $query->insert([
    'name' => 'test',
    'email' => 'test@example.com'
]);

// 批量插入
$inserted = $query->batchInsert($data, 1000);

// 更新
$affected = $query->where('id', '=', 1)
    ->update(['status' => 'active']);

// 批量更新
$updated = $query->batchUpdate($data, 'id', 1000);

// 删除
$affected = $query->where('id', '=', 1)->delete();

// JOIN
$result = $query->select(['users.*', 'profiles.bio'])
    ->join('profiles', 'users.id', '=', 'profiles.user_id', 'LEFT')
    ->get();

// 游标分页（主键游标）
$result = $query->cursorPaginate(20, $cursor, 'id');
// 返回: ['data' => [...], 'next_cursor' => 123, 'has_next' => true]

// 游标分页（时间戳游标）
$result = $query->cursorPaginateByTime(20, $cursor, 'created_at');
// 返回: ['data' => [...], 'prev_cursor' => 1234567890, 'has_prev' => true]

// 查询缓存
$users = $query->cache(3600)->get(); // 缓存 1 小时
$users = $query->cache(7200, 'custom_key')->get(); // 自定义缓存键

// 聚合
$count = $query->count();
$count = $query->count('id');
$max = $query->max('price');
$min = $query->min('price');
$avg = $query->avg('price');
$sum = $query->sum('amount');

// 分组
$result = $query->groupBy('category')
    ->having('count', '>', 10)
    ->get();

// 获取原始SQL（调试用）
echo $query->toRawSql();
```

---

## 钩子系统

### Anon_System_Hook

```php
// 添加动作钩子
Anon_System_Hook::add_action('user_login', function($user) {
    // 用户登录后执行
}, 10, 1);
// 参数：钩子名，回调函数，优先级数字越小越先执行默认10，接受参数数量

// 执行动作钩子
Anon_System_Hook::do_action('user_login', $user);
Anon_System_Hook::do_action('user_login', $user, $timestamp); // 多个参数

// 添加过滤器钩子
Anon_System_Hook::add_filter('response_data', function($data) {
    // 修改响应数据
    return $data;
}, 10, 1);

// 应用过滤器
$filtered = Anon_System_Hook::apply_filters('response_data', $data);
$filtered = Anon_System_Hook::apply_filters('response_data', $data, $arg1, $arg2);

// 移除指定钩子
Anon_System_Hook::removeHook('user_login', $callback, 10);

// 移除所有钩子
Anon_System_Hook::removeAllHooks(); // 移除所有
Anon_System_Hook::removeAllHooks('user_login'); // 移除指定钩子的所有回调
Anon_System_Hook::removeAllHooks('user_login', 10); // 移除指定优先级

// 检查钩子是否存在
$exists = Anon_System_Hook::hasHook('user_login');
$priority = Anon_System_Hook::hasHook('user_login', $callback); // 返回优先级或false

// 获取当前执行的钩子名称
$currentHook = Anon_System_Hook::getCurrentHook();

// 获取钩子统计信息
$stats = Anon_System_Hook::getHookStats(); // 所有统计
$stats = Anon_System_Hook::getHookStats('user_login'); // 指定钩子统计

// 获取所有注册的钩子
$allHooks = Anon_System_Hook::getAllHooks();

// 清除统计信息
Anon_System_Hook::clearStats(); // 清除所有
Anon_System_Hook::clearStats('user_login'); // 清除指定钩子
```

---

## Widget 组件

### Anon_System_Widget

```php
$widget = Anon_System_Widget::getInstance();

// 注册Widget
$widget->register('my_widget', '我的组件', function($args) {
    return ['data' => 'value']; // JSON模式
    // 或 echo '<div>HTML</div>'; // HTML模式
}, ['description' => '组件描述'], 'auto');

// 注销Widget
$widget->unregister('my_widget');

// 渲染HTML输出
$html = $widget->render('my_widget', ['param' => 'value']);

// 获取JSON数据
$data = $widget->getData('my_widget', ['param' => 'value']);

// 获取JSON字符串
$json = $widget->getJson('my_widget', ['param' => 'value']);

// 获取Widget信息
$info = $widget->getInfo('my_widget');

// 获取Widget列表
$list = $widget->list();

// 获取所有包含回调函数的Widget
$all = $widget->all();

// 检查是否存在
$exists = $widget->exists('my_widget');
```

---

## 权限系统

### Anon_Auth_Capability

```php
$capability = Anon_Auth_Capability::getInstance();

// 检查用户权限
$can = $capability->userCan($userId, 'edit_posts');

// 检查角色权限
$can = $capability->roleCan('admin', 'manage_options');

// 检查当前用户权限
$can = $capability->currentUserCan('edit_posts');

// 要求权限，无权限返回403
$capability->requireCapability('manage_options');

// 添加权限
$capability->addCapability('editor', 'custom_permission');

// 移除权限
$capability->removeCapability('admin', 'manage_plugins');

// 获取权限列表
$caps = $capability->getCaps('admin');
$allCaps = $capability->all();
```

---

## 缓存系统

### Anon_System_Cache

```php
// 初始化缓存
Anon_System_Cache::init('file'); // 'file' 或 'memory'

// 设置缓存
Anon_System_Cache::set('key', $value, 3600); // 1小时过期
Anon_System_Cache::set('key', $value, null); // 永不过期

// 获取缓存
$value = Anon_System_Cache::get('key', 'default');

// 检查缓存是否存在
$exists = Anon_System_Cache::has('key');

// 删除缓存
Anon_System_Cache::delete('key');

// 清空所有缓存
Anon_System_Cache::clear();

// 记住缓存（如果不存在则执行闭包并缓存结果）
$value = Anon_System_Cache::remember('key', function() {
    return expensiveOperation();
}, 3600);
```

---

## 容器系统

### Anon_System_Container

```php
$container = Anon_System_Container::getInstance();

// 绑定接口到实现
$container->bind('UserRepositoryInterface', 'UserRepository');

// 单例绑定
$container->singleton('Database', function() {
    return new Database();
});

// 绑定实例
$container->instance('Config', $configInstance);

// 设置别名
$container->alias('db', 'Database');

// 解析依赖
$userRepo = $container->make('UserRepositoryInterface');
$db = $container->make('Database', ['host' => 'localhost']);

// 检查是否已绑定
$bound = $container->bound('Database');

// 清空容器
$container->flush();
```

---

## 中间件

### Anon_Http_Middleware

```php
// 注册全局中间件
Anon_Http_Middleware::global('AuthMiddleware');

// 注册带别名的路由中间件
Anon_Http_Middleware::alias('auth', 'AuthMiddleware');
Anon_Http_Middleware::alias('throttle', 'ThrottleMiddleware');

// 在路由中使用
const Anon_Http_RouterMeta = [
    'middleware' => ['auth', 'throttle'],
];
```

---

## 调试工具

### Anon_Debug

```php
// 初始化调试
Anon_Debug::init();

// 记录日志
Anon_Debug::log('INFO', '消息', ['context' => 'data']);
Anon_Debug::debug('调试消息', ['key' => 'value']);
Anon_Debug::info('信息消息');
Anon_Debug::warn('警告消息');
Anon_Debug::error('错误消息', ['error' => 'details']);
Anon_Debug::fatal('致命错误');

// 性能监控
Anon_Debug::startPerformance('operation');
// ... 执行操作 ...
Anon_Debug::endPerformance('operation', ['data' => 'value']);

// 或使用单次调用
Anon_Debug::performance('operation', $startTime, ['data' => 'value']);

// 记录SQL查询
Anon_Debug::query('SELECT * FROM users', ['id' => 1], 0.12);

// 获取调试数据
$data = Anon_Debug::getData();

// 清空调试数据
Anon_Debug::clear();

// 检查是否启用
$enabled = Anon_Debug::isEnabled();
```

---

## 控制台工具

### Anon_System_Console

```php
// 注册命令
Anon_System_Console::command('cache:clear', function($args) {
    Anon_System_Cache::clear();
    Anon_System_Console::success('缓存已清空');
}, '清空缓存');

// 注册别名
Anon_System_Console::alias('cc', 'cache:clear');

// 运行命令
exit(Anon_System_Console::run($argv));

// 输出消息
Anon_System_Console::info('信息消息');
Anon_System_Console::success('成功消息');
Anon_System_Console::error('错误消息');
Anon_System_Console::warning('警告消息');
Anon_System_Console::line('普通消息');

// 获取所有命令
$commands = Anon_System_Console::getCommands();
```

---

## 防刷限制

### Anon_Auth_RateLimit

#### 获取客户端信息

```php
// 获取客户端IP
$ip = Anon_Auth_RateLimit::getClientIp();
// 返回：string，如 '127.0.0.1'

// 生成设备指纹
$fingerprint = Anon_Auth_RateLimit::generateDeviceFingerprint();
// 返回：string，基于User-Agent、Accept-Language、Accept-Encoding和IP生成的SHA256哈希
```

#### 检查限制

```php
// 检查是否超过限制（通用方法）
$result = Anon_Auth_RateLimit::checkLimit($key, $maxAttempts, $windowSeconds);
// $key: 限制键，如 'register_ip:xxx'
// $maxAttempts: 最大尝试次数
// $windowSeconds: 时间窗口（秒）
// 返回：['allowed' => bool, 'remaining' => int, 'resetAt' => int, 'count' => int]

// 检查注册限制（IP + 设备指纹）
$config = Anon_System_Env::get('app.rateLimit.register', []);
$result = Anon_Auth_RateLimit::checkRegisterLimit($config);
// 返回：['allowed' => bool, 'message' => string, 'remaining' => int, 'resetAt' => int, 'type' => string]
// allowed: 是否允许
// message: 提示信息
// remaining: 剩余次数
// resetAt: 重置时间戳
// type: 限制类型（'ip'、'device'、'success'）
```

#### 清除限制

```php
// 清除指定限制记录
Anon_Auth_RateLimit::clearLimit('register_ip:xxx');

// 清除IP限制
Anon_Auth_RateLimit::clearIpLimit();           // 清除当前IP的限制
Anon_Auth_RateLimit::clearIpLimit('1.2.3.4');  // 清除指定IP的限制

// 清除设备指纹限制
Anon_Auth_RateLimit::clearDeviceLimit();                    // 清除当前设备的限制
Anon_Auth_RateLimit::clearDeviceLimit($fingerprint);        // 清除指定设备的限制
```

#### 使用示例

```php
// 在注册接口中使用
$rateLimitConfig = Anon_System_Env::get('app.rateLimit.register', []);
$rateLimitResult = Anon_Auth_RateLimit::checkRegisterLimit($rateLimitConfig);

if (!$rateLimitResult['allowed']) {
    Anon_Http_Response::error($rateLimitResult['message'], [
        'remaining' => $rateLimitResult['remaining'],
        'resetAt' => $rateLimitResult['resetAt'],
        'type' => $rateLimitResult['type']
    ], 429);
}

// 注册成功后可选清除限制
// Anon_Auth_RateLimit::clearIpLimit();
// Anon_Auth_RateLimit::clearDeviceLimit();
```

---

## 安全功能

### Anon_Auth_Csrf

```php
// 生成 CSRF Token
$token = Anon_Auth_Csrf::generateToken();
// 返回：string，Token 字符串

// 获取当前 CSRF Token
$token = Anon_Auth_Csrf::getToken();
// 返回：string|null，如果不存在则返回 null

// 验证 CSRF Token
Anon_Auth_Csrf::verify(); // 自动从请求中获取并验证
Anon_Auth_Csrf::verify($token); // 验证指定的 Token
$isValid = Anon_Auth_Csrf::verify($token, false); // 验证失败时不抛出异常

// 刷新 CSRF Token
$newToken = Anon_Auth_Csrf::refreshToken();

// 清除 CSRF Token
Anon_Auth_Csrf::clearToken();

// 检查是否启用 CSRF 防护
$enabled = Anon_Auth_Csrf::isEnabled();

// 检查请求方法是否需要 CSRF 验证
$requires = Anon_Auth_Csrf::requiresVerification('POST'); // 返回 true
$requires = Anon_Auth_Csrf::requiresVerification('GET');   // 返回 false
```

### Anon_Security_Security_Security_Security

```php
// 自动过滤输入数据（防止 XSS）
$filtered = Anon_Security_Security_Security_Security_Security::filterInput($_POST, [
    'stripHtml' => true,        // 是否移除 HTML 标签
    'allowedFields' => [],      // 允许的字段列表
    'skipFields' => ['password'] // 跳过的字段列表
]);

// 检查 SQL 查询是否使用了预处理语句
$isSafe = Anon_Security_Security_Security_Security_Security::isUsingPreparedStatement($sql, $params);

// 验证 SQL 查询安全性（开发环境使用）
Anon_Security_Security_Security_Security_Security::validateSqlQuery($sql, $params, true);

// 转义 SQL LIKE 查询中的特殊字符
$escaped = Anon_Security_Security_Security_Security_Security::escapeLike($userInput);

// 检查字符串是否包含潜在的 SQL 注入代码
$hasRisk = Anon_Security_Security_Security_Security_Security::containsSqlInjection($string);

// 检查字符串是否包含潜在的 XSS 代码
$hasRisk = Anon_Security_Security_Security_Security_Security::containsXss($string);
```

### Anon_Security_Security_Security_Security_Sanitize

```php
// 清理文本内容（移除 HTML 标签）
$clean = Anon_Security_Security_Security_Security_Sanitize::text('<script>alert("xss")</script>');

// 清理 HTML 内容（允许指定标签）
$html = Anon_Security_Security_Security_Security_Sanitize::html('<p>Hello</p><script>alert("xss")</script>', '<p><strong>');

// 清理整数
$int = Anon_Security_Security_Security_Security_Sanitize::int('123abc'); // 返回 123

// 清理浮点数
$float = Anon_Security_Security_Security_Security_Sanitize::float('12.34abc'); // 返回 12.34

// 清理字符串
$string = Anon_Security_Security_Security_Security_Sanitize::string('<script>alert("xss")</script>');

// 深度清理数组（递归清理所有字符串值）
$cleaned = Anon_Security_Security_Security_Security_Sanitize::array([
    'name' => '<script>alert("xss")</script>',
    'content' => '<p>Safe content</p>'
], true); // 第二个参数：是否移除 HTML 标签
```

### 安全中间件

```php
// CSRF 防护中间件
Anon_Http_Middleware::global(
    Anon_Auth_CsrfMiddleware::make([
        '/api/public' // 排除的路由
    ])
);

// XSS 过滤中间件
Anon_Http_Middleware::global(
    Anon_XssFilterMiddleware::make(
        true,              // 移除 HTML 标签
        ['password', 'token'] // 跳过的字段
    )
);

// 接口限流中间件
Anon_Http_Middleware::global(
    Anon_Auth_RateLimitMiddleware::make(
        100,    // 最大请求次数
        60,     // 时间窗口（秒）
        'api',  // 限流键前缀
        [
            'useIp' => true,     // 基于 IP
            'useUserId' => true // 基于用户 ID
        ]
    )
);
```

---

## 工具类

### Anon_Helper

```php
// HTML转义
$escaped = Anon_Helper::escHtml('<script>alert("xss")</script>');
$url = Anon_Helper::escUrl('https://example.com');
$attr = Anon_Helper::escAttr('value with "quotes"');
$js = Anon_Helper::escJs('alert("test")');

// 数据清理
$clean = Anon_Helper::sanitizeText('<p>HTML</p>');
$email = Anon_Helper::sanitizeEmail('user@example.com');
$url = Anon_Helper::sanitizeUrl('https://example.com');

// 验证
$valid = Anon_Helper::isValidEmail('user@example.com');
$valid = Anon_Helper::isValidUrl('https://example.com');

// 文本处理
$truncated = Anon_Helper::truncate('很长的文本', 10);
$slug = Anon_Helper::slugify('Hello World!');
$timeAgo = Anon_Helper::timeAgo(time() - 3600);

// 格式化
$size = Anon_Helper::formatBytes(1048576);
$random = Anon_Helper::randomString(32);

// 数组操作
$value = Anon_Helper::get($array, 'user.profile.name', 'default');
Anon_Helper::set($array, 'user.profile.name', 'value');
$merged = Anon_Helper::merge($array1, $array2);
```

---

## 配置管理

### Anon_System_Config

```php
// 添加路由
Anon_System_Config::addRoute('/api/custom', function() {
    Anon_Http_Response::success(['message' => '自定义路由']);
});

// 添加静态文件路由
Anon_System_Config::addStaticRoute(
    '/anon/static/debug/css',  // 路由路径
    __DIR__ . '/../Static/debug.css',  // 文件完整路径
    'text/css',  // MIME类型
    31536000,  // 缓存时间（秒），0表示不缓存，默认1年
    true  // 是否启用压缩，默认true
);

// 添加错误处理器
Anon_System_Config::addErrorHandler(404, function() {
    Anon_Http_Response::notFound('页面不存在');
});

// 获取路由配置
$config = Anon_System_Config::getRouterConfig();

// 检查是否已安装
$installed = Anon_System_Config::isInstalled();
```

### Anon_System_Env

```php
// 获取配置值
$value = Anon_System_Env::get('app.token.enabled', false);
$value = Anon_System_Env::get('system.db.host', 'localhost');
```

---

## 大数据处理

### 游标分页

```php
$db = Anon_Database::getInstance();

// 主键游标分页
$result = $db->db('users')->cursorPaginate(20, $cursor);
// 返回: ['data' => [...], 'next_cursor' => 123, 'has_next' => true]

// 时间戳游标分页
$result = $db->db('posts')->cursorPaginateByTime(20, $cursor);
// 返回: ['data' => [...], 'prev_cursor' => 1234567890, 'has_prev' => true]
```

### 批量操作

```php
$db = Anon_Database::getInstance();

// 批量插入
$inserted = $db->batchInsert('users', $data, 1000);

// 批量更新
$updated = $db->batchUpdate('users', $data, 'id', 1000);
```

### 关联查询优化

```php
// 预加载关联数据，避免 N+1 查询
$users = Anon_Database_QueryOptimizer::eagerLoad(
    $users,
    'user_id',  // 外键
    'orders',   // 关联表
    'id'        // 本地键
);

// 一对一关联
$users = Anon_Database_QueryOptimizer::eagerLoadOne(
    $users,
    'user_id',
    'profiles',
    'id'
);
```

### 分库分表

```php
// 初始化分片配置
Anon_Database_Sharding::init([
    'users' => [
        'shard_count' => 4,
        'strategy' => 'id'
    ]
]);

// 获取分片表名
$tableName = Anon_Database_Sharding::getTableName('users', $userId, 'id');

// 获取所有分片表
$tables = Anon_Database_Sharding::getAllShardTables('users');
```

---

## 通用功能

### Anon_Common

```php
// 设置HTTP响应头
Anon_Common::Header(200, true, true);

// 要求登录
Anon_Common::RequireLogin();

// 获取系统信息
$info = Anon_Common::SystemInfo();

// 获取客户端IP
$ip = Anon_Common::GetClientIp();

// 获取许可证文本
$license = Anon_Common::LICENSE_TEXT;
```

