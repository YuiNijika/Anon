import { useState, useEffect, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi } from '@/services/admin'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

const PATH_STYLES = [
  { value: 'default', label: '默认风格', paths: { post: '/archives/{id}/', page: '/{slug}.html', category: '/category/{slug}/', tag: '/tag/{slug}/' } },
  { value: 'wordpress', label: 'WordPress 风格', paths: { post: '/archives/{slug}.html', page: '/{slug}.html', category: '/category/{slug}/', tag: '/tag/{slug}/' } },
  { value: 'date', label: '按日期归档', paths: { post: '/{year}/{month}/{day}/{slug}.html', page: '/{slug}.html', category: '/category/{slug}/', tag: '/tag/{slug}/' } },
  { value: 'category', label: '按分类归档', paths: { post: '/{category}/{slug}.html', page: '/{slug}.html', category: '/category/{slug}/', tag: '/tag/{slug}/' } },
  { value: 'custom', label: '个性化定义', paths: { post: '', page: '', category: '', tag: '' } },
]

const POST_PARAMS = ['{id}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}']
const PAGE_PARAMS = ['{id}', '{slug}', '{directory}']
const CATEGORY_PARAMS = ['{id}', '{slug}', '{directory}']
const TAG_PARAMS = ['{id}', '{slug}']
const USER_PARAMS = ['{uid}', '{name}']
const PAGINATION_PARAMS = ['{page}']

