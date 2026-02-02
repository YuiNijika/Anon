# 插件系统

一句话：类似 WordPress 的灵活插件系统，支持插件扫描、加载、激活/停用，通过钩子执行方法。

## 概述

插件系统允许开发者在不修改核心代码的情况下扩展框架功能。插件位于 `server/app/Plugin/` 目录，每个插件都是一个独立的目录。

## 插件结构

### 基本结构

```text
server/app/Plugin/
└── HelloWorld/
    └── Index.php
```

### 插件文件示例

```php
<?php
/**
 * Name: HelloWorld
 * Description: Hello World 插件
 * Mode: auto
 * Version: 1.0.0
 * Author: YuiNijika
 * URI: https://github.com/YuiNijika
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 插件主类，类名不区分大小写
class Anon_Plugin_HelloWorld
{
    /**
     * 插件初始化方法
     */
    public static function init()
    {
        // 判断当前应用模式
        if (Anon_System_Plugin::isApiMode()) {
            // API 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (API Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        } elseif (Anon_System_Plugin::isCmsMode()) {
            // CMS 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (CMS Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        }
    }

    /**
     * 插件激活时调用
     */
    public static function activate()
    {
        Anon_Debug::info('HelloWorld 插件已激活');
    }

    /**
     * 插件停用时调用
     */
    public static function deactivate()
    {
        Anon_Debug::info('HelloWorld 插件已停用');
    }

    /**
     * 自定义方法
     */
    public static function index()
    {
        return 'Hello World';
    }
}
```

## 插件元数据

插件元数据通过文件头注释定义，包含以下字段：

- `Name`: 插件名称，必需
- `Description`: 插件描述
- `Version`: 插件版本
- `Author`: 作者名称
- `URI`: 插件主页或作者主页
- `Mode`: 插件模式，默认为 `api`

系统同时支持新格式和旧格式，优先使用新格式。新格式更简洁，建议使用新格式。

**Mode 模式说明：**

- `api`: 仅在 API 模式下加载
- `cms`: 仅在 CMS 模式下加载
- `auto`: 在所有模式下都加载

**示例：**

```php
/**
 * Name: HelloWorld
 * Description: Hello World 插件
 * Mode: auto
 * Version: 1.0.0
 * Author: YuiNijika
 * URI: https://github.com/YuiNijika
 */
```

## 插件类命名规则

插件类名格式：`Anon_Plugin_{插件名称}`

插件名称从目录名获取，类名不区分大小写，系统会自动匹配。例如目录 `HelloWorld` 对应类名 `Anon_Plugin_HelloWorld`。

## 配置插件系统

在 `server/app/useApp.php` 中配置：

```php
return [
    'app' => [
        'plugins' => [
            'enabled' => true,
            'active' => [],
        ],
        // ... 其他配置
    ]
];
```

### 激活特定插件

```php
'plugins' => [
    'enabled' => true,
    'active' => [
        'HelloWorld',
        'AnotherPlugin',
    ],
],
```

### 激活所有插件

```php
'plugins' => [
    'enabled' => true,
    'active' => [], // 空数组表示激活所有插件
],
```

## 路由注册

插件可以通过 `Anon::route()` 方法注册路由，支持完整的路由元数据配置：

```php
Anon::route('/hello', function () {
    // 路由处理逻辑
}, [
    'header' => true,
    'requireLogin' => false,
    'method' => ['GET'],
    'token' => false,
    'cache' => [
        'enabled' => true,
        'time' => 3600,
    ],
]);
```

### 路由元数据说明

- `header`: 是否设置响应头，默认 `true`
- `requireLogin`: 是否需要登录，默认 `false`
- `method`: 允许的 HTTP 方法，字符串或数组
- `token`: 是否需要 Token 验证，`null` 时使用全局配置
- `cache`: 缓存配置
  - `enabled`: 是否启用缓存
  - `time`: 缓存时间，单位秒

## 插件生命周期

### 1. 扫描阶段

系统扫描 `server/app/Plugin/` 目录，查找所有插件目录。

### 2. 加载阶段

- 读取插件元数据
- 查找插件主类
- 检查插件是否在激活列表中

### 3. 初始化阶段

- 调用插件的 `init()` 方法
- 在 `init()` 方法内可通过 `Anon_System_Plugin::isApiMode()` 或 `Anon_System_Plugin::isCmsMode()` 判断当前模式
- 执行插件注册的路由和钩子

### 4. 激活/停用

- `activate()`: 插件激活时调用
- `deactivate()`: 插件停用时调用

## 钩子系统集成

插件可以使用框架的钩子系统：

```php
public static function init()
{
    // 添加动作钩子
    Anon_System_Hook::add_action('some_action', function() {
        // 执行逻辑
    });

    // 添加过滤器钩子
    Anon_System_Hook::add_filter('some_filter', function($value) {
        return $value . ' modified';
    });
}
```

