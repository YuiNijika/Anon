import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type User } from '@/services/admin'

export function useUsers() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<User[]>([])
  const [total, setTotal] = useState(0)

  const loadUsers = useCallback(async (params?: { page?: number; page_size?: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.getUsers(apiAdmin, params)
      if (response.code === 200 && response.data) {
        setData(response.data.list || [])
        setTotal(response.data.total || 0)
        return response.data
      } else {
        toast.error(response.message || '加载用户列表失败')
        return null
      }
    } catch (err) {
      toast.error('加载用户列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const createUser = useCallback(async (data: Partial<User> & { password: string }) => {
    try {
      setLoading(true)
      const response = await AdminApi.createUser(apiAdmin, data)
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

  const updateUser = useCallback(async (data: Partial<User> & { uid: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updateUser(apiAdmin, data)
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

  const deleteUser = useCallback(async (uid: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deleteUser(apiAdmin, uid)
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
    total,
    loadUsers,
    createUser,
    updateUser,
    deleteUser,
  }
}

