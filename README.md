# Anon Framework Next Skeleton

**Anon Framework Next** 是一个现代化的 PHP 后端 API 框架。本仓库是它的应用骨架（Skeleton）。

默认骨架刻意保持轻量，初始化时只保留少量最基础的示例路由和一个简单控制器，方便你像使用 ThinkPHP 一样，创建项目后直接开始改业务代码，而不是先清理一堆复杂 demo。

## 核心架构设计

框架采用了分离式架构：
- **Skeleton** (本仓库)：应用模板，包含控制器、模型、路由定义、中间件以及 `anon.config.php` / `.env` 等业务配置文件的存放地。
- **Core** (`YuiNijika/AnonCore`)：框架的核心逻辑引擎（容器、路由解析、ORM、JWT 等），被安装在 `vendor/` 目录下。

## 目录结构

```text
/
├── app/                  # 应用程序核心代码目录
│   ├── controller/       # 控制器，风格更接近 ThinkPHP
│   ├── Jobs/             # 队列任务
│   ├── Middleware/       # 自定义中间件
│   ├── Model/            # 数据模型 (ORM)
│   ├── route/            # 路由定义 (默认入口: main.php)
│   └── hook.php          # 注册系统生命周期钩子
├── run/                  # 运行时与 Web 根目录
│   ├── index.php         # 框架单一入口文件
│   └── storage/          # 本地文件存储目录
├── runtime/              # 框架生成的运行时文件 (日志、缓存等)
├── anon.config.php       # 项目主配置文件
├── .env                  # 默认环境变量文件
├── anon                  # CLI 命令行入口工具
└── composer.json         # 项目依赖配置
```

## 快速开始

### 1. 创建项目

```bash
composer create-project yuinijika/anon my-app
cd my-app
```

### 2. 环境与项目配置

框架推荐将结构化配置写入 `anon.config.php`，将密码、密钥、环境差异值保留在 `.env*` 中。

直接修改根目录下的 `.env` 与 `anon.config.php` 即可。

默认骨架里的 `anon.config.php` 会刻意保持精简，只预置：

- `cache`
- `session`
- `auth`

像数据库、队列、日志、更完整的 Auth 选项这类配置，不会默认全部塞进去；需要时再按文档逐步加回去即可。

框架同时支持类似 Vite 的环境文件分层加载：

- `.env`
- `.env.local`
- `.env.development`
- `.env.production`
- `.env.{APP_ENV}.local`

框架启动时会优先读取 `anon.config.php`，未配置的项目会继续回退到 `.env` 中的旧环境变量，便于平滑升级。

### 3. 启动开发服务器

框架内置了轻量 CLI 工具，你可以直接启动本地开发服务器：

```bash
php anon dev
```

服务器默认监听 `http://127.0.0.1:8000`。

## 默认示例

创建项目后，骨架默认只提供 5 个非常基础的示例接口：

```text
GET  /
GET  /ping
GET  /hello/{name}
GET  /articles
POST /articles
```

它们分别演示了：

- 最基础的控制器返回
- 健康检查接口
- 动态路由参数
- JSON 列表输出
- POST 请求与参数校验

例如：

```bash
curl http://127.0.0.1:8000/ping
curl "http://127.0.0.1:8000/hello/anon?from=cli"
curl -X POST http://127.0.0.1:8000/articles \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"Hello\",\"content\":\"My first API\"}"
```

默认示例控制器在 `app/controller/Index.php`，默认路由在 `app/route/main.php`。

你通常只需要直接修改这两个文件，就可以开始写自己的项目。

如果你需要更完整的开发示例，例如：

- 新建控制器
- 注册控制器路由
- 静态路由
- 动态路由

建议直接查看 `vitepress/docs/guide/architecture/router.md`，这些示例会统一维护在文档中，而不是继续塞回默认骨架。

## 推荐起步方式

如果你想像 ThinkPHP 那样快速上手，建议按下面的顺序开始：

1. 改 `app/route/main.php`
2. 改 `app/controller/Index.php`
3. 按需调整 `anon.config.php`
4. 需要数据库、认证、队列时，再逐步接入对应能力

一个最小路由示例：

```php
use Anon\Core\Facade\Route;
use Anon\Controller\Admin\User\Index;

Route::get('/admin/users', [Index::class, 'index']);
Route::post('/admin/users', [Index::class, 'store']);
```

## 常用命令

`anon` 命令行工具提供了一些实用的代码生成器，帮助你快速搭建业务骨架：

```bash
# 生成配置缓存
php anon config:cache

# 清理配置缓存
php anon config:clear

# 生成路由缓存（仅支持控制器路由）
php anon route:cache

# 清理路由缓存
php anon route:clear

# 查看失败任务
php anon queue:failed

# 重试单个失败任务
php anon queue:retry --id=job-id

# 清空失败任务
php anon queue:clear-failed

# 生成单文件控制器
php anon make:controller User

# 生成目录式控制器，自动创建 Index.php
php anon make:controller User --group
php anon make:controller Admin/User --group

# 生成资源控制器模板
php anon make:controller User --resource
php anon make:controller Admin/User --group --resource

# 生成模型
php anon make:model User
php anon make:model Admin/User

# 生成中间件
php anon make:middleware CheckLogin
php anon make:middleware Admin/CheckLogin
```

推荐在控制器目录中使用这种更接近 ThinkPHP 的分层方式：

```text
app/controller/User.php
app/controller/User/Index.php
app/controller/Admin/User/Index.php
app/model/User.php
app/model/Admin/User.php
app/middleware/CheckLogin.php
app/middleware/Admin/CheckLogin.php
```

如果你喜欢字符串控制器写法，也可以直接这样注册路由：

```php
Route::get('/admin/users', 'Admin/User/Index@index');
```

如果你的控制器就是标准 CRUD，也可以直接使用：

```php
Route::resource('/admin/users', 'Admin/User/Index');
```

如果你只想保留部分资源动作，也可以这样：

```php
Route::resource('/admin/users', 'Admin/User/Index', [
    'only' => ['index', 'show', 'store'],
]);
```

更新动作同时支持 `PUT` 和 `PATCH`。

## 按需扩展

框架本身已经支持：

- `anon.config.php + .env*` 分层配置
- 配置缓存与路由缓存
- ORM、关联与软删除
- JWT Auth、多 Guard、双令牌、会话管理
- Redis 队列、失败任务、重试与清理

但这些能力不再默认堆在骨架首页示例里，而是建议你在真正需要时再接入。这样初始化项目会更干净，也更接近传统 PHP 框架的使用习惯。

## 生产部署建议

推荐在生产环境使用 `.env.production` 或由部署平台注入环境变量，并在发布完成后立即构建运行时缓存：

```bash
# 1. 安装依赖
composer install --no-dev --optimize-autoloader

# 2. 生成配置缓存
php anon config:cache

# 3. 生成路由缓存
php anon route:cache
```

如果你更新了以下内容，建议重新生成缓存：

- 修改了 `anon.config.php`
- 修改了 `.env.production` 或其他生产环境变量
- 修改了 `app/route/*.php`
- 新增、删除或重命名了控制器动作

推荐发布顺序如下：

```bash
php anon config:clear
php anon route:clear
php anon config:cache
php anon route:cache
```

当前 `route:cache` 仅支持控制器路由；如果你的生产路由中仍然包含闭包，需要先改为控制器动作再进行缓存。

## 官方文档

详细的框架使用指南（包括配置、容器、路由、中间件、数据库、Auth、Queue 等），请查看仓库中的 `vitepress/docs`。

## 许可证 (License)

本项目遵循 [MIT 许可证](LICENSE)。
