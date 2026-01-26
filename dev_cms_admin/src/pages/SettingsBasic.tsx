import { useState, useEffect, useRef } from 'react'
import { Card, Form, Input, Button, Switch, message, Space, Divider, Typography } from 'antd'
import { useApiAdmin, useTheme } from '@/hooks'
import { AdminApi, type BasicSettings } from '@/services/admin'

const { TextArea } = Input
const { Title } = Typography

export default function SettingsBasic() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)

  useEffect(() => {
    if (fetchingRef.current) return

    const loadSettings = async () => {
      fetchingRef.current = true
      try {
        setLoading(true)
        const res = await AdminApi.getBasicSettings(apiAdmin)
        if (res.data) {
          form.setFieldsValue(res.data)
        }
      } catch (err) {
        message.error('加载常规设置失败')
      } finally {
        setLoading(false)
        fetchingRef.current = false
      }
    }

    loadSettings()
  }, [apiAdmin, form])

  const handleSubmit = async (values: BasicSettings) => {
    try {
      setLoading(true)
      await AdminApi.updateBasicSettings(apiAdmin, values)
      message.success('常规设置已保存')
    } catch (err) {
      message.error('保存常规设置失败')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Title level={2} style={{ marginBottom: '24px', color: isDark ? '#1890ff' : '#1890ff' }}>
        常规设置
      </Title>

      <Card loading={loading}>
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
          <Title level={4}>站点信息</Title>
          <Form.Item
            name="title"
            label="站点标题"
            rules={[{ required: true, message: '请输入站点标题' }]}
          >
            <Input placeholder="站点名称" />
          </Form.Item>

          <Form.Item name="description" label="站点描述">
            <TextArea rows={3} placeholder="用简洁的文字描述您的站点" />
          </Form.Item>

          <Form.Item name="keywords" label="关键词">
            <Input placeholder="用逗号分隔的关键词" />
          </Form.Item>

          <Divider />

          <Title level={4}>用户与访问</Title>
          <Form.Item name="allow_register" label="允许注册" valuePropName="checked">
            <Switch />
            <div style={{ marginTop: '8px', fontSize: '13px', color: '#8c8c8c' }}>
              允许用户自行注册账户
            </div>
          </Form.Item>

          <Form.Item name="access_log_enabled" label="启用访问日志" valuePropName="checked">
            <Switch />
            <div style={{ marginTop: '8px', fontSize: '13px', color: '#8c8c8c' }}>
              记录网站的访问日志
            </div>
          </Form.Item>

          <Divider />

          <Title level={4}>API 设置</Title>
          <Form.Item name="api_enabled" label="启用 API" valuePropName="checked">
            <Switch />
            <div style={{ marginTop: '8px', fontSize: '13px', color: '#8c8c8c' }}>
              启用后，系统将提供 RESTful API 接口
            </div>
          </Form.Item>

          <Form.Item
            name="api_prefix"
            label="API 前缀"
            rules={[{ required: true, message: '请输入 API 前缀' }]}
          >
            <Input placeholder="/api" />
            <div style={{ marginTop: '8px', fontSize: '13px', color: '#8c8c8c' }}>
              API 接口的前缀路径，必须以 / 开头
            </div>
          </Form.Item>

          <Divider />

          <Title level={4}>文件上传设置</Title>
          <div style={{ marginBottom: '16px' }}>
            <div style={{ fontSize: '13px', color: '#8c8c8c' }}>
              用逗号分隔的文件扩展名，例如：jpg,png,gif
            </div>
          </div>

          <Form.Item name={['upload_allowed_types', 'image']} label="图片">
            <Input placeholder="gif,jpg,jpeg,png,webp" />
          </Form.Item>

          <Form.Item name={['upload_allowed_types', 'media']} label="媒体">
            <Input placeholder="mp3,mp4,mov" />
          </Form.Item>

          <Form.Item name={['upload_allowed_types', 'document']} label="文档">
            <Input placeholder="pdf,doc,docx" />
          </Form.Item>

          <Form.Item name={['upload_allowed_types', 'other']} label="其他">
            <Input placeholder="zip,rar" />
          </Form.Item>

          <Form.Item>
            <Space>
              <Button type="primary" htmlType="submit" loading={loading}>
                保存更改
              </Button>
              <Button onClick={() => form.resetFields()}>重置</Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  )
}

