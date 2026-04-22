import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi } from '@/services/admin'

export function useThemes() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)

  const uploadTheme = useCallback(async (file: File, overwrite = false) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadTheme(apiAdmin, file, overwrite)
      if (response.code === 200) {
        const data = response.data
        if (data?.needConfirm) {
          return data
        }
        toast.success('上传成功')
        return data
      } else {
        toast.error(response.message || '上传失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '上传失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const deleteTheme = useCallback(async (name: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteTheme(apiAdmin, name)
      if (response.code === 200) {
        toast.success('删除成功')
        return true
      } else {
        toast.error(response.message || '删除失败')
        return false
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '删除失败'))
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  return {
    loading,
    uploadTheme,
    deleteTheme,
  }
}

