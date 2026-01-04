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
 * Plugin Name: HelloWorld
 * Plugin Description: Hello World 插件
 * Version: 1.0.0
 * Author: YuiNijika
 * Plugin URI: https://github.com/YuiNijika
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 插件主类（类名不区分大小写）
class Anon_Plugin_HelloWorld
{
    /**
     * 插件初始化方法（必需）
     * 在插件加载时自动调用
     */
    public static function init()
    {
        // 注册路由
        Anon::route('/hello', function () {
            Anon::success([
                self::index()
            ], 'Hello World from Plugin');
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

    /**
     * 插件激活时调用（可选）
     */
    public static function activate()
    {
        Anon_Debug::info('HelloWorld 插件已激活');
    }

    /**
     * 插件停用时调用（可选）
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

插件元数据通过文件头注释（PHPDoc 风格）定义，包含以下字段：

- `Plugin Name`: 插件名称（必需）
- `Plugin Description`: 插件描述
- `Version`: 插件版本
- `Author`: 作者名称
- `Plugin URI`: 插件主页或作者主页

**示例：**

```php
/**
 * Plugin Name: HelloWorld
 * Plugin Description: Hello World 插件
 * Version: 1.0.0
 * Author: YuiNijika
 * Plugin URI: https://github.com/YuiNijika
 */
```

## 插件类命名规则

插件类名格式：`Anon_Plugin_{插件名称}`

- 插件名称从目录名获取
- 类名不区分大小写（系统会自动匹配）
- 例如：目录 `HelloWorld` → 类名 `Anon_Plugin_HelloWorld`

## 配置插件系统

在 `server/app/useApp.php` 中配置：

```php
return [
    'app' => [
        'plugins' => [
            'enabled' => true, // 是否启用插件系统
            'active' => [],     // 激活的插件列表，空数组表示激活所有插件
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
    'header' => true,           // 是否设置响应头
    'requireLogin' => false,    // 是否需要登录
    'method' => ['GET'],        // 允许的 HTTP 方法
    'token' => false,          // 是否需要 Token 验证
    'cache' => [               // 缓存配置
        'enabled' => true,
        'time' => 3600,
    ],
]);
```

### 路由元数据说明

- `header`: 是否设置响应头（默认 `true`）
- `requireLogin`: 是否需要登录（默认 `false`）
- `method`: 允许的 HTTP 方法，字符串或数组（如 `['GET', 'POST']`）
- `token`: 是否需要 Token 验证（`true`/`false`/`null`，`null` 使用全局配置）
- `cache`: 缓存配置
  - `enabled`: 是否启用缓存
  - `time`: 缓存时间（秒）

## 插件生命周期

### 1. 扫描阶段

系统扫描 `server/app/Plugin/` 目录，查找所有插件目录。

### 2. 加载阶段

- 读取插件元数据（`Anon_PluginMeta`）
- 查找插件主类（`Anon_Plugin_{插件名}`）
- 检查插件是否在激活列表中（如果配置了激活列表）

### 3. 初始化阶段

- 调用插件的 `init()` 方法
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
    Anon_Hook::add_action('some_action', function() {
        // 执行逻辑
    });

    // 添加过滤器钩子
    Anon_Hook::add_filter('some_filter', function($value) {
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

## 注意事项

1. **类名不区分大小写**：系统会自动匹配类名，但建议使用正确的命名规范
2. **必需方法**：`init()` 方法是必需的，插件加载时会自动调用
3. **目录结构**：插件必须放在 `server/app/Plugin/` 目录下
4. **文件命名**：插件主文件建议命名为 `Index.php`
5. **安全检查**：所有插件文件必须包含 `if (!defined('ANON_ALLOWED_ACCESS')) exit;`

## 调试

启用调试模式后，可以在调试控制台查看插件加载信息：

- 插件扫描日志
- 插件加载状态
- 插件元数据信息

## 示例：完整插件

```php
<?php
/**
 * Plugin Name: UserStats
 * Plugin Description: 用户统计插件
 * Version: 1.0.0
 * Author: Your Name
 * Plugin URI: https://example.com
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_UserStats
{
    public static function init()
    {
        // 注册用户统计路由
        Anon::route('/api/user/stats', function () {
            $user = Anon_RequestHelper::requireAuth();
            $stats = self::getUserStats($user['uid']);
            Anon::success($stats, '获取统计数据成功');
        }, [
            'requireLogin' => true,
            'method' => ['GET'],
        ]);

        // 注册钩子
        Anon_Hook::add_action('user_login', [self::class, 'onUserLogin']);
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
