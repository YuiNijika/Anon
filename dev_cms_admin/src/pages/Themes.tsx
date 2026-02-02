import { useState, useEffect, useRef } from 'react'
import { Check, ArrowLeftRight, Upload, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin, useThemes } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type ThemeOptionSchema, type ThemeInfo, type ThemeOptionsSchemaTree } from '@/services/admin'
import { getApiBaseUrl } from '@/utils/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const nullSvgUrl = `${getApiBaseUrl()}/anon/static/img/null`

/** 占位图 data URL，onError 时使用，避免 404 时反复请求同一 URL */
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

  /** 拉取指定主题的设置项，schema 来自主题 setup 文件，values 来自数据库 */
  const loadThemeOptions = async (theme: string) => {
    if (fetchingRef.current) return
    fetchingRef.current = true
    setOptionsLoading(true)
    try {
      const res = await AdminApi.getThemeOptions(apiAdmin, { theme })
      if (res.data) {
        setSchema(res.data.schema || {})
        setFormValues(res.data.values || {})
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
    try {
      setLoading(true)
      await AdminApi.updateThemeOptions(apiAdmin, { theme: currentTheme, values: formValues })
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

  const renderThemeField = (key: string, option: ThemeOptionSchema) => {
    const { type, label, description, options: selectOptions, default: defaultValue } = option
    const value = formValues[key]

    switch (type) {
      case 'text':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key} className="text-sm">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <Input
              id={key}
              value={value ?? ''}
              onChange={(e) => setFieldValue(key, e.target.value)}
              placeholder={defaultValue}
              className="w-full"
            />
          </div>
        )
      case 'textarea':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key} className="text-sm">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <textarea
              id={key}
              rows={4}
              value={value ?? ''}
              onChange={(e) => setFieldValue(key, e.target.value)}
              placeholder={defaultValue}
              className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            />
          </div>
        )
      case 'select':
        return (
          <div key={key} className="space-y-2">
            <Label className="text-sm">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <Select
              value={value ?? ''}
              onValueChange={(v) => setFieldValue(key, v)}
            >
              <SelectTrigger>
                <SelectValue placeholder="请选择" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(selectOptions || {}).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )
      case 'checkbox':
        return (
          <div key={key} className="flex items-center justify-between space-x-2">
            <Label htmlFor={key} className="text-sm flex-1">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <Switch
              id={key}
              checked={!!value}
              onCheckedChange={(checked) => setFieldValue(key, checked)}
            />
          </div>
        )
      case 'number':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key} className="text-sm">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <Input
              id={key}
              type="number"
              value={value ?? ''}
              onChange={(e) => {
                const v = e.target.value
                setFieldValue(key, v === '' ? undefined : Number(v))
              }}
              placeholder={defaultValue}
              className="w-full"
            />
          </div>
        )
      case 'color':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key} className="text-sm">
              {label}
              {description && (
                <span className="ml-1 font-normal text-muted-foreground">({description})</span>
              )}
            </Label>
            <div className="flex items-center gap-2">
              <input
                type="color"
                id={key}
                value={typeof value === 'string' && value.startsWith('#') ? value : '#000000'}
                onChange={(e) => setFieldValue(key, e.target.value)}
                className="h-10 w-14 cursor-pointer rounded border border-input bg-background p-1"
              />
              <Input
                value={value ?? ''}
                onChange={(e) => setFieldValue(key, e.target.value)}
                placeholder="#000000"
                className="flex-1 font-mono text-sm"
              />
            </div>
          </div>
        )
      default:
        return null
    }
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
      await uploadTheme(file)
      await loadThemeList()
    } finally {
      e.target.value = ''
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
                  <div className="relative aspect-[4/3] w-full overflow-hidden bg-muted">
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
                  </div>
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
                      return (
                        <>
                          {Object.entries(items).map(([key, option]) => renderThemeField(key, option))}
                          <div className="flex gap-2">
                            <Button type="submit" disabled={loading}>
                              保存更改
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
                        <Tabs defaultValue={groupNames[0]} className="w-full">
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
                                renderThemeField(key, option)
                              )}
                            </TabsContent>
                          ))}
                        </Tabs>
                        <div className="flex gap-2">
                          <Button type="submit" disabled={loading}>
                            保存更改
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
    </div>
  )
}
