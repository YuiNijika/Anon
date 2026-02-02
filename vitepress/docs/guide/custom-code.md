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

## 主题自定义代码

主题模式下使用主题目录的 `app/code.php` 作为主题级自定义代码文件。

文件位置：`server/app/Theme/{themeName}/app/code.php`

**说明：**

- 文件会在主题初始化时自动加载
- 用于注册主题相关钩子
- 用于注册主题相关路由
- 用于注册错误处理器
- 用于添加自定义函数和类

主题设置项在 `app/setup.php` 中通过 return 数组定义。

**示例：**

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册主题设置项
Anon_Theme_Options::register('site_title', [
    'type' => 'text',
    'label' => '网站标题',
    'description' => '显示在网站首页的标题',
    'default' => '我的网站',
    'sanitize_callback' => function($value) {
        return trim(strip_tags($value));
    },
    'validate_callback' => function($value) {
        return strlen($value) <= 100;
    },
]);

// 注册动作钩子
Anon_System_Hook::add_action('theme_foot', function () {
    echo '<script>console.log("Theme loaded");</script>';
});

// 注册过滤器钩子
Anon_System_Hook::add_filter('theme_page_title', function ($title) {
    $siteTitle = Anon_Theme_Options::get('site_title', '');
    return $siteTitle ? "{$title} - {$siteTitle}" : $title;
});

// 注册自定义路由
Anon_System_Config::addRoute('/theme/custom', function () {
    Anon_Common::Header();
    $setting = Anon_Theme_Options::get('custom_setting', 'default');
    Anon_Http_Response::success(['setting' => $setting], '获取主题设置成功');
});
```

## 扩展权限系统

通过 `anon_auth_capabilities` 过滤器可以扩展或修改角色权限配置：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 扩展角色权限配置
Anon_System_Hook::add_filter('anon_auth_capabilities', function($capabilities) {
    // 为现有角色添加新权限
    $capabilities['admin'][] = 'manage_custom_feature';
    $capabilities['editor'][] = 'edit_custom_content';
    
    // 添加新角色
    $capabilities['moderator'] = [
        'edit_posts',
        'delete_posts',
        'moderate_comments',
    ];
    
    // 为特定角色添加资源级权限
    // editor 对应 CMS 管理端的“编辑”用户组
    $capabilities['editor'][] = 'post:create';
    $capabilities['editor'][] = 'post:edit';
    
    return $capabilities;
});

// 使用权限检查
$capability = Anon_Auth_Capability::getInstance();
if ($capability->currentUserCan('manage_custom_feature')) {
    // 有权限执行
}
```

### 移除权限

通过 `anon_auth_capabilities_remove` 过滤器可以移除特定角色的权限：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 移除角色权限
Anon_System_Hook::add_filter('anon_auth_capabilities_remove', function($removeList) {
    // 从 admin 角色移除 manage_widgets 权限
    $removeList['admin'] = ['manage_widgets'];
    
    // 从 editor 角色移除多个权限
    $removeList['editor'] = ['delete_posts', 'publish_posts'];
    
    // 从 editor 角色移除单个权限，也可以使用字符串
    $removeList['editor'] = 'publish_own_posts';
    
    return $removeList;
});
```

### 权限标识格式

- **简单权限**：`'manage_options'`、`'edit_posts'`
- **资源级权限**：`'post:create'`、`'user:read'`、`'comment:delete'`
- **通配符权限**：`'post:*'` 表示所有 post 操作，`'*:read'` 表示所有资源的读取操作，`'*:*'` 表示所有权限

### 示例：为自定义模块添加权限

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 为自定义模块添加权限
Anon_System_Hook::add_filter('anon_auth_capabilities', function($capabilities) {
    // 添加自定义模块权限
    $capabilities['admin'][] = 'manage_shop';
    $capabilities['admin'][] = 'shop:*';
    
    $capabilities['shop_manager'] = [
        'shop:read',
        'shop:create',
        'shop:edit',
        'shop:delete',
        'order:read',
        'order:edit',
    ];
    
    $capabilities['shop_staff'] = [
        'shop:read',
        'order:read',
    ];
    
    return $capabilities;
});

// 在路由中使用权限检查
Anon_System_Config::addRoute('/api/shop/products', function() {
    $capability = Anon_Auth_Capability::getInstance();
    
    // 要求特定权限
    $capability->requireCapability('shop:read');
    
    // 业务逻辑
    Anon_Http_Response::success(['products' => []]);
});
```
