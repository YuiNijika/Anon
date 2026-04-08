# 插件系统

插件系统提供灵活的扩展机制，允许开发者在不修改核心代码的情况下扩展框架功能。支持 CMS 和 API 双模式运行。

## 快速开始

### 创建第一个插件

在 `app/Plugin/` 目录下创建插件：

```
app/Plugin/
└── HelloWorld/
    ├── Index.php       # 主入口文件
    └── package.json    # 元数据（可选）
```

**Index.php**:

```php
<?php
/**
 * Name: HelloWorld
 * Description: Hello World 插件
 * Mode: auto
 * Version: 1.0.0
 * Author: Your Name
 * URI: https://example.com
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_HelloWorld
{
    public static function init()
    {
        // 注册路由
        Anon::route('/hello', function() {
            Anon::success(['message' => 'Hello World!']);
        }, [
            'method' => 'GET',
            'token' => false,
        ]);
    }
}
```

### 启用插件

在 `app/useApp.php` 中配置：

```php
'plugins' => [
    'enabled' => true,
    'active' => [
        'HelloWorld',  // 激活的插件列表
    ],
],
```

## 插件结构

### 目录结构

```
app/Plugin/
└── PluginName/
    ├── Index.php           # 主入口（必需）
    ├── package.json        # 元数据（可选）
    ├── app/
    │   ├── mode/
    │   │   ├── api.php     # API 模式业务逻辑
    │   │   └── cms.php     # CMS 模式业务逻辑
    │   ├── pages.php       # 管理页面配置
    │   └── setup.php       # 设置项配置
    └── assets/             # 静态资源
        ├── css/
        └── js/
```

### 主入口文件

**基本结构**：

```php
<?php
/**
 * Name: 插件名称
 * Description: 插件描述
 * Mode: auto
 * Version: 1.0.0
 * Author: 作者名
 * URI: 主页链接
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_PluginName
{
    /**
     * 初始化方法（必需）
     */
    public static function init()
    {
        // 初始化逻辑
    }
    
    /**
     * 激活时调用（可选）
     */
    public static function activate()
    {
        // 激活逻辑
    }
    
    /**
     * 停用时调用（可选）
     */
    public static function deactivate()
    {
        // 停用逻辑
    }
}
```

### 元数据格式

#### package.json 方式（推荐）

```json
{
  "name": "PluginName",
  "description": "插件描述",
  "version": "1.0.0",
  "author": "作者名",
  "url": "https://example.com",
  "anon": {
    "mode": "auto"
  }
}
```

#### 文件注释方式

```php
/**
 * Name: PluginName
 * Description: 插件描述
 * Mode: auto
 * Version: 1.0.0
 * Author: 作者名
 * URI: https://example.com
 */
```

## 运行模式

### Mode 配置说明

| 模式 | 说明 | 加载时机 |
|------|------|----------|
| `api` | 仅在 API 模式下加载 | ANON_APP_MODE === 'api' |
| `cms` | 仅在 CMS 模式下加载 | ANON_APP_MODE === 'cms' |
| `auto` | 在所有模式下都加载 | 总是加载 |

### 判断当前模式

```php
class Anon_Plugin_MyPlugin
{
    public static function init()
    {
        if (Anon_System_Plugin::isApiMode()) {
            // API 模式下的初始化
            self::initApi();
        } elseif (Anon_System_Plugin::isCmsMode()) {
            // CMS 模式下的初始化
            self::initCms();
        }
    }
    
    private static function initApi()
    {
        // API 路由注册
        Anon::route('/api/plugin/data', [...]);
    }
    
    private static function initCms()
    {
        // CMS 管理页面、钩子等
        Anon::filter('admin_navbar_sidebar', [...]);
    }
}
```

### 自动加载模式文件

使用高级结构自动加载：

```php
<?php
class Anon_Plugin_MyPlugin extends Anon_Plugin_Base
{
    // 基类会自动加载 app/mode/ 下的对应文件
}
```

**app/mode/api.php**:

```php
<?php
$plugin = $this;

Anon::route('/api/data', function() use ($plugin) {
    // API 业务逻辑
    return ['data' => 'API response'];
});
```

**app/mode/cms.php**:

