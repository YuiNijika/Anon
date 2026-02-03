import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useApiAdmin } from './useApiAdmin'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Plugin } from '@/services/admin'

export function usePlugins() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<Plugin[]>([])

  const loadPlugins = useCallback(async () => {
    try {
      setLoading(true)
      const response = await AdminApi.getPlugins(apiAdmin)
      if (response.code === 200 && response.data) {
        setData(response.data.list || [])
        return response.data
      } else {
        toast.error(response.message || '加载插件列表失败')
        return null
      }
    } catch (err) {
      toast.error('加载插件列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin])

  const uploadPlugin = useCallback(async (file: File, overwrite = false) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadPlugin(apiAdmin, file, overwrite)
      if (response.code === 200) {
        const data = response.data
        if (data?.needConfirm) {
          return data
        }
        toast.success('上传成功')
        await loadPlugins()
        return data
      } else {
        toast.error(response.message || '上传失败')
        return null
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '上传失败'))
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, loadPlugins])

  const activatePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.activatePlugin(apiAdmin, slug)
      if (response.code === 200) {
        toast.success('激活成功')
        await loadPlugins()
        return true
      } else {
        toast.error(response.message || '激活失败')
        return false
      }
    } catch (err) {
      toast.error('激活失败')
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, loadPlugins])

  const deactivatePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deactivatePlugin(apiAdmin, slug)
      if (response.code === 200) {
        toast.success('停用成功')
        await loadPlugins()
        return true
      } else {
        toast.error(response.message || '停用失败')
        return false
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '停用失败'))
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, loadPlugins])

  const deletePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deletePlugin(apiAdmin, slug)
      if (response.code === 200) {
        toast.success('删除成功')
        await loadPlugins()
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
  }, [apiAdmin, loadPlugins])

  return {
    loading,
    data,
    loadPlugins,
    uploadPlugin,
    activatePlugin,
    deactivatePlugin,
    deletePlugin,
  }
}

