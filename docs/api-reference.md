# API 参考文档

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
- [工具类](#工具类)
- [配置管理](#配置管理)
- [通用功能](#通用功能)

---

## 请求处理

### Anon_RequestHelper

#### 获取请求数据

```php
// 获取支持JSON和表单数据的请求输入数据
$data = Anon_RequestHelper::getInput();
// 返回：['key' => 'value', ...]

// 从GET或POST获取请求参数
$value = Anon_RequestHelper::get('key', 'default');
$value = Anon_RequestHelper::get('username'); // 不存在返回null

// 获取POST参数
$value = Anon_RequestHelper::post('key', 'default');

// 获取GET参数
$value = Anon_RequestHelper::getParam('key', 'default');
```

#### 验证请求

```php
// 验证必需参数
$data = Anon_RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);
// 验证失败自动返回400错误

// 要求特定HTTP方法
Anon_RequestHelper::requireMethod('POST');
Anon_RequestHelper::requireMethod(['POST', 'PUT']);

// 检查请求方法
$method = Anon_RequestHelper::method(); // 'GET'、'POST'等
$isPost = Anon_RequestHelper::isPost();
$isGet = Anon_RequestHelper::isGet();
```

#### 用户认证

```php
// 从会话或Cookie获取当前用户ID
$userId = Anon_RequestHelper::getUserId();
// 返回：int|null

// 获取需要登录的当前用户信息
$userInfo = Anon_RequestHelper::requireAuth();
// 未登录自动返回401错误
// 返回：['uid' => 1, 'name' => 'admin', 'email' => '...', ...]

// 验证API Token防止API被刷
Anon_RequestHelper::requireToken();
// Token无效自动返回403错误
```

#### Token 生成

```php
// 根据refresh配置决定智能获取或生成Token
$token = Anon_RequestHelper::getUserToken($userId, $username, $rememberMe);
// refresh为false时如果已有有效Token则返回现有Token，否则生成新Token
// refresh为true时总是生成新Token

// 登录时总是生成新Token
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);
```

---

## 响应处理

### Anon_ResponseHelper

#### 成功响应

```php
// 基本成功响应
Anon_ResponseHelper::success($data, '操作成功', 200);
Anon_ResponseHelper::success(['id' => 1, 'name' => 'test'], '创建成功');

// 分页响应
Anon_ResponseHelper::paginated($data, $pagination, '获取数据成功', 200);
// $pagination = ['page' => 1, 'per_page' => 10, 'total' => 100]
```

#### 错误响应

```php
// 基本错误响应
Anon_ResponseHelper::error('操作失败', $data, 400);

// 验证错误
Anon_ResponseHelper::validationError('参数验证失败', $errors);
// $errors = ['field1' => '错误消息1', 'field2' => '错误消息2']

// 未授权，返回401
Anon_ResponseHelper::unauthorized('请先登录');

// 禁止访问，返回403
Anon_ResponseHelper::forbidden('权限不足');

// 未找到，返回404
Anon_ResponseHelper::notFound('资源不存在');

// 服务器错误，返回500
Anon_ResponseHelper::serverError('服务器内部错误', $data);
```

#### 异常处理

```php
try {
    // 业务逻辑
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e, '操作时发生错误');
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

// 用户注销
Anon_Check::logout();

// 设置认证Cookie
Anon_Check::setAuthCookies($userId, $username, $rememberMe);
// $rememberMe为true表示30天，false表示会话结束

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

### Anon_Token

```php
// 生成Token
$token = Anon_Token::generate(['user_id' => 1], 3600);
// 参数：数据数组，过期时间秒数，默认3600

// 验证Token
$payload = Anon_Token::verify($token);
// 返回：false|array
// 成功返回：['data' => [...], 'timestamp' => ..., 'expire' => ..., 'nonce' => ...]

// 从请求中获取Token
$token = Anon_Token::getTokenFromRequest();
// 从Header的X-API-Token或Authorization Bearer获取

// 检查Token是否启用
$enabled = Anon_Token::isEnabled();

// 检查是否启用刷新
$refreshEnabled = Anon_Token::isRefreshEnabled();

// 获取白名单
$whitelist = Anon_Token::getWhitelist();

// 检查路由是否在白名单
$isWhitelisted = Anon_Token::isWhitelisted('/auth/login');
```

---

## 数据库操作

### Anon_Database

```php
$db = new Anon_Database();

// 推荐使用QueryBuilder
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

// 不推荐执行原始SQL，除非必要
$result = $db->query('SELECT * FROM users WHERE id = 1');

// 准备预处理语句
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [1]);

// 自动发现并访问Repository/Service
$user = $db->userRepository->getUserInfo(1);
$avatar = $db->avatarService->getAvatarUrl(1);
```

### Anon_QueryBuilder

```php
$query = new Anon_QueryBuilder($connection, 'users');

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
])->execute();

