---
outline: deep
---

# 前端框架集成

一句话：统一的前端认证实现，支持 Vue、React、Next.js、Nuxt 等框架。

## 支持的框架

- **Vue 3** - 使用 Pinia 状态管理
- **React** - 使用 React Hooks
- **Next.js** - 使用 React Hooks，支持 SSR
- **Nuxt 4** - 使用 Pinia 状态管理，支持 SSR

## 统一的认证流程

所有框架都使用相同的认证流程和接口：

### 1. 登录/注册流程

```typescript
// 1. 调用登录/注册接口
const res = await api.post('/auth/login', { username, password })

// 2. 设置返回的 token
if (res.data?.token) {
  localStorage.setItem('token', res.data.token)  // Vue/React/Next.js
  // 或
  useCookie('token').value = res.data.token      // Nuxt
}

// 3. 通过 /user/info 获取完整用户信息
const userRes = await api.get('/user/info')
if (userRes.data) {
  setUser(userRes.data)  // 更新用户状态
}
```

### 2. 检查登录状态流程

```typescript
// 1. 检查登录状态
const res = await api.get('/auth/check-login')
const loggedIn = res.data?.loggedIn ?? res.data?.logged_in ?? false

if (loggedIn) {
  // 2. 获取 token
  const tokenRes = await api.get('/auth/token')
  if (tokenRes.data?.token) {
    localStorage.setItem('token', tokenRes.data.token)
  }
  
  // 3. 获取用户信息
  const userRes = await api.get('/user/info')
  if (userRes.data) {
    setUser(userRes.data)
  }
}
```

## API 接口说明

### 认证相关接口

| 接口 | 方法 | 说明 | 需要登录 |
|------|------|------|----------|
| `/auth/login` | POST | 用户登录 | 否 |
| `/auth/register` | POST | 用户注册 | 否 |
| `/auth/logout` | POST | 用户登出 | 否 |
| `/auth/check-login` | GET | 检查登录状态 | 否 |
| `/auth/token` | GET | 获取 Token | 是 |
| `/user/info` | GET | 获取用户信息 | 是 |

### 响应格式

所有接口统一返回格式：

```typescript
interface ApiResponse<T = any> {
  code: number      // 200 表示成功
  message: string   // 响应消息
  data?: T          // 响应数据
}
```

### 登录接口响应

```typescript
interface LoginResponse {
  token?: string    // Token，登录成功时返回
  user?: UserInfo   // 基本用户信息
}
```

### 检查登录状态响应

```typescript
interface CheckLoginResponse {
  loggedIn: boolean    // 是否已登录
  logged_in: boolean   // 兼容字段
}
```

### Token 接口响应

```typescript
interface TokenResponse {
  token: string    // Token 字符串
}
```

### 用户信息响应

```typescript
interface UserInfo {
  uid: number      // 用户ID
  name: string     // 用户名
  email?: string   // 邮箱
  [key: string]: any  // 其他字段
}
```

## Vue 3 使用

### 安装依赖

```bash
cd client/vue
pnpm install
```

### 使用认证

```vue
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// 登录
const handleLogin = async () => {
  try {
    await auth.login({ username, password })
    // 登录成功，用户信息已自动更新
  } catch (error) {
    console.error('登录失败', error)
  }
}

// 检查登录状态
onMounted(async () => {
  await auth.checkLogin()
})
</script>

<template>
  <div v-if="auth.isAuthenticated">
    <p>欢迎，{{ auth.user?.name }}</p>
    <button @click="auth.logout">退出登录</button>
  </div>
  <div v-else>
    <!-- 登录表单 -->
  </div>
</template>
```

### Store 结构

```typescript
const auth = useAuthStore()

// 状态
auth.user          // 用户信息，UserInfo 或 null
auth.loading       // 加载状态
auth.error         // 错误信息

// 计算属性
auth.isAuthenticated  // 是否已登录，boolean

// 方法
auth.login(data)      // 登录
auth.register(data)   // 注册
auth.logout()         // 登出
auth.checkLogin()     // 检查登录状态，返回 Promise<boolean>
```

## React 使用

### 安装依赖

```bash
cd client/react
pnpm install
```

### 使用认证

```tsx
import { useAuth } from '@/hooks/useAuth'

function LoginPage() {
  const { user, isAuthenticated, login, logout, checkLogin } = useAuth()

  useEffect(() => {
    checkLogin()
  }, [])

  const handleLogin = async () => {
    try {
      await login({ username, password })
      // 登录成功
    } catch (error) {
      console.error('登录失败', error)
    }
  }

  if (isAuthenticated) {
    return (
      <div>
        <p>欢迎，{user?.name}</p>
        <button onClick={logout}>退出登录</button>
      </div>
    )
  }

  return <LoginForm onSubmit={handleLogin} />
}
```

### Hook 返回值

```typescript
const {
  user,              // 用户信息，UserInfo 或 null
  isAuthenticated,   // 是否已登录，boolean
  loading,           // 加载状态
  error,             // 错误信息
  login,             // 登录方法
  register,          // 注册方法
  logout,            // 登出方法
  checkLogin,        // 检查登录状态方法
} = useAuth()
```

## Next.js 使用

### 安装依赖

```bash
cd client/next
pnpm install
```

### 使用认证

```tsx
'use client'

import { useAuth } from '@/hooks/useAuth'

export default function LoginPage() {
  const { user, isAuthenticated, login, logout, checkLogin } = useAuth()

  useEffect(() => {
    checkLogin()
  }, [])

  // ... 同 React 使用方式
}
```

