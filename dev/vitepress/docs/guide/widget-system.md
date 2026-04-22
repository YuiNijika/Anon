# Widget 组件系统

Widget 组件系统允许开发者创建可复用的 UI 组件，用于在页面中动态渲染内容。

## 快速开始

### 注册 Widget

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

$widget->register('my_widget', '我的组件', function($args) {
    return '<div class="my-widget">Hello World</div>';
}, [
    'description' => '示例 Widget 组件',
    'class' => 'custom-widget'
]);
```

### 渲染 Widget

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

// 渲染 HTML
$output = $widget->render('my_widget', ['title' => '标题']);

// 获取数据
$data = $widget->getData('my_widget');

// 获取 JSON 格式
$json = $widget->getJson('my_widget');
```

## Widget 结构

### 基本参数

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | string | Widget 唯一标识符 |
| `title` | string | Widget 标题 |
| `callback` | callable | 渲染回调函数 |
| `options` | array | 配置选项 |

### Options 配置

```php
[
    'description' => '组件描述',
    'class' => '自定义 CSS 类名',
    'icon' => '图标',
    'cache' => [
        'enabled' => true,
        'time' => 3600,
    ],
]
```

## 完整示例

### 示例 1：简单文本 Widget

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Http\RequestHelper;

$widget = Widget::getInstance();

$widget->register('welcome', '欢迎信息', function($args) {
    $user = RequestHelper::requireAuth();
    return '<div class="welcome">欢迎，' . htmlspecialchars($user['username']) . '</div>';
}, [
    'description' => '显示用户欢迎信息',
    'cache' => [
        'enabled' => false,
    ],
]);
```

### 示例 2：数据统计 Widget

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Database\Database;

$widget = Widget::getInstance();

$widget->register('site_stats', '站点统计', function($args) {
    $db = Database::getInstance();
    
    $stats = [
        'users' => $db->db('users')->count(),
        'posts' => $db->db('posts')->count(),
        'comments' => $db->db('comments')->count(),
    ];
    
    return <<<HTML
<div class="site-stats">
    <div class="stat-item">
        <span class="label">用户</span>
        <span class="value">{$stats['users']}</span>
    </div>
    <div class="stat-item">
        <span class="label">文章</span>
        <span class="value">{$stats['posts']}</span>
    </div>
    <div class="stat-item">
        <span class="label">评论</span>
        <span class="value">{$stats['comments']}</span>
    </div>
</div>
HTML;
}, [
    'description' => '显示站点统计数据',
    'cache' => [
        'enabled' => true,
        'time' => 300, // 缓存 5 分钟
    ],
]);
```

### 示例 3：带参数的 Widget

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Database\Database;

$widget = Widget::getInstance();

$widget->register('recent_posts', '最新文章', function($args) {
    $limit = $args['limit'] ?? 5;
    $category = $args['category'] ?? null;
    
    $db = Database::getInstance();
    $query = $db->db('posts')->order('created_at', 'DESC');
    
    if ($category) {
        $query->where('category_id', '=', $category);
    }
    
    $posts = $query->limit($limit)->all();
    
    $html = '<ul class="recent-posts">';
    foreach ($posts as $post) {
        $html .= '<li><a href="/post/' . $post['id'] . '">' . 
                 htmlspecialchars($post['title']) . '</a></li>';
    }
    $html .= '</ul>';
    
    return $html;
}, [
    'description' => '显示最新文章列表',
    'cache' => [
        'enabled' => true,
        'time' => 600,
    ],
]);

// 使用
echo $widget->render('recent_posts', ['limit' => 10, 'category' => 3]);
```

### 示例 4：用户信息 Widget

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Database\Database;

$widget = Widget::getInstance();

$widget->register('user_profile', '用户资料', function($args) {
    $userId = $args['user_id'] ?? null;
    
    if (!$userId) {
        return '<div class="error">请提供用户 ID</div>';
    }
    
    $db = Database::getInstance();
    $user = $db->db('users')
        ->where('id', '=', $userId)
        ->first();
    
    if (!$user) {
        return '<div class="error">用户不存在</div>';
    }
    
    return <<<HTML
<div class="user-profile">
    <img src="{$user['avatar']}" alt="头像" class="avatar">
    <h3>{$user['username']}</h3>
    <p class="bio">{$user['bio']}</p>
    <div class="meta">
        <span>文章：{$user['post_count']}</span>
        <span>关注：{$user['following_count']}</span>
    </div>
</div>
HTML;
}, [
    'description' => '显示用户资料卡片',
    'cache' => [
        'enabled' => true,
        'time' => 1800,
    ],
]);
```

## 使用方法

### 1. 直接渲染

```php
<?php
use Anon\Modules\System\Widget;

echo Widget::getInstance()->render('widget_id', $args);
```

### 2. 获取数据

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

// 获取原始数据
$data = $widget->getData('widget_id', $args);

// 获取 JSON 格式
$json = $widget->getJson('widget_id', $args);
```

### 3. 检查是否存在

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

if ($widget->exists('my_widget')) {
    echo $widget->render('my_widget');
}
```

