import { useMemo, useCallback } from 'react'
import { useApi, ensureToken, isNetworkError } from './useApi'

interface ApiResponse<T = any> {
  code: number
  message: string
  data?: T
}

export function useApiAdmin() {
  const api = useApi()

  /**
   * API 请求（使用 apiPrefix）
   * 使用 useApi 的 getApiPrefix 逻辑
   */
  const apiRequest = useCallback(
    async <T = any>(
      endpoint: string,
      options: RequestInit = {},
      params?: Record<string, any>
    ) => {
      const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`
      const query = params
        ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([_, v]) => v != null)
            .map(([k, v]) => [k, String(v)])
        ).toString()
        : ''

      const { request } = api
      return request<T>(`${normalizedEndpoint}${query}`, options)
    },
    [api]
  )

  /**
   * 管理端 API 请求（使用 /anon/cms/admin 前缀）
   */
  const adminRequest = useCallback(
    async <T = any>(
      endpoint: string,
      options: RequestInit = {},
      params?: Record<string, any>
    ) => {
      const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`
      const adminPrefix = '/anon/cms/admin'
      const path = normalizedEndpoint.startsWith(adminPrefix)
        ? normalizedEndpoint
        : `${adminPrefix}${normalizedEndpoint}`

      const query = params
        ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([_, v]) => v != null)
            .map(([k, v]) => [k, String(v)])
        ).toString()
        : ''

      const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
      const url = `${baseUrl}${path}${query}`
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
          if (newToken) {
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
        // 检测网络连接错误
        if (isNetworkError(error)) {
          const networkError = new Error('后端服务不可用，请检查服务是否已启动')
          ;(networkError as any).isNetworkError = true
          throw networkError
        }
        throw error
      }
    },
    []
  )

  /**
   * 不带前缀的 API 请求（直接使用）
   */
  const rawRequest = useCallback(
    async <T = any>(
      endpoint: string,
      options: RequestInit = {},
      params?: Record<string, any>
    ) => {
      const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`

      const query = params
        ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([_, v]) => v != null)
            .map(([k, v]) => [k, String(v)])
        ).toString()
        : ''

      const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
      const url = `${baseUrl}${normalizedEndpoint}${query}`
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
          if (newToken) {
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
        // 检测网络连接错误
        if (isNetworkError(error)) {
          const networkError = new Error('后端服务不可用，请检查服务是否已启动')
          ;(networkError as any).isNetworkError = true
          throw networkError
        }
        throw error
      }
    },
    []
  )

  return useMemo(
    () => ({
      // 普通 API（使用 apiPrefix）
      api: {
        get: <T = any>(endpoint: string, params?: Record<string, any>) => {
          return apiRequest<T>(endpoint, { method: 'GET' }, params)
        },
        post: <T = any>(endpoint: string, body?: any) => {
          return apiRequest<T>(endpoint, { method: 'POST', body: JSON.stringify(body) })
        },
        put: <T = any>(endpoint: string, body?: any) => {
          return apiRequest<T>(endpoint, { method: 'PUT', body: JSON.stringify(body) })
        },
        delete: <T = any>(endpoint: string) => {
          return apiRequest<T>(endpoint, { method: 'DELETE' })
        },
      },
      // 管理端 API（使用 /anon/cms/admin 前缀）
      admin: {
        get: <T = any>(endpoint: string, params?: Record<string, any>) => {
          return adminRequest<T>(endpoint, { method: 'GET' }, params)
        },
        post: <T = any>(endpoint: string, body?: any) => {
          return adminRequest<T>(endpoint, { method: 'POST', body: JSON.stringify(body) })
        },
        put: <T = any>(endpoint: string, body?: any) => {
          return adminRequest<T>(endpoint, { method: 'PUT', body: JSON.stringify(body) })
        },
        delete: <T = any>(endpoint: string) => {
          return adminRequest<T>(endpoint, { method: 'DELETE' })
        },
      },
      // 不带前缀的 API（直接使用）
      raw: {
        get: <T = any>(endpoint: string, params?: Record<string, any>) => {
          return rawRequest<T>(endpoint, { method: 'GET' }, params)
        },
        post: <T = any>(endpoint: string, body?: any) => {
          return rawRequest<T>(endpoint, { method: 'POST', body: JSON.stringify(body) })
        },
        put: <T = any>(endpoint: string, body?: any) => {
          return rawRequest<T>(endpoint, { method: 'PUT', body: JSON.stringify(body) })
        },
        delete: <T = any>(endpoint: string) => {
          return rawRequest<T>(endpoint, { method: 'DELETE' })
        },
      },
    }),
    [apiRequest, adminRequest, rawRequest]
  )
}
