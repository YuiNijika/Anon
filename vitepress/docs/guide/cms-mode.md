# CMS 模式

Anon Framework 支持两种运行模式：**API 模式**和**CMS 模式**。CMS 模式专为内容管理系统设计，提供了完整的主题系统和模板渲染功能。

## 模式切换

系统运行模式在安装时选择，配置存储在 `server/.env.php` 中：

```php
define('ANON_APP_MODE', 'cms');  // 或 'api'
```

CMS 相关配置存储在数据库 `options` 表中，可以通过 `Anon_Cms_Options` 类访问和修改：

```php
// 获取主题名称
$theme = Anon_Cms_Options::get('theme', 'Default');

// 获取路由配置
$routes = json_decode(Anon_Cms_Options::get('routes', '[]'), true);

// 修改配置
Anon_Cms_Options::set('theme', 'MyTheme');
Anon_Cms_Options::set('routes', json_encode([
    '/' => 'index',
    '/about' => 'about',
], JSON_UNESCAPED_UNICODE));
```

## 模式对比

| 特性 | API 模式 | CMS 模式 |
|------|---------|---------|
| 响应格式 | JSON | HTML |
| 路由配置 | `useRouter.php` 或自动路由 | `useApp.php` 中的 `app.cms.routes` |
| 模板系统 | 不支持 | 支持主题模板 |
| 错误页面 | JSON 格式 | HTML 格式 |
| Content-Type | `application/json` | `text/html` |

## CMS 路由配置

CMS 路由配置存储在数据库 `options` 表的 `routes` 字段中，格式为 JSON。

### 基本路由

```php
// 设置路由配置
Anon_Cms_Options::set('routes', json_encode([
    '/' => 'index',           // 首页
    '/about' => 'about',      // 关于页
    '/contact' => 'contact',  // 联系页
], JSON_UNESCAPED_UNICODE));
```

### 参数路由

支持动态参数，参数会传递给模板：

```php
Anon_Cms_Options::set('routes', json_encode([
    '/post/{id}' => 'post',           // 文章详情页
    '/category/{slug}' => 'category', // 分类页
    '/user/{id}/profile' => 'profile', // 用户资料页
], JSON_UNESCAPED_UNICODE));
```

在模板中可以通过 `$id`、`$slug` 等变量访问参数：

```php
<!-- app/Theme/default/post.php -->
<h1>文章 ID: <?php echo htmlspecialchars($id ?? ''); ?></h1>
```

### 嵌套路由

支持嵌套路由配置：

```php
Anon_Cms_Options::set('routes', json_encode([
    'blog' => [
        'index' => 'blog/index',
        'post' => [
            '{id}' => 'blog/post',
        ],
    ],
], JSON_UNESCAPED_UNICODE));
```

上述配置会生成：

- `/blog/index` → `blog/index.php`
- `/blog/post/{id}` → `blog/post.php`

## 主题系统

### 主题目录结构

```
app/Theme/
└── default/              # 主题名称
    ├── index.php         # 首页模板
    ├── about.php         # 关于页模板
    ├── post.php          # 文章模板
    ├── partials/         # 模板片段目录（可选）
    │   ├── header.php
    │   └── footer.php
    └── assets/           # 静态资源（可选）
        ├── css/
        └── js/
```

### 模板文件命名

- **不区分大小写**：`index.php`、`Index.php`、`INDEX.php` 都会被识别
- **支持多种扩展名**：`.php`、`.html`、`.htm`
- **自动查找**：系统会自动查找匹配的模板文件

### 模板渲染

模板文件是标准的 PHP 文件，可以使用所有 PHP 功能：

```php
<!-- app/Theme/default/index.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title ?? '首页'; ?></title>
</head>
<body>
    <h1><?php echo $siteTitle ?? '我的网站'; ?></h1>
    <p><?php echo $content ?? '欢迎访问'; ?></p>
</body>
</html>
```

### 传递数据给模板

在路由处理中，可以通过 `Anon_Cms_Theme::render()` 传递数据：

```php
// 在路由文件中
Anon_Cms_Theme::render('index', [
    'title' => '首页',
    'siteTitle' => '我的网站',
    'content' => '<p>这是首页内容</p>',
]);
```

### 使用模板片段

可以使用 `Anon_Cms_Theme::partial()` 包含模板片段：

```php
<!-- app/Theme/default/index.php -->
<?php Anon_Cms_Theme::partial('header', ['title' => '首页']); ?>

<main>
    <h1>首页内容</h1>
</main>

<?php Anon_Cms_Theme::partial('footer'); ?>
```

### 主题资源 URL

使用 `Anon_Cms_Theme::assets()` 获取主题资源的 URL：

```php
<!-- app/Theme/default/index.php -->
<link rel="stylesheet" href="<?php echo Anon_Cms_Theme::assets('css/style.css'); ?>">
<script src="<?php echo Anon_Cms_Theme::assets('js/main.js'); ?>"></script>
```

