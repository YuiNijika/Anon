# 自定义代码

一句话：在useCode.php中添加自定义代码，注册钩子、路由、中间件等。

## 使用方式

编辑 `server/app/useCode.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册钩子
Anon_System_Hook::add_action('user_login', function($user) {
    // 用户登录后执行
}, 10, 1);

// 注册自定义路由
Anon_System_Config::addRoute('/api/custom', function() {
    Anon_Http_Response::success(['message' => '自定义路由']);
});

// 注册静态文件路由
Anon_System_Config::addStaticRoute(
    '/assets/logo.png',
    __DIR__ . '/../assets/logo.png',
    'image/png',
    86400,  // 缓存1天
    false   // 不压缩图片
);

// 注册错误处理器
Anon_System_Config::addErrorHandler(404, function() {
    Anon_Http_Response::notFound('页面不存在');
});

// 注册中间件
Anon_Http_Middleware::global('AuthMiddleware');

// 绑定服务到容器
$container = Anon_System_Container::getInstance();
$container->singleton('MyService', function() {
    return new MyService();
});
```

