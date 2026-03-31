# 中间件扩展系统

中间件扩展系统提供轻量级、可扩展的钩子机制，允许你在请求生命周期的不同阶段注入自定义逻辑。

## 快速开始

### 启用扩展系统

在 `app/useApp.php` 中配置：

```php
'extensions' => [
    'enabled' => true,
],
```

### 插件自动注册中间件

在插件的 `Index.php` 中添加 `registerMiddleware()` 方法：

```php
<?php
class Anon_Plugin_HelloWorld
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'customHeader',
            [self::class, 'handle'],
            10,
            [
                'hooks' => ['response.before_send'],
            ]
        );
    }
    
    public static function handle($data)
    {
        header('X-Powered-By: Anon Framework');
        return $data;
    }
}
```

当插件加载时，会自动调用 `registerMiddleware()` 方法注册中间件。

## 配置结构

### 扩展系统配置项

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `enabled` | bool | true | 是否启用扩展系统 |

### 中间件注册方法

插件通过 `registerMiddleware()` 方法注册中间件，该方法在插件加载时自动调用。

```php
class Anon_Plugin_MyPlugin
{
    public static function registerMiddleware()
    {
        // 注册多个中间件
        Anon_System_Extension::register(
            'myMiddleware',
            [self::class, 'handle'],
            10,
            ['hooks' => ['request.start']]
        );
    }
}

## Handler 处理器类型

支持三种处理器类型：

### 1. 匿名函数

```php
public static function registerMiddleware()
{
    Anon_System_Extension::register(
        'customHeader',
        function ($data) {
            header('X-Custom-Header: Value');
            return $data;
        },
        10,
        ['hooks' => ['response.before_send']]
    );
}
```

### 2. 类名（自动调用 handle 方法）

```php
class RequestLogger
{
    public static function handle($data)
    {
        Anon_Debug::info('Request received', $_SERVER['REQUEST_URI']);
        return $data;
    }
}

// 在插件中注册
public static function registerMiddleware()
{
    Anon_System_Extension::register(
        'requestLogger',
        RequestLogger::class,
        5
    );
}
```

### 3. 类方法数组

```php
public static function registerMiddleware()
{
    Anon_System_Extension::register(
        'corsHandler',
        [CorsMiddleware::class, 'handle'],
        1
    );
}
```

## 可用钩子

### 请求生命周期钩子

| 钩子名称 | 触发时机 | 说明 |
|----------|---------|------|
| `request.start` | 请求开始时 | 最早执行的钩子，适合 CORS、安全头等 |
| `request.pre_handle` | 路由处理前 | 适合权限验证、限流等 |
| `request.post_handle` | 路由处理后 | 适合数据修改、日志记录 |
| `request.end` | 请求结束时 | 适合性能分析、访问日志 |
| `response.before_send` | 响应发送前 | 适合修改响应头、内容压缩 |
| `*` | 所有钩子 | 监听所有钩子事件 |

### 使用示例

```php
'options' => [
    'hooks' => [
        'request.start',      // 请求开始时
        'request.pre_handle', // 路由处理前
        'request.post_handle',// 路由处理后
        'request.end',        // 请求结束时
        'response.before_send',// 响应发送前
        '*',                   // 所有钩子
    ],
],
```

## 完整示例

### 示例 1：CORS 跨域中间件

```php
class CorsMiddleware
{
    public static function handle($data)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
        
        return $data;
    }
}

// 在插件中注册
class Anon_Plugin_Cors
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'cors',
            [CorsMiddleware::class, 'handle'],
            1,
            [
                'hooks' => ['request.start'],
            ]
        );
    }
}
```

### 示例 2：请求日志中间件

```php
class RequestLogger
{
    private static $startTime;
    
    public static function start($data)
    {
        self::$startTime = microtime(true);
        
        Anon_Debug::info('Request started', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => Anon_Common::GetClientIp(),
        ]);
        
        return $data;
    }
    
    public static function end($data)
    {
        $duration = (microtime(true) - self::$startTime) * 1000;
        
        Anon_Debug::info('Request completed', [
            'duration' => round($duration, 2) . 'ms',
            'memory' => memory_get_usage(true),
        ]);
        
        return $data;
    }
}

// 在插件中注册两个中间件
class Anon_Plugin_Logger
{
    public static function registerMiddleware()
    {
        // 请求开始日志
        Anon_System_Extension::register(
            'loggerStart',
            [RequestLogger::class, 'start'],
            1,
            [
                'hooks' => ['request.start'],
            ]
        );
        
        // 请求结束日志
        Anon_System_Extension::register(
            'loggerEnd',
            [RequestLogger::class, 'end'],
            100,
            [
                'hooks' => ['request.end'],
            ]
        );
    }
}
```

### 示例 3：性能分析中间件

```php
class Anon_Plugin_Profiler
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'profiler',
            function ($data) {
                if (!isset($GLOBALS['__profiler_start'])) {
                    $GLOBALS['__profiler_start'] = microtime(true);
                }
                
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    $start = $GLOBALS['__profiler_start'];
                    $duration = (microtime(true) - $start) * 1000;
                    
                    header('X-Request-Duration: ' . round($duration, 2) . 'ms');
                    header('X-Memory-Usage: ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB');
                }
                
                return $data;
            },
            1,
            [
                'hooks' => ['*'],
            ]
        );
    }
}
```

### 示例 4：安全头中间件

```php
class SecurityHeaders
{
    public static function handle($data)
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        return $data;
    }
}

