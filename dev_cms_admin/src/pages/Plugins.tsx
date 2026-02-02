import { useState, useEffect, useRef } from 'react'
import { Upload, Trash2, MoreHorizontal } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin, usePlugins } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type Plugin } from '@/services/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
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
import { cn } from '@/lib/utils'

export default function Plugins() {
  const apiAdmin = useApiAdmin()
  const { uploadPlugin, activatePlugin, deactivatePlugin, deletePlugin } = usePlugins()

  const [loading, setLoading] = useState(false)
  const [plugins, setPlugins] = useState<Plugin[]>([])
  const [uploading, setUploading] = useState(false)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [searchInput, setSearchInput] = useState('')
  const fetchingRef = useRef(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    loadPlugins()
  }, [apiAdmin])

  useEffect(() => {
    loadPlugins()
  }, [searchKeyword, statusFilter])

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
      await uploadPlugin(file)
      await loadPlugins()
    } finally {
      setUploading(false)
      e.target.value = ''
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

  return (
    <div className="space-y-4">
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
    </div>
  )
}
