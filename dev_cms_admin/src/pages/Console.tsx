import { useEffect, useState, useRef } from 'react'
import { Card, Row, Col, Statistic, Spin, message } from 'antd'
import {
  UserOutlined,
  FileTextOutlined,
  EyeOutlined,
  ClockCircleOutlined,
  CommentOutlined,
  FolderOutlined,
  TagOutlined,
  FileOutlined,
} from '@ant-design/icons'
import { useApiAdmin, useTheme } from '@/hooks'
import { AdminApi, type StatisticsData } from '@/services/admin'

export default function Console() {
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
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="总用户数"
              value={data?.users || 0}
              prefix={<UserOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="总文章数"
              value={data?.posts || 0}
              prefix={<FileTextOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="总访问量"
              value={data?.total_views || 0}
              prefix={<EyeOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="总评论数"
              value={data?.comments || 0}
              prefix={<CommentOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="已发布"
              value={data?.published_posts || 0}
              prefix={<FileTextOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="草稿"
              value={data?.draft_posts || 0}
              prefix={<FileTextOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="分类数"
              value={data?.categories || 0}
              prefix={<FolderOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="标签数"
              value={data?.tags || 0}
              prefix={<TagOutlined />}
              valueStyle={{ fontSize: '24px' }}
            />
          </Card>
        </Col>
      </Row>
    </div>
  )
}
