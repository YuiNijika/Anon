import { useState, useCallback, useMemo, useEffect } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { useCmsConfig } from './useCmsConfig'

interface CaptchaResponse {
  image: string
}

/**
 * 验证码 Hook
 */
export const useCaptcha = () => {
  const apiAdmin = useApiAdmin()
  const { config, loading: configLoading } = useCmsConfig()

  const [image, setImage] = useState('')
  const [refreshing, setRefreshing] = useState(false)

  // 直接从配置派生启用状态
  const enabled = !!config?.captcha

  // 刷新函数
  const refresh = useCallback(async () => {
    // 防止并发刷新
    if (refreshing) return

    setRefreshing(true)
    try {
      const res = await apiAdmin.api.get<CaptchaResponse>('/auth/captcha')
      if (res.data?.image) {
        setImage(res.data.image)
      }
    } catch (e) {
      console.error('Fetch captcha failed:', e)
    } finally {
      setRefreshing(false)
    }
  }, [apiAdmin, refreshing])

  // 自动加载处理
  useEffect(() => {
    // 等待配置加载
    if (configLoading) return

    // 禁用时清空图片
    if (!enabled) {
      if (image) setImage('')
      return
    }

    // 启用且无图片时获取验证码
    if (!image && !refreshing) {
      refresh()
    }
  }, [configLoading, enabled, image, refreshing, refresh])

  return useMemo(() => ({
    image,
    enabled,
    refresh,
    loading: configLoading
  }), [image, enabled, refresh, configLoading])
}
