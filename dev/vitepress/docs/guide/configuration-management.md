# 配置管理系统

本框架的配置来源主要分为两类：文件配置（`app/useEnv.php`、`app/useApp.php`）与 CMS options（数据库 `options` 表）。配置读取统一通过 `Anon\Modules\System\Env` 完成。

## 配置来源

### 1) 环境常量：app/useEnv.php

用于数据库、安装状态、运行模式等全局常量（例如 `ANON_APP_MODE`、`ANON_DB_*`）。

### 2) 应用配置：app/useApp.php

用于路由、缓存、安全、调试等结构化配置。新版结构以 `app.base.*` 为主：

```php
return [
  'app' => [
    'base' => [
      'router' => ['mode' => 'auto'], // auto | manual
      'debug' => ['global' => false, 'router' => false],
      'token' => ['enabled' => true],
      'cache' => ['enabled' => true, 'time' => 3600],
    ],
  ],
];
```

### 3) CMS 配置：options 表（通过 Env 读取）

CMS 相关配置存储在数据库 `options` 表，通过 `Anon\Modules\Cms\Theme\Options` 读取：

```php
use Anon\Modules\Cms\Theme\Options;

$theme = Options::get('theme', 'Default');
$apiPrefix = Options::get('apiPrefix', '/api');
$routes = json_decode(Options::get('routes', '[]'), true);
```

## Env API

`Anon\Modules\System\Env` 负责配置初始化、缓存与读取：

```php
use Anon\Modules\System\Env;

Env::init(); // 通常由框架启动阶段调用

$tokenEnabled = Env::get('app.base.token.enabled', false);
$dbHost = Env::get('system.db.host', 'localhost');

$all = Env::all();
Env::clearCache();
```

## 兼容键映射（旧键 → 新键）

为便于平滑迁移，`Env` 会将部分旧键映射到 `app.base.*` 新结构（例如 `app.cache.*` → `app.base.cache.*`）。文档与新代码建议统一使用新键。

## Options 与 OptionsProxy

如需直接操作 CMS options，可使用 `Anon\Modules\Cms\Options`：

```php
use Anon\Modules\Cms\Options;

$title = Options::get('title', '我的网站');
Options::set('title', '新的标题');
```

插件/主题读取“自身配置 + 主题配置 + 系统配置”时，推荐使用 `OptionsProxy`：

```php
use Anon\Modules\Cms\Theme\OptionsProxy;

$proxy = new OptionsProxy('plugin', 'my-plugin', null);
$value = $proxy->get('setting_name', 'default');
```

## 最佳实践

- 统一使用 namespace + 顶部 use 引入，避免长命名空间调用
- 文档/代码统一使用 `app.base.*` 配置键
- CMS 配置通过 `Env::get('app.cms.*')` / `Options` 读取，避免在文件中硬编码 CMS 路由与主题状态
