import { useState, useEffect } from 'react'
import { useApiAdmin } from '@/hooks'
import { toast } from 'sonner'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi } from '@/services/admin'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Save, RotateCcw } from 'lucide-react'

export default function SettingsStatistics() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [settings, setSettings] = useState({
    enabled: true,
    log_api: false,
    log_static: false,
  })

  useEffect(() => {
    loadSettings()
  }, [])

  const loadSettings = async () => {
    setLoading(true)
    try {
      const res = await AdminApi.getSystemConfig(apiAdmin, [
        'access_log_enabled',
        'access_log_api',
        'access_log_static',
      ])
      if (res.data) {
        setSettings({
          enabled: res.data.access_log_enabled !== null && res.data.access_log_enabled !== undefined 
            ? (res.data.access_log_enabled === '1' || res.data.access_log_enabled === true) 
            : true,
          log_api: res.data.access_log_api !== null && res.data.access_log_api !== undefined 
            ? (res.data.access_log_api === '1' || res.data.access_log_api === true) 
            : false,
          log_static: res.data.access_log_static !== null && res.data.access_log_static !== undefined 
            ? (res.data.access_log_static === '1' || res.data.access_log_static === true) 
            : false,
        })
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载设置失败'))
    } finally {
      setLoading(false)
    }
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await AdminApi.updateSystemConfig(apiAdmin, {
        access_log_enabled: settings.enabled ? '1' : '0',
        access_log_api: settings.log_api ? '1' : '0',
        access_log_static: settings.log_static ? '1' : '0',
      })
      toast.success('设置已保存')
    } catch (err) {
      toast.error(getErrorMessage(err, '保存设置失败'))
    } finally {
      setSaving(false)
    }
  }

  const handleReset = () => {
    setSettings({
      enabled: true,
      log_api: false,
      log_static: false,
    })
    toast.info('已重置为默认值')
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-1/3" />
        <Card>
          <CardContent className="pt-6">
            <div className="space-y-4">
              <Skeleton className="h-6 w-full" />
              <Skeleton className="h-6 w-full" />
              <Skeleton className="h-6 w-full" />
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">访问日志设置</h2>
          <p className="text-muted-foreground">配置系统访问日志的记录规则</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleReset} disabled={saving}>
            <RotateCcw className="mr-2 h-4 w-4" />
            重置
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            <Save className="mr-2 h-4 w-4" />
            保存设置
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>记录规则</CardTitle>
          <CardDescription>选择需要记录的请求类型</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex items-center justify-between space-x-2">
            <div className="space-y-0.5">
              <Label htmlFor="enabled">启用访问日志</Label>
              <p className="text-sm text-muted-foreground">开启后将记录所有符合规则的访问请求</p>
            </div>
            <Switch
              id="enabled"
              checked={settings.enabled}
              onCheckedChange={(checked) => setSettings({ ...settings, enabled: checked })}
            />
          </div>

          <div className="flex items-center justify-between space-x-2">
            <div className="space-y-0.5">
              <Label htmlFor="log_api">记录 API 请求</Label>
              <p className="text-sm text-muted-foreground">记录 /api/ 和 /anon/ 路径下的接口调用（产生大量日志）</p>
            </div>
            <Switch
              id="log_api"
              checked={settings.log_api}
              onCheckedChange={(checked) => setSettings({ ...settings, log_api: checked })}
              disabled={!settings.enabled}
            />
          </div>

          <div className="flex items-center justify-between space-x-2">
            <div className="space-y-0.5">
              <Label htmlFor="log_static">记录静态资源</Label>
              <p className="text-sm text-muted-foreground">记录图片、CSS、JS 等静态文件访问（产生大量日志）</p>
            </div>
            <Switch
              id="log_static"
              checked={settings.log_static}
              onCheckedChange={(checked) => setSettings({ ...settings, log_static: checked })}
              disabled={!settings.enabled}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>说明</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-muted-foreground">
          <p>• 系统会自动排除管理后台、安装程序和安全敏感文件的访问记录</p>
          <p>• 开启静态资源记录可能会导致日志量急剧增加，建议仅在调试时开启</p>
          <p>• 可以通过插件钩子 <code className="bg-muted px-1 rounded">access_log_should_log</code> 自定义记录规则</p>
        </CardContent>
      </Card>
    </div>
  )
}
