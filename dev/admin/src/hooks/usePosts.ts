import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Post } from '@/services/admin'

export function usePosts() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Post[]>([])
  const [total, setTotal] = useState(0)

  const loadPosts = useCallback(async (params?: {
    page?: number
    page_size?: number
    type?: string
    status?: string
    search?: string
  }) => {
    try {
      setLoading(true)
      const response = await AdminApi.getPosts(apiAdmin, params)
      if (response.code === 200 && response.data) {
        setData(response.data.list || [])
        setTotal(response.data.total || 0)
        if (params?.search && response.data.total === 0) {
          toast.info(response.message || '未找到匹配的文章')
        }
        return response.data
      } else {
        toast.error(response.message || '加载文章列表失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载文章列表失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const getPost = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.getPost(apiAdmin, id)
      if (response.code === 200) {
        return response.data
      } else {
        toast.error(response.message || '获取文章失败')
        return null
      }
    } catch (err) {
      toast.error('获取文章失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const createPost = useCallback(async (data: Partial<Post>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createPost(apiAdmin, data)
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

  const updatePost = useCallback(async (data: Partial<Post> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updatePost(apiAdmin, data)
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

  const deletePost = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deletePost(apiAdmin, id)
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
    loadPosts,
    getPost,
    createPost,
    updatePost,
    deletePost,
  }
}

