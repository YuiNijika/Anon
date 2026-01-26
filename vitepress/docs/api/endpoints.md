# API 端点

一句话：系统提供的所有API端点列表。

## 系统端点

- `GET /anon/common/license` - 获取许可证信息
- `GET /anon/common/system` - 获取系统信息
- `GET /anon/common/client-ip` - 获取客户端IP
- `GET /anon/common/config` - 获取配置信息
- `GET /anon/ciallo` - 恰喽~
- `GET /anon/install` - 安装页面
- `GET /anon/install/api/token` - 获取安装 CSRF Token
- `POST /anon/install/api/mode` - 选择安装模式
- `GET /anon/install/api/get-mode` - 获取当前选择的模式
- `POST /anon/install/api/database` - 配置数据库
- `POST /anon/install/api/site` - 配置站点信息（CMS 模式）
- `POST /anon/install/api/install` - 创建管理员账号并完成安装（API 模式）
- `POST /anon/install/api/back` - 返回上一步
- `GET /anon` - 系统根路径

## 调试端点

- `GET /anon/debug/login` - 调试控制台登录页
- `GET /anon/debug/console` - Web调试控制台
- `GET /anon/debug/api/info` - 获取调试信息
- `GET /anon/debug/api/performance` - 获取性能数据
- `GET /anon/debug/api/logs` - 获取日志
- `GET /anon/debug/api/errors` - 获取错误日志
- `GET /anon/debug/api/hooks` - 获取钩子信息
- `GET /anon/debug/api/tools` - 获取工具信息
- `POST /anon/debug/api/clear` - 清空调试数据

## 认证端点

- `POST /auth/login` - 用户登录
- `POST /auth/register` - 用户注册（支持防刷限制）
- `POST /auth/logout` - 用户注销
- `GET /auth/check-login` - 检查登录状态
- `GET /auth/token` - 获取Token
- `GET /auth/captcha` - 获取验证码

## 用户端点

- `GET /user/info` - 获取用户信息

## CMS 管理端点

以下端点使用 `/anon/cms/admin` 前缀：

- `GET /anon/cms/admin/auth/token` - 获取 Token（需要登录）
- `GET /anon/cms/admin/auth/check-login` - 检查登录状态（无需登录）
- `GET /anon/cms/admin/user/info` - 获取用户信息（需要登录）
- `GET /anon/cms/admin/config` - 获取配置信息（无需登录）
- `GET /anon/cms/admin/statistics` - 获取统计数据（需要管理员权限）
- `GET /anon/cms/admin/settings/basic` - 获取基本设置（需要管理员权限）
- `POST /anon/cms/admin/settings/basic` - 更新基本设置（需要管理员权限）
- `GET /anon/cms/admin/settings/theme` - 获取主题列表（需要管理员权限）
- `POST /anon/cms/admin/settings/theme` - 切换主题（需要管理员权限）
- `GET /anon/cms/admin/settings/theme-options` - 获取主题设置项（需要管理员权限）
- `POST /anon/cms/admin/settings/theme-options` - 更新主题设置项（需要管理员权限）

### 配置信息接口

`GET /anon/cms/admin/config` 和 `GET /get-config` 返回相同的配置信息：

```json
{
  "code": 200,
  "message": "获取配置信息成功",
  "data": {
    "token": true,
    "captcha": false,
    "csrfToken": "xxx"
  }
}
```

可通过 `config` 钩子扩展配置字段：

```php
Anon_System_Hook::add_filter('config', function($config) {
    $config['customField'] = 'customValue';
    return $config;
});
```

### 基本设置接口

获取基本设置：

```json
{
  "code": 200,
  "message": "获取基本设置成功",
  "data": {
    "title": "站点名称",
    "description": "站点描述",
    "keywords": "关键词",
    "allow_register": false,
    "api_prefix": "/api",
    "api_enabled": false,
    "upload_allowed_types": {
      "image": "gif,jpg,jpeg,png",
      "media": "mp3,mp4",
      "document": "pdf,doc",
      "other": ""
    }
  }
}
```

更新基本设置：

```json
{
  "title": "新站点名称",
  "description": "新站点描述",
  "keywords": "新关键词",
  "allow_register": true,
  "api_prefix": "/api",
  "api_enabled": true,
  "upload_allowed_types": {
    "image": "gif,jpg,jpeg,png,webp",
    "media": "mp3,mp4,mov",
    "document": "pdf,doc,docx",
    "other": "zip,rar"
  }
}
```

### 统计数据接口

`GET /anon/cms/admin/statistics` 返回统计数据：

```json
{
  "code": 200,
  "message": "获取统计数据成功",
  "data": {
    "posts": 100,
    "comments": 50,
    "attachments": 20,
    "categories": 10,
    "tags": 30,
    "users": 5,
    "published_posts": 80,
    "draft_posts": 20,
    "pending_comments": 5,
    "approved_comments": 45,
    "attachments_size": 10485760,
    "total_views": 10000
  }
}
```

### 主题设置接口

获取主题列表：

```json
{
  "code": 200,
  "message": "获取主题列表成功",
  "data": {
    "current": "default",
    "themes": [
      {
        "name": "default",
        "title": "默认主题",
        "description": "默认主题描述"
      }
    ]
  }
}
```

切换主题：

```json
{
  "theme": "default"
}
```

### 主题设置项接口

获取主题设置项：

```json
{
  "code": 200,
  "message": "获取主题设置项成功",
  "data": {
    "theme": "default",
    "schema": {
      "site_name": {
        "type": "text",
        "label": "站点名称",
        "default": "我的网站"
      }
    },
    "values": {
      "site_name": "我的网站"
    }
  }
}
```

更新主题设置项：

```json
{
  "theme": "default",
  "values": {
    "site_name": "新站点名称"
  }
}
```

## 静态文件

- `GET /anon/static/debug/css` - 调试控制台样式文件
- `GET /anon/static/debug/js` - 调试控制台脚本文件
- `GET /anon/static/vue` - Vue.js 生产版本

**说明：** 静态文件路由通过 `Anon_System_Config::addStaticRoute()` 方法注册，支持自动缓存和压缩。详见 [路由处理文档](./routing.md#静态文件路由)。
