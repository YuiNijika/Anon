# 高级功能

## Widget 组件系统

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

// 要求权限（无权限则返回 403）
$capability->requireCapability('manage_options');

// 添加/移除权限
$capability->addCapability('editor', 'custom_permission');
$capability->removeCapability('admin', 'manage_plugins');

// 获取权限列表
$adminCaps = $capability->getCaps('admin');
$allCaps = $capability->all();
```

### 内置角色和权限

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

---

[← 返回文档首页](../README.md)