```php
<?php
$plugin = $this;

// CMS 管理页面
Anon::filter('admin_navbar_sidebar', function($items) use ($plugin) {
    $items[] = [
        'key' => 'my-plugin',
        'label' => '我的插件',
        'icon' => 'StarOutlined',
        'children' => [
            [
                'key' => '/pages?plugin=my-plugin:settings',
                'label' => '设置',
            ]
        ]
    ];
    return $items;
});
```

## 中间件扩展

插件可以通过 `registerMiddleware()` 方法注册中间件：

### 基本用法

```php
class Anon_Plugin_MyPlugin
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'myMiddleware',
            [self::class, 'handle'],
            10,
            ['hooks' => ['request.start']]
        );
    }
    
    public static function handle($data)
    {
        // 中间件逻辑
        header('X-Custom-Header: MyPlugin');
        return $data;
    }
}
```

### 注册多个中间件

```php
public static function registerMiddleware()
{
    // CORS 中间件（高优先级）
    Anon_System_Extension::register(
        'cors',
        [CorsMiddleware::class, 'handle'],
        1,
        ['hooks' => ['request.start']]
    );
    
    // 日志中间件（低优先级）
    Anon_System_Extension::register(
        'logger',
        [LoggerMiddleware::class, 'handle'],
        100,
        ['hooks' => ['request.end']]
    );
}
```

详见：[中间件扩展系统](extension-system.md)

## Widget 组件

插件可以注册可复用的 UI 组件：

### 注册 Widget

```php
class Anon_Plugin_SidebarWidget
{
    public static function init()
    {
        $widget = Anon_System_Widget::getInstance();
        
        $widget->register('my_widget', '我的组件', function($args) {
            return '<div class="widget">内容</div>';
        });
    }
}
```

详见：[Widget 组件系统](widget-system.md)

## 钩子系统

### 添加动作钩子

```php
Anon_System_Hook::add_action('user_login', function($userId) {
    // 用户登录后的操作
    Anon_Debug::info("用户 {$userId} 登录");
});
```

### 添加过滤器钩子

```php
Anon_System_Hook::add_filter('post_content', function($content) {
    // 修改文章内容
    return $content . '<p>附加内容</p>';
});
```

### 执行钩子

```php
// 执行动作
Anon_System_Hook::do_action('custom_action', $param1, $param2);

// 应用过滤器
$value = Anon_System_Hook::apply_filters('custom_filter', $defaultValue);
```

## 路由注册

### 基本路由

```php
Anon::route('/api/plugin/endpoint', function() {
    Anon::success(['data' => 'response']);
}, [
    'method' => 'POST',
    'requireLogin' => true,
    'token' => true,
]);
```

### 带参数的路由

```php
Anon::route('/api/post/{id}', function($id) {
    $db = Anon_Database::getInstance();
    $post = $db->db('posts')->where('id', '=', $id)->first();
    
    if ($post) {
        Anon::success($post);
    } else {
        Anon::error('文章不存在', 404);
    }
}, [
    'method' => 'GET',
]);
```

### 路由元数据

```php
Anon::route('/secure/data', $handler, [
    'header' => true,              // 设置响应头
    'requireLogin' => true,        // 需要登录
    'method' => ['POST', 'PUT'],   // 允许的 HTTP 方法
    'token' => true,               // 需要 Token 验证
    'cache' => [                   // 缓存配置
        'enabled' => true,
        'time' => 3600,
    ],
]);
```

## 插件设置

### 定义设置 Schema

```php
class Anon_Plugin_MyPlugin
{
    public static function getSettingsSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'API 密钥',
                'default' => '',
                'required' => true,
            ],
            'enable_feature' => [
                'type' => 'checkbox',
                'label' => '启用功能',
                'default' => true,
            ],
            'max_items' => [
                'type' => 'number',
                'label' => '最大数量',
                'default' => 10,
                'min' => 1,
                'max' => 100,
            ],
        ];
    }
}
```

### 读取设置

```php
// 从 options 表读取
$options = Anon_System_Plugin::getPluginOptions('my-plugin');
$apiKey = $options['api_key'] ?? '';

// 或使用代理（支持优先级）
class Anon_Plugin_MyPlugin extends Anon_Plugin_Base
{
    public function init()
    {
        $plugin = $this;
        $apiKey = $plugin->options()->get('api_key', '');
    }
}
```

