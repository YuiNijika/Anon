import { useState, useEffect, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type PermissionSettings } from '@/services/admin'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

const permissionSchema = z.object({
  allow_register: z.boolean().optional(),
  access_log_enabled: z.boolean().optional(),
  api_enabled: z.boolean().optional(),
  api_prefix: z.string().min(1, '请输入 API 前缀'),
})

type PermissionFormValues = z.infer<typeof permissionSchema>

export default function SettingsPermission() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)
  const form = useForm<PermissionFormValues>({
    resolver: zodResolver(permissionSchema),
    defaultValues: {
      allow_register: false,
      access_log_enabled: true,
      api_prefix: '/api',
      api_enabled: false,
    },
  })

  useEffect(() => {
    loadSettings()
  }, [apiAdmin])

  const loadSettings = async () => {
    if (fetchingRef.current) return
    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getPermissionSettings(apiAdmin)
      if (res.data) {
        form.reset(res.data)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载权限设置失败'))
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleSubmit = async (values: PermissionFormValues) => {
    try {
      setLoading(true)
      const submitData: PermissionSettings = {
        allow_register: values.allow_register === true,
        access_log_enabled: values.access_log_enabled !== false,
        api_prefix: values.api_prefix || '/api',
        api_enabled: values.api_enabled === true,
      }
      await AdminApi.updatePermissionSettings(apiAdmin, submitData)
      toast.success('权限设置已保存')
      await loadSettings()
    } catch (err) {
      toast.error(getErrorMessage(err, '保存权限设置失败'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold">用户与访问</h2>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
          <FormField
            control={form.control}
            name="allow_register"
            render={({ field }) => (
              <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <FormLabel>允许注册</FormLabel>
                  <FormDescription>允许用户自行注册账户</FormDescription>
                </div>
                <FormControl>
                  <Switch checked={field.value} onCheckedChange={field.onChange} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="access_log_enabled"
            render={({ field }) => (
              <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <FormLabel>启用访问日志</FormLabel>
                  <FormDescription>记录网站的访问日志</FormDescription>
                </div>
                <FormControl>
                  <Switch checked={field.value} onCheckedChange={field.onChange} />
                </FormControl>
              </FormItem>
            )}
          />
          <hr className="my-6" />
          <h2 className="text-lg font-semibold">API 设置</h2>
          <FormField
            control={form.control}
            name="api_enabled"
            render={({ field }) => (
              <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <FormLabel>启用 API</FormLabel>
                  <FormDescription>启用后，系统将提供 RESTful API 接口</FormDescription>
                </div>
                <FormControl>
                  <Switch checked={field.value} onCheckedChange={field.onChange} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="api_prefix"
            render={({ field }) => (
              <FormItem>
                <FormLabel>API 前缀</FormLabel>
                <FormControl>
                  <Input placeholder="/api" {...field} />
                </FormControl>
                <FormDescription>API 接口的前缀路径，必须以 / 开头</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="flex gap-2 justify-end">
            <Button type="submit" disabled={loading}>
              {loading ? '保存中...' : '保存更改'}
            </Button>
            <Button type="button" variant="outline" onClick={() => form.reset()}>
              重置
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