class Anon_Plugin_Security
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'securityHeaders',
            [SecurityHeaders::class, 'handle'],
            5,
            [
                'hooks' => ['response.before_send'],
            ]
        );
    }
}
```

## 优先级控制

数字越小优先级越高，先执行：

```php
class Anon_Plugin_PriorityExample
{
    public static function registerMiddleware()
    {
        // 第一个执行
        Anon_System_Extension::register(
            'first',
            fn($d) => dd('First'),
            1
        );
        
        // 第二个执行
        Anon_System_Extension::register(
            'second',
            fn($d) => dd('Second'),
            10
        );
        
        // 最后执行
        Anon_System_Extension::register(
            'third',
            fn($d) => dd('Third'),
            100
        );
    }
}
```

## 动态注册中间件

除了插件自动注册，也可以在代码中动态注册：

```php
// 在 useCode.php 或插件的 init() 方法中
Anon_System_Extension::register(
    'myCustomMiddleware',
    function ($data) {
        // 自定义逻辑
        return $data;
    },
    10,
    [
        'hooks' => ['request.start'],
    ]
);
```

## 注销中间件

```php
Anon_System_Extension::unregister('middlewareName');
```

## 检查中间件状态

```php
// 检查是否已注册
$isRegistered = Anon_System_Extension::isRegistered('middlewareName');

// 获取所有中间件
$all = Anon_System_Extension::getAll();
```

## 最佳实践

### 1. 合理设置优先级

```php
class Anon_Plugin_MiddlewareOrder
{
    public static function registerMiddleware()
    {
        // 安全相关优先执行
        Anon_System_Extension::register('security', [...], 1);
        Anon_System_Extension::register('auth', [...], 5);
        
        // 日志中间件
        Anon_System_Extension::register('logging', [...], 10);
        
        // 低优先级：分析等
        Anon_System_Extension::register('analytics', [...], 100);
    }
}
```

### 2. 单一职责

每个中间件只负责一个功能：

```php
class Anon_Plugin_SeparateMiddleware
{
    public static function registerMiddleware()
    {
        // ✅ 正确：分开独立中间件
        Anon_System_Extension::register('cors', [...]);
        Anon_System_Extension::register('securityHeaders', [...]);
        Anon_System_Extension::register('rateLimit', [...]);
    }
}

// ❌ 错误：一个大而全的中间件
class BadAllInOne
{
    public static function handle($data)
    {
        // CORS + 安全头 + 限流 + 日志...
    }
}
```

### 3. 错误处理

中间件内部应该捕获异常，避免影响其他中间件：

```php
class SafeMiddleware
{
    public static function handle($data)
    {
        try {
            // 业务逻辑
            return $data;
        } catch (Throwable $e) {
            Anon_Debug::error('Middleware error', ['error' => $e->getMessage()]);
            return $data;
        }
    }
}

class Anon_Plugin_SafeHandler
{
    public static function registerMiddleware()
    {
        Anon_System_Extension::register(
            'safeMiddleware',
            [SafeMiddleware::class, 'handle']
        );
    }
}
```

### 4. 性能考虑

避免在高优先级中间件中执行耗时操作：

```php
class Anon_Plugin_AsyncAnalytics
{
    public static function registerMiddleware()
    {
        // ✅ 正确：异步或延迟处理
        Anon_System_Extension::register(
            'analytics',
            function ($data) {
                // 记录到队列，稍后处理
                Queue::push('analytics', $data);
                return $data;
            },
            100  // 低优先级
        );
    }
}
```

## 与插件系统的关系

中间件扩展系统是插件系统的一部分，由插件自动注册和管理。

| 特性 | 说明 |
|------|------|
| 注册方式 | 插件的 `registerMiddleware()` 方法自动调用 |
| 管理方式 | 由插件系统统一管理 |
| 配置位置 | 无需手动配置，代码中注册 |
| 执行顺序 | 优先级控制（数字越小越先） |
| 适用场景 | 请求拦截、修改响应、日志记录等 |

## 相关文档

- [访问日志](access-log.md) - 访问日志系统
- [Redis 缓存](cache-redis-guide.md) - Redis 缓存使用指南
- [开发规范](coding-standards.md) - 代码编写规范
