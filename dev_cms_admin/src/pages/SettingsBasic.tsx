import { useState, useEffect, useRef } from 'react'
import { Form, Input, Button, Switch, App, Space, Divider, Typography, Select } from 'antd'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type BasicSettings } from '@/services/admin'

const { TextArea } = Input
const { Title } = Typography

export default function SettingsBasic() {
  const apiAdmin = useApiAdmin()
  const [form] = Form.useForm()
  const app = App.useApp()
  const message = app.message
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)

  useEffect(() => {
    loadSettings()
  }, [apiAdmin])

  // 将逗号分隔的字符串转换为数组
  const stringToArray = (str: string | undefined): string[] => {
    if (!str) return []
    return str.split(',').map((k: string) => k.trim()).filter((k: string) => k)
  }

  // 将数组转换为逗号分隔的字符串
  const arrayToString = (arr: string[] | string | undefined): string => {
    if (Array.isArray(arr)) {
      return arr.filter((k: string) => k && k.trim()).join(',')
    }
    return arr || ''
  }

  const loadSettings = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getBasicSettings(apiAdmin)
      if (res.data) {
        const uploadTypes = res.data.upload_allowed_types || {}

        form.setFieldsValue({
          ...res.data,
          keywords: stringToArray(res.data.keywords),
          upload_allowed_types: {
            image: stringToArray(uploadTypes.image),
            media: stringToArray(uploadTypes.media),
            document: stringToArray(uploadTypes.document),
            other: stringToArray(uploadTypes.other),
          },
        })
      }
    } catch (err) {
      message.error('加载常规设置失败')
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleSubmit = async (values: BasicSettings) => {
    try {
      setLoading(true)

      // 直接使用 onFinish 的 values，它应该包含所有字段值
      // 确保布尔值字段存在，即使为 false 也要明确发送
      // 将数组转换为逗号分隔的字符串
      const uploadTypes = values.upload_allowed_types || {}

      const submitData: BasicSettings = {
        title: values.title || '',
        subtitle: values.subtitle || 'Powered by AnonEcho',
        description: values.description || '',
        keywords: arrayToString(values.keywords),
        allow_register: values.allow_register === true, // 明确转换为布尔值
        api_enabled: values.api_enabled === true, // 明确转换为布尔值
        access_log_enabled: values.access_log_enabled !== false, // 默认为 true
        api_prefix: values.api_prefix || '/api',
        upload_allowed_types: {
          image: arrayToString(uploadTypes.image),
          media: arrayToString(uploadTypes.media),
          document: arrayToString(uploadTypes.document),
          other: arrayToString(uploadTypes.other),
        },
      }

      // 调试信息
      console.log('Form values from onFinish:', values)
      console.log('Submit data:', JSON.stringify(submitData, null, 2))

      await AdminApi.updateBasicSettings(apiAdmin, submitData)
      message.success('常规设置已保存')
      // 保存成功后重新加载数据，确保表单显示最新值
      await loadSettings()
    } catch (err) {
      message.error('保存常规设置失败')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Form form={form} layout="vertical" onFinish={handleSubmit} preserve={false}>
        <Title level={4}>站点信息</Title>
        <Form.Item
          name="title"
          label="站点标题"
          rules={[{ required: true, message: '请输入站点标题' }]}
        >
          <Input placeholder="站点名称" />
        </Form.Item>

        <Form.Item
          name="subtitle"
          label="站点副标题"
          extra="如果为空则不显示"
        >
          <Input placeholder="Powered by AnonEcho" />
        </Form.Item>

        <Form.Item name="description" label="站点描述">
          <TextArea rows={3} placeholder="用简洁的文字描述您的站点" />
        </Form.Item>

        <Form.Item
          name="keywords"
          label="关键词"
          extra="输入关键词后按回车添加，点击标签上的 × 删除"
        >
          <Select
            mode="tags"
            placeholder="输入关键词后按回车"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
          />
        </Form.Item>

        <Divider />

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

        <Divider />

        <Title level={4}>文件上传设置</Title>
        <div style={{ marginBottom: '16px' }}>
          <div style={{ fontSize: '13px', color: '#8c8c8c' }}>
            输入文件扩展名后按回车添加，点击标签上的 × 删除，也可以从下拉列表中选择常见格式
          </div>
        </div>

        <Form.Item
          name={['upload_allowed_types', 'image']}
          label="图片"
          extra="常见的图片格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择图片格式，如：jpg, png"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'gif', label: 'gif' },
              { value: 'jpg', label: 'jpg' },
              { value: 'jpeg', label: 'jpeg' },
              { value: 'png', label: 'png' },
              { value: 'webp', label: 'webp' },
              { value: 'svg', label: 'svg' },
              { value: 'bmp', label: 'bmp' },
              { value: 'ico', label: 'ico' },
              { value: 'tiff', label: 'tiff' },
              { value: 'avif', label: 'avif' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'media']}
          label="媒体"
          extra="常见的音频和视频格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择媒体格式，如：mp3, mp4"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'mp3', label: 'mp3' },
              { value: 'mp4', label: 'mp4' },
              { value: 'mov', label: 'mov' },
              { value: 'wmv', label: 'wmv' },
              { value: 'wma', label: 'wma' },
              { value: 'rmvb', label: 'rmvb' },
              { value: 'rm', label: 'rm' },
              { value: 'avi', label: 'avi' },
              { value: 'flv', label: 'flv' },
              { value: 'ogg', label: 'ogg' },
              { value: 'oga', label: 'oga' },
              { value: 'ogv', label: 'ogv' },
              { value: 'mkv', label: 'mkv' },
              { value: 'webm', label: 'webm' },
              { value: 'wav', label: 'wav' },
              { value: 'aac', label: 'aac' },
              { value: 'm4a', label: 'm4a' },
              { value: '3gp', label: '3gp' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'document']}
          label="文档"
          extra="常见的文档和表格格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择文档格式，如：pdf, doc"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'pdf', label: 'pdf' },
              { value: 'doc', label: 'doc' },
              { value: 'docx', label: 'docx' },
              { value: 'xls', label: 'xls' },
              { value: 'xlsx', label: 'xlsx' },
              { value: 'ppt', label: 'ppt' },
              { value: 'pptx', label: 'pptx' },
              { value: 'txt', label: 'txt' },
              { value: 'rtf', label: 'rtf' },
              { value: 'odt', label: 'odt' },
              { value: 'ods', label: 'ods' },
              { value: 'odp', label: 'odp' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'other']}
          label="其他"
          extra="其他文件格式，如压缩包、安装包等"
        >
          <Select
            mode="tags"
            placeholder="输入或选择其他格式，如：zip, rar"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'zip', label: 'zip' },
              { value: 'rar', label: 'rar' },
              { value: '7z', label: '7z' },
              { value: 'tar', label: 'tar' },
              { value: 'gz', label: 'gz' },
              { value: 'bz2', label: 'bz2' },
              { value: 'exe', label: 'exe' },
              { value: 'dmg', label: 'dmg' },
              { value: 'iso', label: 'iso' },
              { value: 'apk', label: 'apk' },
              { value: 'ipa', label: 'ipa' },
            ]}
          />
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