// 更新
$affected = $query->update(['status' => 'active'])
    ->where('id', '=', 1)
    ->execute();

// 删除
$affected = $query->where('id', '=', 1)->delete()->execute();

// JOIN
$result = $query->select(['users.*', 'profiles.bio'])
    ->join('profiles', 'users.id', '=', 'profiles.user_id', 'LEFT')
    ->get();

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

### Anon_Hook

```php
// 添加动作钩子
Anon_Hook::add_action('user_login', function($user) {
    // 用户登录后执行
}, 10, 1);
// 参数：钩子名，回调函数，优先级数字越小越先执行默认10，接受参数数量

// 执行动作钩子
Anon_Hook::do_action('user_login', $user);
Anon_Hook::do_action('user_login', $user, $timestamp); // 多个参数

// 添加过滤器钩子
Anon_Hook::add_filter('response_data', function($data) {
    // 修改响应数据
    return $data;
}, 10, 1);

// 应用过滤器
$filtered = Anon_Hook::apply_filters('response_data', $data);
$filtered = Anon_Hook::apply_filters('response_data', $data, $arg1, $arg2);

// 移除指定钩子
Anon_Hook::removeHook('user_login', $callback, 10);

// 移除所有钩子
Anon_Hook::removeAllHooks(); // 移除所有
Anon_Hook::removeAllHooks('user_login'); // 移除指定钩子的所有回调
Anon_Hook::removeAllHooks('user_login', 10); // 移除指定优先级

// 检查钩子是否存在
$exists = Anon_Hook::hasHook('user_login');
$priority = Anon_Hook::hasHook('user_login', $callback); // 返回优先级或false

// 获取当前执行的钩子名称
$currentHook = Anon_Hook::getCurrentHook();

// 获取钩子统计信息
$stats = Anon_Hook::getHookStats(); // 所有统计
$stats = Anon_Hook::getHookStats('user_login'); // 指定钩子统计

// 获取所有注册的钩子
$allHooks = Anon_Hook::getAllHooks();

// 清除统计信息
Anon_Hook::clearStats(); // 清除所有
Anon_Hook::clearStats('user_login'); // 清除指定钩子
```

---

## Widget 组件

### Anon_Widget

```php
$widget = Anon_Widget::getInstance();

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

### Anon_Capability

```php
$capability = Anon_Capability::getInstance();

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

### Anon_Cache

```php
// 初始化缓存
Anon_Cache::init('file'); // 'file' 或 'memory'

// 设置缓存
Anon_Cache::set('key', $value, 3600); // 1小时过期
Anon_Cache::set('key', $value, null); // 永不过期

// 获取缓存
$value = Anon_Cache::get('key', 'default');

// 检查缓存是否存在
$exists = Anon_Cache::has('key');

// 删除缓存
Anon_Cache::delete('key');

// 清空所有缓存
Anon_Cache::clear();

// 记住缓存（如果不存在则执行闭包并缓存结果）
$value = Anon_Cache::remember('key', function() {
    return expensiveOperation();
}, 3600);
```

---

## 容器系统

### Anon_Container

```php
$container = Anon_Container::getInstance();

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

### Anon_Middleware

```php
// 注册全局中间件
Anon_Middleware::global('AuthMiddleware');

// 注册带别名的路由中间件
Anon_Middleware::alias('auth', 'AuthMiddleware');
Anon_Middleware::alias('throttle', 'ThrottleMiddleware');

// 在路由中使用
const Anon_RouterMeta = [
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

### Anon_Console

```php
// 注册命令
Anon_Console::command('cache:clear', function($args) {
    Anon_Cache::clear();
    Anon_Console::success('缓存已清空');
}, '清空缓存');

// 注册别名
Anon_Console::alias('cc', 'cache:clear');

// 运行命令
exit(Anon_Console::run($argv));

// 输出消息
Anon_Console::info('信息消息');
Anon_Console::success('成功消息');
Anon_Console::error('错误消息');
Anon_Console::warning('警告消息');
Anon_Console::line('普通消息');

// 获取所有命令
$commands = Anon_Console::getCommands();
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

### Anon_Config

```php
// 添加路由
Anon_Config::addRoute('/api/custom', function() {
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 添加错误处理器
Anon_Config::addErrorHandler(404, function() {
    Anon_ResponseHelper::notFound('页面不存在');
});

// 获取路由配置
$config = Anon_Config::getRouterConfig();

// 检查是否已安装
$installed = Anon_Config::isInstalled();
```

### Anon_Env

```php
// 获取配置值
$value = Anon_Env::get('app.token.enabled', false);
$value = Anon_Env::get('system.db.host', 'localhost');
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

---

[← 返回文档首页](../README.md)