## 插件系统钩子

框架为插件系统提供了以下钩子：

- `plugin_system_before_init`: 插件系统初始化前
- `plugin_system_after_init`: 插件系统初始化后
- `plugin_before_scan`: 插件扫描前
- `plugin_after_scan`: 插件扫描后
- `plugin_before_load`: 插件加载前
- `plugin_after_load`: 插件加载后
- `plugin_load_error`: 插件加载错误时触发

## 插件管理

### 管理后台

在 CMS 模式下，可以通过管理后台的插件管理页面管理插件：

- **插件列表**：查看所有已安装的插件
- **上传插件**：上传 ZIP 格式的插件包
- **启用/停用**：切换插件的激活状态
- **删除插件**：删除不需要的插件，需先停用

### 插件上传

插件必须以 ZIP 格式上传，ZIP 文件结构：

```
plugin-name.zip
└── PluginName/
    └── Index.php
```

上传后系统自动执行：

1. 解压 ZIP 文件
2. 验证插件结构，必须包含 `Index.php`
3. 读取插件元数据
4. 移动到插件目录

## 插件初始化方法

插件必须实现 `init()` 方法，在方法内可通过以下辅助方法判断当前应用模式：

### 判断应用模式

```php
public static function init()
{
    // 判断是否为 API 模式
    if (Anon_System_Plugin::isApiMode()) {
        // API 模式下的初始化逻辑
    }
    
    // 判断是否为 CMS 模式
    if (Anon_System_Plugin::isCmsMode()) {
        // CMS 模式下的初始化逻辑
    }
    
    // 获取当前模式字符串
    $mode = Anon_System_Plugin::getAppMode(); // 返回 'api' 或 'cms'
}
```

### 辅助方法说明

- `Anon_System_Plugin::isApiMode()`: 判断是否为 API 模式，返回 `bool`
- `Anon_System_Plugin::isCmsMode()`: 判断是否为 CMS 模式，返回 `bool`
- `Anon_System_Plugin::getAppMode()`: 获取当前应用模式，返回 `'api'` 或 `'cms'`

## 注意事项

1. 类名不区分大小写，系统会自动匹配，建议使用正确的命名规范
2. 插件必须定义至少一个初始化方法：`apiPlugin()`、`cmsPlugin()` 或 `init()`
3. 插件必须放在 `server/app/Plugin/` 目录下
4. 插件主文件建议命名为 `Index.php`
5. 所有插件文件必须包含 `if (!defined('ANON_ALLOWED_ACCESS')) exit;`

## 调试

启用调试模式后，可以在调试控制台查看插件加载信息：

- 插件扫描日志
- 插件加载状态
- 插件元数据信息

## 示例：完整插件

```php
<?php
/**
 * Name: UserStats
 * Description: 用户统计插件
 * Mode: auto
 * Version: 1.0.0
 * Author: Your Name
 * URI: https://example.com
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_UserStats
{
    public static function init()
    {
        // 注册用户统计路由
        Anon::route('/api/user/stats', function () {
            $user = Anon_Http_Request::requireAuth();
            $stats = self::getUserStats($user['uid']);
            Anon::success($stats, '获取统计数据成功');
        }, [
            'requireLogin' => true,
            'method' => ['GET'],
        ]);

        // 注册钩子
        Anon_System_Hook::add_action('user_login', [self::class, 'onUserLogin']);
    }

    public static function activate()
    {
        // 创建统计表
        $db = Anon_Database::getInstance();
        
        // 检查表是否存在
        if (!$db->tableExists('user_stats')) {
            $db->createTable('user_stats', [
                'user_id' => [
                    'type' => 'INT',
                    'null' => false,
                    'primary' => true,
                ],
                'login_count' => [
                    'type' => 'INT',
                    'null' => false,
                    'default' => 0,
                ],
                'last_login' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public static function deactivate()
    {
        Anon_Debug::info('UserStats 插件已停用');
    }

    private static function getUserStats($userId)
    {
        $db = Anon_Database::getInstance();
        return $db->db('user_stats')
            ->where('user_id', '=', $userId)
            ->first();
    }

    public static function onUserLogin($userId)
    {
        $db = Anon_Database::getInstance();
        
        // 检查用户统计是否存在
        $exists = $db->db('user_stats')
            ->where('user_id', '=', $userId)
            ->exists();
        
        if ($exists) {
            // 更新现有记录
            $current = $db->db('user_stats')
                ->where('user_id', '=', $userId)
                ->first();
            
            $db->db('user_stats')
                ->where('user_id', '=', $userId)
                ->update([
                    'login_count' => ($current['login_count'] ?? 0) + 1,
                    'last_login' => date('Y-m-d H:i:s')
                ]);
        } else {
            // 插入新记录
            $db->db('user_stats')->insert([
                'user_id' => $userId,
                'login_count' => 1,
                'last_login' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
```
