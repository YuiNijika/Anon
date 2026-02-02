import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Category } from '@/services/admin'

export function useCategories() {
  const apiAdmin = useApiAdmin()
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
        toast.error(response.message || '加载分类列表失败')
        return null
      }
    } catch (err) {
      toast.error('加载分类列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const createCategory = useCallback(async (data: Partial<Category>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createCategory(apiAdmin, data)
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

  const updateCategory = useCallback(async (data: Partial<Category> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updateCategory(apiAdmin, data)
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

  const deleteCategory = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteCategory(apiAdmin, id)
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
    loadCategories,
    createCategory,
    updateCategory,
    deleteCategory,
  }
}

