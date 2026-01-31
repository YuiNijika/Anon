import { useState, useCallback } from 'react'
import { useApiAdmin } from './useApiAdmin'
import { AdminApi, type Plugin } from '@/services/admin'
import { App } from 'antd'

export function usePlugins() {
  const apiAdmin = useApiAdmin()
  const { message } = App.useApp()
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
        message.error(response.message || '加载插件列表失败')
        return null
      }
    } catch (err) {
      message.error('加载插件列表失败')
      return null
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message])

  const uploadPlugin = useCallback(async (file: File) => {
    try {
      setLoading(true)
      const response = await AdminApi.uploadPlugin(apiAdmin, file)
      if (response.code === 200) {
        message.success('上传成功')
        await loadPlugins()
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
  }, [apiAdmin, message, loadPlugins])

  const activatePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.activatePlugin(apiAdmin, slug)
      if (response.code === 200) {
        message.success('激活成功')
        await loadPlugins()
        return true
      } else {
        message.error(response.message || '激活失败')
        return false
      }
    } catch (err) {
      message.error('激活失败')
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message, loadPlugins])

  const deactivatePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deactivatePlugin(apiAdmin, slug)
      if (response.code === 200) {
        message.success('停用成功')
        await loadPlugins()
        return true
      } else {
        message.error(response.message || '停用失败')
        return false
      }
    } catch (err) {
      message.error('停用失败')
      return false
    } finally {
      setLoading(false)
    }
  }, [apiAdmin, message, loadPlugins])

  const deletePlugin = useCallback(async (slug: string) => {
    try {
      setLoading(true)
      const response = await AdminApi.deletePlugin(apiAdmin, slug)
      if (response.code === 200) {
        message.success('删除成功')
        await loadPlugins()
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
  }, [apiAdmin, message, loadPlugins])

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

