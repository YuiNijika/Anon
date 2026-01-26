import { useMemo, useCallback } from 'react'

interface ApiResponse<T = any> {
  code: number
  message: string
  data?: T
}

const API_BASE_URLS = {
  dev: '/anon-dev-server',
  prod: import.meta.env.VITE_API_BASE_URL || 'http://anon.localhost:8080',
} as const

const DEFAULT_API_BASE_URL = import.meta.env.DEV ? API_BASE_URLS.dev : API_BASE_URLS.prod

let cachedApiPrefix: string | null = null
let prefixPromise: Promise<string> | null = null
let tokenPromise: Promise<string | null> | null = null
let tokenRefreshPromise: Promise<string | null> | null = null

async function getApiPrefix(): Promise<string> {
  if (cachedApiPrefix) {
    return cachedApiPrefix
  }

  if (prefixPromise) {
    return prefixPromise
  }

  prefixPromise = (async (): Promise<string> => {
    try {
      const configUrl = import.meta.env.DEV
        ? `${DEFAULT_API_BASE_URL}/anon/cms/api-prefix`
        : '/anon/cms/api-prefix'
      const res = await fetch(configUrl, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
      })
      const data: ApiResponse<{ apiPrefix?: string }> = await res.json()

      if (data.code === 200 && data.data?.apiPrefix) {
        const serverPrefix = data.data.apiPrefix
        const normalizedPrefix = serverPrefix.startsWith('/') ? serverPrefix : `/${serverPrefix}`

        if (import.meta.env.DEV) {
          cachedApiPrefix = `${DEFAULT_API_BASE_URL}${normalizedPrefix}`
        } else {
          cachedApiPrefix = normalizedPrefix
        }
        return cachedApiPrefix
      }
    } catch {
      // 静默失败
    }

    const fallback = DEFAULT_API_BASE_URL
    cachedApiPrefix = fallback
    return fallback
  })()

  return prefixPromise
}

function buildQueryString(params?: Record<string, any>): string {
  if (!params) return ''
  const entries = Object.entries(params)
    .filter(([_, v]) => v != null)
    .map(([k, v]) => [k, String(v)])
  return entries.length > 0 ? `?${new URLSearchParams(entries).toString()}` : ''
}

async function fetchToken(forceRefresh = false): Promise<string | null> {
  try {
    const baseUrl = await getApiPrefix()
    const url = `${baseUrl}/auth/token`
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
    }

    const existingToken = localStorage.getItem('token')
    if (existingToken && !forceRefresh) {
      (headers as Record<string, string>)['X-API-Token'] = existingToken
    }

    const res = await fetch(url, {
      method: 'GET',
      headers,
      credentials: 'include',
    })
    const data: ApiResponse<{ token?: string }> = await res.json()

    if (data.code === 200 && data.data?.token) {
      localStorage.setItem('token', data.data.token)
      return data.data.token
    }
  } catch {
    // 静默失败
  }
  return null
}

export async function ensureToken(forceRefresh = false): Promise<string | null> {
  if (!forceRefresh) {
    const existing = localStorage.getItem('token')
    if (existing) return existing
  }

  if (forceRefresh) {
    if (tokenRefreshPromise) {
      const token = await tokenRefreshPromise
      return token || localStorage.getItem('token')
    }
    tokenRefreshPromise = fetchToken(true).finally(() => {
      tokenRefreshPromise = null
    })
    const token = await tokenRefreshPromise
    return token || localStorage.getItem('token')
  }

  if (tokenPromise) {
    const token = await tokenPromise
    return token || localStorage.getItem('token')
  }

  tokenPromise = fetchToken(false).finally(() => {
    tokenPromise = null
  })

  const token = await tokenPromise
  return token || localStorage.getItem('token')
}

/**
 * API Hook
 */
export function useApi() {
  /**
   * 发送请求
   */
  const request = useCallback(
    async <T = any>(
      endpoint: string,
      options: RequestInit = {},
      retryOnAuth = true
    ): Promise<ApiResponse<T>> => {
      const baseUrl = await getApiPrefix()
      const url = `${baseUrl}${endpoint}`
      const headers: HeadersInit = {
        'Content-Type': 'application/json',
        ...options.headers,
      }

      let token = localStorage.getItem('token')
      if (!token) {
        token = await ensureToken()
      }

      if (!token) {
        token = localStorage.getItem('token')
      }

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

        const isAuthError = data.code === 401 || data.code === 403 || res.status === 401 || res.status === 403

        if (isAuthError) {
          localStorage.removeItem('token')
          const newToken = await ensureToken(true)
          if (retryOnAuth && newToken) {
            const retryHeaders: Record<string, string> = {
              'Content-Type': 'application/json',
              'X-API-Token': newToken,
              ...(options.headers as Record<string, string>),
            }
            const retryRes = await fetch(url, {
              ...options,
              headers: retryHeaders,
              credentials: 'include',
            })
            const retryData: ApiResponse<T> = await retryRes.json()
            if (retryData.code === 200) {
              return retryData
            }
          }
        }

        if (data.code !== 200) {
          throw new Error(data.message || '请求失败')
        }

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
          if (isAuthError) {
            localStorage.removeItem('token')
            const newToken = await ensureToken(true)
            if (retryOnAuth && newToken) {
              try {
                return request<T>(endpoint, options, false)
              } catch {
                // 重试失败，继续抛出原错误
              }
            }
          }
          throw error
        }
        throw new Error('网络请求失败')
      }
    },
    []
  )

  return useMemo(
    () => ({
      request,
      get: <T = any>(endpoint: string, params?: Record<string, any>) => {
        const query = buildQueryString(params)
        return request<T>(`${endpoint}${query}`, { method: 'GET' })
      },
      post: <T = any>(endpoint: string, body?: any) =>
        request<T>(endpoint, { method: 'POST', body: JSON.stringify(body) }),
      put: <T = any>(endpoint: string, body?: any) =>
        request<T>(endpoint, { method: 'PUT', body: JSON.stringify(body) }),
      delete: <T = any>(endpoint: string) =>
        request<T>(endpoint, { method: 'DELETE' }),
    }),
    [request]
  )
}
