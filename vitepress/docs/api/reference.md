# API 参考文档

本节说明所有核心模块的公共方法调用参考，方便快速查找可用方法。主要面向 API 模式开发，CMS 模式下管理后台也会调用部分接口。

## 类名变更说明

框架已重构，类名已更新为更清晰的命名。建议使用新类名。

```php
// 新类名示例
Anon_Http_Request::validate([...]);
Anon_Auth_Token::generate([...]);
```

## 📋 目录

- [请求处理](#请求处理)
- [响应处理](#响应处理)
- [用户认证](#用户认证)
- [Token 管理](#token-管理)
- [数据库操作](#数据库操作)
- [系统核心](#系统核心)
  - [钩子系统](#钩子系统)
  - [配置管理](#配置管理)
  - [缓存系统](#缓存系统)
  - [容器系统](#容器系统)
  - [控制台工具](#控制台工具)
- [组件与插件](#组件与插件)
  - [Widget 组件](#widget-组件)
  - [插件系统](#插件系统)
- [安全与防护](#安全与防护)
  - [安全功能](#安全功能)
  - [防刷限制](#防刷限制)
  - [中间件](#中间件)
- [工具类](#工具类)
- [调试工具](#调试工具)
- [通用功能](#通用功能)
- [CMS 专用功能](#cms-专用功能)

---

## 请求处理

### Anon_Http_Request

```php
// 获取输入数据 (自动处理 JSON/POST)
$data = Anon_Http_Request::getInput();

// 获取参数 (支持默认值)
$val = Anon_Http_Request::get('key', 'default');
$val = Anon_Http_Request::post('key');
$val = Anon_Http_Request::getParam('id');

// 验证请求数据
$data = Anon_Http_Request::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);

// 检查请求方法
if (Anon_Http_Request::isPost()) { ... }
Anon_Http_Request::requireMethod('POST');

// 获取当前用户
$userId = Anon_Http_Request::getUserId();
$user = Anon_Http_Request::requireAuth(); // 未登录抛出 401

// 验证 API Token
Anon_Http_Request::requireToken(); // 无效抛出 403
```

---

## 响应处理

### Anon_Http_Response

```php
// 成功响应 (JSON)
Anon_Http_Response::success(['id' => 1], '操作成功');

// 分页响应
Anon_Http_Response::paginated($items, $pagination, '获取成功');

// 错误响应
Anon_Http_Response::error('操作失败', [], 400);
Anon_Http_Response::validationError('验证失败', $errors);
Anon_Http_Response::unauthorized('请登录');
Anon_Http_Response::forbidden('无权访问');
Anon_Http_Response::notFound('资源未找到');
Anon_Http_Response::serverError('系统错误');

// 异常处理
Anon_Http_Response::handleException($e);
```

---

## 用户认证

### Anon_Check

```php
// 检查登录状态
$isLogged = Anon_Check::isLoggedIn();

// 注销
Anon_Check::logout();

// 设置认证 Cookie
Anon_Check::setAuthCookies($uid, $username, $remember);

// 清除认证 Cookie
Anon_Check::clearAuthCookies();
```

---

## Token 管理

### Anon_Auth_Token

```php
// 生成 Token
$token = Anon_Auth_Token::generate(['uid' => 1], 3600);

// 验证 Token
$payload = Anon_Auth_Token::verify($token);

// 从请求头获取 Token
$token = Anon_Auth_Token::getTokenFromRequest();
```

### Anon_Auth_Captcha

```php
// 生成验证码
$data = Anon_Auth_Captcha::generate(120, 40, 4);
// 返回: ['code' => '...', 'image' => 'data:image...']

// 验证
$isValid = Anon_Auth_Captcha::verify($inputCode);

// 保存到 Session
Anon_Auth_Captcha::saveToSession($code);
```

---

## 数据库操作

### Anon_Database

```php
$db = Anon_Database::getInstance();

// 查询构造器
$users = $db->db('users')->where('status', 1)->get();

// 批量操作
$db->batchInsert('users', $rows);
$db->batchUpdate('users', $rows, 'id');

// 预处理查询
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [$id]);
```

### Anon_Database_QueryBuilder

```php
$query = $db->db('users');

// 链式调用
$result = $query->select(['id', 'name'])
    ->where('age', '>', 18)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// 插入数据
$id = $db->db('users')->insert([
    'name' => 'User',
    'email' => 'user@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// 更新数据
$affected = $db->db('users')->where('id', 1)->update(['status' => 1]);

// 删除数据
$affected = $db->db('users')->where('status', 0)->delete();

// 创建表 (推荐使用数组格式定义字段)
$db->db('new_table')->createTable([
    'id' => [
        'type' => 'BIGINT UNSIGNED',
        'autoIncrement' => true,
        'primary' => true
    ],
    'name' => [
        'type' => 'VARCHAR(255)',
        'null' => false,
        'comment' => '名称'
    ],
    'created_at' => [
        'type' => 'DATETIME',
        'null' => true
    ]
], ['engine' => 'InnoDB', 'charset' => 'utf8mb4']);

// 游标分页 (大数据优化)
$result = $query->cursorPaginate(20, $cursor);
// 返回: ['data' => [...], 'next_cursor' => ...]

// 关联查询优化
$users = Anon_Database_QueryOptimizer::eagerLoad($users, 'user_id', 'posts', 'id');

// 分库分表
$tableName = Anon_Database_Sharding::getTableName('logs', $id);
```

---

## 系统核心

### 钩子系统 (Anon_System_Hook)

```php
// 添加动作
Anon_System_Hook::add_action('user_login', function($user) { ... });

// 添加过滤器
Anon_System_Hook::add_filter('content', function($text) { return $text; });

// 执行
Anon_System_Hook::do_action('user_login', $user);
$text = Anon_System_Hook::apply_filters('content', $text);
```

### 配置管理 (Anon_System_Config / Env)

```php
// 获取配置
$val = Anon_System_Env::get('app.name', 'Anon');

// 添加路由
Anon_System_Config::addRoute('/api/test', function() { ... });

// 获取所有配置
$all = Anon_System_Env::all();
```

### 缓存系统 (Anon_System_Cache)

```php
// 设置缓存
Anon_System_Cache::set('key', $value, 3600);

// 获取缓存
$val = Anon_System_Cache::get('key', 'default');

// 记住缓存 (推荐)
$val = Anon_System_Cache::remember('key', function() {
    return expensive_op();
}, 3600);
```

### 容器系统 (Anon_System_Container)

```php
$container = Anon_System_Container::getInstance();
$container->bind('db', function() { return new Database(); });
$db = $container->make('db');
```

### 控制台工具 (Anon_System_Console)

```php
// 注册命令
Anon_System_Console::command('test', function() {
    Anon_System_Console::success('Done');
});

// 输出信息
Anon_System_Console::info('Info');
Anon_System_Console::error('Error');
```

---

## 组件与插件

### Widget 组件 (Anon_System_Widget)

```php
// 注册组件
Anon_System_Widget::getInstance()->register('my_widget', 'Title', function($args) {
    return ['key' => 'val'];
});

// 渲染组件
$html = Anon_System_Widget::getInstance()->render('my_widget');
```

### 插件系统 (Anon_System_Plugin)

```php
// 获取已加载插件
$plugins = Anon_System_Plugin::getLoadedPlugins();

// 激活/停用
Anon_System_Plugin::activatePlugin('plugin-name');
Anon_System_Plugin::deactivatePlugin('plugin-name');

// 获取插件选项
$opts = Anon_System_Plugin::getPluginOptions('plugin-name');
```

---

## 安全与防护

### 安全功能

```php
// CSRF 防护
$token = Anon_Auth_Csrf::generateToken();
Anon_Auth_Csrf::verify();

// 安全过滤 (Anon_Security)
$safe = Anon_Security::filterInput($_POST);
$hasXss = Anon_Security::containsXss($str);
```

### 防刷限制 (Anon_Auth_RateLimit)

```php
// 检查限制
$res = Anon_Auth_RateLimit::checkLimit('key', 100, 60);

// 获取客户端指纹
$fp = Anon_Auth_RateLimit::generateDeviceFingerprint();
```

### 中间件 (Anon_Http_Middleware)

```php
// 注册全局中间件
Anon_Http_Middleware::global(Anon_CsrfMiddleware::make());
Anon_Http_Middleware::global(Anon_RateLimitMiddleware::make(100, 60));
```

---

## 工具类

### Anon_Helper

```php
// 数据清理
$clean = Anon_Helper::sanitizeText($html);
$email = Anon_Helper::sanitizeEmail($input);

// 转义输出
$html = Anon_Helper::escHtml($str);

// 字符串处理
$slug = Anon_Helper::slugify('Title Here');
$trunc = Anon_Helper::truncate($text, 50);

// 验证
$isEmail = Anon_Helper::isValidEmail($email);
```

### Anon_Utils_Validate

```php
// 密码强度验证
$err = Anon_Utils_Validate::passwordStrength($pwd);

// 用户名验证
$err = Anon_Utils_Validate::username($name);
```

---

## 调试工具

### Anon_Debug

```php
// 记录日志
Anon_Debug::log('INFO', 'Message');
Anon_Debug::error('Error', ['data' => $v]);

// 性能打点
Anon_Debug::startPerformance('task');
Anon_Debug::endPerformance('task');
```

---

## 通用功能

### Anon_Common

```php
// 设置响应头
Anon_Common::Header(200);

// 获取系统信息
$info = Anon_Common::SystemInfo();

// 获取客户端 IP
$ip = Anon_Common::GetClientIp();
```

---

## CMS 专用功能

### Anon_Cms_Options

```php
// 获取 CMS 选项
$val = Anon_Cms_Options::get('site_name', 'Default');

// 设置 CMS 选项
Anon_Cms_Options::set('site_name', 'Value');
```

### Anon_Cms_User

```php
// 获取当前 CMS 用户
$user = Anon_Cms::getCurrentUser();
$cmsUser = new Anon_Cms_User($user);

// 获取属性
$name = $cmsUser->displayName();
$avatar = $cmsUser->avatar();
```

### Anon_Cms_Theme

```php
// 获取当前主题
$theme = Anon_Cms_Theme::getCurrentTheme();

// 获取资源 URL
$url = Anon_Cms_Theme::getAssetUrl('style.css');
```
