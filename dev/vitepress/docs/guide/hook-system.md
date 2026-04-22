# 钩子系统

Anon Framework 提供了类似 WordPress 的钩子系统（Hook System），允许你在特定的执行点插入自定义代码，实现功能的扩展和修改。

## 概述

钩子系统包含两种类型的钩子：

- **动作钩子（Action）**：在特定时刻执行操作，不返回值
- **过滤器钩子（Filter）**：修改数据并返回修改后的值

## 基本用法

### 注册动作钩子

使用 `add_action()` 注册一个动作钩子：

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('hook_name', function($arg1, $arg2) {
    // 执行某些操作
    echo "Hook triggered with: $arg1, $arg2";
}, 10, 2);
```

**参数说明：**
- `$hook_name`：钩子名称
- `$callback`：回调函数
- `$priority`：优先级（数字越小越先执行，默认 10）
- `$accepted_args`：接受的参数数量（默认 1）

### 触发动作钩子

使用 `do_action()` 触发所有注册的动作：

```php
<?php
use Anon\Modules\System\Hook;

Hook::do_action('hook_name', 'value1', 'value2');
```

### 注册过滤器钩子

使用 `add_filter()` 注册一个过滤器钩子：

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_filter('filter_name', function($content) {
    // 修改内容
    return strtoupper($content);
}, 10, 1);
```

### 应用过滤器钩子

使用 `apply_filters()` 应用所有注册的过滤器：

```php
<?php
use Anon\Modules\System\Hook;

$result = Hook::apply_filters('filter_name', 'original value');
// 返回: 'ORIGINAL VALUE'
```

## 内置钩子

### 主题钩子

#### theme_head

在页面 `<head>` 标签内触发，用于添加额外的 meta 标签、样式表或脚本。

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('theme_head', function() {
    echo '<meta name="custom" content="value">';
});
```

#### theme_foot

在页面底部触发，用于添加脚本或其他内容。

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('theme_foot', function() {
    echo '<script>console.log("Page loaded");</script>';
});
```

### CMS 钩子

#### cms_post_saved

文章保存后触发。

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('cms_post_saved', function($postId, $postData) {
    // 文章保存后的处理逻辑
    error_log("Post saved: {$postId}");
}, 10, 2);
```

#### cms_comment_approved

评论审核通过后触发。

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('cms_comment_approved', function($commentId) {
    // 发送通知等
    sendNotification($commentId);
}, 10, 1);
```

### 系统钩子

#### system_initialized

系统初始化完成后触发。

```php
<?php
use Anon\Modules\System\Hook;

Hook::add_action('system_initialized', function() {
    // 系统初始化后的自定义逻辑
});
```

## 高级用法

### 优先级控制

通过设置不同的优先级来控制钩子的执行顺序：

```php
<?php
use Anon\Modules\System\Hook;

// 优先级 5，先执行
Hook::add_action('my_hook', function() {
    echo "First";
}, 5);

// 优先级 10，后执行（默认）
Hook::add_action('my_hook', function() {
    echo "Second";
}, 10);

// 优先级 15，最后执行
Hook::add_action('my_hook', function() {
    echo "Third";
}, 15);
```

### 移除钩子

使用 `removeHook()` 移除已注册的钩子：

```php
<?php
use Anon\Modules\System\Hook;

// 定义回调函数
function my_callback() {
    echo "Hello";
}

// 注册钩子
Hook::add_action('my_hook', 'my_callback', 10);

// 移除钩子
Hook::removeHook('my_hook', 'my_callback', 10);
```

### 条件注册钩子

根据条件动态注册钩子：

```php
<?php
use Anon\Modules\System\Hook;
use Anon\Modules\Http\RequestHelper;

if (RequestHelper::isAdmin()) {
    Hook::add_action('admin_init', function() {
        // 仅在后台执行
        loadAdminAssets();
    });
}
```

### 类方法作为回调

可以使用类的方法作为钩子回调：

```php
<?php
namespace MyPlugin;

use Anon\Modules\System\Hook;

class MyPlugin
{
    public function onInit() {
        // 初始化逻辑
    }
    
    public function filterContent($content) {
        return $content . ' [Modified]';
    }
}

$plugin = new MyPlugin();

// 注册动作
Hook::add_action('system_initialized', [$plugin, 'onInit']);

// 注册过滤器
Hook::add_filter('the_content', [$plugin, 'filterContent']);
```

