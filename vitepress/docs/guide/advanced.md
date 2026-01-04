# 高级功能

一句话：Widget组件系统、用户权限系统、钩子系统、验证码等高级功能。

## Widget 组件系统

支持两种模式：HTML输出模式和JSON API模式。

### HTML输出模式

```php
$widget = Anon_Widget::getInstance();

// 注册HTML组件
$widget->register('my_widget', '我的组件', function($args) {
    echo '<div>' . Anon_Helper::escHtml($args['title'] ?? '') . '</div>';
}, [
    'description' => '这是一个示例组件',
    'class' => 'custom-widget'
], 'html'); // 指定为HTML模式

// 渲染组件返回HTML字符串
$output = $widget->render('my_widget', ['title' => '标题']);
```

### JSON API模式（推荐）

```php
$widget = Anon_Widget::getInstance();

// 注册回调函数返回数组或对象的JSON API组件
$widget->register('user_stats', '用户统计', function($args) {
    $db = Anon_Database::getInstance();
    $userId = $args['user_id'] ?? 0;
    
    return [
        'user_id' => $userId,
        'total_posts' => $db->count('posts', ['author_id' => $userId]),
        'total_comments' => $db->count('comments', ['user_id' => $userId]),
        'last_login' => date('Y-m-d H:i:s'),
    ];
}, [
    'description' => '获取用户统计数据'
], 'json'); // 指定为JSON模式

// 获取组件数据返回数组
$data = $widget->getData('user_stats', ['user_id' => 1]);
// 返回: ['user_id' => 1, 'total_posts' => 10, ...]

// 获取组件JSON字符串
$json = $widget->getJson('user_stats', ['user_id' => 1]);
// 返回: '{"user_id":1,"total_posts":10,...}'

// 在API路由中使用
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
$widget->register('auto_widget', '自动检测组件', function($args) {
    // 如果返回数组/对象，自动识别为JSON模式
    return ['status' => 'ok', 'data' => $args];
    // 如果使用echo输出，自动识别为HTML模式
}, [], 'auto'); // 默认为auto
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

## 用户权限系统

### 基本使用

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

// 要求权限，无权限则返回403
$capability->requireCapability('manage_options');
```

### 权限管理

```php
$capability = Anon_Capability::getInstance();

// 添加权限
$capability->addCapability('editor', 'custom_permission');

// 移除权限
$capability->removeCapability('admin', 'manage_plugins');

// 获取权限列表
$adminCaps = $capability->getCaps('admin');
$allCaps = $capability->all();
```

### 内置角色和权限

**admin 管理员：**

- `manage_options` - 管理选项
- `manage_users` - 管理用户
- `manage_plugins` - 管理插件
- `manage_widgets` - 管理组件
- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**editor 编辑：**

- `edit_posts` - 编辑文章
- `delete_posts` - 删除文章
- `publish_posts` - 发布文章

**author 作者：**

- `edit_own_posts` - 编辑自己的文章
- `delete_own_posts` - 删除自己的文章
- `publish_own_posts` - 发布自己的文章

**user 用户：**

- `read` - 阅读

## 钩子系统

### 动作钩子

```php
// 添加动作钩子
Anon_Hook::add_action('user_login', function($user) {
    // 用户登录后执行
}, 10, 1);
// 参数：钩子名，回调函数，优先级默认10，接受参数数量默认1

// 执行动作钩子
Anon_Hook::do_action('user_login', $user);
Anon_Hook::do_action('user_login', $user, $timestamp); // 多个参数
```

### 过滤器钩子

```php
// 添加过滤器钩子
Anon_Hook::add_filter('content_filter', function($content) {
    return str_replace('bad', '***', $content);
}, 10, 1);

// 应用过滤器
$filtered = Anon_Hook::apply_filters('content_filter', $content);
$filtered = Anon_Hook::apply_filters('content_filter', $content, $arg1, $arg2);
```

### 钩子管理

```php
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

## 验证码

### 基本使用

```php
// 生成返回base64图片的验证码
$result = Anon_Captcha::generate();
// 返回: ['image' => 'data:image/svg+xml;base64,...', 'code' => '1234']

// 验证用户输入的验证码
$isValid = Anon_Captcha::verify($userInput);

// 清除验证码
Anon_Captcha::clear();
```

### 完整方法列表

```php
// 生成验证码
$result = Anon_Captcha::generate();
// 返回: ['image' => 'data:image/svg+xml;base64,...', 'code' => '1234']

// 验证验证码
$isValid = Anon_Captcha::verify($code);
// 返回: bool

// 清除验证码
Anon_Captcha::clear();

// 检查是否启用
$enabled = Anon_Captcha::isEnabled();
```

