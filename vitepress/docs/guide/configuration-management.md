# 配置管理系统

框架提供多层配置管理系统，支持从文件、数据库、缓存等多种来源读取配置。

## 配置层级

```
┌─────────────────────────────────────┐
│   Anon_System_Env (系统配置)        │  ← useApp.php, .env.php
├─────────────────────────────────────┤
│   Anon_Cms_Options (CMS 配置)       │  ← 数据库 options 表
├─────────────────────────────────────┤
│   Anon_Cms_Options_Proxy (代理)     │  ← 插件/主题选项代理
└─────────────────────────────────────┘
```

## 系统配置 (Anon_System_Env)

### 配置文件结构

系统配置存储在 `app/useApp.php` 和 `.env.php` 中：

```php
// app/useApp.php
return [
    'app' => [
        'mode' => 'cms',           // 应用模式
        'baseUrl' => '/',          // 基础 URL
        'cache' => [
            'driver' => 'redis',   // 缓存驱动
            'time' => 3600,        // 默认缓存时间
        ],
        'token' => [
            'enabled' => true,
            'secret' => 'your-secret-key',
        ],
    ],
    'system' => [
        'db' => [
            'host' => 'localhost',
            'username' => 'root',
            'password' => 'password',
            'database' => 'anon',
        ],
    ],
];
```

### 读取配置

```php
// 获取配置值
$mode = Anon_System_Env::get('app.mode', 'api');
$cacheDriver = Anon_System_Env::get('app.cache.driver', 'file');

// 检查配置是否存在
if (Anon_System_Env::has('app.token.enabled')) {
    $tokenEnabled = Anon_System_Env::get('app.token.enabled');
}

// 设置配置（运行时）
Anon_System_Env::set('custom.key', 'value');
```

### 常用配置项

#### 应用配置

```php
'app' => [
    'mode' => 'cms',              // api | cms
    'baseUrl' => '/',             // 基础路径
    'debug' => true,              // 调试模式
]
```

#### 缓存配置

```php
'app' => [
    'cache' => [
        'driver' => 'redis',      // file | redis | memory
        'time' => 3600,           // 默认缓存时间（秒）
        'prefix' => 'anon_',      // 缓存前缀
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
        ],
    ],
]
```

#### 数据库配置

```php
'system' => [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'root',
        'password' => 'password',
        'database' => 'anon',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
]
```

#### Token 配置

```php
'app' => [
    'token' => [
        'enabled' => true,
        'secret' => 'your-secret-key',
        'expire' => 86400,        // 过期时间（秒）
        'algorithm' => 'HS256',   // 加密算法
    ],
]
```

## CMS 配置 (Anon_Cms_Options)

CMS 配置存储在数据库 `options` 表中，支持动态修改。

### 基本用法

```php
// 获取配置
$siteTitle = Anon_Cms_Options::get('site_title', '我的网站');
$postsPerPage = Anon_Cms_Options::get('posts_per_page', 10);

// 设置配置
Anon_Cms_Options::set('site_title', '新的标题');
Anon_Cms_Options::set('posts_per_page', 20);

// 清除缓存
Anon_Cms_Options::clearCache();
```

### 数组配置

支持存储 JSON 格式的数组配置：

```php
// 设置数组配置
Anon_Cms_Options::set('allowed_domains', [
    'example.com',
    'www.example.com',
]);

// 获取数组配置
$domains = Anon_Cms_Options::get('allowed_domains', []);

// 设置复杂配置
Anon_Cms_Options::set('seo_config', [
    'title' => '网站标题',
    'keywords' => ['关键词 1', '关键词 2'],
    'description' => '网站描述',
]);
```

### 常用配置项

#### 站点配置

```php
'site_title' => '网站标题'
'site_description' => '网站描述'
'site_keywords' => ['关键词 1', '关键词 2']
'site_url' => 'https://example.com'
```

#### 文章配置

```php
'posts_per_page' => 10              // 每页文章数
'default_comment_status' => true    // 默认评论开关
'default_ping_status' => true       // 默认 Ping 开关
```

#### API 配置

```php
'apiPrefix' => '/api'               // API 前缀
'api_enabled' => true               // API 启用状态
```

#### 访问日志配置

```php
'access_log_enabled' => true        // 访问日志开关
'access_log_retention_days' => 30   // 保留天数
'access_log_exclude_paths' => [...] // 排除路径
```

## 选项代理 (Anon_Cms_Options_Proxy)

选项代理提供统一的配置读取接口，支持优先级控制。

### 使用方式

```php
// 创建代理
$proxy = new Anon_Cms_Options_Proxy('plugin', 'my-plugin');

// 读取配置（自动按优先级）
$value = $proxy->get('setting_name', 'default');

// 写入配置（到指定位置）
$proxy->set('setting_name', 'new_value');
```

### 优先级类型

| 优先级 | 说明 | 存储位置 |
|--------|------|----------|
| `plugin` | 插件配置 | `options` 表 `plugin:slug` |
| `theme` | 主题配置 | `options` 表 `theme:slug` |
| `system` | 系统配置 | `options` 表顶层键 |

### 优先级规则

**插件内：** plugin > theme > system

```php
class Anon_Plugin_MyPlugin extends Anon_Plugin_Base
{
    public function init()
    {
        $plugin = $this;
        
        // 自动按优先级读取
        $value = $plugin->options()->get('setting', 'default');
        // 等价于：
        // 1. 先查 plugin:myplugin.setting
        // 2. 再查 theme:current_theme.setting
        // 3. 最后查 setting
    }
}
```

