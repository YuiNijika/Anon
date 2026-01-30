import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi, type Tag } from '@/services/admin'
import { App } from 'antd'

export function useTags() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
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
        message.error(response.message || '加载标签列表失败')
        return null
      }
    } catch (err) {
      message.error('加载标签列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const createTag = useCallback(async (data: Partial<Tag>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createTag(apiAdmin, data)
      if (response.code === 200) {
        message.success('创建成功')
        return response.data
      } else {
        message.error(response.message || '创建失败')
        return null
      }
    } catch (err) {
      message.error('创建失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const updateTag = useCallback(async (data: Partial<Tag> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updateTag(apiAdmin, data)
      if (response.code === 200) {
        message.success('更新成功')
        return response.data
      } else {
        message.error(response.message || '更新失败')
        return null
      }
    } catch (err) {
      message.error('更新失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const deleteTag = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteTag(apiAdmin, id)
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
    loadTags,
    createTag,
    updateTag,
    deleteTag,
  }
}

