import { useState, useEffect, useRef } from 'react'
import { Form, Input, Button, Switch, App, Space, Divider, Typography } from 'antd'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type PermissionSettings } from '@/services/admin'

const { Title } = Typography

export default function SettingsPermission() {
  const apiAdmin = useApiAdmin()
  const [form] = Form.useForm()
  const app = App.useApp()
  const message = app.message
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)

  useEffect(() => {
    loadSettings()
  }, [apiAdmin])

  const loadSettings = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getPermissionSettings(apiAdmin)
      if (res.data) {
        form.setFieldsValue(res.data)
      }
    } catch (err) {
      message.error('加载权限设置失败')
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleSubmit = async (values: PermissionSettings) => {
    try {
      setLoading(true)

      const submitData: PermissionSettings = {
        allow_register: values.allow_register === true,
        access_log_enabled: values.access_log_enabled !== false,
        api_prefix: values.api_prefix || '/api',
        api_enabled: values.api_enabled === true,
      }

      await AdminApi.updatePermissionSettings(apiAdmin, submitData)
      message.success('权限设置已保存')
      await loadSettings()
    } catch (err) {
      message.error('保存权限设置失败')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Form form={form} layout="vertical" onFinish={handleSubmit} preserve={false}>
        <Title level={4}>用户与访问</Title>
        <Form.Item
          name="allow_register"
          label="允许注册"
          valuePropName="checked"
          extra="允许用户自行注册账户"
        >
          <Switch />
        </Form.Item>

        <Form.Item
          name="access_log_enabled"
          label="启用访问日志"
          valuePropName="checked"
          extra="记录网站的访问日志"
        >
          <Switch />
        </Form.Item>

        <Divider />

        <Title level={4}>API 设置</Title>
        <Form.Item
          name="api_enabled"
          label="启用 API"
          valuePropName="checked"
          extra="启用后，系统将提供 RESTful API 接口"
        >
          <Switch />
        </Form.Item>

        <Form.Item
          name="api_prefix"
          label="API 前缀"
          rules={[{ required: true, message: '请输入 API 前缀' }]}
          extra="API 接口的前缀路径，必须以 / 开头"
        >
          <Input placeholder="/api" />
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
    </div>
  )
}

