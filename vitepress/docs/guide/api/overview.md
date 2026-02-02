# API 模式概述

**本节说明**：何时选择 API 模式、特点与目录/配置要点，并引导到路由与 API 参考。**适用**：仅 API 模式。

API 模式是 Anon Framework 的默认模式，面向纯接口开发：所有响应为 JSON，无模板渲染，适合对接前端、移动端或作为微服务。

## 何时选择 API 模式

- 只提供 REST/JSON 接口，不直接输出 HTML 页面  
- 前端为独立 SPA（Vue、React 等）或移动端，由前端负责页面与路由  
- 需要轻量、低内存占用、高并发的接口服务  

## 特点概览

- **响应格式**：统一 JSON，`Content-Type: application/json`  
- **路由**：基于 `app/Router/` 目录或 `useRouter.php`，支持自动路由  
- **无模板**：不加载主题与模板引擎，错误页也为 JSON  
- **认证与安全**：内置 Token、CSRF、验证码等，见 [用户认证](./authentication.md)、[安全功能](./security.md)  

## 目录与配置要点

- **模式配置**：`server/.env.php` 中 `ANON_APP_MODE` 为 `api`（或 `server/app/useApp.php` 中 `app.mode` 为 `api`）  
- **路由目录**：`server/app/Router/`，文件名与目录结构映射为 URL 路径  
- **应用配置**：`server/app/useApp.php` 中可配置 `autoRouter`、`token`、`security` 等  

详细路由规则见 [路由系统](./routing.md)，接口列表见 [API 参考](/api/reference) 与 [API 端点](/api/endpoints)。
