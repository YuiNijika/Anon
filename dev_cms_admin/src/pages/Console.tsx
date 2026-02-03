import { useEffect, useRef, useState } from 'react'
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from 'recharts'
import type { TooltipContentProps } from 'recharts'
import { toast } from 'sonner'
import {
  Eye,
  MessageCircle,
  Folder,
  Tag,
  User,
  FileText,
} from 'lucide-react'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type StatisticsData } from '@/services/admin'
import { getErrorMessage } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

type GithubRepoInfo = {
  full_name: string
  description: string | null
  stargazers_count: number
  forks_count: number
  open_issues_count: number
  updated_at: string
  html_url: string
}

type GithubReleaseInfo = {
  tag_name: string
  name: string | null
  published_at: string
  html_url: string
}

type CachedValue<T> = {
  expiresAt: number
  value: T
}

const GITHUB_CACHE_TTL_MS = 6 * 60 * 60 * 1000
const GITHUB_REPO_CACHE_KEY = 'anon:console:githubRepo'
const GITHUB_RELEASE_CACHE_KEY = 'anon:console:githubRelease'

function readCache<T>(key: string): T | null {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    const cached = JSON.parse(raw) as CachedValue<T>
    if (!cached || typeof cached.expiresAt !== 'number') return null
    if (Date.now() >= cached.expiresAt) {
      localStorage.removeItem(key)
      return null
    }
    return cached.value ?? null
  } catch {
    return null
  }
}

function writeCache<T>(key: string, value: T): void {
  try {
    const payload: CachedValue<T> = {
      expiresAt: Date.now() + GITHUB_CACHE_TTL_MS,
      value,
    }
    localStorage.setItem(key, JSON.stringify(payload))
  } catch {
    // ignore
  }
}

function DescRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex gap-2 py-1 text-sm">
      <dt className="w-24 shrink-0 font-medium text-muted-foreground">{label}</dt>
      <dd className="min-w-0 flex-1">{children}</dd>
    </div>
  )
}

