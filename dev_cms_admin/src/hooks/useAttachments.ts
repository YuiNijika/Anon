import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi, type Attachment, type AttachmentListResponse } from '@/services/admin'
import { App } from 'antd'

export function useAttachments() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Attachment[]>([])

  const loadAttachments = useCallback(async (params?: { sort?: 'new' | 'old' }) => {
    try {
      setLoading(true)
      const response = await AdminApi.getAttachments(apiAdmin, params)
      if (response.code === 200) {
        setData(response.data.list || [])
        return response.data
      } else {
        message.error(response.message || '加载附件列表失败')
        return null
      }
    } catch (err) {
      message.error('加载附件列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const uploadAttachment = useCallback(async (file: File) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadAttachment(apiAdmin, file)
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

  const deleteAttachment = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteAttachment(apiAdmin, id)
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
    data,
    loadAttachments,
    uploadAttachment,
    deleteAttachment,
  }
}