**注意：** Next.js 使用 `'use client'` 指令确保在客户端运行。

## Nuxt 4 使用

### 安装依赖

```bash
cd client/nuxt
pnpm install
```

### 使用认证

```vue
<script setup lang="ts">
const auth = useAuth()

// 登录
const handleLogin = async () => {
  try {
    await auth.login({ username, password })
  } catch (error) {
    console.error('登录失败', error)
  }
}

// 检查登录状态
onMounted(async () => {
  await auth.checkLogin()
})
</script>

<template>
  <div v-if="auth.isAuthenticated">
    <p>欢迎，{{ auth.user?.name }}</p>
    <button @click="auth.logout">退出登录</button>
  </div>
</template>
```

### Composable 返回值

```typescript
const auth = useAuth()

// 状态
auth.user          // 用户信息，UserInfo 或 null
auth.loading       // 加载状态
auth.error         // 错误信息

// 计算属性
auth.isAuthenticated  // 是否已登录，boolean

// 方法
auth.login(data)      // 登录
auth.register(data)   // 注册
auth.logout()         // 登出
auth.checkLogin()     // 检查登录状态，返回 Promise<boolean>
```

## 配置 API 地址

### Vue / React / Next.js

在配置文件中定义 API 地址数组：

```typescript
// vite.config.ts 或 next.config.ts
const API_BASE_URLS = [
  'http://anon.localhost:8080',  // 生产环境
  '/anon-dev-server'              // 代理模式
]

// 根据环境选择
const API_BASE_URL = import.meta.env.DEV 
  ? API_BASE_URLS[1]  // 开发环境使用代理
  : API_BASE_URLS[0]  // 生产环境使用直接地址
```

### Nuxt

在 `nuxt.config.ts` 中配置：

```typescript
const apiBaseUrls = [
  'http://anon.localhost:8080',
  '/anon-dev-server'
]

const apiBaseUrl = apiBaseUrls[1]  // 修改索引切换地址

export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      apiBaseUrl: apiBaseUrl,
      apiBackendUrl: apiBaseUrls[0],
    }
  },
  vite: {
    server: {
      proxy: {
        '/anon-dev-server': {
          target: apiBaseUrls[0],
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/anon-dev-server/, ''),
        },
      },
    },
  },
})
```

## Token 管理

### 自动处理

所有框架的 `useApi` 或 API 封装都会自动：

1. **发送 Token**：从 Cookie 或 localStorage 读取 Token，自动添加到请求头 `X-API-Token`
2. **保存 Token**：登录接口返回 Token 时，自动保存到 Cookie 或 localStorage
3. **刷新 Token**：如果后端返回新 Token，通过响应头 `X-New-Token`，自动更新本地 Token

### 手动管理

```typescript
// 设置 Token
localStorage.setItem('token', token)  // Vue/React/Next.js
useCookie('token').value = token      // Nuxt

// 获取 Token
const token = localStorage.getItem('token')
const token = useCookie('token').value

// 清除 Token
localStorage.removeItem('token')
useCookie('token').value = null
```

## 状态持久化

### Vue / Nuxt (Pinia)

使用 `pinia-plugin-persistedstate` 自动持久化用户信息：

```typescript
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as UserInfo | null,
    // ...
  }),
  persist: {
    key: 'auth-store',
    storage: localStorage,
    paths: ['user'],  // 只持久化 user
  },
})
```

### React / Next.js

需要手动实现持久化，或使用第三方库如 `zustand-persist`。

## 错误处理

所有框架统一处理错误：

```typescript
try {
  await auth.login({ username, password })
} catch (error) {
  // error 是 Error 实例
  console.error(error.message)  // 显示错误消息
  
  // 或使用 auth.error
  if (auth.error) {
    console.error(auth.error)
  }
}
```

## 最佳实践

1. **页面加载时检查登录状态**

   ```typescript
   onMounted(async () => {
     await auth.checkLogin()
   })
   ```

2. **登录成功后自动获取用户信息**
   - 登录接口返回 Token 后，自动调用 `/user/info` 获取完整用户信息
   - 无需手动处理

3. **使用统一的 API 封装**
   - 所有框架都提供了 `useApi` composable/hook
   - 自动处理 Token、错误处理、响应格式

4. **响应式状态管理**
   - Vue/Nuxt 使用 Pinia，自动响应式更新
   - React/Next.js 使用 useState，自动触发重新渲染

## 常见问题

### Q: Token 在哪里存储？

- **Vue/React/Next.js**: `localStorage`
- **Nuxt**: Cookie，使用 `useCookie`

### Q: 如何切换 API 地址？

修改配置文件中的索引或环境变量即可。

### Q: SSR 时如何处理？

- **Nuxt**: 自动处理，SSR 时跳过需要 Cookie 的请求
- **Next.js**: 使用 `'use client'` 确保在客户端运行

### Q: 如何自定义错误消息？

在 API 封装中修改错误处理逻辑，或使用后端的钩子系统自定义消息。

### Q: useApi 和 useApiAdmin 的区别？

- **useApi**: 使用动态 API 前缀，从 `/anon/cms/api-prefix` 获取，适用于通用 API 调用
- **useApiAdmin**: 固定使用 `/anon/cms` 前缀，专门用于 CMS 管理后台，不受 API 前缀配置影响

**注意**：CMS 管理后台的详细文档请参考 `server/dev_cms_admin/README.md`。
