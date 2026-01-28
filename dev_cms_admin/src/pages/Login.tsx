import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Card, Form, Input, Button, Checkbox, message, Space, theme } from 'antd'
import { UserOutlined, LockOutlined, SafetyOutlined } from '@ant-design/icons'
import { useAuth, useCaptcha } from '@/hooks'

export default function Login() {
  const auth = useAuth()
  const captcha = useCaptcha()
  const navigate = useNavigate()
  const [form] = Form.useForm()
  const { token } = theme.useToken()

  useEffect(() => {
    if (auth.isAuthenticated) {
      navigate('/console', { replace: true })
    }
  }, [auth.isAuthenticated, navigate])


  const handleLogin = async (values: any) => {
    try {
      await auth.login(values)
      message.success('登录成功')
      navigate('/console', { replace: true })
    } catch (err) {
      message.error(err instanceof Error ? err.message : '登录失败')
      if (captcha.enabled) {
        await captcha.refresh()
      }
    }
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px',
        backgroundColor: token.colorBgLayout,
      }}
    >
      <Card
        style={{
          width: '100%',
          maxWidth: 400,
          boxShadow: token.boxShadowSecondary,
        }}
        title={
          <div style={{ textAlign: 'center', fontSize: '24px', fontWeight: 600, color: token.colorPrimary }}>
            AnonEcho
          </div>
        }
      >
        <Form
          form={form}
          onFinish={handleLogin}
          layout="vertical"
          size="large"
          autoComplete="off"
        >
          <Form.Item
            name="username"
            rules={[{ required: true, message: '请输入用户名' }]}
          >
            <Input prefix={<UserOutlined />} placeholder="用户名" />
          </Form.Item>
          <Form.Item
            name="password"
            rules={[{ required: true, message: '请输入密码' }]}
          >
            <Input.Password prefix={<LockOutlined />} placeholder="密码" />
          </Form.Item>
          {captcha.loading ? (
            <div style={{ textAlign: 'center', marginBottom: '24px' }}>加载验证码配置...</div>
          ) : captcha.enabled ? (
            <Form.Item name="captcha" rules={[{ required: true, message: '请输入验证码' }]}>
              <Space.Compact style={{ width: '100%' }}>
                <Input
                  prefix={<SafetyOutlined />}
                  placeholder="验证码"
                  style={{ flex: 1 }}
                />
                {captcha.image ? (
                  <img
                    src={captcha.image}
                    onClick={captcha.refresh}
                    alt="验证码"
                    style={{
                      height: '32px',
                      cursor: 'pointer',
                      border: `1px solid ${token.colorBorder}`,
                      borderRadius: '6px',
                    }}
                  />
                ) : (
                  <div style={{ width: '100px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', border: `1px solid ${token.colorBorder}`, borderRadius: '6px' }}>
                    加载中...
                  </div>
                )}
              </Space.Compact>
            </Form.Item>
          ) : null}
          <Form.Item name="rememberMe" valuePropName="checked">
            <Checkbox>记住我</Checkbox>
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" block loading={auth.loading}>
              登录
            </Button>
          </Form.Item>
        </Form>
      </Card>
    </div>
  )
}