const pageSchema = z.object({
  postPathStyle: z.string(),
  postPath: z.string().min(1).regex(/^\//).refine((v) => POST_PARAMS.some((p) => v.includes(p)), '路径中至少包含一个可用参数'),
  pagePath: z.string().min(1).regex(/^\//).refine((v) => PAGE_PARAMS.some((p) => v.includes(p)), '路径中至少包含一个可用参数'),
  categoryPath: z.string().min(1).regex(/^\//).refine((v) => CATEGORY_PARAMS.some((p) => v.includes(p)), '路径中至少包含一个可用参数'),
  categoryPaginationPath: z.string().min(1).regex(/^\//).refine((v) => PAGINATION_PARAMS.some((p) => v.includes(p)), '路径中至少包含 {page}'),
  tagPath: z.string().min(1).regex(/^\//).refine((v) => TAG_PARAMS.some((p) => v.includes(p)), '路径中至少包含一个可用参数'),
  tagPaginationPath: z.string().min(1).regex(/^\//).refine((v) => PAGINATION_PARAMS.some((p) => v.includes(p)), '路径中至少包含 {page}'),
  userPath: z.string().min(1).regex(/^\//).refine((v) => USER_PARAMS.some((p) => v.includes(p)), '路径中至少包含 {uid} 或 {name}'),
})

type PageFormValues = z.infer<typeof pageSchema>

export default function SettingsPage() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)
  const form = useForm<PageFormValues>({
    resolver: zodResolver(pageSchema),
    defaultValues: {
      postPathStyle: 'default',
      postPath: '/archives/{id}',
      pagePath: '/{slug}.html',
      categoryPath: '/category/{slug}',
      categoryPaginationPath: '/category/{slug}/{page}',
      tagPath: '/tag/{slug}',
      tagPaginationPath: '/tag/{slug}/{page}',
      userPath: '/user/{uid}',
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
      const res = await AdminApi.getPageSettings(apiAdmin)
      if (res.data?.routes) {
        const routes = res.data.routes
        const postPath = Object.entries(routes).find(([, t]) => t === 'post')?.[0] ?? '/archives/{id}/'
        const pagePath = Object.entries(routes).find(([, t]) => t === 'page')?.[0] ?? '/{slug}.html'

        // 查找分类路由
        const categoryPath = Object.entries(routes).find(([k, t]) => t === 'category' && !k.includes('{page}'))?.[0] ?? '/category/{slug}/'
        // 查找分类分页路由
        const categoryPaginationPath = Object.entries(routes).find(([k, t]) => t === 'category' && k.includes('{page}'))?.[0]
          ?? (categoryPath.endsWith('/') ? `${categoryPath}{page}` : `${categoryPath}/{page}`)

        // 查找标签路由
        const tagPath = Object.entries(routes).find(([k, t]) => t === 'tag' && !k.includes('{page}'))?.[0] ?? '/tag/{slug}/'
        // 查找标签分页路由
        const tagPaginationPath = Object.entries(routes).find(([k, t]) => t === 'tag' && k.includes('{page}'))?.[0]
          ?? (tagPath.endsWith('/') ? `${tagPath}{page}` : `${tagPath}/{page}`)

        const userPath = Object.entries(routes).find(([, t]) => t === 'user')?.[0] ?? '/user/{uid}/'
        const detectedStyle = PATH_STYLES.find((s) => s.paths.post === postPath && s.paths.page === pagePath && s.paths.category === categoryPath && s.paths.tag === tagPath)?.value ?? 'custom'

        form.reset({
          postPathStyle: detectedStyle,
          postPath,
          pagePath,
          categoryPath,
          categoryPaginationPath,
          tagPath,
          tagPaginationPath,
          userPath
        })
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载页面设置失败'))
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handlePathStyleChange = (value: string) => {
    const selected = PATH_STYLES.find((s) => s.value === value)
    if (selected?.paths) {
      form.setValue('postPath', selected.paths.post)
      form.setValue('pagePath', selected.paths.page)
      form.setValue('categoryPath', selected.paths.category)
      form.setValue('tagPath', selected.paths.tag)
    }
  }

  const handleSubmit = async (values: PageFormValues) => {
    try {
      setLoading(true)
      const routes: Record<string, string> = {}

      // 添加文章路由
      if (values.postPath?.trim()) routes[values.postPath.trim()] = 'post'

      // 添加页面路由
      if (values.pagePath?.trim()) routes[values.pagePath.trim()] = 'page'

      // 添加分类路由及其分页
      if (values.categoryPath?.trim()) {
        routes[values.categoryPath.trim()] = 'category'
      }
      if (values.categoryPaginationPath?.trim()) {
        routes[values.categoryPaginationPath.trim()] = 'category'
      }

      // 添加标签路由及其分页
      if (values.tagPath?.trim()) {
        routes[values.tagPath.trim()] = 'tag'
      }
      if (values.tagPaginationPath?.trim()) {
        routes[values.tagPaginationPath.trim()] = 'tag'
      }

      // 添加用户路由
      if (values.userPath?.trim()) routes[values.userPath.trim()] = 'user'

      await AdminApi.updatePageSettings(apiAdmin, { routes })
      toast.success('页面设置已保存')
      await loadSettings()
    } catch (err) {
      toast.error(getErrorMessage(err, '保存页面设置失败'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold">URL 规则设置</h2>
      <Alert variant="warning">
        <AlertTitle>提示</AlertTitle>
        <AlertDescription>一旦你选择了某种链接风格请不要轻易修改它，这可能会影响搜索引擎收录和外部链接。</AlertDescription>
      </Alert>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
          <FormField
            control={form.control}
            name="postPathStyle"
            render={({ field }) => (
              <FormItem>
                <FormLabel>路径风格</FormLabel>
                <FormDescription className="mb-2">选择一种合适的文章静态路径风格，使得你的网站链接更加友好。</FormDescription>
                <FormControl>
                  <RadioGroup
                    value={field.value}
                    onValueChange={(v) => {
                      field.onChange(v)
                      handlePathStyleChange(v)
                    }}
                    className="flex flex-col gap-2"
                  >
                    {PATH_STYLES.map((style) => (
                      <div key={style.value} className="flex items-center space-x-2">
                        <RadioGroupItem value={style.value} id={`path-${style.value}`} />
                        <Label htmlFor={`path-${style.value}`}>{style.label}</Label>
                      </div>
                    ))}
                  </RadioGroup>
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="postPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>文章路径</FormLabel>
                <FormControl>
                  <Input placeholder="/archives/{id}" {...field} />
                </FormControl>
                <FormDescription>可用参数: {POST_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="pagePath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>页面路径</FormLabel>
                <FormControl>
                  <Input placeholder="/{slug}.html" {...field} />
                </FormControl>
                <FormDescription>可用参数: {PAGE_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="categoryPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>分类路径</FormLabel>
                <FormControl>
                  <Input placeholder="/category/{slug}" {...field} />
                </FormControl>
                <FormDescription>可用参数: {CATEGORY_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="categoryPaginationPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>分类分页路径</FormLabel>
                <FormControl>
                  <Input placeholder="/category/{slug}/{page}" {...field} />
                </FormControl>
                <FormDescription>可用参数: {PAGINATION_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="tagPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>标签路径</FormLabel>
                <FormControl>
                  <Input placeholder="/tag/{slug}" {...field} />
                </FormControl>
                <FormDescription>可用参数: {TAG_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="tagPaginationPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>标签分页路径</FormLabel>
                <FormControl>
                  <Input placeholder="/tag/{slug}/{page}" {...field} />
                </FormControl>
                <FormDescription>可用参数: {PAGINATION_PARAMS.join(', ')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="userPath"
            render={({ field }) => (
              <FormItem>
                <FormLabel>用户/作者页路径</FormLabel>
                <FormControl>
                  <Input placeholder="/user/{uid}/" {...field} />
                </FormControl>
                <FormDescription>可用参数: {USER_PARAMS.join(', ') + '，如 /user/{uid} 或 /author/{name}'}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="flex gap-2 justify-end">
            <Button type="submit" disabled={loading}>{loading ? '保存中...' : '保存更改'}</Button>
            <Button type="button" variant="outline" onClick={() => loadSettings()}>重置</Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
