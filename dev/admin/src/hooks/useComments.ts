import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Comment, type CommentListParams } from '@/services/admin'

export function useComments() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Comment[]>([])
  const [total, setTotal] = useState(0)

  const loadComments = useCallback(
    async (params?: CommentListParams) => {
      try {
        setLoading(true)
        const response = await AdminApi.getComments(apiAdmin, params)
        if (response.code === 200 && response.data) {
          setData(response.data.list || [])
          setTotal(response.data.total || 0)
          return response.data
        } else {
          toast.error(response.message || '加载评论列表失败')
          return null
        }
      } catch (err) {
        toast.error('加载评论列表失败')
        return null
      } finally {
        setLoading(false)
      }
    },
    [apiAdmin]
  )

  const updateCommentStatus = useCallback(
    async (id: number, status: 'approved' | 'pending' | 'spam' | 'trash') => {
      try {
        setLoading(true)
        const response = await AdminApi.updateCommentStatus(apiAdmin, id, status)
        if (response.code === 200) {
          toast.success('操作成功')
          return true
        } else {
          toast.error(response.message || '操作失败')
          return false
        }
      } catch (err) {
        toast.error(getErrorMessage(err, '操作失败'))
        return false
      } finally {
        setLoading(false)
      }
    },
    [apiAdmin]
  )

  const updateCommentContent = useCallback(
    async (id: number, content: string) => {
      try {
        setLoading(true)
        const response = await AdminApi.updateComment(apiAdmin, id, { content })
        if (response.code === 200) {
          toast.success('评论已更新')
          return true
        } else {
          toast.error(response.message || '更新失败')
          return false
        }
      } catch (err) {
        toast.error(getErrorMessage(err, '更新失败'))
        return false
      } finally {
        setLoading(false)
      }
    },
    [apiAdmin]
  )

  const deleteComment = useCallback(
    async (id: number) => {
      try {
        setLoading(true)
        const response = await AdminApi.deleteComment(apiAdmin, id)
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
    },
    [apiAdmin]
  )

  return {
    loading,
    data,
    total,
    loadComments,
    updateCommentStatus,
    updateCommentContent,
    deleteComment,
  }
}
