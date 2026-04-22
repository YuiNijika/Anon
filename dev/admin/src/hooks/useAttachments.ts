import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Attachment } from '@/services/admin'

export function useAttachments() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Attachment[]>([])

  const loadAttachments = useCallback(async (params?: { sort?: 'new' | 'old' }) => {
    try {
      setLoading(true)
      const response = await AdminApi.getAttachments(apiAdmin, params)
      if (response.code === 200 && response.data) {
        setData(response.data.list || [])
        return response.data
      } else {
        toast.error(response.message || '加载附件列表失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载附件列表失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const uploadAttachment = useCallback(async (file: File) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadAttachment(apiAdmin, file)
      if (response.code === 200) {
        toast.success('上传成功')
        return response.data
      } else {
        toast.error(response.message || '上传失败')
        return null
      }
    } catch (err) {
      toast.error('上传失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const deleteAttachment = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteAttachment(apiAdmin, id)
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
    data,
    loadAttachments,
    uploadAttachment,
    deleteAttachment,
  }
}

