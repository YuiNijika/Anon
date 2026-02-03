import { useState, useEffect, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type BasicSettings } from '@/services/admin'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

function arrayToString(arr: string[] | string | undefined): string {
  if (Array.isArray(arr)) return arr.filter((k) => k?.trim()).join(',')
  return typeof arr === 'string' ? arr : ''
}

const basicSchema = z.object({
  title: z.string().min(1, '请输入站点标题'),
  subtitle: z.string().optional(),
  description: z.string().optional(),
  keywords: z.string().optional(),
  'upload_allowed_types.image': z.string().optional(),
  'upload_allowed_types.media': z.string().optional(),
  'upload_allowed_types.document': z.string().optional(),
  'upload_allowed_types.other': z.string().optional(),
})

type BasicFormValues = z.infer<typeof basicSchema>

export default function SettingsBasic() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)
  const form = useForm<BasicFormValues>({
    resolver: zodResolver(basicSchema),
    defaultValues: {
      title: '',
      subtitle: 'Powered by AnonEcho',
      description: '',
      keywords: '',
      'upload_allowed_types.image': '',
      'upload_allowed_types.media': '',
      'upload_allowed_types.document': '',
      'upload_allowed_types.other': '',
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
      const basicRes = await AdminApi.getBasicSettings(apiAdmin)
      if (basicRes.data) {
        const d = basicRes.data
        const uploadTypes = d.upload_allowed_types || {}
        form.reset({
          title: d.title ?? '',
          subtitle: d.subtitle ?? 'Powered by AnonEcho',
          description: d.description ?? '',
          keywords: typeof d.keywords === 'string' ? d.keywords : arrayToString(d.keywords as any),
          'upload_allowed_types.image': typeof uploadTypes.image === 'string' ? uploadTypes.image : arrayToString(uploadTypes.image as any),
          'upload_allowed_types.media': typeof uploadTypes.media === 'string' ? uploadTypes.media : arrayToString(uploadTypes.media as any),
          'upload_allowed_types.document': typeof uploadTypes.document === 'string' ? uploadTypes.document : arrayToString(uploadTypes.document as any),
          'upload_allowed_types.other': typeof uploadTypes.other === 'string' ? uploadTypes.other : arrayToString(uploadTypes.other as any),
        })
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载设置失败'))
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleSubmit = async (values: BasicFormValues) => {
    try {
      setLoading(true)
      const basicData: BasicSettings = {
        title: values.title || '',
        subtitle: values.subtitle || 'Powered by AnonEcho',
        description: values.description || '',
        keywords: values.keywords ?? '',
        upload_allowed_types: {
          image: values['upload_allowed_types.image'] ?? '',
          media: values['upload_allowed_types.media'] ?? '',
          document: values['upload_allowed_types.document'] ?? '',
          other: values['upload_allowed_types.other'] ?? '',
        },
      }
      await AdminApi.updateBasicSettings(apiAdmin, basicData)
      toast.success('设置已保存')
      await loadSettings()
    } catch (err) {
      toast.error(getErrorMessage(err, '保存设置失败'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-8">
      <h2 className="text-lg font-semibold">站点信息</h2>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
          <FormField
            control={form.control}
            name="title"
            render={({ field }) => (
              <FormItem>
                <FormLabel>站点标题</FormLabel>
                <FormControl>
                  <Input placeholder="站点名称" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="subtitle"
            render={({ field }) => (
              <FormItem>
                <FormLabel>站点副标题</FormLabel>
                <FormDescription>如果为空则不显示</FormDescription>
                <FormControl>
                  <Input placeholder="Powered by AnonEcho" {...field} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="description"
            render={({ field }) => (
              <FormItem>
                <FormLabel>站点描述</FormLabel>
                <FormControl>
                  <textarea className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="用简洁的文字描述您的站点" rows={3} {...field} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="keywords"
            render={({ field }) => (
              <FormItem>
                <FormLabel>关键词</FormLabel>
                <FormDescription>逗号分隔</FormDescription>
                <FormControl>
                  <Input placeholder="关键词1, 关键词2" {...field} />
                </FormControl>
              </FormItem>
            )}
          />

          <hr />
          <h3 className="text-base font-semibold">文件上传设置</h3>
          <p className="text-sm text-muted-foreground">输入文件扩展名，逗号分隔。如：jpg, png, gif</p>
          {(['image', 'media', 'document', 'other'] as const).map((key) => (
            <FormField
              key={key}
              control={form.control}
              name={`upload_allowed_types.${key}` as any}
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{{ image: '图片', media: '媒体', document: '文档', other: '其他' }[key]}</FormLabel>
                  <FormControl>
                    <Input placeholder={{ image: 'jpg, png, gif', media: 'mp3, mp4', document: 'pdf, doc', other: 'zip, rar' }[key]} {...field} />
                  </FormControl>
                </FormItem>
              )}
            />
          ))}

          <div className="flex gap-2 justify-end">
            <Button type="submit" disabled={loading}>{loading ? '保存中...' : '保存更改'}</Button>
            <Button type="button" variant="outline" onClick={() => loadSettings()}>重置</Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
