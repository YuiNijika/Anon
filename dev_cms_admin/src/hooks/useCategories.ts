import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi, type Category } from '@/services/admin'
import { App } from 'antd'

export function useCategories() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Category[]>([])

  const loadCategories = useCallback(async () => {
    try {
      setLoading(true)
      const response = await AdminApi.getCategories(apiAdmin)
      if (response.code === 200) {
        setData(response.data || [])
        return response.data
      } else {
        message.error(response.message || '加载分类列表失败')
        return null
      }
    } catch (err) {
      message.error('加载分类列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const createCategory = useCallback(async (data: Partial<Category>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createCategory(apiAdmin, data)
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

  const updateCategory = useCallback(async (data: Partial<Category> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updateCategory(apiAdmin, data)
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

  const deleteCategory = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteCategory(apiAdmin, id)
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
    loadCategories,
    createCategory,
    updateCategory,
    deleteCategory,
  }
}

