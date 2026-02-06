# 现代特性

依赖注入容器、中间件系统、缓存层、CLI工具等现代PHP特性。

## 依赖注入容器

### 基本使用

```php
$container = Anon_System_Container::getInstance();

// 绑定接口到实现
$container->bind('UserRepositoryInterface', 'UserRepository');

// 绑定单例
$container->singleton('Database', function() {
    return Anon_Database::getInstance();
});

// 注册实例
$container->instance('Config', $configInstance);

// 设置别名
$container->alias('db', 'Database');

// 解析依赖
$userRepo = $container->make('UserRepositoryInterface');
$db = $container->make('Database', ['host' => 'localhost']);

// 检查是否已绑定
$bound = $container->bound('Database');

// 清空容器
$container->flush();
```

### 自动依赖解析

```php
class UserService
{
    private $userRepo;
    
    // 容器会自动注入UserRepository实例
    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }
}

// 自动解析所有依赖
$userService = $container->make('UserService');
```

## 中间件系统

### 创建中间件

```php
class AuthMiddleware implements Anon_Http_MiddlewareInterface
{
    public function handle($request, callable $next)
    {
        // 请求前处理
        if (!Anon_Check::isLoggedIn()) {
            Anon_Http_Response::unauthorized('请先登录');
            return;
        }
        
        // 继续执行下一个中间件或路由处理器
        return $next($request);
    }
}
```

### 注册中间件

```php
// 注册全局中间件
Anon_Http_Middleware::global('AuthMiddleware');

// 注册带别名的路由中间件
Anon_Http_Middleware::alias('auth', 'AuthMiddleware');
Anon_Http_Middleware::alias('throttle', 'ThrottleMiddleware');
```

### 在路由中使用

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
    'middleware' => ['auth', 'throttle'], // 使用中间件
];

// 路由处理代码...
```

## 缓存系统

框架提供了灵活的缓存系统，支持文件缓存和内存缓存两种驱动。

### 初始化缓存

```php
use Anon_System_Cache;

// 使用文件缓存（默认）
Anon_System_Cache::init('file', [
    'dir' => __DIR__ . '/../../cache',
    'ttl' => 3600, // 默认过期时间秒数
]);

// 使用内存缓存（单次请求有效）
Anon_System_Cache::init('memory');
```

### 基本操作

```php
// 设置缓存
Anon_System_Cache::set('user:1', $userData, 3600); // 1小时过期
Anon_System_Cache::set('config', $config, null); // 永不过期

// 获取缓存
$user = Anon_System_Cache::get('user:1');
$config = Anon_System_Cache::get('config', []); // 默认值

// 检查缓存是否存在
if (Anon_System_Cache::has('user:1')) {
    // ...
}

// 删除缓存
Anon_System_Cache::delete('user:1');

// 清空所有缓存
Anon_System_Cache::clear();
```

### 记住缓存（缓存回调结果）

```php
// 如果缓存不存在，执行回调并缓存结果
$users = Anon_System_Cache::remember('users:all', function() {
    return $db->query('SELECT * FROM users');
}, 3600);
```

## CLI 工具

### 创建CLI入口

创建 `server/cli.php`：

```php
<?php
require_once __DIR__ . '/core/Main.php';
require_once __DIR__ . '/core/Modules/Console.php';

use Anon_System_Console;

// 注册命令
Anon_System_Console::command('cache:clear', function($args) {
    Anon_System_Cache::clear();
    Anon_System_Console::success('缓存已清空');
    return 0;
}, '清空缓存');

Anon_System_Console::command('route:list', function($args) {
    $routes = Anon_System_Config::getRouterConfig()['routes'] ?? [];
    Anon_System_Console::info("已注册的路由:");
    foreach ($routes as $path => $handler) {
        Anon_System_Console::line("  {$path}");
    }
    return 0;
}, '列出所有路由');

// 运行命令
exit(Anon_System_Console::run($argv));
```

### 使用命令

```bash
# 清空缓存
php cli.php cache:clear

# 列出路由
php cli.php route:list

# 显示帮助
php cli.php
```

### 输出方法

```php
Anon_System_Console::info('信息消息');
Anon_System_Console::success('成功消息');
Anon_System_Console::error('错误消息');
Anon_System_Console::warning('警告消息');
Anon_System_Console::line('普通消息');
```

