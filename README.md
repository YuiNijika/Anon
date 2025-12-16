# Anon Framework

> 这是一个用于PHP的API开发框架  
> 开发灵感来源于 [Typecho](https://github.com/Typecho) | [TTDF](https://github.com/YuiNijika/TTDF)  

[配套前端](https://github.com/YuiNijika/AnonClient)

金牌赞助: [Cyber蝈蝈总](https://github.com/Katock-Cricket)

**PHP 版本要求：7.4 - 8.4**

---

## 📚 目录

- [快速开始](#-快速开始)
- [核心功能](#-核心功能)
  - [路由处理](#路由处理)
    - [自动路由](#自动路由)
    - [手动路由](#手动路由)
    - [登录检查](#登录检查)
  - [数据库操作](#数据库操作)
  - [请求与响应](#请求与响应)
  - [用户认证](#用户认证)
- [工具类](#-工具类)
  - [辅助函数](#辅助函数)
  - [Utils 工具集](#utils-工具集)
- [高级功能](#-高级功能)
  - [Widget 组件系统](#widget-组件系统)
  - [用户权限系统](#用户权限系统)
  - [钩子系统](#钩子系统)
  - [验证码](#验证码)
  - [Token 验证](#token-验证)
- [配置说明](#-配置说明)
- [调试工具](#-调试工具)

---

## 🚀 快速开始

### 1. 配置数据库

编辑 `server/env.php`：

```php
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'root');
define('ANON_DB_DATABASE', 'anon');
define('ANON_DB_CHARSET', 'utf8mb4');
define('ANON_INSTALLED', true);
```

### 2. 应用配置

编辑 `server/app/useApp.php`：

```php
return [
    'app' => [
        'autoRouter' => true,  // 启用自动路由
        'debug' => [
            'global' => false, // 全局debug
            'router' => false, // 路由debug
        ],
        'token' => [ // api访问token
            'enabled' => true, // 是否启用token
            'whitelist' => [ // 白名单路由
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],
        ],
        'captcha' => [
            'enabled' => true, // 是否启用验证码
        ],
    ],
];
```

### 3. 创建路由

**自动路由模式（推荐）**：

创建 `server/app/Router/Test/Index.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    Anon_ResponseHelper::success(['message' => 'Anon Tokyo~!']);
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

访问：`GET /test/index`（自动注册，路径自动转为小写）

**路由规则**：

- 文件路径：`app/Router/Test/Index.php` → 路由路径：`/test/index`
- 文件路径：`app/Router/User/Profile/Index.php` → 路由路径：`/user/profile/index`
- 所有路由路径自动转为小写，不区分文件大小写
- **特殊处理**：文件名和目录名中的下划线（`_`）会自动转换为连字符（`-`）
  - 文件路径：`app/Router/Aa_Bb/Cc_Dd.php` → 路由路径：`/aa-bb/cc-dd`
  - 文件路径：`app/Router/User_Profile/Index.php` → 路由路径：`/user-profile/index`

---

## 💡 核心功能

### 路由处理

#### 自动路由

**启用方式**：

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => true,  // 启用自动路由
],
```

**工作原理**：

- 自动扫描 `app/Router` 目录下的所有 PHP 文件
- 根据文件结构自动生成路由路径
- 所有路由路径自动转为小写
- 通过 `Anon_RouterMeta` 常量配置路由元数据（Header、登录检查、HTTP 方法等）

**示例**：

```text
app/Router/
  ├── Auth/
  │   ├── Login.php           → /auth/login
  │   ├── Logout.php          → /auth/logout
  │   └── Token.php           → /auth/token
  ├── User/
  │   └── Info.php             → /user/info
  └── User_Profile/
      ├── Index.php            → /user-profile/index
      └── Update_Avatar.php    → /user-profile/update-avatar
```

**命名转换规则**：

- 文件名和目录名中的下划线（`_`）会自动转换为连字符（`-`）
- 所有路径自动转为小写
- 例如：`User_Profile/Update_Avatar.php` → `/user-profile/update-avatar`

#### 手动路由

**启用方式**：

在 `server/app/useApp.php` 中设置：

```php
'app' => [
    'autoRouter' => false,  // 禁用自动路由
],
```

**配置路由**：

编辑 `server/app/useRouter.php`：

```php
return [
    'auth' => [
        'login' => [
            'view' => 'Auth/Login',
        ],
        'logout' => [
            'view' => 'Auth/Logout',
        ],
    ],
    'user' => [
        'info' => [
            'view' => 'User/Info',
        ],
    ],
];
```

#### 路由元数据配置（Anon_RouterMeta）

**推荐方式**：使用 `Anon_RouterMeta` 常量统一配置路由元数据，系统会自动应用这些配置。

**配置项说明**：

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `header` | bool | `true` | 是否设置响应头（包括 CORS、Content-Type 等） |
| `requireLogin` | bool | `false` | 是否需要登录验证 |
| `method` | string\|array | `null` | 允许的 HTTP 方法，如 `'GET'` 或 `['GET', 'POST']` |
| `cors` | bool | `true` | 是否设置 CORS 头 |
| `response` | bool | `true` | 是否设置 JSON 响应头 |
| `code` | int | `200` | HTTP 状态码 |

**示例：需要登录的 GET 接口**：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'GET',
];

try {
    // 获取用户信息（已自动通过登录检查）
    $userInfo = Anon_RequestHelper::requireAuth();
    
    Anon_ResponseHelper::success($userInfo, '获取用户信息成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

**示例：不需要登录的 POST 接口**：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    // 验证输入数据
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    // 业务逻辑
    $db = new Anon_Database();
    $user = $db->getUserInfoByName($data['username']);
    
    Anon_ResponseHelper::success($user, '操作成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

**示例：支持多种 HTTP 方法**：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => ['GET', 'POST'],  // 支持 GET 和 POST
];

try {
    // 根据请求方法执行不同逻辑
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        // GET 逻辑
    } else {
        // POST 逻辑
    }
    
    Anon_ResponseHelper::success(null, '操作成功');
    
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

**注意**：

- `Anon_RouterMeta` 必须在路由文件顶部定义
- 如果未定义 `Anon_RouterMeta`，系统会使用默认配置（`header: true`, `requireLogin: false`, `method: null`）
- HTTP 方法检查会在登录检查之前执行
- 登录检查失败会自动返回 401 错误

#### 动态注册路由

```php
// server/app/useCode.php
Anon_Config::addRoute('/api/custom', function () {
    Anon_Common::Header();
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});
```

---

### 数据库操作

#### 基本使用

```php
$db = new Anon_Database();

// 用户操作（自动转发到 UserRepository）
$db->addUser('admin', 'admin@example.com', 'password', 'admin');
$user = $db->getUserInfo(1);
$user = $db->getUserInfoByName('admin');
$isAdmin = $db->isUserAdmin(1);
$db->updateUserGroup(1, 'admin');
```

#### QueryBuilder

```php
$db = new Anon_Database();

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

// 计数
$count = $db->db('users')
    ->where('group', '=', 'admin')
    ->count()
    ->scalar();

// 存在检查
$exists = $db->db('users')
    ->where('email', '=', 'admin@example.com')
    ->exists()
    ->scalar();
```

#### 创建 Repository/Service

创建 `server/app/Database/User.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_UserRepository extends Anon_Database_Connection
{
    public function getUserInfo(int $uid)
    {
        return $this->db('users')
            ->where('uid', '=', $uid)
            ->first();
    }
    
    public function getUserInfoByName(string $name)
    {
        return $this->db('users')
            ->where('name', '=', $name)
            ->first();
    }
}
```

访问方式：

```php
$db = new Anon_Database();
$user = $db->getUserInfo(1);  // 自动转发
// 或
$user = $db->userRepository->getUserInfo(1);  // 直接访问
```

---

### 请求与响应

#### 请求处理

```php
// 检查请求方法
Anon_RequestHelper::requireMethod('POST');
Anon_RequestHelper::requireMethod(['GET', 'POST']);

// 获取输入
$data = Anon_RequestHelper::getInput();  // JSON 或 POST
$username = Anon_RequestHelper::get('username', 'default');

// 验证必需参数
$data = Anon_RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空',
]);
```

#### 响应处理

```php
// 成功响应
Anon_ResponseHelper::success($data, '操作成功');
Anon_ResponseHelper::success($data, '操作成功', 201);

// 错误响应
Anon_ResponseHelper::error('错误消息');
Anon_ResponseHelper::error('错误消息', $data, 400);

// HTTP 状态码响应
Anon_ResponseHelper::unauthorized('未授权访问');
Anon_ResponseHelper::forbidden('禁止访问');
Anon_ResponseHelper::notFound('资源未找到');
Anon_ResponseHelper::serverError('服务器内部错误');
Anon_ResponseHelper::methodNotAllowed('GET, POST');
Anon_ResponseHelper::validationError('参数验证失败', $errors);

// 处理异常
Anon_ResponseHelper::handleException($e, '自定义错误消息');
```

#### HTTP 响应头

```php
Anon_Common::Header();              // 200, JSON, CORS
Anon_Common::Header(404);          // 404, JSON, CORS
Anon_Common::Header(200, false);   // 200, 非JSON, CORS
Anon_Common::Header(200, true, false); // 200, JSON, 非CORS
```

---

### 用户认证

#### 登录检查

```php
if (Anon_Check::isLoggedIn()) {
    // 已登录
}
```

#### 获取当前用户

```php
// 获取用户 ID
$userId = Anon_RequestHelper::getUserId();

// 获取完整用户信息（需要登录）
$userInfo = Anon_RequestHelper::requireAuth();
```

#### 设置认证 Cookie

```php
Anon_Check::setAuthCookies($userId, $username, $rememberMe);
```

#### 登出

```php
Anon_Check::logout();
```

#### 登录示例

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

---

## 🛠️ 工具类

### 辅助函数

`Anon_Helper` 提供常用工具方法：

```php
// HTML 转义
$escaped = Anon_Helper::escHtml('<script>alert("xss")</script>');
$url = Anon_Helper::escUrl('https://example.com');
$attr = Anon_Helper::escAttr('value with "quotes"');
$js = Anon_Helper::escJs('alert("test")');

// 数据清理
$clean = Anon_Helper::sanitizeText('<p>HTML</p>');
$email = Anon_Helper::sanitizeEmail('user@example.com');
$url = Anon_Helper::sanitizeUrl('https://example.com');

// 验证
if (Anon_Helper::isValidEmail('user@example.com')) {
    // 有效邮箱
}
if (Anon_Helper::isValidUrl('https://example.com')) {
    // 有效 URL
}

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

### Utils 工具集

工具类位于 `server/core/Widget/Utils/`，可直接使用：

```php
// 转义工具
Anon_Utils_Escape::html($text);
Anon_Utils_Escape::url($url);
Anon_Utils_Escape::attr($text);
Anon_Utils_Escape::js($text);

// 清理工具
Anon_Utils_Sanitize::text($text);
Anon_Utils_Sanitize::email($email);
Anon_Utils_Sanitize::url($url);

// 验证工具
Anon_Utils_Validate::email($email);
Anon_Utils_Validate::url($url);

// 文本工具
Anon_Utils_Text::truncate($text, 10);
Anon_Utils_Text::slugify($text);
Anon_Utils_Text::timeAgo($timestamp);

// 格式化工具
Anon_Utils_Format::bytes(1048576);

// 数组工具
Anon_Utils_Array::get($array, 'key', 'default');
Anon_Utils_Array::set($array, 'key', 'value');
Anon_Utils_Array::merge($array1, $array2);

// 随机工具
Anon_Utils_Random::string(32);
```

---

## 🎯 高级功能

### Widget 组件系统

```php
$widget = Anon_Widget::getInstance();

// 注册组件
$widget->register('my_widget', '我的组件', function ($args) {
    echo '<div>' . Anon_Helper::escHtml($args['title'] ?? '') . '</div>';
}, [
    'description' => '这是一个示例组件',
    'class' => 'custom-widget'
]);

// 渲染组件
$output = $widget->render('my_widget', ['title' => '标题']);

// 检查组件是否存在
if ($widget->exists('my_widget')) {
    // 组件存在
}

// 获取所有组件
$allWidgets = $widget->all();

// 注销组件
$widget->unregister('my_widget');
```

### 用户权限系统

```php
$capability = Anon_Capability::getInstance();

// 检查用户权限
if ($capability->userCan($userId, 'edit_posts')) {
    // 用户有权限
}

// 检查角色权限
if ($capability->roleCan('admin', 'manage_options')) {
    // 角色有权限
}

// 检查当前用户权限
if ($capability->currentUserCan('edit_posts')) {
    // 当前用户有权限
}

// 要求权限（无权限则返回 403）
$capability->requireCapability('manage_options');

// 添加/移除权限
$capability->addCapability('editor', 'custom_permission');
$capability->removeCapability('admin', 'manage_plugins');

// 获取权限列表
$adminCaps = $capability->getCaps('admin');
$allCaps = $capability->all();
```

#### 内置角色和权限

**admin（管理员）**：

- `manage_options` - 管理选项
- `manage_users` - 管理用户
- `manage_plugins` - 管理插件
- `manage_widgets` - 管理组件
- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**editor（编辑）**：

- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**author（作者）**：

- `edit_own_posts` - 编辑自己的文章
- `delete_own_posts` - 删除自己的文章
- `publish_own_posts` - 发布自己的文章

**user（用户）**：

- `read` - 阅读

### 钩子系统

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

#### 内置钩子

**请求处理**：

- `request_input` - 过滤请求输入数据

**响应处理**：

- `response_before_success` - 成功响应前
- `response_data` - 过滤响应数据
- `response_message` - 过滤响应消息
- `response_success` - 过滤成功响应
- `response_before_error` - 错误响应前
- `response_error_message` - 过滤错误消息
- `response_error` - 过滤错误响应

**用户认证**：

- `auth_before_set_cookies` - 设置 Cookie 前
- `auth_cookie_options` - 过滤 Cookie 选项
- `auth_after_set_cookies` - 设置 Cookie 后
- `auth_before_logout` - 登出前
- `auth_after_logout` - 登出后

**用户操作**：

- `user_before_get_info` - 获取用户信息前
- `user_info` - 过滤用户信息
- `user_after_get_info` - 获取用户信息后
- `user_before_add` - 添加用户前
- `user_after_add` - 添加用户后
- `user_before_update_group` - 更新用户组前
- `user_after_update_group` - 更新用户组后

### 验证码

```php
// 生成验证码（返回 base64 图片）
$result = Anon_Captcha::generate();
$base64Image = $result['image']; // data:image/svg+xml;base64,...
$code = $result['code']; // 验证码字符串

// 验证用户输入的验证码
if (Anon_Captcha::verify($userInput)) {
    // 验证成功
}

// 清除验证码
Anon_Captcha::clear();
```

**特性**：

- 无需 GD 扩展，使用 SVG 生成
- 仅生成数字验证码（0-9）
- 包含干扰线和干扰点
- 支持文字旋转效果
- 验证码存储在 session 中

### Token 验证

#### 配置

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

#### 生成 Token

```php
// 推荐：生成用户 Token
$token = Anon_RequestHelper::generateUserToken($userId, $username, $rememberMe);

// 手动生成 Token
$token = Anon_Token::generate(['user_id' => 1], 3600); // 1小时
$token = Anon_Token::generate(['user_id' => 1], 86400 * 30); // 30天
```

#### 验证 Token

Token 验证自动在路由执行前进行，验证失败返回 403。

**特性**：

- Token 验证通过后，如果包含用户信息，系统自动设置登录状态
- 每个登录会话都有独立的 Token
- Token 只能从 HTTP Header 获取：`X-API-Token` 或 `Authorization: Bearer`

#### 手动验证

```php
Anon_RequestHelper::requireToken();

$payload = Anon_Token::verify();
if ($payload) {
    $userId = $payload['data']['user_id'] ?? null;
}
```

#### 白名单

支持精确匹配和通配符：

- 精确匹配：`/api/public`
- 通配符：`/api/public/*`

---

## ⚙️ 配置说明

### 系统配置 (env.php)

```php
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_PREFIX', 'anon_');
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'root');
define('ANON_DB_DATABASE', 'anon');
define('ANON_DB_CHARSET', 'utf8mb4');
define('ANON_INSTALLED', true);
```

### 应用配置 (useApp.php)

```php
return [
    'app' => [
        'autoRouter' => true,  // 是否启用自动路由（推荐）
        'debug' => [
            'global' => false,  // 全局调试
            'router' => false,  // 路由调试
        ],
        'avatar' => 'https://www.cravatar.cn/avatar',  // 头像源URL
        'token' => [
            'enabled' => true,  // 是否启用 Token 验证
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],  // Token 验证白名单路由
        ],
        'captcha' => [
            'enabled' => true,  // 是否启用验证码
        ],
    ],
];
```

**配置说明**：

- `autoRouter`: 是否启用自动路由模式
  - `true`: 自动扫描 `app/Router` 目录，根据文件结构自动注册路由
  - `false`: 使用 `useRouter.php` 手动配置路由
- `debug.global`: 全局调试模式，启用后会在控制台输出调试信息
- `debug.router`: 路由调试模式，启用后会记录路由注册和执行日志
- `token.enabled`: 是否启用 Token 验证
- `token.whitelist`: Token 验证白名单，这些路由不需要 Token 验证
- `captcha.enabled`: 是否启用验证码功能

---

### 配置访问

```php
// 通过 Anon_Env 获取配置
Anon_Env::get('app.token.enabled', false);
Anon_Env::get('app.captcha.enabled', false);
Anon_Env::get('system.db.host', 'localhost');
```

---

## 🐛 调试工具

### 代码调试

```php
// 日志记录
Anon_Debug::log('INFO', '消息');
Anon_Debug::log('ERROR', '错误');
Anon_Debug::info('信息消息');
Anon_Debug::error('错误消息', ['context' => 'data']);

// 性能监控
Anon_Debug::startPerformance('operation_name');
// ... 执行操作 ...
Anon_Debug::endPerformance('operation_name');

// SQL 查询记录
Anon_Debug::query('SELECT * FROM users', ['id' => 1], 0.12);
```

### Web 调试控制台

启用调试模式后，访问调试控制台：

```
http://localhost:8080/anon/debug/console
```

**功能**：

- 系统信息：PHP 版本、服务器信息、框架信息
- 性能监控：请求耗时、内存使用、数据库查询统计
- 日志查看：系统日志、错误日志
- 钩子监控：已注册的钩子和执行统计
- 工具集：清理调试数据、导出日志等

**登录**：

调试控制台需要登录才能访问，使用系统管理员账号登录。

---

## 📡 API 端点

### 系统端点

- `GET /anon/common/config` - 获取配置信息
- `GET /anon/common/system` - 获取系统信息
- `GET /anon/common/client-ip` - 获取客户端 IP
- `GET /anon/common/license` - 获取许可证信息

### 认证端点

- `POST /auth/login` - 登录
- `POST /auth/logout` - 登出
- `GET /auth/check-login` - 检查登录状态
- `GET /auth/token` - 获取 Token
- `GET /auth/captcha` - 获取验证码

### 用户端点

- `GET /user/info` - 获取用户信息

---

## 📝 自定义代码

在 `server/app/useCode.php` 中添加自定义代码：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册钩子
Anon_Hook::add_action('router_before_init', function () {
    Anon_Debug::info('路由初始化前');
});

// 注册自定义路由
Anon_Config::addRoute('/api/custom', function () {
    Anon_Common::Header();
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 注册错误处理器
Anon_Config::addErrorHandler(404, function () {
    Anon_Common::Header(404);
    Anon_ResponseHelper::notFound('页面不存在');
});
```

---

## 📄 许可证

MIT License

Copyright (c) 2024-2025 鼠子(YuiNijika)
