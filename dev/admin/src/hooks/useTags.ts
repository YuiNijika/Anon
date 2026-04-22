import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Tag } from '@/services/admin'

export function useTags() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Tag[]>([])

  const loadTags = useCallback(async () => {
    try {
      setLoading(true)
      const response = await AdminApi.getTags(apiAdmin)
      if (response.code === 200) {
        setData(response.data || [])
        return response.data
      } else {
        toast.error(response.message || '加载标签列表失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载标签列表失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const createTag = useCallback(async (data: Partial<Tag>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createTag(apiAdmin, data)
      if (response.code === 200) {
        toast.success('创建成功')
        return response.data
      } else {
        toast.error(response.message || '创建失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '创建失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const updateTag = useCallback(async (data: Partial<Tag> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updateTag(apiAdmin, data)
      if (response.code === 200) {
        toast.success('更新成功')
        return response.data
      } else {
        toast.error(response.message || '更新失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '更新失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const deleteTag = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteTag(apiAdmin, id)
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
    loadTags,
    createTag,
    updateTag,
    deleteTag,
  }
}