## 最佳实践

### 1. 使用命名空间避免冲突

```php
<?php
namespace MyPlugin;

use Anon\Modules\System\Hook;

Hook::add_action('theme_foot', __NAMESPACE__ . '\outputScript');

function outputScript() {
    echo '<script src="/path/to/script.js"></script>';
}
```

### 2. 合理设置优先级

- 默认优先级为 10
- 需要优先执行的设置为 1-9
- 需要延后执行的设置为 11-20
- 避免使用极端值（如 0 或 999）

### 3. 限制参数数量

只接受需要的参数数量，提高性能：

```php
<?php
use Anon\Modules\System\Hook;

// 只需要 1 个参数
Hook::add_action('my_hook', function($arg1) {
    // ...
}, 10, 1);
```

### 4. 避免在钩子中执行耗时操作

钩子会在每次触发时执行，应避免在其中执行数据库查询或文件 I/O 等耗时操作：

```php
<?php
use Anon\Modules\System\Hook;
use Anon\Modules\System\Cache;

// ❌ 不推荐
Hook::add_action('theme_head', function() {
    $data = file_get_contents('large-file.json'); // 耗时操作
    echo $data;
});

// ✅ 推荐：使用缓存
Hook::add_action('theme_head', function() {
    $cache = Cache::get('my_data');
    if (!$cache) {
        $cache = file_get_contents('large-file.json');
        Cache::set('my_data', $cache, 3600);
    }
    echo $cache;
});
```

### 5. 提供钩子供他人扩展

在你的插件或主题中提供钩子，让其他人可以扩展功能：

```php
<?php
use Anon\Modules\System\Hook;

// 在你的插件中
function renderWidget($widgetId) {
    // 允许其他人在渲染前修改配置
    $config = Hook::apply_filters('widget_config', getDefaultConfig(), $widgetId);
    
    // 渲染前触发
    Hook::do_action('before_widget_render', $widgetId, $config);
    
    // 渲染逻辑
    $html = render($config);
    
    // 渲染后触发
    Hook::do_action('after_widget_render', $widgetId, $html);
    
    return $html;
}
```

## 调试钩子

### 查看已注册的钩子

```php
<?php
use Anon\Modules\System\Hook;

// 获取所有注册的钩子
$hooks = Hook::getAllHooks();
print_r($hooks);

// 获取特定钩子的所有回调
$callbacks = Hook::getHooksByPriority('theme_foot');
print_r($callbacks);
```

### 记录钩子执行

```php
<?php
use Anon\Modules\System\Hook;
use Anon\Modules\Debug;

Hook::add_action('all', function($hook_name) {
    Debug::info("Hook executed: {$hook_name}");
});
```

## 与插件系统的关系

钩子系统是插件系统的基础，插件可以通过钩子来：

1. **扩展功能**：在特定时机执行自定义代码
2. **修改行为**：通过过滤器修改框架的默认行为
3. **集成服务**：与其他插件或外部服务集成

示例：

```php
<?php
namespace App\Plugins;

use Anon\Modules\System\Hook;

// 插件 A：提供功能
class PluginA {
    public static function init() {
        Hook::add_action('cms_post_saved', [self::class, 'onPostSaved']);
    }
    
    public static function onPostSaved($postId) {
        // 同步到搜索引擎
        syncToSearchEngine($postId);
    }
}

// 插件 B：扩展功能
class PluginB {
    public static function init() {
        // 在插件 A 之后执行
        Hook::add_action('cms_post_saved', [self::class, 'onPostSaved'], 20);
    }
    
    public static function onPostSaved($postId) {
        // 发送通知
        sendNotification($postId);
    }
}
```

## 相关文档

- [插件系统](./plugin-system.md) - 如何开发插件
- [中间件扩展](./extension-system.md) - 中间件扩展系统
- [Widget 组件](./widget-system.md) - Widget 组件系统
- [短代码系统](./shortcode-system.md) - 短代码解析和执行
