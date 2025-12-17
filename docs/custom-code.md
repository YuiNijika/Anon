# 自定义代码

一句话：在useCode.php中添加自定义代码，注册钩子、路由、中间件等。

## 使用方式

编辑 `server/app/useCode.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册钩子
Anon_Hook::add_action('user_login', function($user) {
    // 用户登录后执行
}, 10, 1);

// 注册自定义路由
Anon_Config::addRoute('/api/custom', function() {
    Anon_ResponseHelper::success(['message' => '自定义路由']);
});

// 注册错误处理器
Anon_Config::addErrorHandler(404, function() {
    Anon_ResponseHelper::notFound('页面不存在');
});

// 注册中间件
Anon_Middleware::global('AuthMiddleware');

// 绑定服务到容器
$container = Anon_Container::getInstance();
$container->singleton('MyService', function() {
    return new MyService();
});
```

---

[← 返回文档首页](../README.md)
