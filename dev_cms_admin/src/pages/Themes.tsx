import { useState, useEffect, useRef } from 'react'
import {
  Card,
  Form,
  Input,
  Button,
  Select,
  Switch,
  InputNumber,
  ColorPicker,
  App,
  Space,
  Tabs,
  Row,
  Col,
  Tag,
  Spin,
  Divider,
  Upload,
  Modal,
} from 'antd'
import { useApiAdmin, useThemes } from '@/hooks'
import { AdminApi, type ThemeOptionSchema, type ThemeInfo } from '@/services/admin'
import { Typography } from 'antd'
import { CheckOutlined, SwapOutlined, UploadOutlined, DeleteOutlined } from '@ant-design/icons'
import { getApiBaseUrl } from '@/utils/api'

const { TextArea } = Input
const { Text } = Typography

export default function SettingsTheme() {
  const apiAdmin = useApiAdmin()
  const { uploadTheme, deleteTheme, loading: themeUploading } = useThemes()
  const [form] = Form.useForm()
  const app = App.useApp()
  const message = app.message
  const modal = app.modal

  const baseUrl = getApiBaseUrl()
  const nullSvgUrl = `${baseUrl}/anon/static/img/null`

  const [loading, setLoading] = useState(false)
  const [themeListLoading, setThemeListLoading] = useState(false)
  const [switchingTheme, setSwitchingTheme] = useState<string | null>(null)
  const [schema, setSchema] = useState<Record<string, ThemeOptionSchema>>({})
  const [initialValues, setInitialValues] = useState<Record<string, any>>({})
  const [currentTheme, setCurrentTheme] = useState<string>('')
  const [themes, setThemes] = useState<ThemeInfo[]>([])
  const [activeTab, setActiveTab] = useState('list')
  const fetchingRef = useRef(false)
  const themeListFetchingRef = useRef(false)

  useEffect(() => {
    loadThemeList()
    loadThemeOptions()
  }, [apiAdmin])

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
      message.error('加载主题列表失败')
    } finally {
      setThemeListLoading(false)
      themeListFetchingRef.current = false
    }
  }

  const loadThemeOptions = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      const res = await AdminApi.getThemeOptions(apiAdmin)
      if (res.data) {
        setSchema(res.data.schema || {})
        setInitialValues(res.data.values || {})
        form.setFieldsValue(res.data.values || {})
      }
    } catch (err) {
      message.error('加载主题设置失败')
    } finally {
      fetchingRef.current = false
    }
  }

  const handleSwitchTheme = async (themeName: string) => {
    if (themeName === currentTheme) {
      return
    }

    setSwitchingTheme(themeName)
    try {
      await AdminApi.updateThemeSettings(apiAdmin, themeName)
      setCurrentTheme(themeName)
      message.success('切换主题成功')
      await loadThemeOptions()
    } catch (err) {
      message.error('切换主题失败')
    } finally {
      setSwitchingTheme(null)
    }
  }

  const handleSubmit = async (values: Record<string, any>) => {
    try {
      setLoading(true)
      await AdminApi.updateThemeOptions(apiAdmin, values)
      message.success('主题设置已保存')
    } catch (err) {
      message.error('保存主题设置失败')
    } finally {
      setLoading(false)
    }
  }

  const renderThemeField = (key: string, option: ThemeOptionSchema) => {
    const { type, label, description, options: selectOptions, default: defaultValue } = option

    switch (type) {
      case 'text':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description}>
            <Input placeholder={defaultValue} />
          </Form.Item>
        )
      case 'textarea':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description}>
            <TextArea rows={4} placeholder={defaultValue} />
          </Form.Item>
        )
      case 'select':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description}>
            <Select
              placeholder="请选择"
              options={Object.entries(selectOptions || {}).map(([k, v]) => ({ value: k, label: v }))}
            />
          </Form.Item>
        )
      case 'checkbox':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description} valuePropName="checked">
            <Switch />
          </Form.Item>
        )
      case 'number':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description}>
            <InputNumber style={{ width: '100%' }} placeholder={defaultValue} />
          </Form.Item>
        )
      case 'color':
        return (
          <Form.Item key={key} name={key} label={label} tooltip={description}>
            <ColorPicker showText />
          </Form.Item>
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

    // JSON 返回的 screenshot 可能是相对路径或绝对 URL
    if (screenshot.startsWith('http://') || screenshot.startsWith('https://')) return screenshot
    if (screenshot.startsWith('/')) return `${baseUrl}${screenshot}`
    return `${baseUrl}/${screenshot}`
  }

  const getUrlLabel = (url: string): string => {
    try {
      const urlObj = new URL(url)
      const hostname = urlObj.hostname.toLowerCase()

      // 移除 www. 前缀
      const domain = hostname.replace(/^www\./, '')

      // 判断代码托管平台
      if (domain.includes('github.com')) return 'GitHub'
      if (domain.includes('gitee.com')) return 'Gitee'
      if (domain.includes('gitlab.com')) return 'GitLab'
      if (domain.includes('bitbucket.org')) return 'Bitbucket'
      if (domain.includes('coding.net')) return 'Coding'

      // 默认
      return '访问网站'
    } catch {
      // URL 解析失败，返回默认
      return '访问网站'
    }
  }

  const handleUploadTheme = async (file: File) => {
    try {
      await uploadTheme(file)
      await loadThemeList()
    } finally {
    }
    return false
  }

  const handleDeleteTheme = (theme: ThemeInfo) => {
    if (theme.name === currentTheme) {
      message.warning('不能删除当前使用的主题')
      return
    }

    modal.confirm({
      title: '确认删除',
      content: `确定要删除主题 "${theme.displayName}" 吗？此操作不可恢复。`,
      onOk: async () => {
        await deleteTheme(theme.name)
        await loadThemeList()
      },
    })
  }

  const tabItems = [
    {
      key: 'list',
      label: '主题列表',
      children: (
        <div>
          <div style={{ marginBottom: 16, textAlign: 'right' }}>
            <Upload
              accept=".zip"
              showUploadList={false}
              beforeUpload={handleUploadTheme}
              disabled={themeUploading}
            >
              <Button type="primary" icon={<UploadOutlined />} loading={themeUploading}>
                上传主题
              </Button>
            </Upload>
          </div>
          {themeListLoading ? (
            <Card>
              <div style={{ textAlign: 'center', padding: '40px' }}>
                <Spin size="large" />
              </div>
            </Card>
          ) : themes.length === 0 ? (
            <Card>
              <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                暂无可用主题
              </div>
            </Card>
          ) : (
            <Row gutter={[16, 16]}>
              {sortedThemes.map((theme) => (
                <Col key={theme.name} xs={24} sm={12} lg={8} xl={6}>
                  <Card
                    hoverable
                    style={{ width: '100%' }}
                    cover={
                      <img
                        draggable={false}
                        alt={theme.displayName}
                        src={getScreenshotUrl(theme)}
                        style={{ width: '100%', height: '200px', objectFit: 'cover' }}
                        onError={(e) => {
                          const target = e.target as HTMLImageElement
                          target.src = nullSvgUrl
                        }}
                      />
                    }
                    actions={[
                      <Button
                        key="switch"
                        type={theme.name === currentTheme ? 'default' : 'primary'}
                        icon={theme.name === currentTheme ? <CheckOutlined /> : <SwapOutlined />}
                        loading={switchingTheme === theme.name}
                        onClick={() => handleSwitchTheme(theme.name)}
                        disabled={theme.name === currentTheme}
                        block
                      >
                        {theme.name === currentTheme ? '当前使用' : '切换主题'}
                      </Button>,
                      <Button
                        key="delete"
                        danger
                        icon={<DeleteOutlined />}
                        onClick={() => handleDeleteTheme(theme)}
                        disabled={theme.name === currentTheme}
                        block
                      >
                        删除
                      </Button>,
                    ]}
                  >
                    <Card.Meta
                      title={
                        <Space>
                          <span>{theme.displayName}</span>
                          {theme.name === currentTheme && <Tag color="blue">当前使用</Tag>}
                        </Space>
                      }
                      description={
                        <div>
                          {theme.description && <div style={{ marginBottom: '8px' }}>{theme.description}</div>}
                          <Space size="small" split={<Divider type="vertical" />}>
                            {theme.version && <Tag>{theme.version}</Tag>}
                            {theme.author && <Text type="secondary">{theme.author}</Text>}
                            {theme.url && (
                              <Text type="secondary">
                                <a href={theme.url} target="_blank" rel="noopener noreferrer">
                                  {getUrlLabel(theme.url)}
                                </a>
                              </Text>
                            )}
                          </Space>
                        </div>
                      }
                    />
                  </Card>
                </Col>
              ))}
            </Row>
          )}
        </div>
      ),
    },
    {
      key: 'options',
      label: '主题设置',
      children: (
        <Card loading={loading}>
          <Form form={form} layout="vertical" onFinish={handleSubmit} initialValues={initialValues}>
            {Object.entries(schema).map(([key, option]) => renderThemeField(key, option))}
            {Object.keys(schema).length === 0 && (
              <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                当前主题没有可用的设置项
              </div>
            )}
            {Object.keys(schema).length > 0 && (
              <Form.Item>
                <Space>
                  <Button type="primary" htmlType="submit" loading={loading}>
                    保存更改
                  </Button>
                  <Button onClick={() => form.resetFields()}>重置</Button>
                </Space>
              </Form.Item>
            )}
          </Form>
        </Card>
      ),
    },
  ]

  return (
    <div>
      <Tabs activeKey={activeTab} onChange={setActiveTab} items={tabItems} />
    </div>
  )
}
