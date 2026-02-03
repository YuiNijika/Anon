import { useEffect, useState, useRef } from 'react'
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from 'recharts'
import type { TooltipContentProps } from 'recharts'
import {
  FileText,
  MessageCircle,
  FileIcon,
  FolderOpen,
  Tag,
  User,
  Eye,
  CheckCircle,
  Clock,
  Globe,
  Search,
  RotateCcw,
} from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type StatisticsData, type AccessLog, type AccessStats } from '@/services/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

function formatDate(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const seconds = String(d.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

function StatBlock({
  title,
  value,
  icon: Icon,
  valueClassName,
}: {
  title: string
  value: number | string
  icon: React.ComponentType<{ className?: string }>
  valueClassName?: string
}) {
  return (
    <div className="rounded-lg border bg-card p-4">
      <p className="text-sm font-medium text-muted-foreground">{title}</p>
      <div className="mt-1 flex items-center gap-2">
        {Icon && <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />}
        <span className={cn('text-xl font-semibold', valueClassName)}>{value}</span>
      </div>
    </div>
  )
}

function ChartTooltip({ active, payload, label }: TooltipContentProps<number, string>) {
  if (!active || !payload?.length || label == null) return null
  const date = new Date(label)
  const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  return (
    <div className="rounded-md border bg-popover px-3 py-2 text-sm shadow-md">
      <p className="font-medium text-popover-foreground">{dateStr}</p>
      <p className="text-muted-foreground">
        访问量: <span className="font-medium text-foreground">{payload[0].value}</span>
      </p>
    </div>
  )
}

function parseUserAgent(ua: string | null): string {
  if (!ua) return '-'
  if (ua.includes('Chrome')) return 'Chrome'
  if (ua.includes('Firefox')) return 'Firefox'
  if (ua.includes('Safari') && !ua.includes('Chrome')) return 'Safari'
  if (ua.includes('Edge')) return 'Edge'
  if (ua.includes('Opera')) return 'Opera'
  return ua.substring(0, 50) + (ua.length > 50 ? '...' : '')
}

function getStatusBadgeClass(status: number): string {
  if (status >= 200 && status < 300) return 'bg-green-500/10 text-green-700 dark:text-green-400'
  if (status >= 300 && status < 400) return 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
  if (status >= 400) return 'bg-destructive/10 text-destructive'
  return 'bg-muted text-muted-foreground'
}

export default function Statistics() {
  const apiAdmin = useApiAdmin()
  const [activeTab, setActiveTab] = useState('logs')

  const [loading, setLoading] = useState(true)
  const [data, setData] = useState<StatisticsData | null>(null)

  const [viewsTrend, setViewsTrend] = useState<Array<{ date: string; count: number }>>([])
  const [trendLoading, setTrendLoading] = useState(false)
  const [days, setDays] = useState<7 | 14 | 30>(7)

  const [logsLoading, setLogsLoading] = useState(false)
  const [logs, setLogs] = useState<AccessLog[]>([])
  const [logsTotal, setLogsTotal] = useState(0)
  const [logsPage, setLogsPage] = useState(1)
  const [logsPageSize] = useState(20)
  const [accessStats, setAccessStats] = useState<AccessStats | null>(null)
  const [accessStatsLoading, setAccessStatsLoading] = useState(false)

  const [filters, setFilters] = useState<{
    ip?: string
    path?: string
    type?: string
    user_agent?: string
    status_code?: number
    start_date?: string
    end_date?: string
  }>({})

  const [startDate, setStartDate] = useState<string>('')
  const [endDate, setEndDate] = useState<string>('')

  const fetchingRef = useRef(false)
  const trendFetchingRef = useRef(false)
  const logsFetchingRef = useRef(false)
  const statsFetchingRef = useRef(false)

  useEffect(() => {
    if (fetchingRef.current) return
    const fetchData = async () => {
      fetchingRef.current = true
      try {
        setLoading(true)
        const res = await AdminApi.getStatistics(apiAdmin)
        if (res.data) {
          setData(res.data)
          setViewsTrend(res.data.views_trend || [])
        }
      } catch (err) {
        toast.error(getErrorMessage(err, '获取统计数据失败'))
        console.error(err)
      } finally {
        setLoading(false)
        fetchingRef.current = false
      }
    }
    fetchData()
  }, [apiAdmin])

  useEffect(() => {
    if (trendFetchingRef.current) return
    const fetchTrend = async () => {
      trendFetchingRef.current = true
      try {
        setTrendLoading(true)
        const res = await AdminApi.getViewsTrend(apiAdmin, days)
        if (res.data) setViewsTrend(res.data)
      } catch (err) {
        toast.error('获取访问趋势数据失败')
        console.error(err)
      } finally {
        setTrendLoading(false)
        trendFetchingRef.current = false
      }
    }
    fetchTrend()
  }, [apiAdmin, days])

  const loadLogs = async (pageOverride?: number) => {
    if (logsFetchingRef.current) return
    logsFetchingRef.current = true
    const page = pageOverride ?? logsPage
    try {
      setLogsLoading(true)
      const params: Record<string, unknown> = { page, page_size: logsPageSize }
      if (filters.ip) params.ip = filters.ip
      if (filters.path) params.path = filters.path
      if (filters.type) params.type = filters.type
      if (filters.user_agent) params.user_agent = filters.user_agent
      if (filters.status_code) params.status_code = filters.status_code
      if (filters.start_date) params.start_date = filters.start_date
      if (filters.end_date) params.end_date = filters.end_date

      const res = await AdminApi.getAccessLogs(apiAdmin, params)
      if (res.data) {
        setLogs(res.data.list || [])
        setLogsTotal(res.data.total || 0)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '获取访问日志失败'))
      console.error(err)
    } finally {
      setLogsLoading(false)
      logsFetchingRef.current = false
    }
  }

  const loadAccessStats = async () => {
    if (statsFetchingRef.current) return
    statsFetchingRef.current = true
    try {
      setAccessStatsLoading(true)
      const params: Record<string, unknown> = {}
      if (filters.start_date) params.start_date = filters.start_date
      if (filters.end_date) params.end_date = filters.end_date
      if (filters.ip) params.ip = filters.ip
      if (filters.path) params.path = filters.path
      if (filters.type) params.type = filters.type

      const res = await AdminApi.getAccessStats(apiAdmin, params)
      if (res.data) setAccessStats(res.data)
    } catch (err) {
      toast.error('获取访问统计失败')
      console.error(err)
    } finally {
      setAccessStatsLoading(false)
      statsFetchingRef.current = false
    }
  }

  useEffect(() => {
    if (activeTab === 'logs') {
      loadLogs()
      loadAccessStats()
    }
  }, [activeTab, logsPage, logsPageSize])

  useEffect(() => {
    if (activeTab === 'logs') {
      setLogsPage(1)
      loadLogs(1)
      loadAccessStats()
    }
  }, [filters])

  const handleSearch = () => {
    setLogsPage(1)
    loadLogs()
    loadAccessStats()
  }

  const handleReset = () => {
    setFilters({})
    setStartDate('')
    setEndDate('')
    setLogsPage(1)
  }

  const handleStartDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value
    setStartDate(value)
    if (value) {
      setFilters({ ...filters, start_date: `${value} 00:00:00` })
    } else {
      const next = { ...filters }
      delete next.start_date
      setFilters(next)
    }
  }

  const handleEndDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value
    setEndDate(value)
    if (value) {
      setFilters({ ...filters, end_date: `${value} 23:59:59` })
    } else {
      const next = { ...filters }
      delete next.end_date
      setFilters(next)
    }
  }

  const typeMap: Record<string, { text: string; className: string }> = {
    page: { text: '页面', className: 'bg-primary/10 text-primary' },
    api: { text: 'API', className: 'bg-green-500/10 text-green-700 dark:text-green-400' },
    static: { text: '静态', className: 'bg-muted text-muted-foreground' },
  }

  const methodClass = (method: string) =>
    method === 'GET'
      ? 'bg-primary/10 text-primary'
      : method === 'POST'
        ? 'bg-green-500/10 text-green-700 dark:text-green-400'
        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'

  const logsTotalPages = Math.max(1, Math.ceil(logsTotal / logsPageSize))

  if (loading) {
    return (
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardContent className="pt-4">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="mt-2 h-8 w-16" />
              </CardContent>
            </Card>
          ))}
        </div>
        <Card>
          <CardContent className="pt-4">
            <Skeleton className="h-[420px] w-full" />
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="logs">日志</TabsTrigger>
          <TabsTrigger value="statistics">统计</TabsTrigger>
        </TabsList>

        <TabsContent value="logs" className="space-y-4">
          {/* 访问统计 */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="pt-4">
                {accessStatsLoading ? (
                  <Skeleton className="h-4 w-20" />
                ) : (
                  <StatBlock title="总访问量" value={accessStats?.total ?? 0} icon={Eye} />
                )}
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4">
                {accessStatsLoading ? (
                  <Skeleton className="h-4 w-20" />
                ) : (
                  <StatBlock title="独立IP" value={accessStats?.unique_ips ?? 0} icon={Globe} />
                )}
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4">
                {accessStatsLoading ? (
                  <Skeleton className="h-4 w-20" />
                ) : (
                  <StatBlock
                    title="热门页面数"
                    value={accessStats?.top_pages?.length ?? 0}
                    icon={FileText}
                  />
                )}
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4">
                {accessStatsLoading ? (
                  <Skeleton className="h-4 w-20" />
                ) : (
                  <StatBlock title="当前筛选" value={logsTotal} icon={Search} />
                )}
              </CardContent>
            </Card>
          </div>

          {accessStats?.top_pages && accessStats.top_pages.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>热门页面</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex flex-col gap-2">
                  {accessStats.top_pages.map((page, index) => (
                    <div
                      key={index}
                      className="flex items-center justify-between gap-4"
                    >
                      <span className="min-w-0 flex-1 truncate font-mono text-xs" title={page.path}>
                        {page.path}
                      </span>
                      <span className="shrink-0 rounded bg-primary/10 px-2 py-0.5 text-xs text-primary">
                        {page.count} 次
                      </span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* 筛选 */}
          <Card>
            <CardHeader>
              <CardTitle>筛选条件</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Input
                  placeholder="IP地址"
                  value={filters.ip ?? ''}
                  onChange={(e) => setFilters({ ...filters, ip: e.target.value || undefined })}
                  className="w-full"
                />
                <Input
                  placeholder="访问路径"
                  value={filters.path ?? ''}
                  onChange={(e) => setFilters({ ...filters, path: e.target.value || undefined })}
                  className="w-full"
                />
                <Select
                  value={filters.type ?? ''}
                  onValueChange={(v) => setFilters({ ...filters, type: v || undefined })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="请求类型" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="page">页面</SelectItem>
                    <SelectItem value="api">API</SelectItem>
                    <SelectItem value="static">静态资源</SelectItem>
                  </SelectContent>
                </Select>
                <Select
                  value={filters.status_code?.toString() ?? ''}
                  onValueChange={(v) =>
                    setFilters({ ...filters, status_code: v ? Number(v) : undefined })
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder="状态码" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="200">200 OK</SelectItem>
                    <SelectItem value="301">301 重定向</SelectItem>
                    <SelectItem value="302">302 重定向</SelectItem>
                    <SelectItem value="404">404 未找到</SelectItem>
                    <SelectItem value="500">500 服务器错误</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Input
                  placeholder="User-Agent (关键词)"
                  value={filters.user_agent ?? ''}
                  onChange={(e) =>
                    setFilters({ ...filters, user_agent: e.target.value || undefined })
                  }
                  className="w-full"
                />
                <div className="flex flex-col gap-1">
                  <label className="text-xs text-muted-foreground">开始日期</label>
                  <Input
                    type="date"
                    value={startDate}
                    onChange={handleStartDateChange}
                    className="w-full"
                    aria-label="开始日期"
                  />
                </div>
                <div className="flex flex-col gap-1">
                  <label className="text-xs text-muted-foreground">结束日期</label>
                  <Input
                    type="date"
                    value={endDate}
                    onChange={handleEndDateChange}
                    className="w-full"
                    aria-label="结束日期"
                  />
                </div>
                <div className="flex gap-2">
                  <Button onClick={handleSearch}>
                    <Search className="mr-2 h-4 w-4" />
                    搜索
                  </Button>
                  <Button variant="outline" onClick={handleReset}>
                    <RotateCcw className="mr-2 h-4 w-4" />
                    重置
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* 访问日志列表 */}
          <Card>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-[80px]">ID</TableHead>
                      <TableHead className="w-[180px]">访问时间</TableHead>
                      <TableHead className="w-[140px]">IP地址</TableHead>
                      <TableHead>访问路径</TableHead>
                      <TableHead className="w-[80px]">类型</TableHead>
                      <TableHead className="w-[80px]">方法</TableHead>
                      <TableHead className="w-[100px]">状态码</TableHead>
                      <TableHead className="w-[100px]">响应时间</TableHead>
                      <TableHead>User-Agent</TableHead>
                      <TableHead>来源</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {logsLoading ? (
                      <TableRow>
                        <TableCell colSpan={10} className="h-24 text-center text-muted-foreground">
                          加载中...
                        </TableCell>
                      </TableRow>
                    ) : !logs.length ? (
                      <TableRow>
                        <TableCell colSpan={10} className="h-24 text-center text-muted-foreground">
                          暂无访问日志
                        </TableCell>
                      </TableRow>
                    ) : (
                      logs.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell className="font-mono">{row.id}</TableCell>
                          <TableCell className="text-muted-foreground">
                            {formatDate(row.created_at)}
                          </TableCell>
                          <TableCell>
                            <span className="font-mono text-sm" title={row.ip}>
                              {row.ip}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span
                              className="max-w-[200px] truncate font-mono text-xs"
                              title={row.path}
                            >
                              {row.path}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span
                              className={cn(
                                'rounded px-2 py-0.5 text-xs',
                                (typeMap[row.type] ?? { className: 'bg-muted' }).className
                              )}
                            >
                              {(typeMap[row.type] ?? { text: row.type }).text}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span
                              className={cn(
                                'rounded px-2 py-0.5 text-xs',
                                methodClass(row.method)
                              )}
                            >
                              {row.method}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span
                              className={cn(
                                'rounded px-2 py-0.5 text-xs',
                                getStatusBadgeClass(row.status_code)
                              )}
                            >
                              {row.status_code}
                            </span>
                          </TableCell>
                          <TableCell>
                            {row.response_time == null ? '-' : `${row.response_time}ms`}
                          </TableCell>
                          <TableCell>
                            <span className="max-w-[120px] truncate text-xs" title={row.user_agent ?? '-'}>
                              {parseUserAgent(row.user_agent)}
                            </span>
                          </TableCell>
                          <TableCell>
                            {!row.referer ? (
                              '-'
                            ) : (
                              <a
                                href={row.referer}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="max-w-[160px] truncate text-xs text-primary underline underline-offset-2"
                                title={row.referer}
                              >
                                {row.referer.length > 40
                                  ? row.referer.substring(0, 40) + '...'
                                  : row.referer}
                              </a>
                            )}
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>
              <div className="flex items-center justify-between border-t px-4 py-2 text-sm text-muted-foreground">
                <span>共 {logsTotal} 条</span>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={logsPage <= 1}
                    onClick={() => setLogsPage((p) => p - 1)}
                  >
                    上一页
                  </Button>
                  <span>
                    第 {logsPage} / {logsTotalPages} 页
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={logsPage >= logsTotalPages}
                    onClick={() => setLogsPage((p) => p + 1)}
                  >
                    下一页
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="statistics" className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatBlock title="文章总数" value={data?.posts ?? 0} icon={FileText} />
            <StatBlock
              title="已发布"
              value={data?.published_posts ?? 0}
              icon={CheckCircle}
              valueClassName="text-green-600 dark:text-green-400"
            />
            <StatBlock
              title="草稿"
              value={data?.draft_posts ?? 0}
              icon={Clock}
              valueClassName="text-destructive"
            />
            <StatBlock title="总访问量" value={data?.total_views ?? 0} icon={Eye} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatBlock title="评论总数" value={data?.comments ?? 0} icon={MessageCircle} />
            <StatBlock
              title="待审核"
              value={data?.pending_comments ?? 0}
              icon={Clock}
              valueClassName="text-amber-600 dark:text-amber-400"
            />
            <StatBlock
              title="已通过"
              value={data?.approved_comments ?? 0}
              icon={CheckCircle}
              valueClassName="text-green-600 dark:text-green-400"
            />
            <StatBlock title="用户总数" value={data?.users ?? 0} icon={User} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatBlock title="分类数" value={data?.categories ?? 0} icon={FolderOpen} />
            <StatBlock title="标签数" value={data?.tags ?? 0} icon={Tag} />
            <StatBlock title="附件数" value={data?.attachments ?? 0} icon={FileIcon} />
            <StatBlock
              title="附件总大小"
              value={formatFileSize(data?.attachments_size ?? 0)}
              icon={FileIcon}
            />
          </div>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>访问量趋势</CardTitle>
              <RadioGroup
                value={String(days)}
                onValueChange={(v) => setDays(Number(v) as 7 | 14 | 30)}
                className="flex gap-2"
              >
                {([7, 14, 30] as const).map((d) => (
                  <div key={d} className="flex items-center space-x-2">
                    <RadioGroupItem value={String(d)} id={`days-${d}`} />
                    <Label htmlFor={`days-${d}`} className="cursor-pointer text-sm">
                      {d}天
                    </Label>
                  </div>
                ))}
              </RadioGroup>
            </CardHeader>
            <CardContent>
              <div className="min-h-[420px] w-full">
                {trendLoading ? (
                  <Skeleton className="h-[420px] w-full" />
                ) : viewsTrend.length > 0 ? (
                  <ResponsiveContainer width="100%" height={420}>
                    <LineChart
                      data={viewsTrend}
                      margin={{ top: 8, right: 8, left: 8, bottom: 8 }}
                    >
                      <XAxis
                        dataKey="date"
                        tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: 12 }}
                        tickFormatter={(text) => {
                          const date = new Date(text)
                          return `${date.getMonth() + 1}/${date.getDate()}`
                        }}
                        stroke="hsl(var(--border))"
                      />
                      <YAxis
                        tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: 12 }}
                        stroke="hsl(var(--border))"
                      />
                      <Tooltip content={(props: TooltipContentProps<number, string>) => <ChartTooltip {...props} />} />
                      <Line
                        type="monotone"
                        dataKey="count"
                        name="访问量"
                        stroke="hsl(var(--chart-1))"
                        strokeWidth={2}
                        dot={{ fill: 'hsl(var(--chart-1))', r: 4 }}
                        activeDot={{ r: 6 }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex h-[420px] items-center justify-center text-muted-foreground">
                    暂无访问数据
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
