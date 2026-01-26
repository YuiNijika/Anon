import { useNotify } from './useNotify'

/**
 * API 响应接口
 */
interface ApiResponse<T = any> {
    code: number
    message: string
    data?: T
}

/**
 * 统计数据接口
 */
export interface StatisticsData {
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

/**
 * 上传文件类型设置接口
 */
export interface UploadAllowedTypes {
    image: string
    media: string
    document: string
    other: string
}

/**
 * 基本设置接口
 */
export interface BasicSettings {
    title: string
    description: string
    keywords: string
    allow_register: boolean
    api_prefix: string
    api_enabled: boolean
    upload_allowed_types: UploadAllowedTypes
}

/**
 * 用户信息接口
 */
export interface UserInfo {
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

/**
 * 主题信息接口
 */
export interface ThemeInfo {
    name: string
    displayName: string
    description: string
    author: string
    version: string
    url: string
    screenshot: string
}

/**
 * 主题设置接口
 */
export interface ThemeSettings {
    current: string
    themes: ThemeInfo[]
}

const API_BASE_URLS = {
    dev: '/anon-dev-server',
    prod: '',
} as const

const DEFAULT_API_BASE_URL = import.meta.env.DEV ? API_BASE_URLS.dev : API_BASE_URLS.prod
const ADMIN_API_PREFIX = '/anon/cms'

let tokenPromise: Promise<string | null> | null = null
let tokenRefreshPromise: Promise<string | null> | null = null

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
 * 后台管理 API 客户端
 */
export function useApiAdmin() {
    const notify = useNotify()

    /**
     * 获取 token
     */
    async function fetchToken(forceRefresh = false): Promise<string | null> {
        try {
            const url = buildFullUrl('/admin/auth/token')
            const headers: HeadersInit = {
                'Content-Type': 'application/json',
            }

            const existingToken = localStorage.getItem('token')
            if (existingToken && !forceRefresh) {
                headers['X-API-Token'] = existingToken
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
        } catch (err) {
            console.error('Fetch token failed:', err)
        }
        return null
    }

    /**
     * 确保本地有 token
     */
    async function ensureToken(forceRefresh = false): Promise<string | null> {
        // 优先使用本地token，避免重复请求
        if (!forceRefresh) {
            const existing = localStorage.getItem('token')
            if (existing) return existing
        }

        // 强制刷新时，等待正在进行的刷新请求
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

        // 普通获取token时，等待正在进行的请求
        if (tokenPromise) {
            const token = await tokenPromise
            return token || localStorage.getItem('token')
        }

        // 创建新的token请求
        tokenPromise = fetchToken(false).finally(() => {
            tokenPromise = null
        })

        const token = await tokenPromise
        return token || localStorage.getItem('token')
    }

    /**
     * 构建完整的管理 API 路径
     */
    function buildAdminPath(endpoint: string): string {
        const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`
        if (normalizedEndpoint.startsWith(ADMIN_API_PREFIX)) {
            return normalizedEndpoint
        }
        return `${ADMIN_API_PREFIX}${normalizedEndpoint}`
    }

    /**
     * 构建完整的请求 URL
     */
    function buildFullUrl(endpoint: string, params?: Record<string, any>): string {
        const path = buildAdminPath(endpoint)
        const query = buildQueryString(params)
        return `${DEFAULT_API_BASE_URL}${path}${query}`
    }

    /**
     * 发送请求
     */
    async function request<T = any>(
        endpoint: string,
        options: RequestInit = {},
        params?: Record<string, any>
    ): Promise<ApiResponse<T>> {
        const url = buildFullUrl(endpoint, params)
        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            ...options.headers,
        }

        // 优先使用本地token，避免不必要的请求
        let token = localStorage.getItem('token')

        // 只有在没有token时才请求新的token
        if (!token) {
            token = await ensureToken()
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

                try {
                    const { useAuthStore } = await import('../stores/auth')
                    const authStore = useAuthStore()
                    authStore.user = null
                    authStore.error = null
                } catch {
                    // 静默失败
                }
            }

            if (data.code !== 200) {
                notify.error(data.message || '请求失败')
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
                notify.error(error.message || '网络请求失败')
                throw error
            }
            notify.error('网络请求失败')
            throw new Error('网络请求失败')
        }
    }

    return {
        /**
         * GET 请求
         */
        get: <T = any>(endpoint: string, params?: Record<string, any>) => {
            return request<T>(endpoint, { method: 'GET' }, params)
        },

        /**
         * POST 请求
         */
        post: <T = any>(endpoint: string, body?: any) => {
            return request<T>(endpoint, {
                method: 'POST',
                body: JSON.stringify(body),
            })
        },

        /**
         * PUT 请求
         */
        put: <T = any>(endpoint: string, body?: any) => {
            return request<T>(endpoint, {
                method: 'PUT',
                body: JSON.stringify(body),
            })
        },

        /**
         * DELETE 请求
         */
        delete: <T = any>(endpoint: string) => {
            return request<T>(endpoint, { method: 'DELETE' })
        },

        /**
         * 获取统计数据
         */
        getStatistics: () => {
            return request<StatisticsData>('/admin/statistics', { method: 'GET' })
        },

        /**
         * 获取基本设置
         */
        getBasicSettings: () => {
            return request<BasicSettings>('/admin/settings/basic', { method: 'GET' })
        },

        /**
         * 更新基本设置
         */
        updateBasicSettings: (settings: BasicSettings) => {
            return request<BasicSettings>('/admin/settings/basic', {
                method: 'POST',
                body: JSON.stringify(settings),
            }, undefined)
        },

        /**
         * 获取 Token
         */
        getToken: () => {
            return request<{ token?: string }>('/admin/auth/token', { method: 'GET' })
        },

        /**
         * 检查登录状态
         */
        checkLogin: () => {
            return request<{ loggedIn?: boolean; logged_in?: boolean }>('/admin/auth/check-login', { method: 'GET' })
        },

        /**
         * 获取用户信息
         */
        getUserInfo: () => {
            return request<UserInfo>('/admin/user/info', { method: 'GET' })
        },

        /**
         * 获取配置信息
         */
        getConfig: () => {
            return request<{ token?: boolean; captcha?: boolean; csrfToken?: string }>('/admin/config', { method: 'GET' })
        },

        /**
         * 获取主题设置
         */
        getThemeSettings: () => {
            return request<ThemeSettings>('/admin/settings/theme', { method: 'GET' })
        },

        /**
         * 更新主题设置
         */
        updateThemeSettings: (theme: string) => {
            return request<{ theme: string }>('/admin/settings/theme', {
                method: 'POST',
                body: JSON.stringify({ theme }),
            })
        },
    }
}
