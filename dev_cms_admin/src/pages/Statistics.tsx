import { useState, useEffect } from 'react'
import { useApiAdmin, useTheme } from '@/hooks'
import { toast } from 'sonner'
import { getErrorMessage } from '@/lib/utils'
import { AdminApi, type StatisticsData, type AccessLog, type AccessLogListResponse, type AccessStats } from '@/services/admin'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts'
import {
  Eye,
  Users,
  FileText,
  TrendingUp,
  Search,
  RefreshCw,
  Calendar,
  Globe,
  Smartphone,
  Filter,
  Download,
  Trash2,
  Clock,
  MapPin,
  Monitor,
  MoreHorizontal,
  X,
} from 'lucide-react'

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8']

export default function Statistics() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [loading, setLoading] = useState(true)
  const [statistics, setStatistics] = useState<StatisticsData | null>(null)
  const [accessLogs, setAccessLogs] = useState<AccessLog[]>([])
  const [accessStats, setAccessStats] = useState<AccessStats | null>(null)
  const [totalLogs, setTotalLogs] = useState(0)
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(20)
  const [filters, setFilters] = useState({
    ip: '',
    path: '',
    type: '',
    status_code: '',
    start_date: '',
    end_date: '',
  })
  const [trendDays, setTrendDays] = useState(7)
  const [showFilters, setShowFilters] = useState(true)

  useEffect(() => {
    loadData()
  }, [])

  useEffect(() => {
    loadAccessLogs()
  }, [currentPage, pageSize, filters])

  // 当趋势天数改变时重新加载统计数据
  useEffect(() => {
    if (trendDays !== 7) {
      loadTrendData()
    }
  }, [trendDays])

  const loadData = async () => {
    try {
      setLoading(true)
      const [statsRes, accessStatsRes] = await Promise.all([
        AdminApi.getStatistics(apiAdmin),
        AdminApi.getAccessStats(apiAdmin),
      ])
      
      if (statsRes.data) {
        setStatistics(statsRes.data)
      }
      if (accessStatsRes.data) {
        setAccessStats(accessStatsRes.data)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载统计数据失败'))
    } finally {
      setLoading(false)
    }
  }

  const loadTrendData = async () => {
    try {
      const res = await AdminApi.getViewsTrend(apiAdmin, trendDays)
      if (res.data && statistics) {
        setStatistics({
          ...statistics,
          views_trend: res.data,
        })
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载趋势数据失败'))
    }
  }

  const loadAccessLogs = async () => {
    try {
      const params: any = {
        page: currentPage,
        page_size: pageSize,
      }
      
      if (filters.ip) params.ip = filters.ip
      if (filters.path) params.path = filters.path
      if (filters.type) params.type = filters.type
      if (filters.status_code) params.status_code = parseInt(filters.status_code)
      if (filters.start_date) params.start_date = filters.start_date
      if (filters.end_date) params.end_date = filters.end_date

      const res = await AdminApi.getAccessLogs(apiAdmin, params)
      if (res.data) {
        setAccessLogs(res.data.list || [])
        setTotalLogs(res.data.total || 0)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载访问日志失败'))
    }
  }

  const handleFilterChange = (key: string, value: string) => {
    setFilters(prev => ({ ...prev, [key]: value }))
    setCurrentPage(1)
  }

  const handleSearch = () => {
    setCurrentPage(1)
    loadAccessLogs()
  }

  const handleReset = () => {
    setFilters({
      ip: '',
      path: '',
      type: '',
      status_code: '',
      start_date: '',
      end_date: '',
    })
    setCurrentPage(1)
  }

  const handleExport = () => {
    toast.info('导出功能开发中...')
  }

  const handleClearLogs = () => {
    if (confirm('确定要清空所有访问日志吗？此操作不可恢复！')) {
      toast.info('清空功能需要后端支持')
    }
  }

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
  }

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleString('zh-CN')
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-1/3" />
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[...Array(4)].map((_, i) => (
            <Card key={i}>
              <CardContent className="pt-6">
                <Skeleton className="h-20 w-full" />
              </CardContent>
            </Card>
          ))}
        </div>
        <Card>
          <CardContent className="pt-6">
            <Skeleton className="h-64 w-full" />
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">统计分析</h2>
          <p className="text-muted-foreground">查看网站访问数据和用户行为分析</p>
        </div>
        <Button onClick={loadData} variant="outline">
          <RefreshCw className="mr-2 h-4 w-4" />
          刷新数据
        </Button>
      </div>

      {/* 数据概览 */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">总访问量</CardTitle>
            <Eye className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{statistics?.total_views?.toLocaleString() || 0}</div>
            <p className="text-xs text-muted-foreground">累计页面浏览</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">文章总数</CardTitle>
            <FileText className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{statistics?.posts?.toLocaleString() || 0}</div>
            <p className="text-xs text-muted-foreground">
              已发布 {statistics?.published_posts || 0} · 草稿 {statistics?.draft_posts || 0}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">评论数</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{statistics?.comments?.toLocaleString() || 0}</div>
            <p className="text-xs text-muted-foreground">
              已通过 {statistics?.approved_comments || 0} · 待审核 {statistics?.pending_comments || 0}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">附件大小</CardTitle>
            <Globe className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatBytes(statistics?.attachments_size || 0)}</div>
            <p className="text-xs text-muted-foreground">{statistics?.attachments || 0} 个文件</p>
          </CardContent>
        </Card>
      </div>

      {/* 实时统计 */}
      {accessStats && (
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">总访问次数</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-primary">{accessStats.total?.toLocaleString() || 0}</div>
              <p className="text-xs text-muted-foreground mt-1">记录在案的访问请求</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">独立 IP 数</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600">{accessStats.unique_ips?.toLocaleString() || 0}</div>
              <p className="text-xs text-muted-foreground mt-1">不同访客数量</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">平均访问深度</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600">
                {accessStats.total && accessStats.unique_ips
                  ? (accessStats.total / accessStats.unique_ips).toFixed(1)
                  : '0'}
              </div>
              <p className="text-xs text-muted-foreground mt-1">每 IP 平均访问页数</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* 访问趋势图 */}
      {statistics?.views_trend && statistics.views_trend.length > 0 && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5" />
                  访问趋势
                </CardTitle>
                <CardDescription>最近 {trendDays} 天的访问量变化</CardDescription>
              </div>
              <Select value={String(trendDays)} onValueChange={(v) => setTrendDays(parseInt(v))}>
                <SelectTrigger className="w-[120px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="7">最近7天</SelectItem>
                  <SelectItem value="14">最近14天</SelectItem>
                  <SelectItem value="30">最近30天</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={350}>
              <LineChart data={statistics.views_trend} margin={{ top: 20, right: 30, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#8884d8" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#8884d8" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#374151' : '#e5e7eb'} vertical={false} />
                <XAxis 
                  dataKey="date" 
                  axisLine={false}
                  tickLine={false}
                  tick={{ fontSize: 12, fill: isDark ? '#9ca3af' : '#6b7280' }}
                  dy={10}
                />
                <YAxis 
                  axisLine={false}
                  tickLine={false}
                  tick={{ fontSize: 12, fill: isDark ? '#9ca3af' : '#6b7280' }}
                  dx={-10}
                />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: isDark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                    border: 'none',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                    padding: '12px'
                  }}
                  labelStyle={{ color: isDark ? '#f3f4f6' : '#374151', fontWeight: 600, marginBottom: '4px' }}
                  itemStyle={{ color: '#8884d8' }}
                />
                <Line 
                  type="monotone" 
                  dataKey="count" 
                  stroke="#8884d8" 
                  strokeWidth={3}
                  dot={{ r: 4, fill: '#8884d8', strokeWidth: 2, stroke: '#fff' }}
                  activeDot={{ r: 6, fill: '#8884d8', strokeWidth: 2, stroke: '#fff' }}
                  name="访问量"
                />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      )}

      {/* 访问日志 */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <Clock className="h-5 w-5" />
                访问日志
              </CardTitle>
              <CardDescription>查看详细的访问记录，支持多维度筛选</CardDescription>
            </div>
            <div className="flex gap-2">
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm">
                    <MoreHorizontal className="mr-2 h-4 w-4" />
                    更多操作
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-[160px]">
                  <DropdownMenuLabel>操作</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={handleExport}>
                    <Download className="mr-2 h-4 w-4" />
                    导出日志
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleClearLogs}>
                    <Trash2 className="mr-2 h-4 w-4" />
                    清空日志
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => setShowFilters(!showFilters)}>
                    <Filter className="mr-2 h-4 w-4" />
                    {showFilters ? '隐藏筛选' : '显示筛选'}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              <Button variant="outline" size="sm" onClick={handleReset}>
                <X className="mr-2 h-4 w-4" />
                重置
              </Button>
              <Button onClick={handleSearch} size="sm">
                <Search className="mr-2 h-4 w-4" />
                搜索
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {/* 筛选器 */}
          {showFilters && (
            <div className="mb-4 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
              <div className="relative">
                <MapPin className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="IP 地址"
                  value={filters.ip}
                  onChange={(e) => handleFilterChange('ip', e.target.value)}
                  className="pl-8"
                />
              </div>
              <div className="relative">
                <Globe className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="路径"
                  value={filters.path}
                  onChange={(e) => handleFilterChange('path', e.target.value)}
                  className="pl-8"
                />
              </div>
              <Select value={filters.type || 'all'} onValueChange={(v) => handleFilterChange('type', v === 'all' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder="请求类型" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">全部</SelectItem>
                  <SelectItem value="page">页面</SelectItem>
                  <SelectItem value="api">API</SelectItem>
                  <SelectItem value="static">静态资源</SelectItem>
                </SelectContent>
              </Select>
              <Input
                placeholder="状态码"
                value={filters.status_code}
                onChange={(e) => handleFilterChange('status_code', e.target.value)}
              />
              <Input
                type="date"
                value={filters.start_date}
                onChange={(e) => handleFilterChange('start_date', e.target.value)}
              />
              <Input
                type="date"
                value={filters.end_date}
                onChange={(e) => handleFilterChange('end_date', e.target.value)}
              />
            </div>
          )}

          {/* 表格 */}
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[160px]">时间</TableHead>
                  <TableHead className="w-[120px]">IP</TableHead>
                  <TableHead>路径</TableHead>
                  <TableHead className="w-[70px]">方法</TableHead>
                  <TableHead className="w-[80px]">类型</TableHead>
                  <TableHead className="w-[70px]">状态</TableHead>
                  <TableHead>UA</TableHead>
                  <TableHead className="w-[90px]">响应</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {accessLogs.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                      <div className="flex flex-col items-center gap-2">
                        <Clock className="h-8 w-8 text-muted-foreground/50" />
                        <p>暂无访问记录</p>
                      </div>
                    </TableCell>
                  </TableRow>
                ) : (
                  accessLogs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="font-mono text-xs">
                        {formatDate(log.created_at)}
                      </TableCell>
                      <TableCell className="font-mono text-xs">{log.ip}</TableCell>
                      <TableCell>
                        <div className="max-w-[250px] truncate" title={log.path}>
                          {log.path}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="font-mono text-xs">{log.method}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            log.type === 'page'
                              ? 'default'
                              : log.type === 'api'
                              ? 'secondary'
                              : 'outline'
                          }
                          className="text-xs"
                        >
                          {log.type}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            log.status_code >= 200 && log.status_code < 300
                              ? 'default'
                              : log.status_code >= 400
                              ? 'destructive'
                              : 'secondary'
                          }
                          className="font-mono text-xs"
                        >
                          {log.status_code}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="max-w-[300px] truncate text-xs" title={log.user_agent || '-'}>
                          {log.user_agent || '-'}
                        </div>
                      </TableCell>
                      <TableCell className="text-xs">
                        {log.response_time ? (
                          <span className={log.response_time > 1000 ? 'text-red-600' : log.response_time > 500 ? 'text-yellow-600' : 'text-green-600'}>
                            {log.response_time}ms
                          </span>
                        ) : (
                          '-'
                        )}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          {/* 分页 */}
          {totalLogs > 0 && (
            <div className="mt-4 flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="text-sm text-muted-foreground">
                  共 {totalLogs} 条记录，第 {currentPage} / {Math.ceil(totalLogs / pageSize)} 页
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-sm text-muted-foreground">每页显示：</span>
                  <Select value={String(pageSize)} onValueChange={(v) => {
                    setPageSize(parseInt(v))
                    setCurrentPage(1)
                  }}>
                    <SelectTrigger className="w-[100px]">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="10">10 条</SelectItem>
                      <SelectItem value="20">20 条</SelectItem>
                      <SelectItem value="50">50 条</SelectItem>
                      <SelectItem value="100">100 条</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                >
                  上一页
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => p + 1)}
                  disabled={currentPage >= Math.ceil(totalLogs / pageSize)}
                >
                  下一页
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* 热门页面 */}
      {accessStats?.top_pages && accessStats.top_pages.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>热门页面 TOP 10</CardTitle>
            <CardDescription>访问量最高的页面</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {accessStats.top_pages.map((page, index) => (
                <div key={index} className="flex items-center justify-between rounded-lg border p-3">
                  <div className="flex items-center gap-3">
                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                      {index + 1}
                    </span>
                    <span className="font-mono text-sm">{page.path}</span>
                  </div>
                  <Badge variant="secondary">{page.count} 次访问</Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
