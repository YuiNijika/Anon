# 管理后台

本节说明CMS模式下的管理后台入口、功能清单、前端集成与CMS管理端点索引，包含静态资源与附件说明，仅适用于CMS模式。

CMS模式提供完整的管理后台，用于配置站点、主题、插件、内容与附件等。后台通过统一前缀访问，前端可独立部署并调用CMS管理端点。

## 后台入口

- **路由前缀**：`/admin`  
- **访问示例**：`https://your-domain.com/admin`，具体入口以部署的前端为准，独立 Admin SPA 的根路径即后台首页。

管理后台及下文所列端点仅在CMS模式下可用，API模式无CMS后台与CMS专用端点。

## 功能清单

| 功能 | 说明 |
|------|------|
| **设置** | 基本设置如站点名称、描述、关键词、API 前缀等，主题切换与主题设置项 |
| **主题** | 主题列表、切换主题、主题选项，存于 options 表 theme:主题名 |
| **插件** | 插件列表、上传、启用、停用、删除，插件设置存于 options 表 plugin:插件名 |
| **文章** | 文章/页面增删改查，分类与标签关联 |
| **评论** | 评论列表、高级筛选（状态/类型/根或回复/关键词/日期）、编辑内容、通过/待审核/垃圾/回收站、删除；展示 IP、解析 UA（浏览器·系统）、回复关系与子评论高亮，详见 [评论功能](./comments.md) |
| **分类** | 分类增删改查 |
| **标签** | 标签增删改查 |
| **附件** | 附件上传、列表、删除，按类型分类存储 |
| **统计** | 文章、评论、附件、用户等统计数据 |
| **访问日志** | 访问日志查看、统计、筛选（IP、路径、类型、日期范围等），详见 [访问日志](#访问日志) |

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
- **评论**：`GET/PUT/DELETE /anon/cms/admin/comments`（列表、更新状态/内容、删除）
- **访问日志**：`GET /anon/cms/admin/access-logs`（列表）、`GET /anon/cms/admin/access-logs/statistics`（统计）

## 访问日志

访问日志功能用于记录和统计网站的访问情况，帮助管理员了解网站流量和访问模式。

### 功能说明

- **自动记录**：系统自动记录每次页面访问（排除静态资源、API 和后台管理页面）
- **访问统计**：提供访问量统计、独立 IP 统计、热门页面排行等功能
- **高级筛选**：支持按 IP、路径、类型、日期范围等条件筛选日志
- **独立开关**：通过 `access_log_enabled` 选项控制是否启用访问日志

### 启用/禁用访问日志

访问日志的开关存储在数据库 `options` 表中，键名为 `access_log_enabled`：

- **启用**：`Anon_Cms_AccessLog::enable()`
- **禁用**：`Anon_Cms_AccessLog::disable()`
- **检查状态**：通过管理后台「设置」→「权限设置」中的「访问日志」开关控制

**重要提示**：
- 访问日志开关（`access_log_enabled`）**仅影响** `AccessLog` 模块
- **不影响**其他日志系统（如 `Debug` 日志）
- **不影响**文章阅读量功能（`posts.views` 字段）

### 访问日志记录内容

每条访问日志包含以下信息：

| 字段 | 说明 |
|------|------|
| `url` | 完整请求 URL |
| `path` | 请求路径 |
| `method` | HTTP 方法（GET、POST 等） |
| `type` | 请求类型（page、api、static） |
| `ip` | 客户端 IP 地址 |
| `user_agent` | User-Agent 字符串 |
| `referer` | 来源页面 URL |
| `status_code` | HTTP 状态码 |
| `response_time` | 响应时间（毫秒） |
| `created_at` | 访问时间 |

### 自动排除的路径

系统会自动排除以下路径，不记录访问日志：

- `/anon-dev-server` - 开发服务器
- `/anon/cms/admin` - 管理后台
- `/anon/install` - 安装页面
- `/anon/static` - 静态资源
- `/assets` - 资源文件
- `/static` - 静态文件
- `/.well-known` - 标准路径
- `/favicon.ico` - 网站图标
- `/robots.txt` - 爬虫协议
- API 路径（根据 `apiPrefix` 配置）
- curl、wget 等命令行工具的请求
- 敏感文件路径（如 `.env`、`.git` 等）

### 访问日志统计

访问日志统计功能提供：

- **总访问量**：指定时间范围内的总访问次数
- **独立 IP 数**：访问的独立 IP 地址数量
- **热门页面**：按访问次数排序的前 10 个页面

### API 端点

- `GET /anon/cms/admin/access-logs` - 获取访问日志列表
- `GET /anon/cms/admin/access-logs/statistics` - 获取访问日志统计

详细端点说明请参考 [API 端点 - 访问日志](/api/endpoints#访问日志接口)。  

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
