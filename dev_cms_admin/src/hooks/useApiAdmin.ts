import { useMemo, useCallback } from 'react'
import { useApi, isNetworkError } from './useApi'
import { getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

interface ApiResponse<T = any> {
  code: number
  message: string
  data?: T
}

const inFlightGetRequests = new Map<string, Promise<ApiResponse<any>>>()

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
   * 管理端 API 请求（使用 {apiPrefix}/cms/admin 前缀，如果 apiPrefix 为空则使用 /anon）
   */
  const adminRequest = useCallback(
    async <T = any>(
      endpoint: string,
      options: RequestInit = {},
      params?: Record<string, any>
    ) => {
      const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`

      // 获取 API 前缀，如果为空则使用 /anon
      const apiPrefix = await getApiPrefix()
      const prefix = apiPrefix === '' ? '/anon' : apiPrefix
      const adminPrefix = `${prefix}/cms/admin`

      const path = normalizedEndpoint.startsWith(adminPrefix) || normalizedEndpoint.startsWith('/cms/admin')
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

      const baseUrl = getApiBaseUrl()
      const url = `${baseUrl}${path}${query}`
      const headers: HeadersInit = {
        'Content-Type': 'application/json',
        ...options.headers,
      }

      // 先检查登录状态，如果已登录再获取 token
      const isLoggedIn = await checkLoginStatus()
      if (isLoggedIn) {
        const token = await getAdminToken()
        if (token) {
          (headers as Record<string, string>)['X-API-Token'] = token
        }
      }

      try {
        const method = (options.method || 'GET').toUpperCase()
        if (method === 'GET') {
          const cached = inFlightGetRequests.get(url)
          if (cached) {
            return (await cached) as ApiResponse<T>
          }
        }

        const requestPromise = (async () => {
          const res = await fetch(url, {
            ...options,
            headers,
            credentials: 'include',
          })
          const data: ApiResponse<T> = await res.json()

          const isAuthError = data.code === 401 || data.code === 403 || res.status === 401 || res.status === 403

          if (isAuthError) {
            // 重新检查登录状态并获取 token
            const isLoggedIn = await checkLoginStatus()
            if (isLoggedIn) {
              const newToken = await getAdminToken()
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
          }

          if (data.code !== 200) {
            throw new Error(data.message || '请求失败')
          }

          return data
        })()

        if ((options.method || 'GET').toUpperCase() === 'GET') {
          inFlightGetRequests.set(url, requestPromise as Promise<ApiResponse<any>>)
          requestPromise.finally(() => inFlightGetRequests.delete(url))
        }

        return await requestPromise
      } catch (error) {
        // 检测网络连接错误
        if (isNetworkError(error)) {
          const networkError = new Error('后端服务不可用，请检查服务是否已启动')
            ; (networkError as any).isNetworkError = true
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

      const baseUrl = getApiBaseUrl()
      const url = `${baseUrl}${normalizedEndpoint}${query}`
      const headers: HeadersInit = {
        'Content-Type': 'application/json',
        ...options.headers,
      }

      // 先检查登录状态，如果已登录再获取 token
      const isLoggedIn = await checkLoginStatus()
      if (isLoggedIn) {
        const token = await getAdminToken()
        if (token) {
          (headers as Record<string, string>)['X-API-Token'] = token
        }
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
          // 重新检查登录状态并获取 token
          const isLoggedIn = await checkLoginStatus()
          if (isLoggedIn) {
            const newToken = await getAdminToken()
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
        }

        if (data.code !== 200) {
          throw new Error(data.message || '请求失败')
        }

        return data
      } catch (error) {
        // 检测网络连接错误
        if (isNetworkError(error)) {
          const networkError = new Error('后端服务不可用，请检查服务是否已启动')
            ; (networkError as any).isNetworkError = true
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
