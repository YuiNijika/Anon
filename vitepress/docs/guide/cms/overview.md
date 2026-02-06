# CMS 模式概述

本节说明CMS模式的概念、与API模式的简要对比以及如何在CMS模式下与API共存，仅适用于CMS模式。

Anon Framework支持API模式与CMS模式两种运行模式。CMS模式面向内容管理，提供主题系统与HTML模板渲染，适合博客、官网等站点。

## 模式切换

运行模式在安装时选择，并写入 `server/.env.php`：

```php
define('ANON_APP_MODE', 'cms');  // 或 'api'
```

CMS 相关配置存储在数据库 `options` 表中，可通过 `Anon_Cms_Options` 访问：

```php
$theme = Anon_Cms_Options::get('theme', 'Default');
$routes = json_decode(Anon_Cms_Options::get('routes', '[]'), true);
Anon_Cms_Options::set('theme', 'MyTheme');
```

详细对比见 [模式对比 API vs CMS](../mode-overview.md)。

## 与 API 模式共存

在 CMS 模式下可同时提供 API：

- **API 路由**：通过 `app/Router/` 或 `useRouter.php` 配置  
- **CMS 路由**：通过 `options` 表中的 `routes` 配置  
- **API 前缀**：通过 `options` 表中的 `apiPrefix` 配置，默认为 `/api`  
- 系统按请求路径分别匹配 CMS 路由与 API 路由  

当ANON_APP_MODE为cms时，CMS路由优先。API路由需带前缀如/api，以避免与页面路由冲突。

## 下一步

- **[路由与页面](./routes.md)** — 配置 CMS 路由与模板映射  
- **[主题系统与开发](./theme-system.md)** — 主题结构、模板渲染与主题基础  
- **[管理后台](./admin.md)** — 后台入口、功能与 CMS 管理端点  
