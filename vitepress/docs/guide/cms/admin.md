# 管理后台

**本节说明**：CMS 模式下的管理后台入口、功能清单、前端集成与 CMS 管理端点索引；含静态资源与附件说明。**适用**：仅 CMS 模式。

CMS 模式提供完整的管理后台，用于配置站点、主题、插件、内容与附件等。后台通过统一前缀访问，前端可独立部署并调用 CMS 管理端点。

## 后台入口

- **路由前缀**：`/anon/cms/admin`  
- **访问示例**：`https://your-domain.com/anon/cms/admin`，具体入口以部署的前端为准，独立 Admin SPA 的根路径即后台首页。

::: tip 适用模式：CMS 模式  
管理后台及下文所列端点仅在 **CMS 模式** 下可用；API 模式无 CMS 后台与 CMS 专用端点。  
:::

## 功能清单

| 功能 | 说明 |
|------|------|
| **设置** | 基本设置如站点名称、描述、关键词、API 前缀等，主题切换与主题设置项 |
| **主题** | 主题列表、切换主题、主题选项，存于 options 表 theme:主题名 |
| **插件** | 插件列表、上传、启用、停用、删除，插件设置存于 options 表 plugin:插件名 |
| **文章** | 文章/页面增删改查，分类与标签关联 |
| **分类** | 分类增删改查 |
| **标签** | 标签增删改查 |
| **附件** | 附件上传、列表、删除，按类型分类存储 |
| **统计** | 文章、评论、附件、用户等统计数据 |

## 前端集成

管理后台前端通过 CMS 管理端点与后端交互，需使用 `/anon/cms/admin` 前缀。认证流程如 Token、登录状态检查、用户信息与通用 [前端框架集成](../client.md#cms-管理后台-api) 一致，baseURL 指向 CMS 管理前缀。详见 [前端框架集成](../client.md)。

## CMS 管理端点索引

以下为 CMS 管理相关端点摘要，完整请求/响应格式见 [API 端点](/api/endpoints#cms-管理端点)。

- **认证**：`GET /anon/cms/admin/auth/token`、`GET /anon/cms/admin/auth/check-login`、`GET /anon/cms/admin/user/info`  
- **配置**：`GET /anon/cms/admin/config`  
- **统计**：`GET /anon/cms/admin/statistics`  
- **设置**：`GET/POST /anon/cms/admin/settings/basic`，`GET/POST /anon/cms/admin/settings/theme`，`GET/POST /anon/cms/admin/settings/theme-options`  
- **插件**：GET 列表、POST 上传、PUT 启用或停用、DELETE 删除；GET/POST plugins/options 获取与保存插件设置项  
- **附件**：`GET/POST/DELETE /anon/cms/admin/attachments`  
- **分类**：`GET/POST/PUT/DELETE /anon/cms/admin/metas/categories`  
- **标签**：`GET/POST/PUT/DELETE /anon/cms/admin/metas/tags`  
- **文章**：`GET/POST/PUT/DELETE /anon/cms/admin/posts`  

### 路由注册与元数据

管理路由由 `Anon_Cms_Admin::initRoutes()` 注册，可通过 `Anon_Cms_Admin::addRoute()` 扩展。元数据支持：`requireLogin`、`requireAdmin`、`method`、`token`。全局配置可通过 `Anon_System_Config::getConfig()` 获取，并由 `config` 钩子扩展。

## 静态资源与附件

### 附件目录结构

上传文件按类型自动归类存储：

```
Upload/
├── image/          # 图片
├── video/          # 视频
├── audio/          # 音频
├── document/       # 文档
└── other/          # 其他
```

### 上传与命名

- 命名规则：`{16 位十六进制}-{时间戳}.{扩展名}`，例如 `a1b2c3d4e5f67890-1760000000.jpg`  
- 主题与插件上传支持 ZIP 包，无目录层级时按约定解析，具体以实现为准。

### 附件 URL 规则

- **原始文件**：`/anon/static/upload/{filetype}/{baseName}`，URL **不包含后缀**，避免按静态规则 404  
- **图片格式转换**：`/anon/static/upload/{filetype}/{baseName}/{format}`，支持 `webp`、`jpg`、`jpeg`、`png`  
- 转换结果缓存于 `Upload/{filetype}/processed/`，避免重复处理  

示例：

```text
# 原始图片
GET /anon/static/upload/image/a1b2c3d4e5f67890-1760000000

# WebP 格式
GET /anon/static/upload/image/a1b2c3d4e5f67890-1760000000/webp
```

与 [API 端点](/api/endpoints#静态文件) 中静态文件小节保持一致。
