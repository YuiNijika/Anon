# 路由与页面

**本节说明**：CMS 模式下路由的配置方式、基本/参数/嵌套路由及与模板的对应关系。**适用**：仅 CMS 模式。

CMS 路由决定 URL 与主题模板的映射，配置存储在数据库 options 表的 routes 字段，或使用安装、初始化时的默认配置，格式为 JSON。

## 配置存储

- 通过 `Anon_Cms_Options::get('routes', '[]')` 读取  
- 通过 `Anon_Cms_Options::set('routes', json_encode($routes, JSON_UNESCAPED_UNICODE))` 写入  
- 也可在 `server/app/useApp.php` 的 `app.cms.routes` 中配置初始值  

## 基本路由

```php
Anon_Cms_Options::set('routes', json_encode([
    '/' => 'index',           // 首页
    '/about' => 'about',      // 关于页
    '/contact' => 'contact',  // 联系页
], JSON_UNESCAPED_UNICODE));
```

路径对应主题下的模板文件名，例如 index.php、about.php。

## 参数路由

支持动态参数，参数会传入模板：

```php
Anon_Cms_Options::set('routes', json_encode([
    '/post/{id}' => 'post',
    '/category/{slug}' => 'category',
    '/user/{id}/profile' => 'profile',
], JSON_UNESCAPED_UNICODE));
```

模板中通过变量访问，例如：

```php
<!-- app/Theme/default/post.php -->
<h1>文章 ID: <?php echo htmlspecialchars($id ?? ''); ?></h1>
```

## 嵌套路由

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

生成：

- `/blog/index` → 模板 `blog/index.php`  
- `/blog/post/{id}` → 模板 `blog/post.php`  

## 响应头与路由元数据

CMS 模式下默认输出 HTML，系统会设置 `Content-Type: text/html; charset=utf-8`。若请求头带 `Accept: application/json`，可仍返回 JSON。

在模板文件顶部可使用 `Anon_RouterMeta` 配置元数据：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,        // CMS 下为 HTML 响应头
    'requireLogin' => false,
    'method' => 'GET',
    'cache' => ['enabled' => true, 'time' => 3600],
];
?>
```

## 错误处理

- **404**：CMS 模式下返回 HTML 404 页，可通过 `router_no_match` 钩子自定义：

```php
Anon_System_Hook::add_action('router_no_match', function($path) {
    Anon_Cms_Theme::render('404', ['path' => $path]);
});
```

主题基础与模板渲染细节见 [CMS 模式概述](./overview.md) 与 [主题系统与开发](./theme-system.md)。
