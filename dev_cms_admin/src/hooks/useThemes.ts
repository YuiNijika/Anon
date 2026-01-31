import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi } from '@/services/admin'
import { App } from 'antd'

export function useThemes() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
  const [loading, setLoading] = useState(false)

  const uploadTheme = useCallback(async (file: File) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadTheme(apiAdmin, file)
      if (response.code === 200) {
        message.success('上传成功')
        return response.data
      } else {
        message.error(response.message || '上传失败')
        return null
      }
    } catch (err) {
      message.error('上传失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const deleteTheme = useCallback(async (name: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteTheme(apiAdmin, name)
      if (response.code === 200) {
        message.success('删除成功')
        return true
      } else {
        message.error(response.message || '删除失败')
        return false
      }
    } catch (err) {
      message.error('删除失败')
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  return {
    loading,
    uploadTheme,
    deleteTheme,
  }
}

