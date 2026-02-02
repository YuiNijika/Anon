# API 端点

**本节说明**：系统提供的所有 API 端点列表，按适用模式分区。**适用**：通用；部分端点仅限 API 或仅限 CMS 模式。

一句话：系统提供的所有 API 端点列表。

下文按分区列出：系统与安装、调试为通用；认证与用户为 API 与 CMS 共用；CMS 管理端点仅限 CMS 模式。

## 系统与安装

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

## 调试

- `GET /anon/debug/login` - 调试控制台登录页
- `GET /anon/debug/console` - Web调试控制台
- `GET /anon/debug/api/info` - 获取调试信息
- `GET /anon/debug/api/performance` - 获取性能数据
- `GET /anon/debug/api/logs` - 获取日志
- `GET /anon/debug/api/errors` - 获取错误日志
- `GET /anon/debug/api/hooks` - 获取钩子信息
- `GET /anon/debug/api/tools` - 获取工具信息
- `POST /anon/debug/api/clear` - 清空调试数据

## 认证与用户

- `POST /auth/login` - 用户登录
- `POST /auth/register` - 用户注册（支持防刷限制）
- `POST /auth/logout` - 用户注销
- `GET /auth/check-login` - 检查登录状态
- `GET /auth/token` - 获取Token
- `GET /auth/captcha` - 获取验证码

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

### 附件管理接口

- `GET /anon/cms/admin/attachments` - 获取附件列表（需要管理员权限）
- `POST /anon/cms/admin/attachments` - 上传附件（需要管理员权限）
- `DELETE /anon/cms/admin/attachments` - 删除附件（需要管理员权限）

获取附件列表支持分页和类型筛选：

```json
// 请求参数
{
  "page": 1,
  "page_size": 20,
  "mime_type": "image"  // 可选：image, video, audio, document, other 或完整 MIME 类型
}

// 响应
{
  "code": 200,
  "message": "获取附件列表成功",
  "data": {
    "list": [
      {
        "id": 1,
        "user_id": 1,
        "filename": "a1b2c3d4e5f67890-1760000000.jpg",
        "original_name": "photo.jpg",
        "mime_type": "image/jpeg",
        "file_size": 102400,
        "file_path": "/path/to/Upload/image/a1b2c3d4e5f67890-1760000000.jpg",
        "url": "/anon/static/upload/image/a1b2c3d4e5f67890-1760000000",
        "created_at": 1760000000
      }
    ],
    "total": 100,
    "page": 1,
    "page_size": 20
  }
}
```

上传附件：

```json
// 请求：multipart/form-data
// file: 文件对象

// 响应
{
  "code": 200,
  "message": "上传成功",
  "data": {
    "id": 1,
    "filename": "a1b2c3d4e5f67890-1760000000.jpg",
    "original_name": "photo.jpg",
    "mime_type": "image/jpeg",
    "file_size": 102400,
    "url": "/anon/static/upload/image/a1b2c3d4e5f67890-1760000000"
  }
}
```

### 分类管理接口

- `GET /anon/cms/admin/metas/categories` - 获取分类列表（需要管理员权限）
- `POST /anon/cms/admin/metas/categories` - 创建分类（需要管理员权限）
- `PUT /anon/cms/admin/metas/categories` - 更新分类（需要管理员权限）
- `DELETE /anon/cms/admin/metas/categories` - 删除分类（需要管理员权限）

### 标签管理接口

- `GET /anon/cms/admin/metas/tags` - 获取标签列表（需要管理员权限）
- `POST /anon/cms/admin/metas/tags` - 创建标签（需要管理员权限）
- `PUT /anon/cms/admin/metas/tags` - 更新标签（需要管理员权限）
- `DELETE /anon/cms/admin/metas/tags` - 删除标签（需要管理员权限）

### 文章管理接口

- `GET /anon/cms/admin/posts` - 获取文章列表或单篇文章（需要管理员权限）
- `POST /anon/cms/admin/posts` - 创建文章（需要管理员权限）
- `PUT /anon/cms/admin/posts` - 更新文章（需要管理员权限）
- `DELETE /anon/cms/admin/posts` - 删除文章（需要管理员权限）

获取文章列表支持分页、搜索和筛选：

```json
// 请求参数
{
  "page": 1,
  "page_size": 20,
  "search": "关键词",      // 可选：搜索标题或别名
  "status": "publish",     // 可选：draft, publish, private
  "type": "post"           // 可选：post, page
}

// 响应
{
  "code": 200,
  "message": "获取文章列表成功",
  "data": {
    "list": [...],
    "total": 100,
    "page": 1,
    "page_size": 20
  }
}
```

获取单篇文章：

```json
// 请求：GET /anon/cms/admin/posts?id=1

// 响应
{
  "code": 200,
  "message": "获取文章成功",
  "data": {
    "id": 1,
    "title": "文章标题",
    "content": "文章内容",
    "status": "publish",
    "type": "post",
    "category_id": 1,
    "tag_ids": "[1,2,3]",
    "created_at": 1760000000,
    "updated_at": 1760000000
  }
}
```

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

### 系统静态文件

- `GET /anon/static/debug/css` - 调试控制台样式文件
- `GET /anon/static/debug/js` - 调试控制台脚本文件
- `GET /anon/static/vue` - Vue.js 生产版本

### 附件静态文件

附件文件通过静态路由提供访问，支持无后缀 URL 和图片格式转换：

- `GET /anon/static/upload/{filetype}/{file}` - 获取原始附件文件
- `GET /anon/static/upload/{filetype}/{file}/{format}` - 获取转换后的图片（支持 webp, jpg, jpeg, png）

**文件类型分类：**

- `image` - 图片文件（image/*）
- `video` - 视频文件（video/*）
- `audio` - 音频文件（audio/*）
- `document` - 文档文件（application/pdf）
- `other` - 其他文件

**示例：**

```
// 获取原始图片
GET /anon/static/upload/image/a1b2c3d4e5f67890-1760000000

// 获取 WebP 格式（自动转换并缓存）
GET /anon/static/upload/image/a1b2c3d4e5f67890-1760000000/webp

// 获取 PNG 格式
GET /anon/static/upload/image/a1b2c3d4e5f67890-1760000000/png
```

**说明：**

- 附件 URL 不包含文件后缀，避免浏览器按静态资源规则直接返回 404
- 图片格式转换结果会缓存到 `Upload/{filetype}/processed/` 目录
- 转换后的文件如果已存在且不旧于原文件，直接返回缓存
- 支持透明背景（PNG/WebP）

**静态文件路由注册：** 通过 `Anon_System_Config::addStaticRoute()` 方法注册，支持自动缓存和压缩。详见 [路由处理文档](/guide/api/routing#静态文件路由)。