### 保存设置

```php
// 直接保存
Anon_Cms_Options::set('plugin:my-plugin', [
    'api_key' => 'new-key',
    'enable_feature' => true,
]);

// 或使用代理
$plugin->options()->set('api_key', 'new-key');
```

## 自定义管理页面

### 页面配置

在 `app/pages.php` 中定义：

```php
<?php
return [
    'settings' => [
        'title' => '插件设置',
        'content' => <<<HTML
<div class="max-w-2xl mx-auto p-6">
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-2">API 密钥</label>
            <input type="text" id="api_key" class="w-full px-3 py-2 border rounded-lg" />
        </div>
        <button onclick="saveSettings()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
            保存
        </button>
    </div>
</div>
<script>
function saveSettings() {
    const apiKey = document.getElementById('api_key').value;
    // 保存逻辑
}
</script>
HTML,
        'handler' => function($action, $data) {
            if ($action === 'save') {
                // 后端处理逻辑
                return ['success' => true];
            }
            return null;
        }
    ]
];
```

### 访问页面

```
/#/pages?plugin=plugin-slug:page-slug
```

例如：`/#/pages?plugin=my-plugin:settings`

## 管理后台菜单

### 添加顶级菜单

```php
Anon::filter('admin_navbar_sidebar', function($items) {
    $items[] = [
        'key' => 'my-plugin',
        'icon' => 'StarOutlined',
        'label' => '我的插件',
        'children' => [
            [
                'key' => '/pages?plugin=my-plugin:settings',
                'label' => '设置',
            ],
            [
                'key' => '/pages?plugin=my-plugin:data',
                'label' => '数据管理',
            ],
        ],
    ];
    return $items;
});
```

### 挂载到现有菜单

```php
Anon::filter('admin_navbar_sidebar', function($items) {
    Anon_Cms_Admin_UI_Navbar::mount($items, 'manage', [
        'key' => '/pages?plugin=my-plugin:config',
        'label' => '插件配置',
        'icon' => 'SettingOutlined',
    ]);
    return $items;
});
```

## 数据库操作

### 创建表

```php
class Anon_Plugin_MyPlugin
{
    public static function activate()
    {
        $db = Anon_Database::getInstance();
        
        if (!$db->tableExists('plugin_my_table')) {
            $db->createTable('plugin_my_table', [
                'id' => [
                    'type' => 'INT',
                    'null' => false,
                    'primary' => true,
                    'key' => 'PRI',
                    'auto_increment' => true,
                ],
                'name' => [
                    'type' => 'VARCHAR(255)',
                    'null' => false,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
        }
    }
}
```

### CRUD 操作

```php
$db = Anon_Database::getInstance();

// 插入
$id = $db->db('plugin_my_table')->insert([
    'name' => '测试数据',
    'created_at' => date('Y-m-d H:i:s'),
]);

// 查询
$data = $db->db('plugin_my_table')
    ->where('id', '=', $id)
    ->first();

// 更新
$db->db('plugin_my_table')
    ->where('id', '=', $id)
    ->update(['name' => '新名称']);

// 删除
$db->db('plugin_my_table')
    ->where('id', '=', $id)
    ->delete();
```

## CLI 命令

插件可以注册命令行工具：

```php
class Anon_Plugin_MyPlugin
{
    public static function init()
    {
        Anon_System_Console::command('my-plugin:task', function($args) {
            Anon_System_Console::info('执行任务...');
            
            // 任务逻辑
            
            Anon_System_Console::success('任务完成');
            return 0;
        }, '我的插件任务');
    }
}
```

详见：[CLI 命令系统](cli-system.md)

## 插件生命周期

### 1. 扫描阶段

系统扫描 `app/Plugin/` 目录，查找所有插件。

### 2. 加载阶段

- 读取插件元数据
- 检查激活状态
- 加载主入口文件

### 3. 初始化阶段

- 调用 `init()` 方法
- 注册路由、钩子、中间件
- 执行初始化逻辑

### 4. 激活/停用

- **激活**：调用 `activate()` 方法
- **停用**：调用 `deactivate()` 方法

### 5. 重新加载

