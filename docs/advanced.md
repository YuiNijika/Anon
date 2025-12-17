# 高级功能

## Widget 组件系统

Widget 组件系统支持两种模式：**HTML 输出模式**和**JSON API 模式**。

### HTML 输出模式传统模式

```php
$widget = Anon_Widget::getInstance();

// 注册 HTML 组件
$widget->register('my_widget', '我的组件', function ($args) {
    echo '<div>' . Anon_Helper::escHtml($args['title'] ?? '') . '</div>';
}, [
    'description' => '这是一个示例组件',
    'class' => 'custom-widget'
], 'html'); // 指定为 HTML 模式

// 渲染组件返回 HTML 字符串
$output = $widget->render('my_widget', ['title' => '标题']);
```

### JSON API 模式推荐

```php
$widget = Anon_Widget::getInstance();

// 注册回调函数返回数组或对象的 JSON API 组件
$widget->register('user_stats', '用户统计', function ($args) {
    $db = new Anon_Database();
    $userId = $args['user_id'] ?? 0;
    
    return [
        'user_id' => $userId,
        'total_posts' => $db->count('posts', ['author_id' => $userId]),
        'total_comments' => $db->count('comments', ['user_id' => $userId]),
        'last_login' => date('Y-m-d H:i:s'),
    ];
}, [
    'description' => '获取用户统计数据'
], 'json'); // 指定为 JSON 模式

// 获取组件数据返回数组
$data = $widget->getData('user_stats', ['user_id' => 1]);
// 返回: ['user_id' => 1, 'total_posts' => 10, ...]

// 获取组件 JSON 字符串
$json = $widget->getJson('user_stats', ['user_id' => 1]);
// 返回: '{"user_id":1,"total_posts":10,...}'

// 在 API 路由中使用
try {
    $userInfo = Anon_RequestHelper::requireAuth();
    $stats = $widget->getData('user_stats', ['user_id' => $userInfo['uid']]);
    
    Anon_ResponseHelper::success($stats, '获取统计数据成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

### 自动检测模式

```php
// 不指定类型，系统自动检测
$widget->register('auto_widget', '自动检测组件', function ($args) {
    // 如果返回数组/对象，自动识别为 JSON 模式
    return ['status' => 'ok', 'data' => $args];
    // 如果使用 echo 输出，自动识别为 HTML 模式
}, [], 'auto'); // 默认值
```

### 组件管理

```php
$widget = Anon_Widget::getInstance();

// 检查组件是否存在
if ($widget->exists('my_widget')) {
    // 组件存在
}

// 获取不执行回调的组件信息
$info = $widget->getInfo('my_widget');
// 返回: ['id' => 'my_widget', 'name' => '我的组件', 'type' => 'json', 'args' => [...]]

// 获取仅信息不执行回调的所有组件列表
$list = $widget->list();
// 返回: [['id' => 'widget1', ...], ['id' => 'widget2', ...]]

// 获取所有包含回调函数的组件用于内部使用
$allWidgets = $widget->all();

// 注销组件
$widget->unregister('my_widget');
```

### 方法对比

| 方法 | 用途 | 返回类型 | 适用模式 |
|------|------|----------|----------|
| `render()` | 渲染 HTML 输出 | `string` | HTML 模式 |
| `getData()` | 获取组件数据 | `array\|null` | JSON 模式 |
| `getJson()` | 获取 JSON 字符串 | `string\|null` | JSON 模式 |
| `getInfo()` | 获取组件信息 | `array\|null` | 所有模式 |
| `list()` | 获取组件列表 | `array` | 所有模式 |

### 钩子支持

Widget 系统支持钩子过滤：

```php
// 过滤组件参数
Anon_Hook::add_filter('widget_args', function ($args, $id) {
    if ($id === 'user_stats') {
        $args['cache'] = true; // 添加缓存参数
    }
    return $args;
}, 10, 2);

// 过滤 JSON 模式的组件数据
Anon_Hook::add_filter('widget_data', function ($data, $id) {
    if ($id === 'user_stats') {
        $data['cached'] = true;
    }
    return $data;
}, 10, 2);

// 过滤 HTML 模式的组件输出
Anon_Hook::add_filter('widget_output', function ($output, $id) {
    return '<div class="widget-wrapper">' . $output . '</div>';
}, 10, 2);
```

## 用户权限系统

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

// 要求权限，无权限则返回 403
$capability->requireCapability('manage_options');

// 添加/移除权限
$capability->addCapability('editor', 'custom_permission');
$capability->removeCapability('admin', 'manage_plugins');

// 获取权限列表
$adminCaps = $capability->getCaps('admin');
$allCaps = $capability->all();
```

### 内置角色和权限

**admin 管理员**：

- `manage_options` - 管理选项
- `manage_users` - 管理用户
- `manage_plugins` - 管理插件
- `manage_widgets` - 管理组件
- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**editor 编辑**：

- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**author 作者**：

- `edit_own_posts` - 编辑自己的文章
- `delete_own_posts` - 删除自己的文章
- `publish_own_posts` - 发布自己的文章

**user 用户**：

- `read` - 阅读

## 钩子系统

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

### 内置钩子

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

## 验证码

```php
// 生成返回 base64 图片的验证码
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
- 仅生成 0-9 数字验证码
- 包含干扰线和干扰点
- 支持文字旋转效果
- 验证码存储在 session 中

---

[← 返回文档首页](../README.md)

