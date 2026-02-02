import { useState, useEffect, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Pencil, FileText, Image } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import MDEditor from '@uiw/react-md-editor'
import '@uiw/react-md-editor/markdown-editor.css'
import { useTheme, useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import MediaLibrary from '@/components/MediaLibrary'
import { buildPublicUrl } from '@/utils/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

type ContentType = 'post' | 'page'

const writeSchema = z.object({
  title: z.string().min(1, '请输入标题'),
  slug: z.string().optional(),
  status: z.enum(['draft', 'publish', 'private']).optional(),
  type: z.enum(['post', 'page']),
  category: z.number().optional().nullable(),
  tags: z.array(z.string()).optional(),
  content: z.string().optional(),
})

type WriteFormValues = z.infer<typeof writeSchema>

export default function Write() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [searchParams] = useSearchParams()
  const [loading, setLoading] = useState(false)
  const [categories, setCategories] = useState<{ id: number; name: string }[]>([])
  const [mediaLibraryOpen, setMediaLibraryOpen] = useState(false)
  const [postId, setPostId] = useState<number | null>(null)
  const [loadingPost, setLoadingPost] = useState(false)

  const form = useForm<WriteFormValues>({
    resolver: zodResolver(writeSchema),
    defaultValues: {
      title: '',
      slug: '',
      status: 'publish',
      type: 'post',
      category: null,
      tags: [],
      content: '',
    },
  })

  const contentType = form.watch('type') as ContentType

  const loadCategories = useCallback(async () => {
    try {
      const response = await apiAdmin.admin.get('/metas/categories')
      if (response.code === 200) setCategories(response.data || [])
    } catch (err) {
      console.error('加载分类失败:', err)
    }
  }, [apiAdmin])

  useEffect(() => {
    loadCategories()
  }, [loadCategories])

  const loadPost = useCallback(async (id: number) => {
    try {
      setLoadingPost(true)
      const response = await apiAdmin.admin.get('/posts', { id })
      if (response.code === 200 && response.data) {
        const post = response.data as Record<string, unknown>
        setPostId(post.id as number)
        form.reset({
          title: (post.title as string) || '',
          slug: (post.slug as string) || '',
          status: (post.status as WriteFormValues['status']) || 'publish',
          type: (post.type as ContentType) || 'post',
          category: (post.category as number) ?? null,
          tags: Array.isArray(post.tags) ? (post.tags as string[]) : [],
          content: (post.content as string) || '',
        })
      } else {
        toast.error('加载文章失败')
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载文章失败'))
    } finally {
      setLoadingPost(false)
    }
  }, [apiAdmin, form])

  useEffect(() => {
    const id = searchParams.get('id')
    if (id) {
      const postIdNum = parseInt(id, 10)
      if (!isNaN(postIdNum) && postIdNum > 0) loadPost(postIdNum)
    } else {
      setPostId(null)
      form.reset({
        title: '',
        slug: '',
        status: 'publish',
        type: 'post',
        category: null,
        tags: [],
        content: '',
      })
    }
  }, [searchParams, loadPost, form])

  const handleSubmit = async (values: WriteFormValues) => {
    try {
      setLoading(true)
      const submitData = {
        ...values,
        content: values.content ?? '',
      }
      let response
      if (postId) {
        response = await apiAdmin.admin.put('/posts', { id: postId, ...submitData })
      } else {
        response = await apiAdmin.admin.post('/posts', submitData)
      }
      if (response.code === 200) {
        toast.success(`${postId ? '更新' : '创建'}${contentType === 'post' ? '文章' : '页面'}成功`)
        if (!postId && response.data?.id) {
          setPostId(response.data.id)
          window.history.replaceState({}, '', `/write?id=${response.data.id}`)
        }
      } else {
        toast.error((response as { message?: string }).message || `${postId ? '更新' : '创建'}失败`)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, `${postId ? '更新' : '创建'}${contentType === 'post' ? '文章' : '页面'}失败`))
    } finally {
      setLoading(false)
    }
  }

  const handleTypeChange = (type: ContentType) => {
    form.setValue('type', type)
    if (!postId) {
      form.setValue('title', '')
      form.setValue('slug', '')
      form.setValue('content', '')
    }
  }

  const handleMediaSelect = (attachmentOrList: unknown) => {
    const list = Array.isArray(attachmentOrList) ? attachmentOrList : [attachmentOrList]
    const currentContent = form.getValues('content') || ''
    const blocks = list
      .filter(Boolean)
      .map((a: Record<string, unknown>) => {
        const url = (a.insert_url || a.url) as string
        const name = (a.original_name ?? a.name ?? '') as string
        return `![${name}](${buildPublicUrl(url)})`
      })
    const newContent = currentContent + (currentContent ? '\n\n' : '') + blocks.join('\n\n')
    form.setValue('content', newContent)
  }

  return (
    <div className="flex gap-6">
      <div className="w-[75%] shrink-0">
        <Card>
          <CardContent className="pt-6">
            <Form {...form}>
              <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                <FormField
                  control={form.control}
                  name="title"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>标题</FormLabel>
                      <FormControl>
                        <Input placeholder={contentType === 'post' ? '文章标题' : '页面标题'} className="text-xl font-semibold" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="content"
                  render={({ field }) => (
                    <FormItem>
                      <div className="flex items-center justify-between">
                        <FormLabel>内容</FormLabel>
                        <Button type="button" variant="link" size="sm" onClick={() => setMediaLibraryOpen(true)}>
                          <Image className="mr-1 h-4 w-4" />
                          插入媒体
                        </Button>
                      </div>
                      <FormControl>
                        <div data-color-mode={isDark ? 'dark' : 'light'} className="rounded-md border overflow-hidden">
                          <MDEditor
                            value={field.value ?? ''}
                            onChange={(value) => field.onChange(value ?? '')}
                            preview="edit"
                            hideToolbar={false}
                            height={500}
                          />
                        </div>
                      </FormControl>
                    </FormItem>
                  )}
                />
              </form>
            </Form>
          </CardContent>
        </Card>
      </div>

      <div className="w-[25%] shrink-0 space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">发布</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Form {...form}>
              <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                <FormField
                  control={form.control}
                  name="type"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>内容类型</FormLabel>
                      <FormControl>
                        <RadioGroup
                          value={field.value}
                          onValueChange={(v) => handleTypeChange(v as ContentType)}
                          className="flex flex-col gap-2"
                          disabled={!!postId}
                        >
                          <div className="flex items-center space-x-2 rounded-md border p-3">
                            <RadioGroupItem value="post" id="type-post" />
                            <Label htmlFor="type-post" className="flex cursor-pointer items-center gap-2">
                              <Pencil className="h-4 w-4" />
                              文章
                            </Label>
                          </div>
                          <div className="flex items-center space-x-2 rounded-md border p-3">
                            <RadioGroupItem value="page" id="type-page" />
                            <Label htmlFor="type-page" className="flex cursor-pointer items-center gap-2">
                              <FileText className="h-4 w-4" />
                              页面
                            </Label>
                          </div>
                        </RadioGroup>
                      </FormControl>
                    </FormItem>
                  )}
                />
                <hr />
                <FormField
                  control={form.control}
                  name="status"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>状态</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="draft">草稿</SelectItem>
                          <SelectItem value="publish">发布</SelectItem>
                          <SelectItem value="private">私有</SelectItem>
                        </SelectContent>
                      </Select>
                    </FormItem>
                  )}
                />
                <Button type="submit" className="w-full" disabled={loading || loadingPost}>
                  {postId ? '更新' : '立即发布'}
                </Button>
                <hr />
                {contentType === 'post' && (
                  <>
                    <FormField
                      control={form.control}
                      name="category"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>分类</FormLabel>
                          <Select
                            value={field.value != null ? String(field.value) : ''}
                            onValueChange={(v) => field.onChange(v ? Number(v) : null)}
                          >
                            <SelectTrigger>
                              <SelectValue placeholder="选择分类" />
                            </SelectTrigger>
                            <SelectContent>
                              {categories.map((cat) => (
                                <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="tags"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>标签</FormLabel>
                          <FormControl>
                            <Input
                              placeholder="逗号分隔的标签"
                              value={Array.isArray(field.value) ? field.value.join(', ') : ''}
                              onChange={(e) => {
                                const v = e.target.value
                                const arr = v ? v.split(',').map((s) => s.trim()).filter(Boolean) : []
                                field.onChange(arr)
                              }}
                            />
                          </FormControl>
                        </FormItem>
                      )}
                    />
                    <hr />
                  </>
                )}
                <FormField
                  control={form.control}
                  name="slug"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>别名</FormLabel>
                      <FormControl>
                        <Input placeholder="URL 友好的别名" {...field} />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </form>
            </Form>
          </CardContent>
        </Card>
      </div>

      <MediaLibrary open={mediaLibraryOpen} onClose={() => setMediaLibraryOpen(false)} onSelect={handleMediaSelect} />
    </div>
  )
}