```php
// 重新加载单个插件
Anon_System_Plugin::reloadPlugin('my-plugin');

// 清除扫描缓存
Anon_System_Plugin::clearScanCache();
```

## 插件系统钩子

框架为插件系统提供的钩子：

```php
// 插件系统初始化前
Anon_System_Hook::add_action('plugin_system_before_init', function() {
    // ...
});

// 插件系统初始化后
Anon_System_Hook::add_action('plugin_system_after_init', function($loadedPlugins) {
    // ...
});

// 插件扫描前
Anon_System_Hook::add_action('plugin_before_scan', function() {
    // ...
});

// 插件扫描后
Anon_System_Hook::add_action('plugin_after_scan', function() {
    // ...
});

// 插件加载前
Anon_System_Hook::add_action('plugin_before_load', function($slug, $meta) {
    // ...
});

// 插件加载后
Anon_System_Hook::add_action('plugin_after_load', function($slug, $meta) {
    // ...
});

// 插件加载错误
Anon_System_Hook::add_action('plugin_load_error', function($slug, $e) {
    // ...
});
```

## 最佳实践

### 1. 命名规范

```php
// ✅ 正确：清晰的命名
class Anon_Plugin_UserAnalytics
{
    public static function init() { }
}

// ❌ 错误：模糊的命名
class Anon_Plugin_UA
{
    public static function init() { }
}
```

### 2. 错误处理

```php
public static function init()
{
    try {
        // 初始化逻辑
    } catch (Throwable $e) {
        Anon_Debug::error('Plugin init failed', [
            'plugin' => self::class,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### 3. 性能优化

```php
// ✅ 正确：使用缓存
public static function getData()
{
    $cached = Anon_Cache::get('plugin_data');
    if ($cached !== null) {
        return $cached;
    }
    
    $data = self::fetchData(); // 耗时操作
    Anon_Cache::set('plugin_data', $data, 3600);
    
    return $data;
}
```

### 4. 安全检查

```php
// 验证用户输入
$data = Anon_Http_Request::validate([
    'username' => '用户名不能为空',
    'email' => '邮箱格式不正确',
]);

// 权限检查
$user = Anon_Http_Request::requireAuth();
if (!$user['is_admin']) {
    Anon::error('无权访问', 403);
}
```

## 短代码系统

插件可以注册短代码，在内容中嵌入 React 组件。

### 默认短代码

系统已默认注册以下短代码：

| 短代码 | 说明 | 示例 |
|--------|------|------|
| `[Anon_Plugin_Editor]` | Markdown 编辑器 | `[Anon_Plugin_Editor placeholder="输入..." height="400px"]` |
| `[Anon_Plugin_Gallery]` | 图片画廊 | `[Anon_Plugin_Gallery images="url1,url2" columns="3"]` |
| `[Anon_Plugin_Alert]` | 警告框 | `[Anon_Plugin_Alert type="success" title="提示"]` |

### 扩展短代码

通过钩子注册自定义短代码：

```php
<?php
// 监听短代码注册钩子
Anon_System_Hook::add_action('anon_register_shortcodes', function() {
    // 注册代码编辑器
    Anon_System_Shortcode::add_shortcode('Anon_Code', function($attrs) {
        return Anon_System_Shortcode::render_react_component('CodeEditor', [
            'language' => $attrs['language'] ?? 'javascript',
            'height' => $attrs['height'] ?? '300px',
        ]);
    });
});
```

### 前端注册组件

在 `dev_cms_admin/src/components/ReactComponents/index.ts` 中注册：

```typescript
import { registerReactComponent } from '@/hooks/useReactComponents'
import { CodeEditor } from './CodeEditor'

export function registerAllComponents() {
  // ... 现有组件
  registerReactComponent('CodeEditor', CodeEditor)
}
```

详见：[短代码系统](plugin-shortcode-system.md)

## 相关文档

- [中间件扩展系统](extension-system.md) - 中间件注册与管理
- [Widget 组件系统](widget-system.md) - UI 组件开发
- [钩子系统](hook-system.md) - 钩子与事件
- [短代码系统](shortcode-system.md) - React 组件集成
- [CLI 命令系统](cli-system.md) - 命令行工具
- [配置管理](configuration-management.md) - 插件配置存储
