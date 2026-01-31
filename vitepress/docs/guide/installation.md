# 安装指南

Anon Framework 提供了可视化的安装向导，帮助您快速完成系统安装和配置。

## 访问安装页面

当系统未安装时，访问任何页面都会自动重定向到安装页面：

```
http://your-domain.com/anon/install
```

## 安装流程

安装过程分为多个步骤，根据选择的模式，API 或 CMS，会有所不同。

### 步骤 1：选择安装模式

首先需要选择系统运行模式：

- **API 模式**：纯 API 接口模式，不加载主题系统，所有响应为 JSON 格式
- **CMS 模式**：内容管理系统模式，支持主题和页面模板，响应为 HTML 格式

### 步骤 2：配置数据库

填写数据库连接信息：

- **数据库主机**：数据库服务器地址，默认为 `localhost`
- **数据库端口**：数据库端口，默认为 `3306`
- **数据库用户名**：数据库用户名，默认为 `root`
- **数据库密码**：数据库密码，必填，不能为空
- **数据库名称**：要使用的数据库名称
- **数据表前缀**：数据表前缀，默认为 `anon_`，只能包含字母、数字和下划线

系统会自动测试数据库连接，确保配置正确。

### 步骤 3A：站点配置

如果选择 CMS 模式，需要配置站点信息：

- **管理员账号**：
  - 用户名：管理员登录用户名
  - 邮箱：管理员邮箱地址
  - 密码：管理员密码，至少 8 个字符
- **站点信息**：
  - 网站标题：网站名称
  - 网站介绍：网站描述

### 步骤 3B：管理员账号

如果选择 API 模式，只需配置管理员账号：

- **用户名**：管理员登录用户名
- **邮箱**：管理员邮箱地址
- **密码**：管理员密码，至少 8 个字符

## 安装完成

安装完成后，系统会：

1. 创建数据库表结构，根据选择的模式创建对应的表
2. 写入配置文件 `.env.php`
3. 创建管理员账号
4. 初始化 CMS 选项

安装成功后会自动跳转到首页。

## 安装 API 端点

安装向导使用以下 API 端点：

### 获取 CSRF Token

```
GET /anon/install/api/token
```

返回：
```json
{
  "code": 200,
  "message": "操作成功",
  "data": {
    "csrf_token": "随机生成的 token"
  }
}
```

### 选择安装模式

```
POST /anon/install/api/mode
```

请求体：
```json
{
  "csrf_token": "token",
  "app_mode": "api"  // 或 "cms"
}
```

### 获取当前模式

```
GET /anon/install/api/get-mode
```

返回：
```json
{
  "code": 200,
  "message": "操作成功",
  "data": {
    "mode": "api"  // 或 "cms"
  }
}
```

### 配置数据库

```
POST /anon/install/api/database
```

请求体：
```json
{
  "csrf_token": "token",
  "db_host": "localhost",
  "db_port": 3306,
  "db_user": "root",
  "db_pass": "password",
  "db_name": "database_name",
  "db_prefix": "anon_"
}
```

### 配置站点信息

```
POST /anon/install/api/site
```

请求体：
```json
{
  "csrf_token": "token",
  "username": "admin",
  "email": "admin@example.com",
  "password": "password123",
  "site_title": "我的网站",
  "site_description": "网站描述"
}
```

### 创建管理员账号

```
POST /anon/install/api/install
```

请求体：
```json
{
  "csrf_token": "token",
  "username": "admin",
  "email": "admin@example.com",
  "password": "password123"
}
```

### 返回上一步

```
POST /anon/install/api/back
```

请求体：
```json
{
  "csrf_token": "token"
}
```

## 配置文件说明

### .env.php

安装完成后，系统会在 `server/.env.php` 中写入以下配置：

```php
define('ANON_APP_MODE', 'api');  // 或 'cms'
define('ANON_DB_HOST', 'localhost');
define('ANON_DB_PORT', 3306);
define('ANON_DB_USER', 'root');
define('ANON_DB_PASSWORD', 'password');
define('ANON_DB_DATABASE', 'database_name');
define('ANON_DB_PREFIX', 'anon_');
define('ANON_INSTALLED', true);
define('ANON_APP_KEY', 'base64:随机生成的密钥');
```

### options 表

CMS 模式下，系统会在 `options` 表中存储以下配置：

- `charset`: 字符集，默认为 `UTF-8`
- `title`: 网站标题
- `description`: 网站描述
- `keywords`: 网站关键词
- `theme`: 默认主题名称，默认为 `Default`
- `apiPrefix`: API 路由前缀，默认为 `/api`
- `routes`: CMS 路由配置，JSON 格式

## 数据库表结构

### 全局表

- `users`: 用户表
- `user_meta`: 用户元数据表
- `sessions`: 会话表
- `tokens`: Token 表
- `login_logs`: 登录日志表

### CMS 模式专用表

- `posts`: 内容表，文章和页面
- `comments`: 评论表
- `attachments`: 附件表
- `options`: 选项表

## 常见问题

### 1. 数据库连接失败

- 检查数据库服务是否运行
- 确认数据库主机、端口、用户名、密码是否正确
- 确认数据库名称是否存在
- 检查数据库用户是否有足够的权限

### 2. 安装页面无法访问

- 确认 PHP 版本 >= 8.1
- 检查文件权限
- 查看错误日志

### 3. 安装后无法登录

- 确认管理员账号创建成功
- 检查密码是否正确
- 查看数据库中的 `users` 表

### 4. 重新安装

如果需要重新安装：

1. 删除 `.env.php` 中的 `ANON_INSTALLED` 定义或设置为 `false`
2. 清空数据库表（可选）
3. 重新访问安装页面

**注意**：重新安装会覆盖现有配置和数据，请谨慎操作。

## 安全建议

1. **安装完成后立即删除安装文件**（如果不需要重新安装）
2. **使用强密码**：管理员密码应至少包含 8 个字符，建议包含大小写字母、数字和特殊字符
3. **保护配置文件**：确保 `.env.php` 文件权限正确，不要暴露在公开目录
4. **定期备份**：安装完成后定期备份数据库和配置文件

## 下一步

安装完成后，您可以：

- [快速开始](./quick-start.md) - 了解基本使用方法
- [配置说明](./configuration.md) - 了解详细配置选项
- [CMS 模式](./cms-mode.md) - 了解 CMS 模式的使用（如果选择了 CMS 模式）
- [路由系统](./routing.md) - 了解路由配置

