# API 参考文档

本节说明所有核心模块的公共方法调用参考，方便快速查找可用方法。主要面向 API 模式开发，CMS 模式下管理后台也会调用部分接口。

## 类名变更说明

框架已重构，类名已更新为更清晰的命名。建议使用新类名。

::: tip 使用提示
本文档展示的是类的公共方法。在实际使用时,需要在文件顶部添加相应的 `use` 语句:

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\Auth\Csrf;
use Anon\Modules\Auth\Captcha;
use Anon\Modules\Auth\RateLimit;
use Anon\Modules\Auth\Token;
use Anon\Modules\Auth\Capability;
use Anon\Modules\Database\Database;
use Anon\Modules\Database\QueryOptimizer;
use Anon\Modules\Database\Sharding;
use Anon\Modules\System\Hook;
use Anon\Modules\System\Env;
use Anon\Modules\System\Config;
use Anon\Modules\System\Cache\Cache;
use Anon\Modules\System\Container;
use Anon\Modules\System\Console;
use Anon\Modules\System\Plugin;
use Anon\Modules\System\Widget;
use Anon\Modules\Security\Security;
use Anon\Modules\Middleware\CsrfMiddleware;
use Anon\Modules\Middleware\RateLimitMiddleware;
use Anon\Widgets\Utils\Escape;
use Anon\Modules\Debug;
use Anon\Modules\Common;
use Anon\Modules\Check;
use Anon\Modules\Cms\Options;
use Anon\Modules\Cms\Cms;
use Anon\Widgets\Cms\User;
use Anon\Modules\Cms\Theme\Theme;
// ... 根据使用的类添加对应的 use 语句
```
:::

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Auth\Token;

// 新类名示例
RequestHelper::validate([...]);
Token::generate([...]);
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

### RequestHelper

```php
use Anon\Modules\Http\RequestHelper;

// 获取输入数据 (自动处理 JSON/POST)
$data = RequestHelper::getInput();

// 获取参数 (支持默认值)
$val = RequestHelper::get('key', 'default');
$val = RequestHelper::post('key');
$val = RequestHelper::getParam('id');

// 验证请求数据
$data = RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);

// 检查请求方法
if (RequestHelper::isPost()) { ... }
RequestHelper::requireMethod('POST');

// 获取当前用户
$userId = RequestHelper::getUserId();
$user = RequestHelper::requireAuth(); // 未登录抛出 401

// 验证 API Token
RequestHelper::requireToken(); // 无效抛出 403
```

---

## 响应处理

### ResponseHelper

```php
use Anon\Modules\Http\ResponseHelper;

// 成功响应 (JSON)
ResponseHelper::success(['id' => 1], '操作成功');

// 分页响应
ResponseHelper::paginated($items, $pagination, '获取成功');

// 错误响应
ResponseHelper::error('操作失败', [], 400);
ResponseHelper::validationError('验证失败', $errors);
ResponseHelper::unauthorized('请登录');
ResponseHelper::forbidden('无权访问');
ResponseHelper::notFound('资源未找到');
ResponseHelper::serverError('系统错误');

// 异常处理
ResponseHelper::handleException($e);
```

---

## 用户认证

### Check (登录状态检查)

```php
<?php
use Anon\Modules\Check;

// 检查登录状态
$isLogged = Check::isLoggedIn();

// 启动 Session
Check::startSessionIfNotStarted();
```

### Capability (权限系统)

```php
<?php
use Anon\Modules\Auth\Capability;

$capability = Capability::getInstance();

// 检查当前用户权限
if ($capability->currentUserCan('post:create')) {
    // 有权限
}

// 要求权限(无权限时抛出403)
$capability->requireCapability('admin:manage');

// 检查指定用户权限
if ($capability->userCan($userId, 'post:edit')) {
    // 有权限
}
```

---

## Token 管理

### Token

```php
<?php
use Anon\Modules\Auth\Token;

// 生成 Token
$token = Token::generate(['uid' => 1], 3600);

// 验证 Token
$payload = Token::verify($token);

// 从请求头获取 Token
$token = Token::getTokenFromRequest();

// 检查是否启用
if (Token::isEnabled()) {
    // Token 功能已启用
}

// 检查是否在白名单中
if (Token::isWhitelisted('/api/public')) {
    // 路径在白名单中
}
```

### Captcha

```php
<?php
use Anon\Modules\Auth\Captcha;

// 生成验证码
$data = Captcha::generate(120, 40, 4);
// 返回: ['code' => '...', 'image' => 'data:image...']

// 验证
$isValid = Captcha::verify($inputCode);

// 保存到 Session
Captcha::saveToSession($code);

// 检查是否启用
if (Captcha::isEnabled()) {
    // 验证码功能已启用
}
```

---

## 数据库操作

### Database

```php
<?php
use Anon\Modules\Database\Database;

$db = Database::getInstance();

// 查询构造器
$users = $db->db('users')->where('status', 1)->get();

// 批量操作
$db->batchInsert('users', $rows);
$db->batchUpdate('users', $rows, 'id');

// 预处理查询
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [$id]);

// 获取用户信息
$user = $db->getUserInfo($uid);
$user = $db->getUserInfoByName($username);
```

### QueryBuilder

```php
<?php
use Anon\Modules\Database\Database;
use Anon\Modules\Database\QueryOptimizer;
use Anon\Modules\Database\Sharding;

