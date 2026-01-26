# CMS 管理后台

Anon Framework CMS 管理后台前端项目，基于 Vue 3 + Vite + TypeScript + Nuxt UI。

## 项目结构

```
dev_cms_admin/
├── src/
│   ├── composables/        # 组合式函数
│   │   ├── useApi.ts       # 通用 API 客户端
│   │   ├── useApiAdmin.ts  # CMS 管理后台 API 客户端
│   │   └── ...
│   ├── stores/            # Pinia 状态管理
│   │   └── auth.ts        # 认证状态
│   ├── views/             # 页面组件
│   │   ├── console/       # 控制台页面
│   │   └── settings/      # 设置页面
│   └── components/         # 公共组件
├── README.md
└── package.json
```

## 快速开始

### 安装依赖

```sh
pnpm install
```

### 开发模式

```sh
pnpm dev
```

### 构建生产版本

```sh
pnpm build
```

## API 客户端

### useApiAdmin

CMS 管理后台使用独立的 API 客户端 `useApiAdmin`，直接使用 `/anon/cms` 前缀，不受 `useApi` 的 API 前缀影响。

#### 基本使用

```typescript
import { useApiAdmin } from '@/composables/useApiAdmin'

const api = useApiAdmin()

// 获取统计数据
const stats = await api.getStatistics()

// 获取基本设置
const settings = await api.getBasicSettings()

// 更新基本设置
await api.updateBasicSettings({
  title: '新站点名称',
  description: '新站点描述',
  keywords: '关键词',
  allow_register: false,
  api_prefix: '/api',
  api_enabled: false,
  upload_allowed_types: {
    image: 'gif,jpg,jpeg,png',
    media: 'mp3,mp4',
    document: 'pdf,doc',
    other: ''
  }
})

// 获取配置信息
const config = await api.getConfig()

// 获取 Token
const tokenRes = await api.getToken()

// 检查登录状态
const loginRes = await api.checkLogin()

// 获取用户信息
const userRes = await api.getUserInfo()
```

#### 可用方法

| 方法 | 说明 | 需要登录 | 需要管理员 |
|------|------|----------|------------|
| `getStatistics()` | 获取统计数据 | 是 | 是 |
| `getBasicSettings()` | 获取基本设置 | 是 | 是 |
| `updateBasicSettings(settings)` | 更新基本设置 | 是 | 是 |
| `getConfig()` | 获取配置信息 | 否 | 否 |
| `getToken()` | 获取 Token | 是 | 否 |
| `checkLogin()` | 检查登录状态 | 否 | 否 |
| `getUserInfo()` | 获取用户信息 | 是 | 否 |
| `get(endpoint, params?)` | GET 请求 | - | - |
| `post(endpoint, body?)` | POST 请求 | - | - |
| `put(endpoint, body?)` | PUT 请求 | - | - |
| `delete(endpoint)` | DELETE 请求 | - | - |

#### Token 自动管理

`useApiAdmin` 会自动处理 Token：

1. **自动获取**：首次请求时自动从 `/anon/cms/admin/auth/token` 获取 Token（需要登录）
2. **自动刷新**：Token 过期时自动刷新并重试请求
3. **自动重试**：401/403 错误时自动刷新 Token 并重试一次

#### 类型定义

```typescript
interface StatisticsData {
  posts: number
  comments: number
  attachments: number
  categories: number
  tags: number
  users: number
  published_posts: number
  draft_posts: number
  pending_comments: number
  approved_comments: number
  attachments_size?: number
  total_views?: number
}

interface BasicSettings {
  title: string
  description: string
  keywords: string
  allow_register: boolean
  api_prefix: string
  api_enabled: boolean
  upload_allowed_types: {
    image: string
    media: string
    document: string
    other: string
  }
}

interface UserInfo {
  uid: number
  name: string
  email?: string
  display_name?: string
  displayName?: string
  public_name?: string
  publicName?: string
  avatar?: string
  avatar_url?: string
  group?: string
  [key: string]: any
}
```

## 认证

### 使用认证 Store

```typescript
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// 检查登录状态
onMounted(async () => {
  await auth.checkLogin()
})

// 登录状态
auth.isAuthenticated  // boolean
auth.user             // UserInfo | null
auth.loading          // boolean
auth.error            // string | null
```

### 认证流程

1. **检查登录状态**：调用 `auth.checkLogin()` 检查用户是否已登录
2. **获取配置**：如果已登录，获取配置信息（包括 token 是否启用）
3. **获取 Token**：如果 token 启用，获取用户 token
4. **获取用户信息**：获取完整的用户信息

## API 端点

所有管理后台 API 使用 `/anon/cms/admin` 前缀：

- `GET /anon/cms/admin/auth/token` - 获取 Token（需要登录）
- `GET /anon/cms/admin/auth/check-login` - 检查登录状态（无需登录）
- `GET /anon/cms/admin/user/info` - 获取用户信息（需要登录）
- `GET /anon/cms/admin/config` - 获取配置信息（无需登录）
- `GET /anon/cms/admin/statistics` - 获取统计数据（需要管理员权限）
- `GET /anon/cms/admin/settings/basic` - 获取基本设置（需要管理员权限）
- `POST /anon/cms/admin/settings/basic` - 更新基本设置（需要管理员权限）

## 开发规范

### IDE 设置

推荐使用 VS Code + Vue (Official) 扩展，并禁用 Vetur。

### 浏览器设置

- Chromium 浏览器：安装 [Vue.js devtools](https://chromewebstore.google.com/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd)
- Firefox：安装 [Vue.js devtools](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)

### TypeScript 支持

项目使用 `vue-tsc` 进行类型检查，编辑器需要 Volar 扩展来支持 `.vue` 文件的类型。

## 常见问题

### Q: useApi 和 useApiAdmin 的区别？

- **useApi**: 使用动态 API 前缀（从 `/anon/cms/api-prefix` 获取），适用于通用 API 调用
- **useApiAdmin**: 固定使用 `/anon/cms` 前缀，专门用于 CMS 管理后台，不受 API 前缀配置影响

### Q: Token 在哪里存储？

Token 存储在 `localStorage` 中，键名为 `token`。

### Q: 如何扩展配置信息？

在后端使用 `config` 钩子扩展配置字段：

```php
Anon_System_Hook::add_filter('config', function($config) {
    $config['customField'] = 'customValue';
    return $config;
});
```

### Q: 如何自定义错误处理？

在 `useApiAdmin.ts` 中修改 `request` 函数的错误处理逻辑，或使用后端的钩子系统自定义错误消息。