### 4. 获取 Widget 信息

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();
$info = $widget->getInfo('my_widget');
// 返回：['id' => 'my_widget', 'title' => '标题', 'description' => '描述', ...]
```

## Widget 管理

### 获取所有 Widget

```php
<?php
use Anon\Modules\System\Widget;

$widgets = Widget::getInstance()->getAll();

foreach ($widgets as $id => $widget) {
    echo "ID: {$id}, 标题：{$widget['title']}<br>";
}
```

### 注销 Widget

```php
<?php
use Anon\Modules\System\Widget;

Widget::getInstance()->unregister('widget_id');
```

### 清空所有 Widget

```php
<?php
use Anon\Modules\System\Widget;

Widget::getInstance()->clear();
```

## 高级用法

### 在主题中使用

```php
<?php
// functions.php 或 sidebar.php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

// 注册侧边栏 Widget
$widget->register('sidebar_ads', '广告位', function($args) {
    return '<div class="ads">广告内容</div>';
});

// 在模板中渲染
?>
<aside class="sidebar">
    <?php echo $widget->render('sidebar_ads'); ?>
</aside>
```

### 在插件中使用

```php
<?php
namespace Anon\Modules\System\Plugin;

use Anon\Modules\System\Widget;

// 插件初始化时注册 Widget
class MyPlugin extends Base
{
    public static function init()
    {
        $widget = Widget::getInstance();
        
        $widget->register('plugin_widget', '插件组件', function($args) {
            // 插件业务逻辑
            return '<div>插件内容</div>';
        });
    }
}
```

### 缓存控制

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

$widget->register('cached_widget', '缓存组件', function($args) {
    // 耗时操作
    return $data;
}, [
    'cache' => [
        'enabled' => true,
        'time' => 3600,      // 缓存 1 小时
        'key' => 'custom_key' // 自定义缓存键
    ]
]);
```

### 条件渲染

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Http\RequestHelper;

$widget = Widget::getInstance();

$widget->register('conditional_widget', '条件组件', function($args) {
    if (RequestHelper::isUserLoggedIn()) {
        return '<div>登录用户可见</div>';
    }
    return '<div>游客可见</div>';
});
```

## API 参考

### register()

注册 Widget 组件。

```php
public function register(string $id, string $title, callable $callback, array $options = [])
```

**参数：**
- `id`: Widget 唯一标识符
- `title`: Widget 标题
- `callback`: 渲染回调函数
- `options`: 配置选项

### render()

渲染 Widget 并返回 HTML。

```php
public function render(string $id, array $args = []): string
```

### getData()

获取 Widget 数据（不渲染 HTML）。

```php
public function getData(string $id, array $args = [])
```

### getJson()

获取 Widget 的 JSON 格式数据。

```php
public function getJson(string $id, array $args = []): string
```

### exists()

检查 Widget 是否存在。

```php
public function exists(string $id): bool
```

### unregister()

注销 Widget。

```php
public function unregister(string $id): void
```

### getAll()

获取所有已注册的 Widget。

```php
public function getAll(): array
```

### getInfo()

获取 Widget 详细信息。

```php
public function getInfo(string $id): array
```

### clear()

清空所有 Widget。

```php
public function clear(): void
```

## 最佳实践

### 1. 合理使用缓存

对耗时操作启用缓存：

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

// ✅ 正确：数据库查询启用缓存
$widget->register('stats', '统计', function() {
    // 耗时的数据库查询
    return $data;
}, ['cache' => ['enabled' => true, 'time' => 300]]);

// ❌ 错误：简单字符串也启用缓存
$widget->register('hello', '问候', function() {
    return 'Hello World';
}, ['cache' => ['enabled' => true, 'time' => 300]]);
```

### 2. 参数验证

在回调函数中验证参数：

```php
<?php
use Anon\Modules\System\Widget;

$widget = Widget::getInstance();

$widget->register('user_info', '用户信息', function($args) {
    if (!isset($args['user_id'])) {
        return '<div class="error">缺少用户 ID</div>';
    }
    
    $userId = (int)$args['user_id'];
    if ($userId <= 0) {
        return '<div class="error">无效的用户 ID</div>';
    }
    
    // 正常逻辑
    return $userInfo;
});
```

### 3. 错误处理

捕获可能的异常：

```php
<?php
use Anon\Modules\System\Widget;
use Anon\Modules\Debug;
use Throwable;

$widget = Widget::getInstance();

$widget->register('safe_widget', '安全组件', function($args) {
    try {
        // 业务逻辑
        return $result;
    } catch (Throwable $e) {
        Debug::error('Widget error', ['error' => $e->getMessage()]);
        return '<div class="error">组件加载失败</div>';
    }
});
```

### 4. 命名规范

使用有意义的 ID 和标题：

```php
// ✅ 正确
$widget->register('recent_comments', '最新评论', $callback);

// ❌ 错误
$widget->register('widget1', '组件 1', $callback);
```

## 相关文档

- [钩子系统](hook-system.md) - 钩子与事件系统
- [插件系统](cms/plugin-system.md) - 插件开发指南
- [主题开发](cms/theme-system.md) - 主题开发指南