$db = Database::getInstance();
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

// 关联查询优化
$users = QueryOptimizer::eagerLoad($users, 'user_id', 'posts', 'id');

// 分库分表
$tableName = Sharding::getTableName('logs', $id);
```

---

## 系统核心

### 钩子系统 (Hook)

```php
<?php
use Anon\Modules\System\Hook;

// 添加动作
Hook::add_action('user_login', function($user) { ... });

// 添加过滤器
Hook::add_filter('content', function($text) { return $text; });

// 执行
Hook::do_action('user_login', $user);
$text = Hook::apply_filters('content', $text);
```

### 配置管理 (Config / Env)

```php
<?php
use Anon\Modules\System\Env;
use Anon\Modules\System\Config;

// 获取配置
$val = Env::get('app.name', 'Anon');

// 添加路由
Config::addRoute('/api/test', function() { ... });

// 获取所有配置
$all = Env::all();
```

### 缓存系统 (Cache)

```php
<?php
use Anon\Modules\System\Cache\Cache;

// 设置缓存
Cache::set('key', $value, 3600);

// 获取缓存
$val = Cache::get('key', 'default');

// 记住缓存 (推荐)
$val = Cache::remember('key', function() {
    return expensive_op();
}, 3600);
```

### 容器系统 (Container)

```php
<?php
use Anon\Modules\System\Container;
use Anon\Modules\Database\Database;

$container = Container::getInstance();
$container->bind('db', function() { return new Database(); });
$db = $container->make('db');
```

### 控制台工具 (Console)

```php
<?php
use Anon\Modules\System\Console;

// 注册命令
Console::command('test', function() {
    Console::success('Done');
});

// 输出信息
Console::info('Info');
Console::error('Error');
```

---

## 组件与插件

### Widget 组件 (Widget)

```php
<?php
use Anon\Modules\System\Widget;

// 注册组件
Widget::getInstance()->register('my_widget', 'Title', function($args) {
    return ['key' => 'val'];
});

// 渲染组件
$html = Widget::getInstance()->render('my_widget');
```

### 插件系统 (Plugin)

```php
<?php
use Anon\Modules\System\Plugin;

// 获取已加载插件
$plugins = Plugin::getLoadedPlugins();

// 激活/停用
Plugin::activatePlugin('plugin-name');
Plugin::deactivatePlugin('plugin-name');

// 获取插件选项
$opts = Plugin::getPluginOptions('plugin-name');
```

---

## 安全与防护

### 安全功能

```php
<?php
use Anon\Modules\Auth\Csrf;
use Anon\Modules\Security\Security;

// CSRF 防护
$token = Csrf::generateToken();
Csrf::verify();

// 安全过滤 (Security)
$safe = Security::filterInput($_POST);
$hasXss = Security::containsXss($str);
```

### 防刷限制 (RateLimit)

```php
<?php
use Anon\Modules\Auth\RateLimit;

// 检查限制
$res = RateLimit::checkLimit('key', 100, 60);

// 获取客户端指纹
$fp = RateLimit::generateDeviceFingerprint();
```

### 中间件 (Middleware)

```php
<?php
use Anon\Modules\Http\Middleware;
use Anon\Middleware\CsrfMiddleware;
use Anon\Middleware\RateLimitMiddleware;

// 注册全局中间件
Middleware::global(CsrfMiddleware::make());
Middleware::global(RateLimitMiddleware::make(100, 60));
```

---

## 工具类

### Helper

```php
<?php
use AnonModules\Helper;
use Anon\Widgets\Utils\Escape;

// 数据清理
$clean = Helper::sanitizeText($html);
$email = Helper::sanitizeEmail($input);

// 转义输出
$html = Escape::html($str);

// 字符串处理
$slug = Helper::slugify('Title Here');
$trunc = Helper::truncate($text, 50);

// 验证
$isEmail = Helper::isValidEmail($email);
```

### Validate

```php
// 密码强度验证
$err = Validate::passwordStrength($pwd);

// 用户名验证
$err = Validate::username($name);
```

---

## 调试工具

### Debug

```php
<?php
use Anon\Modules\Debug;

// 记录日志
Debug::log('INFO', 'Message');
Debug::error('Error', ['data' => $v]);

// 性能打点
Debug::startPerformance('task');
Debug::endPerformance('task');
```

---

## 通用功能

### Common

```php
<?php
use Anon\Modules\Common;

// 设置响应头
Common::Header(200);

// 获取系统信息
$info = Common::SystemInfo();

// 获取客户端 IP
$ip = Common::GetClientIp();
```

---

## CMS 专用功能

### Options

```php
<?php
use Anon\Modules\Cms\Options;

// 获取 CMS 选项
$val = Options::get('site_name', 'Default');

// 设置 CMS 选项
Options::set('site_name', 'Value');
```

### User

```php
<?php
use Anon\Modules\Cms\Cms;
use Anon\Widgets\Cms\User;

// 获取当前 CMS 用户
$user = Cms::getCurrentUser();
$cmsUser = new User($user);

// 获取属性
$name = $cmsUser->displayName();
$avatar = $cmsUser->avatar();
```

### Theme

```php
<?php
use Anon\Modules\Cms\Theme\Theme;

// 获取当前主题
$theme = Theme::getCurrentTheme();

// 获取资源 URL
$url = Theme::getAssetUrl('style.css');
```
