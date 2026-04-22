# 路由系统

Anon Framework 提供两种路由定义方式：**手动配置**和**自动扫描**。系统优先使用手动配置，当配置文件不存在时自动启用文件扫描。

## 快速开始

### 方式一：手动配置（推荐）

创建 `app/useRouter.php` 文件：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    // API 路由
    '/api/users' => 'Auth/users',
    
    // CMS 路由
    '/about' => 'pages/about',
];
```

### 方式二：自动扫描（零配置）

当不存在 `useRouter.php` 时，系统自动扫描 `app/Router/` 目录：

```
app/Router/
├── index.php         → /
├── about.php         → /about
└── api/
    └── users.php     → /api/users
```

## 手动配置模式

### 基本语法

**简单路由：**

```php
return [
    '路径' => '视图文件',
];
```

**带元数据的路由：**

```php
return [
    'users' => [
        'view' => 'Auth/users',
        'middleware' => ['auth'],
        'requireLogin' => true,
        'method' => 'POST',
    ],
];
```

### 完整示例

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    // 首页
    '/' => 'index',
    
    // 关于页
    '/about' => 'pages/about',
    
    // API 路由
    'api' => [
        // 用户相关
        'users' => [
            // GET /api/users
            '' => [
                'view' => 'Auth/users',
                'method' => 'GET',
                'middleware' => ['auth'],
            ],
            // POST /api/users
            'create' => [
                'view' => 'Auth/createUser',
                'method' => 'POST',
                'requireLogin' => true,
            ],
        ],
        
        // 文章相关
        'posts' => [
            '' => 'Api/posts',
            'detail' => 'Api/postDetail',
        ],
    ],
];
```

## 自动扫描模式

### 文件命名规则

| 文件名 | 映射路径 | 说明 |
|--------|----------|------|
| `index.php` | `/` | 根路径 |
| `about.php` | `/about` | 单文件路由 |
| `contact.php` | `/contact` | 单文件路由 |
| `dir/index.php` | `/dir` | 目录索引 |
| `dir/page.php` | `/dir/page` | 子目录文件 |

### 目录结构示例

```
app/Router/
├── index.php              # /
├── about.php              # /about
├── contact.php            # /contact
├── blog/
│   ├── index.php          # /blog
│   ├── post.php           # /blog/post
│   └── archive.php        # /blog/archive
└── api/
    ├── users.php          # /api/users
    └── posts.php          # /api/posts
```

### 路由元数据（推荐）

自动扫描的路由文件可以通过常量 `RouterMeta` 配置元数据（例如 method、middleware、requireLogin、cache 等），框架会在加载路由文件时解析该常量。

```php
<?php
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Exception;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

const RouterMeta = [
    'method' => 'POST',
    'middleware' => ['auth', 'csrf'],
    'requireLogin' => true,
    'token' => false,
    'cache' => ['enabled' => true, 'time' => 3600],
];

try {
    $data = RequestHelper::validate([
        'title' => '标题不能为空',
    ]);
    ResponseHelper::success($data);
} catch (Exception $e) {
    ResponseHelper::handleException($e);
}
```

## 路由优先级

```
1. useRouter.php 配置（最高优先级）
   ↓
2. 文件系统自动扫描（降级方案）
```

**注意：** 当存在 `useRouter.php` 时，自动扫描不会执行。

## 使用场景

### 适合手动配置的场景

- ✅ 大型项目，需要精确控制
- ✅ 复杂路由树结构
- ✅ 需要细粒度权限控制
- ✅ 团队协作，需要明确的路由文档

### 适合自动扫描的场景

- ✅ 小型项目、快速原型
- ✅ 个人博客、官网
- ✅ 快速迭代开发
- ✅ 简单的 RESTful API

## 最佳实践

### 1. 选择合适的模式

**小项目/快速开发：** 使用自动扫描

```
app/Router/
├── index.php
├── about.php
└── contact.php
```

**大项目/企业应用：** 使用手动配置

```php
// app/useRouter.php
return [
    'api' => [
        'v1' => [
            'users' => [
                '' => ['view' => 'Api/Users::list', 'middleware' => ['auth']],
                'create' => ['view' => 'Api/Users::create', 'permission' => 'user.create'],
            ],
        ],
    ],
];
```

### 2. 组织路由结构

**按功能模块分组：**

```
app/Router/
├── Auth/          # 认证相关
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── User/          # 用户相关
│   ├── profile.php
│   └── settings.php
└── Admin/         # 管理后台
    ├── dashboard.php
    └── users.php
```

### 3. 使用中间件保护路由

```php
<?php
/**
 * @middleware auth,admin
 * @requireLogin true
 */
// 管理员专用路由
```

### 4. RESTful API 设计

**自动扫描模式：**

```
app/Router/api/
├── users.php       # GET, POST /api/users
├── posts.php       # GET, POST /api/posts
└── comments.php    # GET, POST /api/comments
```

**手动配置模式：**

```php
return [
    'api' => [
        'users' => [
            '' => ['view' => 'Api/Users::index', 'method' => 'GET'],
            'create' => ['view' => 'Api/Users::store', 'method' => 'POST'],
        ],
    ],
];
```

## 高级用法

### 动态参数路由

自动扫描模式下，使用查询参数：

```php
// app/Router/post.php
$id = $_GET['id'] ?? null;
```

访问：`/post?id=123`

### 条件路由

```php
// app/useRouter.php
$mode = defined('ANON_APP_MODE') ? ANON_APP_MODE : 'api';

return $mode === 'cms' ? [
    '/' => 'cms/index',
] : [
    '/' => 'api/index',
];
```

### 路由分组

```php
// app/useRouter.php
$apiRoutes = [
    'users' => 'Api/Users',
    'posts' => 'Api/Posts',
];

return [
    'api' => [
        'v1' => $apiRoutes,
        'v2' => array_map(function($route) {
            return ['view' => $route, 'middleware' => ['api.v2']];
        }, $apiRoutes),
    ],
];
```

## 调试技巧

### 查看已注册路由

```php
// 在任意路由文件中添加
use Anon\Modules\Debug;

Debug::info('Current route', [
    'path' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
]);
```

### 检查路由模式

```php
use Anon\Modules\Debug;
use Anon\Modules\System\Env;

$hasManual = is_file(ANON_ROOT . 'app/useRouter.php');
$mode = Env::get('app.base.router.mode', 'auto');
$routerMode = $hasManual ? 'manual(useRouter.php)' : "auto(app.base.router.mode={$mode})";

Debug::info("Router mode: {$routerMode}");
```

## 常见问题

### Q1. 两种模式可以混用吗？

**不可以。** 系统优先使用 `useRouter.php`，只有当它不存在时才启用自动扫描。

### Q2. 如何从自动扫描切换到手动配置？

1. 创建 `app/useRouter.php`
2. 定义路由配置
3. 系统自动切换到手动模式

### Q3. 自动扫描支持子目录吗？

支持。子目录会作为路径前缀：

```
app/Router/api/users.php  →  /api/users
```

### Q4. 如何为自动扫描的路由添加中间件？

在路由文件头部添加注释：

```php
<?php
/**
 * @middleware auth
 */
```

## 相关文档

- [中间件扩展系统](extension-system.md) - 中间件开发
- [请求与响应](api/request-response.md) - 请求处理
- [API 端点](api/endpoints.md) - API 路由参考
