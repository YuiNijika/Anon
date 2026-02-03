import { useState, useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Upload, Trash2, MoreHorizontal, Settings, ArrowLeft } from 'lucide-react'
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
    if (optionsFetchingRef.current || !slug) return
    optionsFetchingRef.current = true
    setOptionsLoading(true)
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

  const handlePluginOptionsSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!optionsSlug) return
    const valuesToSave: Record<string, any> = {}
    Object.keys(pluginOptionsSchema).forEach((key) => {
      const opt = pluginOptionsSchema[key]
      if (opt && !DISPLAY_ONLY_OPTION_TYPES.includes(opt.type as any) && pluginOptionsValues[key] !== undefined) {
        valuesToSave[key] = pluginOptionsValues[key]
      }
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
  const schemaEntries = Object.entries(pluginOptionsSchema).filter(
    ([, def]) => def && typeof def === 'object' && def.type
  )

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
            ) : schemaEntries.length === 0 ? (
              <p className="text-sm text-muted-foreground">该插件暂无设置项。</p>
            ) : (
              <form onSubmit={handlePluginOptionsSubmit} className="space-y-6 max-w-xl">
                {schemaEntries.map(([key, def]) => renderPluginOptionField(key, def))}
                <div className="flex gap-2 justify-end">
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