生成的 URL 格式：`/theme/default/css/style.css`

## 响应头处理

在 CMS 模式下，系统会自动设置正确的响应头：

- **HTML 响应**：`Content-Type: text/html; charset=utf-8`
- **JSON 请求**：如果请求头包含 `Accept: application/json`，仍返回 JSON

### 路由元数据

在模板文件中可以使用 `Anon_RouterMeta` 配置路由元数据：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,        // 设置响应头（CMS 模式下为 HTML）
    'requireLogin' => false, // 是否需要登录
    'method' => 'GET',       // 允许的 HTTP 方法
    'cache' => [             // 缓存配置
        'enabled' => true,
        'time' => 3600,
    ],
];
?>
```

**注意**：在 CMS 模式下，`header: true` 会设置 HTML 响应头，而不是 JSON。

## 错误处理

### 404 错误

在 CMS 模式下，404 错误会显示友好的 HTML 页面：

```html
<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
</head>
<body>
    <h1>404</h1>
    <p>Not Found</p>
</body>
</html>
```

### 自定义错误页面

可以通过钩子自定义错误页面：

```php
// server/app/useCode.php
Anon_System_Hook::add_action('router_no_match', function($path) {
    // 自定义 404 处理逻辑
    Anon_Cms_Theme::render('404', ['path' => $path]);
});
```

## 最佳实践

### 1. 主题组织

- 将公共部分提取为模板片段（`partials/`）
- 使用布局模板减少重复代码
- 将静态资源放在 `assets/` 目录

### 2. 数据传递

- 在路由处理中准备数据，然后传递给模板
- 使用数组传递多个变量
- 避免在模板中执行复杂业务逻辑

### 3. 性能优化

- 启用模板缓存（如果实现）
- 使用 CDN 托管静态资源
- 合理使用路由缓存配置

### 4. 安全性

- 始终使用 `htmlspecialchars()` 转义输出
- 验证路由参数
- 使用框架提供的安全功能

## 示例：完整的 CMS 配置

```php
// 在安装时选择 CMS 模式，或手动设置
// server/.env.php
define('ANON_APP_MODE', 'cms');

// 通过代码设置 CMS 配置
Anon_Cms_Options::set('theme', 'Default');
Anon_Cms_Options::set('routes', json_encode([
    '/' => 'index',
    '/about' => 'about',
    '/blog' => [
        'index' => 'blog/index',
        'post' => [
            '{id}' => 'blog/post',
        ],
    ],
    '/category/{slug}' => 'category',
], JSON_UNESCAPED_UNICODE));
```

## 与 API 模式共存

Anon Framework 支持在 CMS 模式下同时使用 API 功能：

- API 路由：通过 `app/Router/` 目录或 `useRouter.php` 配置
- CMS 路由：通过 `options` 表中的 `routes` 配置
- API 路由前缀：通过 `options` 表中的 `apiPrefix` 配置（默认为 `/api`）
- 系统会根据请求路径自动匹配对应的路由

**注意**：当 `ANON_APP_MODE` 设置为 `cms` 时，CMS 路由优先，API 路由需要添加前缀（如 `/api`）。

## 管理后台

CMS 模式提供了完整的管理后台功能，通过 `/anon/cms/admin` 路由前缀访问。

### 管理路由

管理路由通过 `Anon_Cms_Admin` 类注册，支持以下功能：

- **认证相关**：Token 获取、登录状态检查、用户信息获取
- **配置管理**：获取全局配置信息（支持钩子扩展）
- **数据统计**：获取 CMS 统计数据（文章、评论、附件等）
- **设置管理**：基本设置（站点名称、描述、关键词等）的获取和更新

### 路由注册

管理路由在 `Anon_Cms_Admin::initRoutes()` 中注册：

```php
// 添加管理路由
Anon_Cms_Admin::addRoute('/statistics', function () {
    // 处理逻辑
}, [
    'requireAdmin' => '需要管理员权限',
    'method' => 'GET',
    'token' => true,
]);
```

### 路由元数据

管理路由支持以下元数据：

- `requireLogin`: 是否需要登录（布尔值或错误消息字符串）
- `requireAdmin`: 是否需要管理员权限（布尔值或错误消息字符串）
- `method`: 允许的 HTTP 方法（字符串或数组）
- `token`: 是否需要 Token 验证（布尔值）

### 全局配置方法

使用 `Anon_System_Config::getConfig()` 获取全局配置信息：

```php
$config = Anon_System_Config::getConfig();
// 返回: ['token' => true, 'captcha' => false, 'csrfToken' => 'xxx']
```

配置信息可通过 `config` 钩子扩展：

```php
Anon_System_Hook::add_filter('config', function($config) {
    $config['customField'] = 'customValue';
    return $config;
});
```

### 前端集成

管理后台前端使用 `useApiAdmin` composable，详见 [前端框架集成文档](./client.md#cms-管理后台-api)。
