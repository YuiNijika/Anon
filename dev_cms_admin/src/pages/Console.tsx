import { useEffect, useMemo, useRef, useState } from 'react'
import { Alert, Button, Card, Col, Descriptions, Row, Spin, Statistic, message } from 'antd'
import {
  EyeOutlined,
  CommentOutlined,
  FolderOutlined,
  TagOutlined,
  UserOutlined,
  FileTextOutlined,
} from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type StatisticsData } from '@/services/admin'
import { useAuth } from '@/hooks/useAuth'

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

export default function Console() {
  const apiAdmin = useApiAdmin()
  const auth = useAuth()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [data, setData] = useState<StatisticsData | null>(null)
  const [basic, setBasic] = useState<any>(null)
  const [theme, setTheme] = useState<any>(null)
  const [githubRepo, setGithubRepo] = useState<GithubRepoInfo | null>(null)
  const [githubRelease, setGithubRelease] = useState<GithubReleaseInfo | null>(null)
  const fetchingRef = useRef(false)

  const userName = useMemo(() => {
    return auth.user?.display_name || auth.user?.name || '用户'
  }, [auth.user])

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
          setData(statsRes.value.data)
        }
        if (basicRes.status === 'fulfilled') {
          setBasic(basicRes.value.data || null)
        }
        if (themeRes.status === 'fulfilled') {
          setTheme(themeRes.value.data || null)
        }

        const cachedRepo = readCache<GithubRepoInfo>(GITHUB_REPO_CACHE_KEY)
        if (cachedRepo) {
          setGithubRepo(cachedRepo)
        }
        const cachedRelease = readCache<GithubReleaseInfo>(GITHUB_RELEASE_CACHE_KEY)
        if (cachedRelease) {
          setGithubRelease(cachedRelease)
        }

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
        message.error('获取统计数据失败')
        console.error(err)
      } finally {
        setLoading(false)
        fetchingRef.current = false
      }
    }

    fetchData()
  }, [apiAdmin])

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '50px' }}>
        <Spin size="large" />
      </div>
    )
  }

  return (
    <div>
      <Alert
        message={`您好 ${userName}, 欢迎使用 AnonEcho !`}
        type="success"
        style={{ marginBottom: 16 }}
        showIcon
        action={
          <Button
            size="small"
            onClick={async () => {
              await auth.logout()
              navigate('/login', { replace: true })
            }}
          >
            退出登录
          </Button>
        }
      />
      <Row gutter={[16, 16]}>
        <Col sm={24} lg={12}>
          <Card title="站点信息">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="站点标题">{basic?.title || '-'}</Descriptions.Item>
              <Descriptions.Item label="站点描述">{basic?.description || '-'}</Descriptions.Item>
              <Descriptions.Item label="关键词">{basic?.keywords || '-'}</Descriptions.Item>
              <Descriptions.Item label="当前主题">{theme?.current || '-'}</Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col sm={24} lg={12}>
          <Card title="最新动态">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="仓库">
                {githubRepo?.html_url ? (
                  <a href={githubRepo.html_url} target="_blank" rel="noreferrer">
                    {githubRepo.full_name}
                  </a>
                ) : (
                  'YuiNijika/Anon'
                )}
              </Descriptions.Item>
              <Descriptions.Item label="描述">{githubRepo?.description || '-'}</Descriptions.Item>
              <Descriptions.Item label="最新版本">
                {githubRelease?.html_url ? (
                  <a href={githubRelease.html_url} target="_blank" rel="noreferrer">
                    {githubRelease.tag_name}
                  </a>
                ) : (
                  '-'
                )}
              </Descriptions.Item>
              <Descriptions.Item label="更新时间">
                {githubRepo?.updated_at ? new Date(githubRepo.updated_at).toLocaleString('zh-CN') : '-'}
              </Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
      </Row>
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={24}>
          <Card title="数据概览">
            <Row gutter={[16, 16]}>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="文章" value={data?.posts ?? 0} prefix={<FileTextOutlined />} />
              </Col>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="评论" value={data?.comments ?? 0} prefix={<CommentOutlined />} />
              </Col>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="分类" value={data?.categories ?? 0} prefix={<FolderOutlined />} />
              </Col>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="标签" value={data?.tags ?? 0} prefix={<TagOutlined />} />
              </Col>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="用户" value={data?.users ?? 0} prefix={<UserOutlined />} />
              </Col>
              <Col xs={12} sm={8} lg={4}>
                <Statistic title="总访问" value={data?.total_views ?? 0} prefix={<EyeOutlined />} />
              </Col>
            </Row>
          </Card>
        </Col>
      </Row>
    </div>
  )
}
