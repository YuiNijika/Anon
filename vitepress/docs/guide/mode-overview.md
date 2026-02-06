# 模式对比 API vs CMS

本节说明API模式与CMS模式的差异以及安装后按模式推荐的下一步，适用于通用场景。

Anon Framework 支持两种运行模式：**API 模式**与**CMS 模式**。安装时选择其一，后续可在配置中切换。下表帮助快速选边。

## 模式对比表

| 维度 | API 模式 | CMS 模式 |
|------|----------|----------|
| **响应格式** | JSON | HTML（可接受 `Accept: application/json` 时仍可返回 JSON） |
| **路由来源** | `app/Router/` 或 `useRouter.php`、自动路由 | 数据库 `options.routes` 或 `useApp.php` 中的 `app.cms.routes` |
| **是否模板** | 不支持 | 支持主题模板（`app/Theme/`） |
| **错误页形式** | JSON 格式 | HTML 格式（可自定义） |
| **Content-Type** | `application/json` | `text/html; charset=utf-8` |
| **典型用途** | 纯接口、移动端/前端对接、微服务 | 内容站、博客、官网、后台+前台一体 |

## 安装后下一步

根据您选择的模式，建议按以下顺序阅读：

### 选择 API 模式时

1. **[API 模式概述](/guide/api/overview)** — 了解 API 模式特点与配置要点  
2. **[路由系统](/guide/api/routing)** — 配置路由与请求入口  
3. **[API 参考](/api/reference)**、**[API 端点](/api/endpoints)** — 查阅可用接口与端点  
4. **[用户认证](/guide/api/authentication)**、**[安全功能](/guide/api/security)** — 按需配置认证与安全  

### 选择 CMS 模式时

1. **[CMS 模式概述](/guide/cms/overview)** — 了解 CMS 模式与 API 共存方式  
2. **[路由与页面](/guide/cms/routes)** — 配置 CMS 路由与页面映射  
3. **[主题系统与开发](/guide/cms/theme-system)** — 选择/开发主题  
4. **[管理后台](/guide/cms/admin)** — 后台入口、功能与 CMS 管理端点  

## 术语说明

文档中统一使用：

- **API 模式**：纯接口模式，响应为 JSON  
- **CMS 模式**：内容管理模式，支持主题与 HTML 渲染  

二者可在同一项目中并存：CMS 模式下可为 API 路由配置前缀（如 `/api`），详见 [CMS 模式概述](/guide/cms/overview#与-api-模式共存)。
