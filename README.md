# Anon Framework Next Skeleton

**Anon Framework Next** 是一个现代化的 PHP 后端 API 框架。本仓库是该框架的应用骨架（Skeleton），为开发者提供了一个开箱即用的目录结构和基础配置，让你能够以最快的速度启动一个新的 API 项目。

## 核心架构设计

框架采用了分离式架构：
- **Skeleton** (本仓库)：应用模板，包含控制器、模型、路由定义、中间件以及 `.env` 配置文件等业务代码的存放地。
- **Core** (`yuinijika/anon-core`)：框架的核心逻辑引擎（容器、路由解析、ORM、JWT 等），被安装在 `vendor/` 目录下。

## 目录结构

```text
/
├── app/                  # 应用程序核心代码目录
│   ├── controller/       # 控制器
│   ├── middleware/       # 自定义中间件
│   ├── model/            # 数据模型 (ORM)
│   ├── route/            # 路由定义 (默认入口: main.php)
│   └── hook.php          # 注册系统生命周期钩子
├── run/                  # 运行时与 Web 根目录
│   ├── index.php         # 框架单一入口文件
│   └── storage/          # 本地文件存储目录
├── runtime/              # 框架生成的运行时文件 (日志、缓存等)
├── .env.example          # 环境变量示例文件
├── anon                  # CLI 命令行入口工具
└── composer.json         # 项目依赖配置
```

## 快速开始

### 1. 创建项目

```bash
composer create-project yuinijika/anon my-app
cd my-app
```

### 2. 环境配置

复制环境配置文件并根据你的需求进行修改：

```bash
cp .env.example .env
```

确保 `.env` 中的 `DATABASE_*` 和 `JWT_SECRET` 配置正确。

### 3. 启动开发服务器

框架内置了轻量的 CLI 工具，你可以直接启动一个本地开发服务器：

```bash
php anon dev
```
服务器将默认监听在 `http://127.0.0.1:8000`。你可以通过访问 `http://127.0.0.1:8000/` 来查看默认的欢迎路由。

## 常用命令

`anon` 命令行工具提供了一些实用的代码生成器，帮助你快速搭建业务骨架：

```bash
# 生成控制器
php anon make:controller UserController

# 生成模型
php anon make:model User

# 生成中间件
php anon make:middleware AuthMiddleware
```

## 官方文档

详细的框架使用指南（包括容器、路由、中间件、数据库等），请查看我们在仓库中附带的 `vitepress/docs`，或者访问官方在线文档站点。

## 许可证 (License)

本项目遵循 [MIT 许可证](LICENSE)。