**主题内：** theme > plugin > system

```php
// 主题 functions.php 中
$proxy = new Anon_Cms_Options_Proxy('theme', 'my-theme');

// 优先读取主题配置
$color = $proxy->get('primary_color', '#333');
```

### 指定优先级

```php
// 仅从插件配置读取
$pluginValue = $proxy->get('setting', 'default', false, 'plugin');

// 仅从系统配置读取
$systemValue = $proxy->get('setting', 'default', false, 'system');

// 输出并返回
$proxy->get('setting', 'default', true); // true = echo + return
```

## 配置最佳实践

### 1. 分层管理

将配置按类型分层管理：

```php
// ✅ 正确：清晰的分层
'app' => [
    'mode' => 'cms',
    'cache' => [...],
    'token' => [...],
],

// ❌ 错误：扁平化混乱
'mode' => 'cms',
'cache_driver' => 'redis',
'cache_time' => 3600,
'token_enabled' => true,
```

### 2. 合理缓存

对频繁读取的配置使用缓存：

```php
class ConfigManager
{
    private static $cache = [];
    
    public static function get($key, $default = null)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $value = Anon_Cms_Options::get($key, $default);
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    public static function clear()
    {
        self::$cache = [];
    }
}
```

### 3. 配置验证

在保存前验证配置：

```php
public function saveSettings($config)
{
    // 验证必需字段
    if (empty($config['site_title'])) {
        throw new Exception('网站标题不能为空');
    }
    
    // 验证类型
    if (!is_int($config['posts_per_page'])) {
        throw new Exception('每页文章数必须为整数');
    }
    
    // 验证范围
    if ($config['posts_per_page'] < 1 || $config['posts_per_page'] > 100) {
        throw new Exception('每页文章数必须在 1-100 之间');
    }
    
    // 保存配置
    foreach ($config as $key => $value) {
        Anon_Cms_Options::set($key, $value);
    }
}
```

### 4. 配置导出

支持配置导出用于迁移：

```php
Anon_System_Console::command('config:export', function($args) {
    $keys = [
        'site_title',
        'site_description',
        'posts_per_page',
        'access_log_enabled',
    ];
    
    $config = [];
    foreach ($keys as $key) {
        $config[$key] = Anon_Cms_Options::get($key);
    }
    
    $filename = ANON_ROOT . 'config_export_' . date('Y-m-d') . '.json';
    file_put_contents($filename, json_encode($config, JSON_PRETTY_PRINT));
    
    Anon_System_Console::success("配置已导出：{$filename}");
    return 0;
}, '导出配置到 JSON 文件');
```

### 5. 配置导入

支持配置导入用于批量更新：

```php
Anon_System_Console::command('config:import', function($args) {
    if (empty($args[0])) {
        Anon_System_Console::error('请提供配置文件路径');
        return 1;
    }
    
    $file = $args[0];
    if (!file_exists($file)) {
        Anon_System_Console::error('文件不存在：' . $file);
        return 1;
    }
    
    $config = json_decode(file_get_contents($file), true);
    if (!is_array($config)) {
        Anon_System_Console::error('无效的配置文件');
        return 1;
    }
    
    $count = 0;
    foreach ($config as $key => $value) {
        Anon_Cms_Options::set($key, $value);
        $count++;
    }
    
    Anon_System_Console::success("导入 {$count} 个配置项");
    return 0;
}, '从 JSON 文件导入配置');
```

## 配置管理 API

### RESTful API

CMS 模式下提供配置管理 API：

```
GET    /anon/cms/admin/options           # 获取配置列表
GET    /anon/cms/admin/options/{name}    # 获取单个配置
PUT    /anon/cms/admin/options/{name}    # 更新配置
POST   /anon/cms/admin/options/bulk      # 批量更新配置
DELETE /anon/cms/admin/options/{name}    # 删除配置
```

### 请求示例

#### 批量更新配置

```http
POST /anon/cms/admin/options/bulk
Content-Type: application/json

{
    "options": {
        "site_title": "新标题",
        "posts_per_page": 15,
        "access_log_enabled": true
    }
}
```

#### 响应示例

```json
{
    "code": 200,
    "message": "操作成功",
    "data": {
        "updated": 3,
        "failed": 0
    }
}
```

## 故障排查

### 常见问题

#### 1. 配置未生效

**检查点：**
- 确认配置键名是否正确
- 检查缓存是否需要清除
- 验证配置文件语法是否正确
- 确认配置层级是否正确

#### 2. 配置读取失败

**解决方案：**
```php
try {
    $value = Anon_Cms_Options::get('key', 'default');
} catch (Exception $e) {
    // 记录错误
    Anon_Debug::error('Config read failed', ['error' => $e->getMessage()]);
    // 返回默认值
    $value = 'default';
}
```

#### 3. 配置冲突

当多个地方修改同一配置时：

```php
// 使用事务确保原子性
$db = Anon_Database::getInstance();
$db->begin_transaction();

try {
    Anon_Cms_Options::set('critical_config', $newValue);
    // 其他相关操作...
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## 相关文档

- [访问日志系统](access-log.md) - 访问日志配置
- [插件系统](cms/plugin-system.md) - 插件配置管理
- [主题开发](cms/theme-system.md) - 主题配置
- [CLI 命令系统](cli-system.md) - 命令行配置管理
