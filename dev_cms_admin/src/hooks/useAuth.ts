import { useState, useCallback, useRef, useEffect } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { isNetworkError } from './useApi'
import { checkLoginStatus, clearLoginStatusCache } from '../utils/token'
import { AuthApi, type LoginDTO, type UserInfo } from '../services/auth'
import { UserApi } from '../services/user'

export const useAuth = () => {
  const apiAdmin = useApiAdmin()
  const [user, setUser] = useState<UserInfo | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [initializing, setInitializing] = useState(true)
  const checkingRef = useRef(false)

  /**
   * 初始化：检查登录状态
   * 先请求后端检查登录状态，如果已登录则获取用户信息
   */
  const initialize = useCallback(async () => {
    if (checkingRef.current) return
    checkingRef.current = true
    setInitializing(true)

    try {
      // 先检查登录状态（直接使用 checkLoginStatus，不通过 api.api，避免先获取 token）
      const loggedIn = await checkLoginStatus()

      if (loggedIn) {
        // 已登录，获取用户信息
        try {
          const userRes = await UserApi.getInfo(apiAdmin)
          if (userRes.data) {
            setUser(userRes.data)
          }
        } catch (err) {
          console.warn('获取用户信息失败:', err)
        }
      } else {
        // 未登录
        setUser(null)
      }
    } catch (err) {
      console.warn('检查登录状态失败:', err)
      setUser(null)
    } finally {
      setInitializing(false)
      checkingRef.current = false
    }
  }, [apiAdmin])

  useEffect(() => {
    initialize()
  }, [initialize])

  const login = useCallback(
    async (data: LoginDTO) => {
      setLoading(true)
      setError(null)
      try {
        const res = await AuthApi.login(apiAdmin, data)
        const userRes = await UserApi.getInfo(apiAdmin)
        if (userRes.data) {
          setUser(userRes.data)
        }
        return res
      } catch (err) {
        if (isNetworkError(err) || (err instanceof Error && (err as any).isNetworkError)) {
          clearAuth()
        } else if (err instanceof Error && (err.message.includes('401') || err.message.includes('403'))) {
          clearAuth()
        }
        const message = err instanceof Error ? err.message : '登录失败'
        setError(message)
        throw err
      } finally {
        setLoading(false)
      }
    },
    [apiAdmin]
  )

  const register = useCallback(
    async (data: LoginDTO) => {
      setLoading(true)
      setError(null)
      try {
        const res = await AuthApi.register(apiAdmin, data)
        if (res.data?.user) {
          setUser(res.data.user)
        } else {
          const userRes = await UserApi.getInfo(apiAdmin)
          if (userRes.data) {
            setUser(userRes.data)
          }
        }
        return res
      } catch (err) {
        if (isNetworkError(err) || (err instanceof Error && (err as any).isNetworkError)) {
          clearAuth()
        } else if (err instanceof Error && (err.message.includes('401') || err.message.includes('403'))) {
          clearAuth()
        }
        const message = err instanceof Error ? err.message : '注册失败'
        setError(message)
        throw err
      } finally {
        setLoading(false)
      }
    },
    [apiAdmin]
  )

  const clearAuth = useCallback(() => {
    setUser(null)
    setError(null)
  }, [])

  const logout = useCallback(async () => {
    setLoading(true)
    try {
      await AuthApi.logout(apiAdmin)
      clearLoginStatusCache()
      clearAuth()
    } catch {
      clearLoginStatusCache()
      clearAuth()
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, clearAuth])

  /**
   * 检查登录状态
   * 重新请求后端检查登录状态
   */
  const checkLogin = useCallback(async (): Promise<boolean> => {
    try {
      const res = await AuthApi.checkLogin(apiAdmin)
      const loggedIn = res.data?.loggedIn ?? res.data?.logged_in ?? false

      if (loggedIn) {
        // 已登录，获取用户信息
        const userRes = await UserApi.getInfo(apiAdmin)
        if (userRes.data) {
          setUser(userRes.data)
          return true
        }
      }

      // 未登录
      clearAuth()
      return false
    } catch (err) {
      console.warn('检查登录状态失败:', err)
      clearAuth()
      return false
    }
  }, [apiAdmin, clearAuth])

  return {
    user,
    isAuthenticated: !!user,
    loading,
    initializing,
    error,
    login,
    register,
    logout,
    checkLogin,
    initialize,
  }
}