function StatBlock({ title, value, icon: Icon }: { title: string; value: number; icon: React.ComponentType<{ className?: string }> }) {
  return (
    <div className="rounded-lg border bg-card p-4">
      <p className="text-sm font-medium text-muted-foreground">{title}</p>
      <div className="mt-1 flex items-center gap-2">
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
        <span className="text-2xl font-semibold">{value}</span>
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

export default function Console() {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(true)
  const [data, setData] = useState<StatisticsData | null>(null)
  const [basic, setBasic] = useState<any>(null)
  const [theme, setTheme] = useState<any>(null)
  const [githubRepo, setGithubRepo] = useState<GithubRepoInfo | null>(null)
  const [githubRelease, setGithubRelease] = useState<GithubReleaseInfo | null>(null)
  const [viewsTrend, setViewsTrend] = useState<Array<{ date: string; count: number }>>([])
  const [trendLoading, setTrendLoading] = useState(false)
  const [trendDays] = useState<7 | 14 | 30>(7)
  const fetchingRef = useRef(false)
  const trendFetchingRef = useRef(false)

  useEffect(() => {
    if (fetchingRef.current) return

    const fetchData = async () => {
      fetchingRef.current = true
      try {
        setLoading(true)
        const [statsRes, basicRes, themeRes] = await Promise.allSettled([
          AdminApi.getStatistics(apiAdmin),
          AdminApi.getBasicSettings(apiAdmin),
          AdminApi.getThemeSettings(apiAdmin),
        ])

        if (statsRes.status === 'fulfilled' && statsRes.value.data) {
          const stats = statsRes.value.data
          setData(stats)
          setViewsTrend(stats.views_trend || [])
        }
        if (basicRes.status === 'fulfilled') {
          setBasic(basicRes.value.data || null)
        }
        if (themeRes.status === 'fulfilled') {
          setTheme(themeRes.value.data || null)
        }

        const cachedRepo = readCache<GithubRepoInfo>(GITHUB_REPO_CACHE_KEY)
        if (cachedRepo) setGithubRepo(cachedRepo)
        const cachedRelease = readCache<GithubReleaseInfo>(GITHUB_RELEASE_CACHE_KEY)
        if (cachedRelease) setGithubRelease(cachedRelease)

        if (!cachedRepo || !cachedRelease) {
          const [repoRes, releaseRes] = await Promise.allSettled([
            cachedRepo ? Promise.resolve(cachedRepo) : fetch('https://api.github.com/repos/YuiNijika/Anon').then((r) => r.json()),
            cachedRelease ? Promise.resolve(cachedRelease) : fetch('https://api.github.com/repos/YuiNijika/Anon/releases/latest').then((r) => r.json()),
          ])
          if (repoRes.status === 'fulfilled' && repoRes.value && (repoRes.value as any).full_name) {
            const repo = repoRes.value as GithubRepoInfo
            setGithubRepo(repo)
            writeCache(GITHUB_REPO_CACHE_KEY, repo)
          }
          if (releaseRes.status === 'fulfilled' && releaseRes.value && (releaseRes.value as any).tag_name) {
            const release = releaseRes.value as GithubReleaseInfo
            setGithubRelease(release)
            writeCache(GITHUB_RELEASE_CACHE_KEY, release)
          }
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
    if (trendFetchingRef.current || !apiAdmin) return
    const fetchTrend = async () => {
      trendFetchingRef.current = true
      try {
        setTrendLoading(true)
        const res = await AdminApi.getViewsTrend(apiAdmin, trendDays)
        if (res.data?.length) setViewsTrend(res.data)
      } catch (err) {
        console.error(err)
      } finally {
        setTrendLoading(false)
        trendFetchingRef.current = false
      }
    }
    fetchTrend()
  }, [apiAdmin, trendDays])

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-1 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <Skeleton className="h-6 w-24" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-20 w-full" />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <Skeleton className="h-6 w-24" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-20 w-full" />
            </CardContent>
          </Card>
        </div>
        <Card>
          <CardHeader>
            <Skeleton className="h-6 w-24" />
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <Skeleton key={i} className="h-20" />
              ))}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <Skeleton className="h-6 w-32" />
          </CardHeader>
          <CardContent>
            <Skeleton className="h-[320px] w-full" />
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-1 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>站点信息</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="space-y-0">
              <DescRow label="站点标题">{basic?.title ?? '-'}</DescRow>
              <DescRow label="站点描述">{basic?.description ?? '-'}</DescRow>
              <DescRow label="关键词">{basic?.keywords ?? '-'}</DescRow>
              <DescRow label="当前主题">{theme?.current ?? '-'}</DescRow>
            </dl>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>最新动态</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="space-y-0">
              <DescRow label="仓库">
                {githubRepo?.html_url ? (
                  <a href={githubRepo.html_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                    {githubRepo.full_name}
                  </a>
                ) : (
                  'YuiNijika/Anon'
                )}
              </DescRow>
              <DescRow label="描述">{githubRepo?.description ?? '-'}</DescRow>
              <DescRow label="最新版本">
                {githubRelease?.html_url ? (
                  <a href={githubRelease.html_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                    {githubRelease.tag_name}
                  </a>
                ) : (
                  '-'
                )}
              </DescRow>
              <DescRow label="请求时间">
                {githubRepo?.updated_at ? new Date(githubRepo.updated_at).toLocaleString('zh-CN') : '-'}
              </DescRow>
            </dl>
          </CardContent>
        </Card>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>数据概览</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <StatBlock title="文章" value={data?.posts ?? 0} icon={FileText} />
            <StatBlock title="评论" value={data?.comments ?? 0} icon={MessageCircle} />
            <StatBlock title="分类" value={data?.categories ?? 0} icon={Folder} />
            <StatBlock title="标签" value={data?.tags ?? 0} icon={Tag} />
            <StatBlock title="用户" value={data?.users ?? 0} icon={User} />
            <StatBlock title="总访问" value={data?.total_views ?? 0} icon={Eye} />
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>访问量趋势</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="min-h-[320px] w-full">
            {trendLoading ? (
              <Skeleton className="h-[320px] w-full" />
            ) : viewsTrend.length > 0 ? (
              <ResponsiveContainer width="100%" height={320}>
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
              <div className="flex h-[320px] items-center justify-center text-muted-foreground">
                暂无访问数据
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
