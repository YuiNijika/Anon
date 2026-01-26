import { useEffect, useState, useRef } from 'react'
import { Card, Row, Col, Statistic, Spin, message, Divider } from 'antd'
import {
  FileTextOutlined,
  CommentOutlined,
  FileOutlined,
  FolderOutlined,
  TagOutlined,
  UserOutlined,
  EyeOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
} from '@ant-design/icons'
import { useApiAdmin, useTheme } from '@/hooks'
import { AdminApi, type StatisticsData } from '@/services/admin'

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

export default function Statistics() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [loading, setLoading] = useState(true)
  const [data, setData] = useState<StatisticsData | null>(null)
  const fetchingRef = useRef(false)

  useEffect(() => {
    if (fetchingRef.current) return

    const fetchData = async () => {
      fetchingRef.current = true
      try {
        setLoading(true)
        const res = await AdminApi.getStatistics(apiAdmin)
        if (res.data) {
          setData(res.data)
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
  }, [])

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '50px' }}>
        <Spin size="large" />
      </div>
    )
  }

  return (
    <div>
      <div style={{ marginBottom: '24px' }}>
        <h1 style={{ margin: 0, fontSize: '24px', fontWeight: 600, color: isDark ? '#1890ff' : '#1890ff' }}>
          统计数据
        </h1>
      </div>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="文章总数"
              value={data?.posts || 0}
              prefix={<FileTextOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="已发布"
              value={data?.published_posts || 0}
              prefix={<CheckCircleOutlined />}
              valueStyle={{ color: '#3f8600' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="草稿"
              value={data?.draft_posts || 0}
              prefix={<ClockCircleOutlined />}
              valueStyle={{ color: '#cf1322' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="总访问量"
              value={data?.total_views || 0}
              prefix={<EyeOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="评论总数"
              value={data?.comments || 0}
              prefix={<CommentOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="待审核"
              value={data?.pending_comments || 0}
              prefix={<ClockCircleOutlined />}
              valueStyle={{ color: '#faad14' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="已通过"
              value={data?.approved_comments || 0}
              prefix={<CheckCircleOutlined />}
              valueStyle={{ color: '#3f8600' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="用户总数"
              value={data?.users || 0}
              prefix={<UserOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="分类数"
              value={data?.categories || 0}
              prefix={<FolderOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="标签数"
              value={data?.tags || 0}
              prefix={<TagOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="附件数"
              value={data?.attachments || 0}
              prefix={<FileOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="附件总大小"
              value={formatFileSize(data?.attachments_size || 0)}
              prefix={<FileOutlined />}
            />
          </Card>
        </Col>
      </Row>
    </div>
  )
}
