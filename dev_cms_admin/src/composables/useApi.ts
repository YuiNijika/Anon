interface ApiResponse<T = any> {
  code: number
  message: string
  data?: T
}

import { useNotify } from '@/composables/useNotify'

type ToastMode = 'none' | 'error' | 'success' | 'all'

interface RequestMeta {
  toast?: ToastMode
}

function shouldToastSuccess(method: string, toast?: ToastMode): boolean {
  if (toast === 'none' || toast === 'error') return false
  if (toast === 'all' || toast === 'success') return true
  // 非 GET 才弹成功，避免刷屏
  return method.toUpperCase() !== 'GET'
}

function shouldToastError(toast?: ToastMode): boolean {
  return toast !== 'none'
}

const API_BASE_URLS = {
  dev: '/anon-dev-server',
  prod: '/api',
} as const

const DEFAULT_API_BASE_URL = import.meta.env.DEV ? API_BASE_URLS.dev : API_BASE_URLS.prod

// API 前缀缓存
let cachedApiPrefix: string | null = null
let prefixPromise: Promise<string> | null = null

/**
 * 获取 API 前缀
 */
async function getApiPrefix(): Promise<string> {
  if (cachedApiPrefix) {
    return cachedApiPrefix
  }

  if (prefixPromise) {
    return prefixPromise
  }

  prefixPromise = (async (): Promise<string> => {
    try {
      const configUrl = import.meta.env.DEV ? `${DEFAULT_API_BASE_URL}/anon/cms/api-prefix` : '/anon/cms/api-prefix'
      const res = await fetch(configUrl, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
      })
      const data: ApiResponse<{ apiPrefix?: string }> = await res.json()

      if (data.code === 200 && data.data?.apiPrefix) {
        const serverPrefix = data.data.apiPrefix
        // 确保前缀以 / 开头
        const normalizedPrefix = serverPrefix.startsWith('/') ? serverPrefix : `/${serverPrefix}`

        // 开发环境下：使用代理路径 + 获取到的前缀
        // 生产环境下：直接使用获取到的前缀
        if (import.meta.env.DEV) {
          cachedApiPrefix = `${DEFAULT_API_BASE_URL}${normalizedPrefix}`
        } else {
          cachedApiPrefix = normalizedPrefix
        }
        return cachedApiPrefix
      }
    } catch {
      // 静默失败，使用默认值
    }

    // 如果获取失败，使用默认值
    cachedApiPrefix = DEFAULT_API_BASE_URL
    return cachedApiPrefix
  })()

  return prefixPromise
}

/**
 * 刷新 Token
 */
async function refreshToken(baseUrl: string): Promise<boolean> {
  try {
    const res = await fetch(`${baseUrl}/auth/token`, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
    })
    const data: ApiResponse<{ token?: string }> = await res.json()

    if (data.code === 200 && data.data?.token) {
      localStorage.setItem('token', data.data.token)
      return true
    }
  } catch {
    // 静默失败
  }
  return false
}

/**
 * 清除认证状态
 */
async function clearAuth(): Promise<void> {
  localStorage.removeItem('token')
  try {
    const { useAuthStore } = await import('../stores/auth')
    const authStore = useAuthStore()
    // 清除用户状态，这会触发 isAuthenticated 更新
    authStore.user = null
    authStore.error = null
  } catch {
    // 静默失败，避免循环依赖
  }
}

/**
 * 构建查询字符串
 */
function buildQueryString(params?: Record<string, any>): string {
  if (!params) return ''
  const entries = Object.entries(params)
    .filter(([_, v]) => v != null)
    .map(([k, v]) => [k, String(v)])
  return entries.length > 0 ? `?${new URLSearchParams(entries).toString()}` : ''
}

/**
 * API 客户端
 */
export function useApi() {
  const notify = useNotify()

  /**
   * 发送请求
   */
  async function request<T = any>(
    endpoint: string,
    options: RequestInit = {},
    retryOnAuth = true,
    meta: RequestMeta = {}
  ): Promise<ApiResponse<T>> {
    let toasted = false
    const baseUrl = await getApiPrefix()
    const url = `${baseUrl}${endpoint}`
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      ...options.headers,
    }

    const token = localStorage.getItem('token')
    if (token) {
      (headers as Record<string, string>)['X-API-Token'] = token
    }

    try {
      const res = await fetch(url, {
        ...options,
        headers,
        credentials: 'include',
      })
      const data: ApiResponse<T> = await res.json()

      // 处理认证错误
      const isAuthError = data.code === 401 || data.code === 403 || res.status === 401 || res.status === 403

      if (isAuthError) {
        // 尝试刷新 token
        if (retryOnAuth && (await refreshToken(baseUrl))) {
          return request<T>(endpoint, options, false)
        }
        await clearAuth()
      }

      if (data.code !== 200) {
        if (shouldToastError(meta.toast)) {
          notify.error(data.message || '请求失败')
          toasted = true
        }
        throw new Error(data.message || '请求失败')
      }

      const method = (options.method || 'GET').toUpperCase()
      if (data.message && shouldToastSuccess(method, meta.toast)) {
        notify.success(data.message)
        toasted = true
      }

      // 保存返回的 token
      if (data.data && typeof data.data === 'object' && 'token' in data.data) {
        const tokenValue = (data.data as { token?: string }).token
        if (tokenValue) {
          localStorage.setItem('token', tokenValue)
        }
      }

      return data
    } catch (error) {
      if (error instanceof Error) {
        const isAuthError = error.message.includes('401') || error.message.includes('403')
        if (isAuthError && retryOnAuth && (await refreshToken(baseUrl))) {
          return request<T>(endpoint, options, false, meta)
        }
        if (isAuthError) {
          await clearAuth()
        }
        // 避免同一次请求重复弹 toast
        if (!toasted && shouldToastError(meta.toast)) notify.error(error.message || '网络请求失败')
        throw error
      }
      if (!toasted && shouldToastError(meta.toast)) notify.error('网络请求失败')
      throw new Error('网络请求失败')
    }
  }

  return {
    get: <T = any>(endpoint: string, params?: Record<string, any>) => {
      const query = buildQueryString(params)
      return request<T>(`${endpoint}${query}`, { method: 'GET' }, true, { toast: 'error' })
    },
    post: <T = any>(endpoint: string, body?: any) =>
      request<T>(endpoint, { method: 'POST', body: JSON.stringify(body) }, true, { toast: 'all' }),
    put: <T = any>(endpoint: string, body?: any) =>
      request<T>(endpoint, { method: 'PUT', body: JSON.stringify(body) }, true, { toast: 'all' }),
    delete: <T = any>(endpoint: string) =>
      request<T>(endpoint, { method: 'DELETE' }, true, { toast: 'all' }),
  }
}
