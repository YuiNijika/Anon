import { useState, useCallback, useRef, useEffect } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { isNetworkError } from './useApi'
import { AuthApi, type LoginDTO, type UserInfo } from '../services/auth'
import { UserApi } from '../services/user'

export const useAuth = () => {
  const apiAdmin = useApiAdmin()
  const [user, setUser] = useState<UserInfo | null>(() => {
    try {
      const persisted = localStorage.getItem('auth-user')
      return persisted ? JSON.parse(persisted) : null
    } catch {
      return null
    }
  })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const checkingRef = useRef(false)
  const initializedRef = useRef(false)

  const hasToken = useCallback(() => {
    return !!localStorage.getItem('token')
  }, [])

  const initialize = useCallback(() => {
    if (initializedRef.current) return

    const token = localStorage.getItem('token')
    if (token || user) {
      initializedRef.current = true
      return
    }

    setUser(null)
    initializedRef.current = true
  }, [user])

  useEffect(() => {
    initialize()
  }, [initialize])

  const login = useCallback(
    async (data: LoginDTO) => {
      setLoading(true)
      setError(null)
      try {
        const res = await AuthApi.login(apiAdmin, data)
        if (res.data?.token) {
          localStorage.setItem('token', res.data.token)
        }
        const userRes = await UserApi.getInfo(apiAdmin)
        if (userRes.data) {
          setUser(userRes.data)
          localStorage.setItem('auth-user', JSON.stringify(userRes.data))
        }
        return res
      } catch (err) {
        // 网络错误或认证错误时清除认证状态
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
        if (res.data?.token) {
          localStorage.setItem('token', res.data.token)
        }
        if (res.data?.user) {
          setUser(res.data.user)
          localStorage.setItem('auth-user', JSON.stringify(res.data.user))
        } else {
          const userRes = await UserApi.getInfo(apiAdmin)
          if (userRes.data) {
            setUser(userRes.data)
            localStorage.setItem('auth-user', JSON.stringify(userRes.data))
          }
        }
        return res
      } catch (err) {
        // 网络错误或认证错误时清除认证状态
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
    localStorage.removeItem('token')
    localStorage.removeItem('auth-user')
  }, [])

  const logout = useCallback(async () => {
    setLoading(true)
    try {
      await AuthApi.logout(apiAdmin)
      clearAuth()
    } catch {
      clearAuth()
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, clearAuth])

  /**
   * 检查登录状态
   * 只在必要时才请求后端
   */
  const checkLogin = useCallback(async (force = false): Promise<boolean> => {
    if (checkingRef.current && !force) {
      return !!user
    }

    if (!hasToken() && !user) {
      setUser(null)
      initializedRef.current = true
      return false
    }

    checkingRef.current = true

    try {
      // 如果已有用户信息且未强制刷新，直接返回
      if (user && !force) {
        initializedRef.current = true
        checkingRef.current = false
        return true
      }

      // 先检查登录状态
      const res = await AuthApi.checkLogin(apiAdmin)
      const loggedIn = res.data?.loggedIn ?? res.data?.logged_in ?? false

      if (!loggedIn) {
        clearAuth()
        checkingRef.current = false
        initializedRef.current = true
        return false
      }

      // 如果已有用户信息，不需要再次获取
      if (user) {
        checkingRef.current = false
        initializedRef.current = true
        return true
      }

      // 获取用户信息
      const userRes = await UserApi.getInfo(apiAdmin)
      if (userRes.data) {
        setUser(userRes.data)
        localStorage.setItem('auth-user', JSON.stringify(userRes.data))
        setError(null)
        checkingRef.current = false
        initializedRef.current = true
        return true
      } else {
        clearAuth()
        checkingRef.current = false
        initializedRef.current = true
        return false
      }
    } catch (err) {
      console.error('Check login failed:', err)
      
      // 如果是网络连接错误，清除认证状态，设置为未登录模式
      if (isNetworkError(err) || (err instanceof Error && (err as any).isNetworkError)) {
        clearAuth()
        checkingRef.current = false
        initializedRef.current = true
        return false
      }
      
      // 其他错误：如果没有 token 和用户信息，清除认证状态
      if (!hasToken() && !user) {
        clearAuth()
      }
      checkingRef.current = false
      initializedRef.current = true
      return !!user
    }
  }, [apiAdmin, user, hasToken, clearAuth])

  return {
    user,
    isAuthenticated: !!user,
    hasToken: hasToken(),
    loading,
    error,
    login,
    register,
    logout,
    checkLogin,
    initialize,
  }
}
