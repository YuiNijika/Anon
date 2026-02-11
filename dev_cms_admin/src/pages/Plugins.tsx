import { useState, useEffect, useRef, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Upload, Trash2, MoreHorizontal, Settings, ArrowLeft, FileText } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin, usePlugins } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Plugin, type PluginOptionSchema } from '@/services/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { OptionField } from '@/components/OptionField'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

const DISPLAY_ONLY_OPTION_TYPES = ['badge', 'divider', 'alert', 'notice', 'alert_dialog', 'content', 'heading', 'accordion', 'result', 'card', 'description_list', 'table', 'tooltip', 'tag'] as const
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { cn } from '@/lib/utils'

export default function Plugins() {
  const apiAdmin = useApiAdmin()
  const [searchParams, setSearchParams] = useSearchParams()
  const { uploadPlugin, activatePlugin, deactivatePlugin, deletePlugin } = usePlugins()

  const optionsSlug = searchParams.get('options') ?? ''

  const [loading, setLoading] = useState(false)
  const [plugins, setPlugins] = useState<Plugin[]>([])
  const [uploading, setUploading] = useState(false)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [searchInput, setSearchInput] = useState('')
  const [optionsLoading, setOptionsLoading] = useState(false)
  const [pluginOptionsSchema, setPluginOptionsSchema] = useState<Record<string, PluginOptionSchema>>({})
  const [pluginOptionsValues, setPluginOptionsValues] = useState<Record<string, any>>({})
  const [pluginOptionsSaving, setPluginOptionsSaving] = useState(false)
  const [activeGroupTab, setActiveGroupTab] = useState<string>('')
  const [overwriteDialog, setOverwriteDialog] = useState<{
    open: boolean
    name: string
    existingVersion: string
    newVersion: string
    upgrade: boolean
    pendingFile: File | null
  }>({ open: false, name: '', existingVersion: '', newVersion: '', upgrade: false, pendingFile: null })
  const fetchingRef = useRef(false)
  const optionsFetchingRef = useRef(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    loadPlugins()
  }, [apiAdmin])

  useEffect(() => {
    loadPlugins()
  }, [searchKeyword, statusFilter])

  useEffect(() => {
    if (optionsSlug) {
      loadPluginOptions(optionsSlug)
    } else {
      setPluginOptionsSchema({})
      setPluginOptionsValues({})
    }
  }, [optionsSlug])

  const loadPlugins = async () => {
    if (fetchingRef.current) return
    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getPlugins(apiAdmin)
      if (res.data) {
        let filteredPlugins = res.data.list || []

        if (searchKeyword) {
          filteredPlugins = filteredPlugins.filter(
            (plugin) =>
              plugin.name.toLowerCase().includes(searchKeyword.toLowerCase()) ||
              (plugin.description &&
                plugin.description.toLowerCase().includes(searchKeyword.toLowerCase()))
          )
        }

        if (statusFilter === 'active') {
          filteredPlugins = filteredPlugins.filter((plugin) => plugin.active)
        } else if (statusFilter === 'inactive') {
          filteredPlugins = filteredPlugins.filter((plugin) => !plugin.active)
        }

        const sortedPlugins = [...filteredPlugins].sort((a, b) => {
          if (a.active && !b.active) return -1
          if (!a.active && b.active) return 1
          return 0
        })

        setPlugins(sortedPlugins)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载插件列表失败'))
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setUploading(true)
    try {
      const result = await uploadPlugin(file)
      if (result?.needConfirm) {
        setOverwriteDialog({
          open: true,
          name: result.name ?? result.slug ?? '',
          existingVersion: result.existingVersion ?? '',
          newVersion: result.newVersion ?? '',
          upgrade: result.upgrade ?? false,
          pendingFile: file,
        })
        e.target.value = ''
        return
      }
      await loadPlugins()
    } finally {
      setUploading(false)
      e.target.value = ''
    }
  }

  const handleOverwriteConfirm = async () => {
    const file = overwriteDialog.pendingFile
    if (!file) {
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
      return
    }
    setUploading(true)
    try {
      const result = await uploadPlugin(file, true)
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
      if (result && !result.needConfirm) await loadPlugins()
    } finally {
      setUploading(false)
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
    }
  }

  const handleToggle = async (plugin: Plugin) => {
    try {
      if (plugin.active) {
        await deactivatePlugin(plugin.slug)
      } else {
        await activatePlugin(plugin.slug)
      }
      await loadPlugins()
    } catch (err) {
      // Error handled in hook
    }
  }

  const handleDelete = async (plugin: Plugin) => {
    if (plugin.active) {
      toast.warning('请先停用插件再删除')
      return
    }
    if (!window.confirm(`确定要删除插件 "${plugin.name}" 吗？此操作不可恢复。`)) return
    await deletePlugin(plugin.slug)
    await loadPlugins()
  }

  const openPluginOptions = (slug: string) => {
    setSearchParams({ options: slug })
  }

  const closePluginOptions = () => {
    setSearchParams({})
  }

  const loadPluginOptions = async (slug: string) => {
    // 即使 optionsFetchingRef.current 为 true，如果 slug 变了也应该允许重新加载
    // 但为了简单起见，这里先重置 loading 状态
    setOptionsLoading(true)
    optionsFetchingRef.current = true
    try {
      const res = await AdminApi.getPluginOptions(apiAdmin, { slug })
      if (res.data) {
        setPluginOptionsSchema(res.data.schema || {})
        setPluginOptionsValues(res.data.values || {})
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载插件设置失败'))
    } finally {
      optionsFetchingRef.current = false
      setOptionsLoading(false)
    }
  }

  const openPluginPage = (pluginSlug: string, pageSlug: string) => {
    // setSearchParams({ pages: `${pluginSlug}:${pageSlug}` })
    window.location.href = `#/pages?plugin=${pluginSlug}&page=${pageSlug}`
  }

  // 归一化 Schema：统一转换为分组结构
  const normalizedSchema = useMemo(() => {
    if (!pluginOptionsSchema || Object.keys(pluginOptionsSchema).length === 0) return {}

    // 检查是否已经是分组结构
    // 检查第一个 entry 的 value 是否包含 'type' 属性
    // 如果包含 type，说明是 OptionSchema，即扁平结构
    const firstValue = Object.values(pluginOptionsSchema)[0] as any
    const isFlat = firstValue && typeof firstValue === 'object' && 'type' in firstValue

    if (isFlat) {
      return { '常规设置': pluginOptionsSchema } // 默认分组名
    }
    // 否则认为是分组结构
    return pluginOptionsSchema as unknown as Record<string, Record<string, PluginOptionSchema>>
  }, [pluginOptionsSchema])

  useEffect(() => {
    const groups = Object.keys(normalizedSchema)
    if (groups.length > 0 && (!activeGroupTab || !groups.includes(activeGroupTab))) {
      setActiveGroupTab(groups[0])
    }
  }, [normalizedSchema])

  const handlePluginOptionsSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!optionsSlug) return
    const valuesToSave: Record<string, any> = {}

    // 遍历所有分组收集数据
    Object.values(normalizedSchema).forEach((groupItems) => {
      if (!groupItems || typeof groupItems !== 'object') return
      Object.keys(groupItems).forEach((key) => {
        const opt = (groupItems as any)[key] as PluginOptionSchema
        if (opt && !DISPLAY_ONLY_OPTION_TYPES.includes(opt.type as any) && pluginOptionsValues[key] !== undefined) {
          valuesToSave[key] = pluginOptionsValues[key]
        }
      })
    })

    setPluginOptionsSaving(true)
    try {
      await AdminApi.updatePluginOptions(apiAdmin, { slug: optionsSlug, values: valuesToSave })
      toast.success('插件设置已保存')
    } catch (err) {
      toast.error(getErrorMessage(err, '保存插件设置失败'))
    } finally {
      setPluginOptionsSaving(false)
    }
  }

  const setPluginOptionValue = (key: string, value: any) => {
    setPluginOptionsValues((prev) => ({ ...prev, [key]: value }))
  }

  const renderPluginOptionField = (key: string, option: PluginOptionSchema) => (
    <OptionField
      key={key}
      name={key}
      option={option}
      value={pluginOptionsValues[key]}
      onChange={(v) => setPluginOptionValue(key, v)}
    />
  )

  const getModeClass = (mode: string) => {
    switch (mode) {
      case 'cms':
        return 'bg-primary/10 text-primary'
      case 'api':
        return 'bg-green-500/10 text-green-700 dark:text-green-400'
      case 'auto':
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
      default:
        return 'bg-muted text-muted-foreground'
    }
  }

  const pluginForOptions = plugins.find((p) => p.slug === optionsSlug)
  // schemaEntries 不再直接用于渲染，改为使用 normalizedSchema
  const hasOptions = Object.keys(normalizedSchema).length > 0

  return (
    <div className="space-y-4">
      {optionsSlug ? (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="icon" onClick={closePluginOptions} title="返回列表">
                <ArrowLeft className="h-4 w-4" />
              </Button>
              <CardTitle>
                插件设置
                {pluginForOptions ? ` - ${pluginForOptions.name}` : ` (${optionsSlug})`}
              </CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            {optionsLoading ? (
              <div className="space-y-4">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-16 w-full" />
              </div>
            ) : !hasOptions ? (
              <p className="text-sm text-muted-foreground">该插件暂无设置项。</p>
            ) : (
              <form onSubmit={handlePluginOptionsSubmit} className="space-y-6">
                {(() => {
                  const groupNames = Object.keys(normalizedSchema)
                  // 如果只有一个分组，直接渲染，不显示 Tabs
                  if (groupNames.length === 1) {
                    const groupKey = groupNames[0]
                    const groupItems = normalizedSchema[groupKey]
                    return Object.entries(groupItems || {}).map(([key, def]) =>
                      renderPluginOptionField(key, def as PluginOptionSchema)
                    )
                  }

                  // 多个分组，使用 Tabs
                  return (
                    <Tabs value={activeGroupTab} onValueChange={setActiveGroupTab} className="w-full">
                      <TabsList className="mb-4">
                        {groupNames.map((g) => (
                          <TabsTrigger key={g} value={g}>{g}</TabsTrigger>
                        ))}
                      </TabsList>
                      {groupNames.map((groupKey) => (
                        <TabsContent key={groupKey} value={groupKey} className="space-y-4 mt-0">
                          {Object.entries(normalizedSchema[groupKey] || {}).map(([key, def]) =>
                            renderPluginOptionField(key, def as PluginOptionSchema)
                          )}
                        </TabsContent>
                      ))}
                    </Tabs>
                  )
                })()}

                <div className="mt-4 flex gap-2 justify-end">
                  <Button type="submit" disabled={pluginOptionsSaving}>
                    {pluginOptionsSaving ? '保存中...' : '保存'}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => optionsSlug && loadPluginOptions(optionsSlug)}
                  >
                    重置
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>
      ) : null}

      {!optionsSlug && (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>插件管理</CardTitle>
            <div className="flex items-center gap-2">
              <input
                ref={fileInputRef}
                type="file"
                accept=".zip"
                className="hidden"
                onChange={handleUpload}
              />
              <Button
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading}
              >
                <Upload className="mr-2 h-4 w-4" />
                上传插件
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <Input
                placeholder="搜索插件名称或描述"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && setSearchKeyword(searchInput)}
                className="w-[300px] max-w-full"
              />
              <Button variant="secondary" onClick={() => setSearchKeyword(searchInput)}>
                搜索
              </Button>
              <div className="flex gap-1">
                {(['all', 'active', 'inactive'] as const).map((s) => (
                  <Button
                    key={s}
                    variant={statusFilter === s ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setStatusFilter(s)}
                  >
                    {s === 'all' ? '全部' : s === 'active' ? '已启用' : '未启用'}
                  </Button>
                ))}
              </div>
            </div>

            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>插件名称</TableHead>
                    <TableHead>描述</TableHead>
                    <TableHead className="w-[100px]">模式</TableHead>
                    <TableHead className="w-[100px]">版本</TableHead>
                    <TableHead className="w-[150px]">作者</TableHead>
                    <TableHead className="w-[100px]">状态</TableHead>
                    <TableHead className="w-[80px]">操作</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow>
                      <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                        加载中...
                      </TableCell>
                    </TableRow>
                  ) : !plugins.length ? (
                    <TableRow>
                      <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                        {searchKeyword ? '未找到匹配的插件' : '暂无插件'}
                      </TableCell>
                    </TableRow>
                  ) : (
                    plugins.map((plugin) => (
                      <TableRow key={plugin.slug}>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <span className="truncate" title={plugin.name}>
                              {plugin.name || '-'}
                            </span>
                            {plugin.active && (
                              <span className="shrink-0 rounded bg-green-500/10 px-2 py-0.5 text-xs text-green-700 dark:text-green-400">
                                已启用
                              </span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <span
                            className="block max-w-[300px] truncate text-sm text-muted-foreground"
                            title={plugin.description || '-'}
                          >
                            {plugin.description || '-'}
                          </span>
                        </TableCell>
                        <TableCell>
                          <span
                            className={cn(
                              'rounded px-2 py-0.5 text-xs',
                              getModeClass(plugin.mode)
                            )}
                          >
                            {plugin.mode.toUpperCase()}
                          </span>
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {plugin.version || '-'}
                        </TableCell>
                        <TableCell className="max-w-[150px] truncate text-muted-foreground">
                          {plugin.author || '-'}
                        </TableCell>
                        <TableCell>
                          <Switch
                            checked={plugin.active}
                            onCheckedChange={() => handleToggle(plugin)}
                          />
                          <span className="ml-2 text-sm text-muted-foreground">
                            {plugin.active ? '启用' : '停用'}
                          </span>
                        </TableCell>
                        <TableCell>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              {plugin.pages?.map((page) => (
                                <DropdownMenuItem key={page.slug} onClick={() => openPluginPage(plugin.slug, page.slug)}>
                                  <FileText className="mr-2 h-4 w-4" />
                                  {page.title}
                                </DropdownMenuItem>
                              ))}
                              <DropdownMenuItem onClick={() => openPluginOptions(plugin.slug)}>
                                <Settings className="mr-2 h-4 w-4" />
                                设置
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                disabled={plugin.active}
                                onClick={() => handleDelete(plugin)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                删除
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
            {!loading && plugins.length > 0 && (
              <p className="text-sm text-muted-foreground">共 {plugins.length} 条</p>
            )}
          </CardContent>
        </Card>
      )}
      <AlertDialog open={overwriteDialog.open} onOpenChange={(open) => !open && setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{overwriteDialog.upgrade ? '更新插件' : '覆盖插件'}</AlertDialogTitle>
            <AlertDialogDescription>
              {overwriteDialog.upgrade ? (
                <>发现新版本：已安装 <strong>{overwriteDialog.existingVersion || '—'}</strong>，上传包版本 <strong>{overwriteDialog.newVersion || '—'}</strong>。是否覆盖并更新插件「{overwriteDialog.name}」？</>
              ) : (
                <>当前已安装版本 <strong>{overwriteDialog.existingVersion || '—'}</strong>，上传包版本 <strong>{overwriteDialog.newVersion || '—'}</strong> 较低或相同。仍要覆盖插件「{overwriteDialog.name}」吗？</>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>取消</AlertDialogCancel>
            <Button variant={overwriteDialog.upgrade ? 'default' : 'destructive'} onClick={handleOverwriteConfirm}>
              确认覆盖
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
