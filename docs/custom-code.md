# 自定义代码

在 `server/app/useCode.php` 中添加自定义代码：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册钩子
Anon_Hook::add_action('router_before_init', function () {
    Anon_Debug::info('路由初始化前');
});

// 注册自定义路由
Anon_Config::addRoute('/api/custom', function () {
    Anon_Common::Header();
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 注册错误处理器
Anon_Config::addErrorHandler(404, function () {
    Anon_Common::Header(404);
    Anon_ResponseHelper::notFound('页面不存在');
});

// 注册中间件
Anon_Middleware::global('MyGlobalMiddleware');
Anon_Middleware::alias('my-middleware', 'MyMiddleware');

// 绑定服务到容器
$container = Anon_Container::getInstance();
$container->singleton('MyService', function() {
    return new MyService();
});
```

---

[← 返回文档首页](../README.md)

