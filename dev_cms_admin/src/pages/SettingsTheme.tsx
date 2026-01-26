import { useState, useEffect, useRef } from 'react'
import { Card, Form, Input, Button, Select, Switch, InputNumber, ColorPicker, message, Space } from 'antd'
import { useApiAdmin, useTheme } from '@/hooks'
import { AdminApi, type ThemeOptionSchema } from '@/services/admin'
import { Typography } from 'antd'

const { TextArea } = Input
const { Title } = Typography

export default function SettingsTheme() {
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)
  const [schema, setSchema] = useState<Record<string, ThemeOptionSchema>>({})
  const [initialValues, setInitialValues] = useState<Record<string, any>>({})
  const fetchingRef = useRef(false)

  useEffect(() => {
    if (fetchingRef.current) return

    const loadSettings = async () => {
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

    loadSettings()
  }, [apiAdmin, form])

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

  return (
    <div>
      <Title level={2} style={{ marginBottom: '24px', color: isDark ? '#1890ff' : '#1890ff' }}>
        主题设置
      </Title>

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
    </div>
  )
}

