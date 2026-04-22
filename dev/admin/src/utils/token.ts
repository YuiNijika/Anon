import { getApiBaseUrl } from './api'

interface ApiResponse<T = any> {
  code: number
  message: string
  data?: T
}

let cachedApiPrefix: string | null = null
let prefixPromise: Promise<string> | null = null
let cachedLoginStatus: boolean | null = null
let loginStatusPromise: Promise<boolean> | null = null
let adminTokenPromise: Promise<string | null> | null = null

async function getApiPrefixInternal(): Promise<string> {
  if (cachedApiPrefix !== null) {
    return cachedApiPrefix
  }

  if (prefixPromise) {
    return prefixPromise
  }

  prefixPromise = (async (): Promise<string> => {
    try {
      const baseUrl = getApiBaseUrl()
      const url = `${baseUrl}/anon/cms/api-prefix?t=${Date.now()}` // 添加时间戳避免缓存
      console.log('Fetching API prefix from:', url)
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        cache: 'no-cache', // 禁用浏览器缓存
      })
      const data: ApiResponse<{ apiPrefix?: string }> = await res.json()
      console.log('API prefix response:', data)
  
      if (data.code === 200 && data.data?.apiPrefix !== undefined) {
        const prefix = data.data.apiPrefix
        const normalizedPrefix = prefix === '' ? '' : (prefix.startsWith('/') ? prefix : `/${prefix}`)
        console.log('Normalized API prefix:', normalizedPrefix)
        cachedApiPrefix = normalizedPrefix
        return normalizedPrefix
      } else {
        console.warn('API prefix response code is not 200 or data is undefined')
      }
    } catch (error) {
      console.warn('获取 API前缀失败:', error)
    }
    cachedApiPrefix = ''
    return ''
  })()

  return prefixPromise
}

export async function getApiPrefix(): Promise<string> {
  return await getApiPrefixInternal()
}

export async function checkLoginStatus(forceRefresh = false): Promise<boolean> {
  if (!forceRefresh && cachedLoginStatus !== null) {
    return cachedLoginStatus
  }

  if (!forceRefresh && loginStatusPromise) {
    return loginStatusPromise
  }

  loginStatusPromise = (async (): Promise<boolean> => {
    try {
      const apiPrefix = await getApiPrefixInternal()
      // 确保 apiPrefix 不为空，如果为空使用默认值 /api
      const prefix = !apiPrefix || apiPrefix === '' ? '/api' : apiPrefix
      const baseUrl = getApiBaseUrl()
      const url = `${baseUrl}${prefix}/auth/check-login`
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        cache: 'no-cache',
      })
      const data: ApiResponse<{ loggedIn?: boolean; logged_in?: boolean }> = await res.json()

      if (data.code === 200) {
        const loggedIn = data.data?.loggedIn ?? data.data?.logged_in ?? false
        cachedLoginStatus = loggedIn
        return loggedIn
      }
    } catch (error) {
      console.warn('检查登录状态失败:', error)
    }
    cachedLoginStatus = false
    return false
  })().finally(() => {
    loginStatusPromise = null
  })

  return loginStatusPromise
}

export async function getAdminToken(): Promise<string | null> {
  const existing = localStorage.getItem('token')
  if (existing) {
    return existing
  }

  if (adminTokenPromise) {
    return adminTokenPromise
  }

  adminTokenPromise = (async (): Promise<string | null> => {
    try {
      const isLoggedIn = await checkLoginStatus()
      if (!isLoggedIn) {
        return null
      }

      const apiPrefix = await getApiPrefixInternal()
      // 确保 apiPrefix 不为空，如果为空使用默认值 /api
      const prefix = !apiPrefix || apiPrefix === '' ? '/api' : apiPrefix
      const baseUrl = getApiBaseUrl()
      const url = `${baseUrl}${prefix}/auth/token`
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        cache: 'no-cache',
      })
      const data: ApiResponse<{ token?: string }> = await res.json()

      if (data.code === 200 && data.data?.token) {
        localStorage.setItem('token', data.data.token)
        return data.data.token
      }
    } catch (error) {
      console.warn('获取 Token 失败:', error)
    }
    return null
  })().finally(() => {
    adminTokenPromise = null
  })

  return adminTokenPromise
}

export function clearLoginStatusCache(): void {
  cachedLoginStatus = null
}

/**
 * 清除 API 前缀缓存，强制下次请求时重新获取
 */
export function clearApiPrefixCache(): void {
  cachedApiPrefix = null
  prefixPromise = null
}

