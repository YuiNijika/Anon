import { useState, useEffect, useRef, useMemo } from 'react'
import { Check, ArrowLeftRight, Upload, Trash2, Search, Pencil } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin, useThemes } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type ThemeOptionSchema, type ThemeInfo, type ThemeOptionsSchemaTree } from '@/services/admin'
import { getApiBaseUrl } from '@/utils/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Input } from '@/components/ui/input'
import { OptionField } from '@/components/OptionField'
import { Lightbox } from '@/components/ui/lightbox'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

const DISPLAY_ONLY_TYPES = ['badge', 'divider', 'alert', 'notice', 'alert_dialog', 'content', 'heading', 'accordion', 'result', 'card', 'description_list', 'table', 'tooltip', 'tag'] as const

const nullSvgUrl = `${getApiBaseUrl()}/anon/static/img/null`

// 占位图 data URL，onError 时使用，避免 404 时反复请求同一 URL
const PLACEHOLDER_IMG =
  'data:image/svg+xml,' +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1" viewBox="0 0 1 1"><rect width="1" height="1" fill="%23e5e7eb"/></svg>'
  )

export default function SettingsTheme() {
  const apiAdmin = useApiAdmin()
  const { uploadTheme, deleteTheme, loading: themeUploading } = useThemes()
  const hasReportedNullError = useRef(false)

  const [loading, setLoading] = useState(false)
  const [optionsLoading, setOptionsLoading] = useState(false)
  const [themeListLoading, setThemeListLoading] = useState(false)
  const [switchingTheme, setSwitchingTheme] = useState<string | null>(null)
  const [schema, setSchema] = useState<ThemeOptionsSchemaTree>({})
  const [formValues, setFormValues] = useState<Record<string, any>>({})
  const [currentTheme, setCurrentTheme] = useState<string>('')
  const [themes, setThemes] = useState<ThemeInfo[]>([])
  const [activeTab, setActiveTab] = useState('list')
  const [lightboxOpen, setLightboxOpen] = useState(false)
  const [lightboxSrc, setLightboxSrc] = useState('')
  const [searchOpen, setSearchOpen] = useState(false)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [activeGroupTab, setActiveGroupTab] = useState<string>('')
  const fieldRefs = useRef<Record<string, HTMLDivElement | null>>({})
  const [editDialog, setEditDialog] = useState<{
    open: boolean
    groupKey: string
    key: string
    option: ThemeOptionSchema | null
  }>({
    open: false,
    groupKey: '',
    key: '',
    option: null,
  })
  const [overwriteDialog, setOverwriteDialog] = useState<{
    open: boolean
    name: string
    existingVersion: string
    newVersion: string
    upgrade: boolean
    pendingFile: File | null
  }>({ open: false, name: '', existingVersion: '', newVersion: '', upgrade: false, pendingFile: null })
  const fetchingRef = useRef(false)
  const themeListFetchingRef = useRef(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const initialized = useRef(false)

  useEffect(() => {
    if (initialized.current) return
    initialized.current = true

    const init = async () => {
      if (themeListFetchingRef.current) return
      themeListFetchingRef.current = true
      try {
        setThemeListLoading(true)
        const res = await AdminApi.getThemeSettings(apiAdmin)
        if (res.data) {
          setCurrentTheme(res.data.current)
          setThemes(res.data.themes || [])
          await loadThemeOptions(res.data.current || 'default')
        }
      } catch (err) {
        toast.error('加载主题列表失败')
      } finally {
        setThemeListLoading(false)
        themeListFetchingRef.current = false
      }
    }
    init()
  }, [])

  const loadThemeList = async () => {
    if (themeListFetchingRef.current) return
    themeListFetchingRef.current = true
    try {
      setThemeListLoading(true)
      const res = await AdminApi.getThemeSettings(apiAdmin)
      if (res.data) {
        setCurrentTheme(res.data.current)
        setThemes(res.data.themes || [])
      }
    } catch (err) {
      toast.error('加载主题列表失败')
    } finally {
      setThemeListLoading(false)
      themeListFetchingRef.current = false
    }
  }

  // 拉取指定主题的设置项，schema 来自主题 setup 文件，values 来自数据库
  const loadThemeOptions = async (theme: string) => {
    if (fetchingRef.current) return
    fetchingRef.current = true
    setOptionsLoading(true)
    try {
      const res = await AdminApi.getThemeOptions(apiAdmin, { theme })
      if (res.data) {
        setSchema(res.data.schema || {})
        const values = res.data.values || {}
        // 确保所有 text_list 类型的字段都被初始化为数组
        const normalizedValues: Record<string, any> = { ...values }
        Object.values(res.data.schema || {}).forEach((tab: any) => {
          if (tab && typeof tab === 'object') {
            Object.entries(tab).forEach(([key, option]: [string, any]) => {
              if (option?.type === 'text_list') {
                const currentValue = normalizedValues[key]
                if (!Array.isArray(currentValue)) {
                  // 值不是数组时初始化为空数组或默认值
                  normalizedValues[key] = Array.isArray(option.default) ? option.default : []
                }
              }
            })
          }
        })
        setFormValues(normalizedValues)
        // 初始化 activeGroupTab 为第一个分组
        const firstGroup = Object.keys(res.data.schema || {})[0]
        if (firstGroup) {
          setActiveGroupTab(firstGroup)
        }
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载主题设置失败'))
    } finally {
      fetchingRef.current = false
      setOptionsLoading(false)
    }
  }

  const handleSwitchTheme = async (themeName: string) => {
    if (themeName === currentTheme) return
    setSwitchingTheme(themeName)
    try {
      await AdminApi.updateThemeSettings(apiAdmin, themeName)
      setCurrentTheme(themeName)
      toast.success('切换主题成功')
      await loadThemeOptions(themeName)
    } catch (err) {
      toast.error('切换主题失败')
    } finally {
      setSwitchingTheme(null)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!currentTheme) return

    const allKeys = Object.keys(schema).flatMap((tab) => Object.keys(schema[tab] || {}))
    const schemaMap = new Map<string, ThemeOptionSchema>()
    Object.values(schema).forEach((tab) => {
      Object.entries(tab || {}).forEach(([k, v]) => schemaMap.set(k, v))
    })
    const valuesToSave: Record<string, any> = {}
    allKeys.forEach((key) => {
      const option = schemaMap.get(key)
      if (!option || DISPLAY_ONLY_TYPES.includes(option.type as any)) return

      if (option.type === 'text_list') {
        // text_list 类型检查所有文本框是否有值，有值则保存无值则不保存
        const raw = formValues[key]
        let list: string[] = []
        if (Array.isArray(raw)) {
          list = raw
        } else if (raw !== null && raw !== undefined) {
          list = [String(raw)]
        }
        // 过滤空字符串项，仅保留有值的项
        const filtered = list
          .map((item) => String(item).trim())
          .filter((item) => item !== '')
        // 有值则保存，无值则跳过
        if (filtered.length > 0) {
          valuesToSave[key] = filtered
        }
        return
      }

      if (formValues[key] !== undefined) valuesToSave[key] = formValues[key]
    })
    try {
      setLoading(true)
      await AdminApi.updateThemeOptions(apiAdmin, { theme: currentTheme, values: valuesToSave })
      toast.success('主题设置已保存')
    } catch (err) {
      toast.error(getErrorMessage(err, '保存主题设置失败'))
    } finally {
      setLoading(false)
    }
  }

  const setFieldValue = (key: string, value: any) => {
    setFormValues((prev) => ({ ...prev, [key]: value }))
  }

  const renderThemeField = (key: string, option: ThemeOptionSchema, groupKey?: string) => (
    <div key={key} ref={(el) => { if (el) fieldRefs.current[`${groupKey || ''}_${key}`] = el }}>
      <OptionField
        name={key}
        option={option}
        value={formValues[key]}
        onChange={(v) => setFieldValue(key, v)}
      />
    </div>
  )

  // 搜索设置项
  const searchResults = useMemo(() => {
    if (!searchKeyword.trim()) return []
    const keyword = searchKeyword.toLowerCase().trim()
    const results: Array<{ groupKey: string; key: string; option: ThemeOptionSchema; matchText: string }> = []

    Object.entries(schema).forEach(([groupKey, groupItems]) => {
      if (!groupItems || typeof groupItems !== 'object') return
      Object.entries(groupItems).forEach(([key, option]) => {
        const label = (option.label || '').toLowerCase()
        const description = (option.description || '').toLowerCase()
        const keyLower = key.toLowerCase()

        if (label.includes(keyword) || description.includes(keyword) || keyLower.includes(keyword)) {
          let matchText = option.label || key
          if (option.description) {
            matchText += ` - ${option.description}`
          }
          results.push({ groupKey, key, option, matchText })
        }
      })
    })

    return results
  }, [searchKeyword, schema])

  // 跳转到指定设置项
  const jumpToField = (groupKey: string, key: string, focusInput = false) => {
    setSearchOpen(false)
    setSearchKeyword('')

    // 切换到对应的 tab
    if (groupKey) {
      setActiveGroupTab(groupKey)
    }

    // 等待 DOM 更新后滚动到对应字段
    setTimeout(() => {
      const fieldId = `${groupKey}_${key}`
      const fieldElement = fieldRefs.current[fieldId]
      if (fieldElement) {
        fieldElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
        // 高亮显示
        fieldElement.style.transition = 'background-color 0.3s'
        fieldElement.style.backgroundColor = 'hsl(var(--accent))'
        setTimeout(() => {
          if (fieldElement) {
            fieldElement.style.backgroundColor = ''
          }
        }, 2000)

        // 如果需要聚焦到输入框，查找输入框并聚焦
        if (focusInput) {
          const inputElement = fieldElement.querySelector('input, textarea, select') as HTMLElement
          if (inputElement) {
            setTimeout(() => {
              inputElement.focus()
              if (inputElement instanceof HTMLInputElement || inputElement instanceof HTMLTextAreaElement) {
                inputElement.select()
              }
            }, 300)
          }
        }
      }
    }, 200)
  }

  const sortedThemes = [...themes].sort((a, b) => {
    if (a.name === currentTheme) return -1
    if (b.name === currentTheme) return 1
    return 0
  })

  const getScreenshotUrl = (theme: ThemeInfo) => {
    const screenshot = theme.screenshot?.trim()
    if (!screenshot) return nullSvgUrl
    const baseUrl = getApiBaseUrl()
    if (screenshot.startsWith('http://') || screenshot.startsWith('https://')) return screenshot
    if (screenshot.startsWith('/')) return `${baseUrl}${screenshot}`
    return `${baseUrl}/${screenshot}`
  }

  const getUrlLabel = (url: string): string => {
    try {
      const urlObj = new URL(url)
      const hostname = urlObj.hostname.toLowerCase()
      const domain = hostname.replace(/^www\./, '')
      if (domain.includes('github.com')) return 'GitHub'
      if (domain.includes('gitee.com')) return 'Gitee'
      if (domain.includes('gitlab.com')) return 'GitLab'
      if (domain.includes('bitbucket.org')) return 'Bitbucket'
      if (domain.includes('coding.net')) return 'Coding'
      return '访问网站'
    } catch {
      return '访问网站'
    }
  }

  const handleUploadTheme = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
      const result = await uploadTheme(file)
      if (result?.needConfirm) {
        setOverwriteDialog({
          open: true,
          name: result.name ?? '',
          existingVersion: result.existingVersion ?? '',
          newVersion: result.newVersion ?? '',
          upgrade: result.upgrade ?? false,
          pendingFile: file,
        })
        return
      }
      await loadThemeList()
    } finally {
      e.target.value = ''
    }
  }

  const handleOverwriteConfirm = async () => {
    const file = overwriteDialog.pendingFile
    if (!file) {
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
      return
    }
    try {
      const result = await uploadTheme(file, true)
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
      if (result && !result.needConfirm) await loadThemeList()
    } finally {
      setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))
    }
  }

  const handleDeleteTheme = async (theme: ThemeInfo) => {
    if (theme.name === currentTheme) {
      toast.warning('不能删除当前使用的主题')
      return
    }
    if (!window.confirm(`确定要删除主题 "${theme.displayName}" 吗？此操作不可恢复。`)) return
    await deleteTheme(theme.name)
    await loadThemeList()
  }

  return (
    <div className="space-y-4">
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="list">主题列表</TabsTrigger>
          <TabsTrigger value="options">主题设置</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="space-y-4">
          <div className="flex justify-end">
            <input
              ref={fileInputRef}
              type="file"
              accept=".zip"
              className="hidden"
              onChange={handleUploadTheme}
            />
            <Button
              onClick={() => fileInputRef.current?.click()}
              disabled={themeUploading}
            >
              <Upload className="mr-2 h-4 w-4" />
              上传主题
            </Button>
          </div>

          {themeListLoading ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[1, 2, 3, 4].map((i) => (
                <Card key={i}>
                  <Skeleton className="h-[200px] w-full rounded-t-lg" />
                  <CardContent className="pt-4">
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="mt-2 h-3 w-full" />
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : themes.length === 0 ? (
            <Card>
              <CardContent className="py-12 text-center text-muted-foreground">
                暂无可用主题
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {sortedThemes.map((theme) => (
                <Card key={theme.name} className="overflow-hidden">
                  <button
                    type="button"
                    className="relative aspect-[4/3] w-full overflow-hidden bg-muted cursor-zoom-in outline-none"
                    onClick={() => {
                      setLightboxSrc(getScreenshotUrl(theme))
                      setLightboxOpen(true)
                    }}
                  >
                    <img
                      draggable={false}
                      alt={theme.displayName}
                      src={getScreenshotUrl(theme)}
                      className="h-full w-full object-cover"
                      onError={(e) => {
                        const target = e.target as HTMLImageElement
                        if (target.src?.startsWith('data:')) return
                        const failedUrl = target.src ?? ''
                        target.src = PLACEHOLDER_IMG
                        if (failedUrl.includes('/anon/static/img/null') && !hasReportedNullError.current) {
                          hasReportedNullError.current = true
                          toast.error('占位图加载失败，请检查 /anon/static/img/null 是否可访问')
                        }
                      }}
                    />
                  </button>
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                      {theme.displayName}
                      {theme.name === currentTheme && (
                        <span className="rounded bg-primary/10 px-2 py-0.5 text-xs font-normal text-primary">
                          当前使用
                        </span>
                      )}
                    </CardTitle>
                    {theme.description && (
                      <p className="text-sm text-muted-foreground">{theme.description}</p>
                    )}
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                      {theme.version && (
                        <span className="rounded bg-muted px-1.5 py-0.5">{theme.version}</span>
                      )}
                      {theme.author && <span>{theme.author}</span>}
                      {theme.url && (
                        <a
                          href={theme.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-primary underline underline-offset-2"
                        >
                          {getUrlLabel(theme.url)}
                        </a>
                      )}
                    </div>
                  </CardHeader>
                  <CardContent className="flex flex-col gap-2 pt-0">
                    <Button
                      variant={theme.name === currentTheme ? 'secondary' : 'default'}
                      size="sm"
                      disabled={theme.name === currentTheme}
                      onClick={() => handleSwitchTheme(theme.name)}
                    >
                      {switchingTheme === theme.name ? (
                        '切换中...'
                      ) : theme.name === currentTheme ? (
                        <>
                          <Check className="mr-2 h-4 w-4" />
                          当前使用
                        </>
                      ) : (
                        <>
                          <ArrowLeftRight className="mr-2 h-4 w-4" />
                          切换主题
                        </>
                      )}
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      disabled={theme.name === currentTheme}
                      onClick={() => handleDeleteTheme(theme)}
                    >
                      <Trash2 className="mr-2 h-4 w-4" />
                      删除
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </TabsContent>

        <TabsContent value="options">
          <Card>
            <CardContent className="pt-6">
              {optionsLoading ? (
                <div className="space-y-4">
                  <Skeleton className="h-10 w-full" />
                  <Skeleton className="h-10 w-full" />
                  <Skeleton className="h-10 w-full" />
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  {(() => {
                    const groupNames = Object.keys(schema)
                    const totalFields = groupNames.reduce(
                      (n, g) => n + (schema[g] && typeof schema[g] === 'object' ? Object.keys(schema[g]).length : 0),
                      0
                    )
                    if (totalFields === 0) {
                      return (
                        <div className="py-10 text-center text-muted-foreground">
                          当前主题没有可用的设置项，或主题目录下无 app/setup.php
                        </div>
                      )
                    }
                    if (groupNames.length <= 1) {
                      const items = groupNames.length ? schema[groupNames[0]] || {} : {}
                      const groupKey = groupNames[0] || ''
                      return (
                        <>
                          {Object.entries(items).map(([key, option]) => renderThemeField(key, option, groupKey))}
                          <div className="flex gap-2 justify-end">
                            <Button type="submit" disabled={loading}>
                              保存更改
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              onClick={() => setSearchOpen(true)}
                            >
                              <Search className="h-4 w-4" />
                              搜索
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              onClick={() => currentTheme && loadThemeOptions(currentTheme)}
                            >
                              重置
                            </Button>
                          </div>
                        </>
                      )
                    }
                    return (
                      <>
                        <Tabs value={activeGroupTab || groupNames[0]} onValueChange={setActiveGroupTab} className="w-full">
                          <TabsList className="mb-4">
                            {groupNames.map((g) => (
                              <TabsTrigger key={g} value={g}>
                                {g}
                              </TabsTrigger>
                            ))}
                          </TabsList>
                          {groupNames.map((groupKey) => (
                            <TabsContent key={groupKey} value={groupKey} className="space-y-4 mt-0">
                              {Object.entries(schema[groupKey] || {}).map(([key, option]) =>
                                renderThemeField(key, option, groupKey)
                              )}
                            </TabsContent>
                          ))}
                        </Tabs>
                        <div className="flex gap-2 justify-end">
                          <Button type="submit" disabled={loading}>
                            保存更改
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSearchOpen(true)}
                          >
                            <Search className="h-4 w-4" />
                            搜索
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => currentTheme && loadThemeOptions(currentTheme)}
                          >
                            重置
                          </Button>
                        </div>
                      </>
                    )
                  })()}
                </form>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Lightbox
        open={lightboxOpen}
        onOpenChange={setLightboxOpen}
        src={lightboxSrc}
      />
      <AlertDialog open={overwriteDialog.open} onOpenChange={(open) => !open && setOverwriteDialog((d) => ({ ...d, open: false, pendingFile: null }))}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{overwriteDialog.upgrade ? '更新主题' : '覆盖主题'}</AlertDialogTitle>
            <AlertDialogDescription>
              {overwriteDialog.upgrade ? (
                <>发现新版本：已安装 <strong>{overwriteDialog.existingVersion || '—'}</strong>，上传包版本 <strong>{overwriteDialog.newVersion || '—'}</strong>。是否覆盖并更新主题「{overwriteDialog.name}」？</>
              ) : (
                <>当前已安装版本 <strong>{overwriteDialog.existingVersion || '—'}</strong>，上传包版本 <strong>{overwriteDialog.newVersion || '—'}</strong> 较低或相同。仍要覆盖主题「{overwriteDialog.name}」吗？</>
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

      <Dialog
        open={searchOpen}
        onOpenChange={(open) => {
          setSearchOpen(open)
          if (!open) {
            setSearchKeyword('')
          } else {
            // 打开搜索弹窗时，如果没有 activeGroupTab，设置为第一个分组
            if (!activeGroupTab) {
              const firstGroup = Object.keys(schema)[0]
              if (firstGroup) {
                setActiveGroupTab(firstGroup)
              }
            }
          }
        }}
      >
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>搜索主题设置项</DialogTitle>
            <DialogDescription>
              输入关键词搜索设置项的名称、描述或键名
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <Input
              placeholder="输入搜索关键词..."
              value={searchKeyword}
              onChange={(e) => setSearchKeyword(e.target.value)}
              autoFocus
              className="w-full"
            />
            <div className="max-h-[400px] overflow-y-auto">
              {searchKeyword.trim() ? (
                searchResults.length > 0 ? (
                  <div className="space-y-1">
                    {searchResults.map((result) => (
                      <div
                        key={`${result.groupKey}_${result.key}`}
                        className="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-muted transition-colors group"
                      >
                        <button
                          type="button"
                          onClick={() => jumpToField(result.groupKey, result.key)}
                          className="flex-1 text-left"
                        >
                          <div className="font-medium">{result.option.label || result.key}</div>
                          {result.option.description && (
                            <div className="text-sm text-muted-foreground mt-1">{result.option.description}</div>
                          )}
                          <div className="text-xs text-muted-foreground mt-1">
                            分组：{result.groupKey} | 键名：{result.key}
                          </div>
                        </button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
                          onClick={(e) => {
                            e.stopPropagation()
                            setEditDialog({
                              open: true,
                              groupKey: result.groupKey,
                              key: result.key,
                              option: result.option,
                            })
                          }}
                          title="编辑此项"
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="py-8 text-center text-muted-foreground">
                    未找到匹配的设置项
                  </div>
                )
              ) : (
                <div className="py-8 text-center text-muted-foreground">
                  输入关键词开始搜索
                </div>
              )}
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={editDialog.open} onOpenChange={(open) => setEditDialog((prev) => ({ ...prev, open }))}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>编辑设置项</DialogTitle>
            <DialogDescription>
              {editDialog.option?.label || editDialog.key}
              {editDialog.option?.description && ` - ${editDialog.option.description}`}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {editDialog.option && (
              <OptionField
                name={editDialog.key}
                option={editDialog.option}
                value={formValues[editDialog.key]}
                onChange={(v) => setFieldValue(editDialog.key, v)}
              />
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
