import { useState, useEffect, useRef } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi } from '@/services/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import * as Vue from 'vue'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import 'element-plus/theme-chalk/dark/css-vars.css'

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
  const fetchingRef = useRef(false)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (pluginSlug && pageSlug) {
      loadPluginPage(pluginSlug, pageSlug)
    } else {
      setPageConfig(null)
    }
  }, [pluginSlug, pageSlug])

  const loadPluginPage = async (pluginSlug: string, pageSlug: string) => {
    setLoading(true)
    fetchingRef.current = true
    try {
      const res = await AdminApi.getPluginPage(apiAdmin, { slug: pluginSlug, page: pageSlug })
      if (res.data) {
        setPageConfig(res.data)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载页面配置失败'))
    } finally {
      fetchingRef.current = false
      setLoading(false)
    }
  }

  useEffect(() => {
    if (!pageConfig?.content) return

    const container = containerRef.current
    if (!container) return

    // 注入 HTML
    container.innerHTML = pageConfig.content

    // 查找并执行 scripts
    const scripts = container.querySelectorAll('script')
    scripts.forEach(oldScript => {
      const newScript = document.createElement('script')
      Array.from(oldScript.attributes).forEach(attr => {
        newScript.setAttribute(attr.name, attr.value)
      })
      newScript.textContent = oldScript.textContent
      document.body.appendChild(newScript)
      oldScript.remove()
      // Optional: remove the script tag after execution to keep DOM clean
      // setTimeout(() => newScript.remove(), 0)
    })

    // 等待脚本执行完毕，获取组件定义并挂载 Vue
    setTimeout(() => {
      // @ts-ignore
      if (window.AnonPluginPage) {
        try {
          // @ts-ignore
          const app = Vue.createApp(window.AnonPluginPage);
          app.use(ElementPlus);
          app.mount("#anon-plugin-page");

          // 清理全局对象
          // @ts-ignore
          window.AnonPluginPage = null;
        } catch (e) {
          console.error("Vue mount failed:", e);
        }
      } else {
        console.warn('AnonPluginPage not found');
      }
    }, 100)

  }, [pageConfig])

  if (!pluginSlug || !pageSlug) {
    return (
      <div className="p-4 text-center text-muted-foreground">
        参数缺失
      </div>
    )
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" asChild title="返回列表">
            <Link to="/plugins">
              <ArrowLeft className="h-4 w-4" />
            </Link>
          </Button>
          <CardTitle>
            {pageConfig?.title || '加载中...'}
          </CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-4">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
        ) : (
          <div ref={containerRef} id="anon-plugin-page" className="min-h-[200px]">
            {/* HTML injected via useEffect */}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
