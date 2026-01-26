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
  message,
  Space,
  Tabs,
  Row,
  Col,
  Tag,
  Spin,
  Image,
  Divider,
} from 'antd'
import { useApiAdmin, useTheme } from '@/hooks'
import { AdminApi, type ThemeOptionSchema, type ThemeInfo } from '@/services/admin'
import { Typography } from 'antd'
import { CheckOutlined, SwapOutlined } from '@ant-design/icons'

const { TextArea } = Input
const { Title, Text } = Typography

export default function SettingsTheme() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [form] = Form.useForm()
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
    if (theme.screenshot) {
      return theme.screenshot
    }
    const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
    return `${baseUrl}/anon/static/cms/theme/${theme.name}/screenshot`
  }

  const tabItems = [
    {
      key: 'list',
      label: '主题列表',
      children: (
        <div>
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
                    cover={
                      <div style={{ height: '180px', overflow: 'hidden', backgroundColor: '#f5f5f5' }}>
                        <Image
                          src={getScreenshotUrl(theme)}
                          alt={theme.displayName}
                          style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                          preview={false}
                          fallback="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect fill='%23ddd' width='200' height='200'/%3E%3Ctext fill='%23999' font-family='sans-serif' font-size='14' dy='10.5' font-weight='bold' x='50%25' y='50%25' text-anchor='middle'%3E无截图%3C/text%3E%3C/svg%3E"
                        />
                      </div>
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
                    ]}
                  >
                    <Card.Meta
                      avatar={
                        <Image
                          src={getScreenshotUrl(theme)}
                          alt={theme.displayName}
                          width={64}
                          height={64}
                          style={{ borderRadius: '4px', objectFit: 'cover' }}
                          preview={false}
                          fallback="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64'%3E%3Crect fill='%23ddd' width='64' height='64'/%3E%3Ctext fill='%23999' font-family='sans-serif' font-size='10' dy='10.5' font-weight='bold' x='50%25' y='50%25' text-anchor='middle'%3E无%3C/text%3E%3C/svg%3E"
                        />
                      }
                      title={
                        <Space>
                          <span>{theme.displayName}</span>
                          {theme.name === currentTheme && <Tag color="blue">当前使用</Tag>}
                          {theme.version && <Tag>{theme.version}</Tag>}
                        </Space>
                      }
                      description={
                        <div>
                          {theme.description && (
                            <div style={{ marginBottom: '8px' }}>{theme.description}</div>
                          )}
                          <Space size="small" split={<Divider type="vertical" />}>
                            {theme.author && <Text type="secondary">作者: {theme.author}</Text>}
                            {theme.url && (
                              <a href={theme.url} target="_blank" rel="noopener noreferrer">
                                访问官网
                              </a>
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
      <Title level={2} style={{ marginBottom: '24px', color: isDark ? '#1890ff' : '#1890ff' }}>
        主题设置
      </Title>

      <Tabs activeKey={activeTab} onChange={setActiveTab} items={tabItems} />
    </div>
  )
}
