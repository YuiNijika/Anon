import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi, type Post } from '@/services/admin'
import { App } from 'antd'

export function usePosts() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
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
          message.info(response.message || '未找到匹配的文章')
        }
        return response.data
      } else {
        message.error(response.message || '加载文章列表失败')
        return null
      }
    } catch (err) {
      message.error('加载文章列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const getPost = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.getPost(apiAdmin, id)
      if (response.code === 200) {
        return response.data
      } else {
        message.error(response.message || '获取文章失败')
        return null
      }
    } catch (err) {
      message.error('获取文章失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const createPost = useCallback(async (data: Partial<Post>) => {
    try {
      setLoading(true)
      const response = await AdminApi.createPost(apiAdmin, data)
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

  const updatePost = useCallback(async (data: Partial<Post> & { id: number }) => {
    try {
      setLoading(true)
      const response = await AdminApi.updatePost(apiAdmin, data)
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

  const deletePost = useCallback(async (id: number) => {
    try {
      setLoading(true)
      const response = await AdminApi.deletePost(apiAdmin, id)
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
    total,
    loadPosts,
    getPost,
    createPost,
    updatePost,
    deletePost,
  }
}

