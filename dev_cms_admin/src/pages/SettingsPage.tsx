import { useState, useEffect, useRef } from 'react'
import { Form, Input, Button, App, Space, Typography, Radio, Alert } from 'antd'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type PageSettings } from '@/services/admin'

const { Title, Text } = Typography

// 路径预设风格（包含所有路径类型）
const PATH_STYLES = [
  {
    value: 'default',
    label: '默认风格',
    paths: {
      post: '/archives/{id}/',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'wordpress',
    label: 'WordPress 风格',
    paths: {
      post: '/archives/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'date',
    label: '按日期归档',
    paths: {
      post: '/{year}/{month}/{day}/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'category',
    label: '按分类归档',
    paths: {
      post: '/{category}/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'custom',
    label: '个性化定义',
    paths: {
      post: '',
      page: '',
      category: '',
      tag: '',
    },
  },
]

// 可用参数说明
const POST_PARAMS = ['{id}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}']
const PAGE_PARAMS = ['{id}', '{slug}', '{directory}']
const CATEGORY_PARAMS = ['{id}', '{slug}', '{directory}']
const TAG_PARAMS = ['{id}', '{slug}']

export default function SettingsPage() {
  const apiAdmin = useApiAdmin()
  const [form] = Form.useForm()
  const app = App.useApp()
  const message = app.message
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)
  const [postPathStyle, setPostPathStyle] = useState<string>('default')

  useEffect(() => {
    loadSettings()
  }, [apiAdmin])

  const loadSettings = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getPageSettings(apiAdmin)
      if (res.data) {
        const routes = res.data.routes || {}

        // 获取各路径
        const postPath = Object.entries(routes).find(([_, template]) => template === 'post')?.[0] || '/archives/{id}/'
        const pagePath = Object.entries(routes).find(([_, template]) => template === 'page')?.[0] || '/{slug}.html'
        const categoryPath = Object.entries(routes).find(([_, template]) => template === 'category')?.[0] || '/category/{slug}/'
        const tagPath = Object.entries(routes).find(([_, template]) => template === 'tag')?.[0] || '/tag/{slug}/'

        // 检测路径风格
        const detectedStyle =
          PATH_STYLES.find(
            (style) =>
              style.paths.post === postPath &&
              style.paths.page === pagePath &&
              style.paths.category === categoryPath &&
              style.paths.tag === tagPath
          )?.value || 'custom'
        setPostPathStyle(detectedStyle)

        // 设置表单值
        form.setFieldsValue({
          postPath: postPath,
          postPathStyle: detectedStyle,
          pagePath: pagePath,
          categoryPath: categoryPath,
          tagPath: tagPath,
        })
      }
    } catch (err) {
      message.error('加载页面设置失败')
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handlePostPathStyleChange = (style: string) => {
    setPostPathStyle(style)
    const selectedStyle = PATH_STYLES.find((s) => s.value === style)
    if (selectedStyle && selectedStyle.paths) {
      // 更新所有路径，保持风格一致
      form.setFieldsValue({
        postPath: selectedStyle.paths.post,
        pagePath: selectedStyle.paths.page,
        categoryPath: selectedStyle.paths.category,
        tagPath: selectedStyle.paths.tag,
      })
    }
    // 个性化定义时，不清空现有值，让用户自己输入
  }

  const handleSubmit = async (values: any) => {
    try {
      setLoading(true)

      const routes: Record<string, string> = {}

      // 文章路径
      if (values.postPath && values.postPath.trim()) {
        routes[values.postPath.trim()] = 'post'
      }

      // 独立页面路径
      if (values.pagePath && values.pagePath.trim()) {
        routes[values.pagePath.trim()] = 'page'
      }

      // 分类路径
      if (values.categoryPath && values.categoryPath.trim()) {
        routes[values.categoryPath.trim()] = 'category'
      }

      // 标签路径
      if (values.tagPath && values.tagPath.trim()) {
        routes[values.tagPath.trim()] = 'tag'
      }

      const submitData: PageSettings = {
        routes,
      }

      await AdminApi.updatePageSettings(apiAdmin, submitData)
      message.success('页面设置已保存')
      await loadSettings()
    } catch (err) {
      message.error('保存页面设置失败')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Form form={form} layout="vertical" onFinish={handleSubmit} preserve={false}>
        <Title level={4}>URL 规则设置</Title>

        <Alert
          message="提示"
          description="一旦你选择了某种链接风格请不要轻易修改它，这可能会影响搜索引擎收录和外部链接。"
          type="warning"
          showIcon
          style={{ marginBottom: '24px' }}
        />

        {/* 文章路径设置 */}
        <Title level={5} style={{ marginTop: '24px', marginBottom: '16px' }}>自定义文章路径</Title>
        <div style={{ marginBottom: '16px' }}>
          <Text type="secondary" style={{ fontSize: '13px' }}>
            选择一种合适的文章静态路径风格，使得你的网站链接更加友好。
          </Text>
        </div>

        <Form.Item name="postPathStyle" label="路径风格">
          <Radio.Group
            value={postPathStyle}
            onChange={(e) => handlePostPathStyleChange(e.target.value)}
            options={PATH_STYLES.map((style) => ({
              label: style.label,
              value: style.value,
            }))}
          />
        </Form.Item>

        <Form.Item
          name="postPath"
          label="文章路径"
          rules={[
            { required: true, message: '请输入文章路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = POST_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {POST_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/archives/{id}/" />
        </Form.Item>

        {/* 独立页面路径设置 */}
        <Form.Item
          name="pagePath"
          label="页面路径"
          rules={[
            { required: true, message: '请输入页面路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = PAGE_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {PAGE_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/{slug}.html" />
        </Form.Item>

        {/* 分类路径设置 */}
        <Form.Item
          name="categoryPath"
          label="分类路径"
          rules={[
            { required: true, message: '请输入分类路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = CATEGORY_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {CATEGORY_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/category/{slug}/" />
        </Form.Item>

        {/* 标签路径设置 */}
        <Form.Item
          name="tagPath"
          label="标签路径"
          rules={[
            { required: true, message: '请输入标签路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = TAG_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {TAG_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/tag/{slug}/" />
        </Form.Item>

        <Form.Item style={{ marginTop: '32px' }}>
          <Space>
            <Button type="primary" htmlType="submit" loading={loading}>
              保存更改
            </Button>
            <Button onClick={() => form.resetFields()}>重置</Button>
          </Space>
        </Form.Item>
      </Form>
    </div>
  )
}
