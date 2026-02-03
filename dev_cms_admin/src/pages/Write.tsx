import { useState, useEffect, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Pencil, FileText, Image, X } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import MDEditor from '@uiw/react-md-editor'
import '@uiw/react-md-editor/markdown-editor.css'
import { useTheme, useApiAdmin, useTags } from '@/hooks'
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
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { Tag } from '@/services/admin'
import CategoryManager from '@/components/CategoryManager'

type ContentType = 'post' | 'page'

function TagInput({
  value,
  onChange,
  suggestions,
  placeholder = '输入标签，可从下方选择或回车添加',
}: {
  value: string[]
  onChange: (v: string[]) => void
  suggestions: Tag[]
  placeholder?: string
}) {
  const [inputValue, setInputValue] = useState('')
  const [open, setOpen] = useState(false)
  const filtered = suggestions.filter(
    (t) =>
      !value.includes(t.name) &&
      (inputValue === '' || t.name.toLowerCase().includes(inputValue.toLowerCase()))
  )
  const addTag = (name: string) => {
    const n = name.trim()
    if (n && !value.includes(n)) onChange([...value, n])
    setInputValue('')
    setOpen(false)
  }
  const removeTag = (name: string) => onChange(value.filter((t) => t !== name))
  return (
    <div className="relative">
      <div
        className={cn(
          'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2'
        )}
      >
        {value.map((tag) => (
          <Badge key={tag} variant="secondary" className="gap-0.5 pr-1">
            {tag}
            <button
              type="button"
              className="ml-0.5 rounded-full p-0.5 hover:bg-muted"
              onClick={() => removeTag(tag)}
              aria-label="移除"
            >
              <X className="h-3 w-3" />
            </button>
          </Badge>
        ))}
        <input
          value={inputValue}
          onChange={(e) => {
            setInputValue(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ',') {
              e.preventDefault()
              addTag(inputValue)
            }
          }}
          placeholder={value.length === 0 ? placeholder : ''}
          className="flex-1 min-w-[120px] border-0 bg-transparent p-0 outline-none placeholder:text-muted-foreground"
        />
      </div>
      {open && (inputValue !== '' || filtered.length > 0) && (
        <ul className="absolute z-50 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover py-1 shadow-md">
          {filtered.length === 0 ? (
            inputValue.trim() && (
              <li>
                <button
                  type="button"
                  className="w-full px-3 py-2 text-left text-sm hover:bg-muted"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => addTag(inputValue.trim())}
                >
                  添加「{inputValue.trim()}」
                </button>
              </li>
            )
          ) : (
            filtered.map((t) => (
              <li key={t.id}>
                <button
                  type="button"
                  className="w-full px-3 py-2 text-left text-sm hover:bg-muted"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => addTag(t.name)}
                >
                  {t.name}
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  )
}

const writeSchema = z.object({
  title: z.string().min(1, '请输入标题'),
  slug: z.string().optional(),
  status: z.enum(['draft', 'publish', 'private']).optional(),
  type: z.enum(['post', 'page']),
  category: z.number().nullable(),
  tags: z.array(z.string()).optional(),
  content: z.string().optional(),
}).refine(
  (data) => {
    // 如果是文章且状态为发布，则分类必填
    if (data.type === 'post' && data.status === 'publish' && data.category === null) {
      return false
    }
    return true
  },
  {
    message: '发布文章时分类不能为空',
    path: ['category'],
  }
)

type WriteFormValues = z.infer<typeof writeSchema>

export default function Write() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const { data: tagsList, loadTags } = useTags()
  const [searchParams] = useSearchParams()
  const [loading, setLoading] = useState(false)
  const [categories, setCategories] = useState<{ id: number; name: string; slug?: string; description?: string }[]>([])
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
      category: null, // 将在useEffect中动态设置
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

  useEffect(() => {
    loadTags()
  }, [loadTags])



  const loadPost = useCallback(async (id: number) => {
    try {
      setLoadingPost(true)
      const [postRes, tagsData] = await Promise.all([
        apiAdmin.admin.get('/posts', { id }),
        loadTags(),
      ])
      const response = postRes
      if (response.code === 200 && response.data) {
        const post = response.data as Record<string, unknown>
        setPostId(post.id as number)
        const tagIds = Array.isArray(post.tags) ? (post.tags as number[]) : []
        const allTags = Array.isArray(tagsData) ? tagsData : []
        const tagNames: string[] = tagIds.length
          ? (tagIds as number[])
            .map((tid) => allTags.find((t) => t.id === tid)?.name)
            .filter((n): n is string => Boolean(n))
          : []
        form.reset({
          title: (post.title as string) || '',
          slug: (post.slug as string) || '',
          status: (post.status as WriteFormValues['status']) || 'publish',
          type: (post.type as ContentType) || 'post',
          category: (post.category as number) ?? null,
          tags: tagNames,
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
  }, [apiAdmin, form, loadTags])

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
        category: null, // 将在另一个useEffect中设置
        tags: [],
        content: '',
      })
    }
  }, [searchParams, loadPost, form])

  // 新建文章时，分类加载后设置默认分类
  useEffect(() => {
    const id = searchParams.get('id')
    if (!id && categories.length > 0) {
      const currentCategory = form.getValues('category')
      if (currentCategory === null || currentCategory === undefined) {
        form.setValue('category', categories[0].id)
      }
    }
  }, [categories, searchParams, form])

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
    <div className="flex flex-col lg:flex-row gap-6">
      <div className="flex-1 lg:w-[75%] lg:shrink-0">
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
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
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

      <div className="lg:w-[25%] lg:shrink-0 space-y-4 w-full">
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
                        <FormItem className="space-y-3">
                          <FormLabel>分类 *</FormLabel>
                          <>
                            <Select
                              value={field.value ? String(field.value) : ''}
                              onValueChange={(v) => field.onChange(Number(v))}
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
                            <CategoryManager
                              categories={categories}
                              selectedCategoryId={field.value}
                              onCategoriesChange={setCategories}
                              onCategorySelect={(categoryId) => {
                                field.onChange(categoryId)
                                // 更新默认分类逻辑：如果新建文章且没有选中分类，选择第一个分类
                                const id = searchParams.get('id')
                                if (!id && categoryId && categories.length > 0) {
                                  field.onChange(categoryId)
                                }
                              }}
                            />
                            <FormMessage />
                          </>
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
                            <TagInput
                              value={Array.isArray(field.value) ? field.value : []}
                              onChange={field.onChange}
                              suggestions={tagsList ?? []}
                              placeholder="输入标签，可从下方选择或回车添加"
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
