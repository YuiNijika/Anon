import { defineStore } from 'pinia'
import { useApi } from '@/composables/useApi'
import { useApiAdmin, type UserInfo } from '@/composables/useApiAdmin'
import { AuthApi, type LoginDTO } from '@/services/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as UserInfo | null,
    loading: false,
    checking: false,
    error: null as string | null,
    initialized: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    hasToken: () => !!localStorage.getItem('token'),
  },

  actions: {
    async login(data: LoginDTO) {
      this.loading = true
      this.error = null
      try {
        const api = useApi()
        const res = await AuthApi.login(api, data)
        if (res.data?.token) {
          localStorage.setItem('token', res.data.token)
        }
        const apiAdmin = useApiAdmin()
        const userRes = await apiAdmin.getUserInfo()
        if (userRes.data) {
          this.user = userRes.data
        }
        return res
      } catch (err) {
        this.error = err instanceof Error ? err.message : '登录失败'
        throw err
      } finally {
        this.loading = false
      }
    },

    async register(data: LoginDTO) {
      this.loading = true
      this.error = null
      try {
        const api = useApi()
        const res = await AuthApi.register(api, data)
        if (res.data?.token) {
          localStorage.setItem('token', res.data.token)
        }
        if (res.data?.user) {
          this.user = res.data.user
        } else {
          const apiAdmin = useApiAdmin()
          const userRes = await apiAdmin.getUserInfo()
          if (userRes.data) {
            this.user = userRes.data
          }
        }
        return res
      } catch (err) {
        this.error = err instanceof Error ? err.message : '注册失败'
        throw err
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      try {
        const api = useApi()
        await AuthApi.logout(api)
        this.clearAuth()
      } catch {
        this.clearAuth()
      } finally {
        this.loading = false
      }
    },

    /**
     * 初始化认证状态
     * 利用本地 token 和持久化数据，避免刷新时立即请求
     */
    initialize() {
      if (this.initialized) return

      const token = localStorage.getItem('token')
      if (token || this.user) {
        this.initialized = true
        return
      }

      this.user = null
      this.initialized = true
    },

    /**
     * 检查登录状态
     * 只在必要时才请求后端
     */
    async checkLogin(force = false): Promise<boolean> {
      if (this.checking && !force) {
        return this.isAuthenticated
      }

      if (!this.hasToken && !this.user) {
        this.user = null
        this.initialized = true
        return false
      }

      this.checking = true
      try {
        const apiAdmin = useApiAdmin()

        // 如果已有用户信息且未强制刷新，直接返回
        if (this.user && !force) {
          this.initialized = true
          return true
        }

        // 先检查登录状态
        const res = await apiAdmin.checkLogin()
        const loggedIn = res.data?.loggedIn ?? res.data?.logged_in ?? false

        if (!loggedIn) {
          this.clearAuth()
          return false
        }

        // 如果已有用户信息，不需要再次获取
        if (this.user) {
          this.initialized = true
          return true
        }

        // 获取用户信息
        const userRes = await apiAdmin.getUserInfo()
        if (userRes.data) {
          this.user = userRes.data
          this.error = null
          this.initialized = true
          return true
        } else {
          this.clearAuth()
          return false
        }
      } catch (err) {
        console.error('Check login failed:', err)
        if (!this.hasToken && !this.user) {
          this.clearAuth()
        }
        return this.isAuthenticated
      } finally {
        this.checking = false
        this.initialized = true
      }
    },

    /**
     * 清除认证状态
     */
    clearAuth() {
      this.user = null
      this.error = null
      localStorage.removeItem('token')
      try {
        const persisted = localStorage.getItem('auth-store')
        if (persisted) {
          const data = JSON.parse(persisted)
          if (data && data.user) {
            data.user = null
            localStorage.setItem('auth-store', JSON.stringify(data))
          }
        }
      } catch {
        // 静默失败
      }
    },
  },

  persist: {
    key: 'auth-store',
    storage: localStorage,
    paths: ['user'],
  } as any,
})
