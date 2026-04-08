import { useState, useEffect, useRef } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin, useReactComponentMounter } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi } from '@/services/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import * as Vue from 'vue'

export default function Pages() {
  const apiAdmin = useApiAdmin()
  const [searchParams] = useSearchParams()
  const pluginParam = searchParams.get('plugin')
  
  let pluginSlug = pluginParam
  let pageSlug = searchParams.get('page')

  // 支持 plugin=applist:add-app 格式
  if (pluginParam && pluginParam.includes(':')) {
    const parts = pluginParam.split(':')
    pluginSlug = parts[0]
    pageSlug = parts[1]
  }

  const [pageConfig, setPageConfig] = useState<any>(null)
  const [loading, setLoading] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)
  
  // 使用 React 组件挂载器
  const { remount } = useReactComponentMounter([pageConfig?.content])

  useEffect(() => {
    if (pluginSlug && pageSlug) {
      loadPluginPage(pluginSlug, pageSlug)
    }
  }, [pluginSlug, pageSlug])

  const loadPluginPage = async (pSlug: string, pgSlug: string) => {
    setLoading(true)
    try {
      const res = await AdminApi.getPluginPage(apiAdmin, { slug: pSlug, page: pgSlug })
      if (res.data) {
        setPageConfig(res.data)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载页面配置失败'))
    } finally {
      setLoading(false)
    }
  }

  // 处理 HTML 字符串注入与 Vue 挂载逻辑
  useEffect(() => {
    if (!pageConfig?.content) return

    const container = containerRef.current
    if (!container) return

    // 清理旧的 Vue 实例防止内存泄漏
    if ((container as any).__vue_app__) {
      ;(container as any).__vue_app__.unmount()
      ;(container as any).__vue_app__ = null
    }
    container.innerHTML = ''

    // 创建临时容器解析 HTML 并提取脚本
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = pageConfig.content
    const scripts = Array.from(tempDiv.querySelectorAll('script'))
    
    scripts.forEach((script) => {
      let code = script.textContent
      if (code && code.trim()) {
        try {
          // 移除 BOM 和非法控制字符确保执行安全
          code = code.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F-\u009F]/g, '')
          new Function(code)()
        } catch (e) {
          console.error('Plugin Script Error:', e)
        }
      }
      script.remove()
    })

    // 注入纯净的 HTML 内容
    container.innerHTML = tempDiv.innerHTML

    // 挂载 React 组件（短代码）
    setTimeout(() => remount(), 0)

    // 轮询检查并挂载 Vue 应用
    let retryCount = 0
    const checkAndMount = () => {
      const PluginPage = (window as any).AnonPluginPage
      if (PluginPage) {
        const app = Vue.createApp(PluginPage)
        app.mount("#anon-plugin-page")
        ;(container as any).__vue_app__ = app
        ;(window as any).AnonPluginPage = null
      } else if (retryCount < 15) {
        retryCount++
        setTimeout(checkAndMount, 50)
      }
    }
    setTimeout(checkAndMount, 10)

    // 组件卸载时清理资源
    return () => {
      if ((container as any).__vue_app__) {
        ;(container as any).__vue_app__.unmount()
        ;(container as any).__vue_app__ = null
      }
    }
  }, [pageConfig])

  if (!pluginSlug || !pageSlug) {
    return <div className="p-4 text-center text-muted-foreground">参数缺失</div>
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" asChild title="返回列表">
            <Link to="/plugins"><ArrowLeft className="h-4 w-4" /></Link>
          </Button>
          <CardTitle>{pageConfig?.title || '加载中...'}</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-4">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
        ) : (
          <div ref={containerRef} id="anon-plugin-page" className="min-h-[200px]" />
        )}
      </CardContent>
    </Card>
  )
}
