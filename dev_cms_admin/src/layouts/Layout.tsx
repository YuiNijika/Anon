import { useEffect, useState } from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import {
  Layout as AntLayout,
  Menu,
  Avatar,
  Dropdown,
  Button,
  message,
  Drawer,
  Space,
} from 'antd'
import type { MenuProps } from 'antd'
import {
  DashboardOutlined,
  SettingOutlined,
  BarChartOutlined,
  LogoutOutlined,
  UserOutlined,
  MenuOutlined,
  AppstoreOutlined,
  BgColorsOutlined,
} from '@ant-design/icons'
import { useAuth, useTheme } from '@/hooks'

const { Header, Content, Sider } = AntLayout

const menuItems: MenuProps['items'] = [
  {
    key: '/console',
    icon: <DashboardOutlined />,
    label: '控制台',
  },
  {
    key: '/statistics',
    icon: <BarChartOutlined />,
    label: '统计',
  },
  {
    key: 'settings',
    icon: <SettingOutlined />,
    label: '设置',
    children: [
      {
        key: '/settings/basic',
        icon: <AppstoreOutlined />,
        label: '常规设置',
      },
      {
        key: '/settings/theme',
        icon: <BgColorsOutlined />,
        label: '主题设置',
      },
    ],
  },
]

function useResponsive() {
  const [isMobile, setIsMobile] = useState(() => window.innerWidth < 768)

  useEffect(() => {
    const handleResize = () => {
      setIsMobile(window.innerWidth < 768)
    }

    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  return { isMobile }
}

export default function Layout() {
  const auth = useAuth()
  const { isDark } = useTheme()
  const { isMobile } = useResponsive()
  const navigate = useNavigate()
  const location = useLocation()
  const [selectedKey, setSelectedKey] = useState('/console')
  const [openKeys, setOpenKeys] = useState<string[]>([])
  const [mounted, setMounted] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  useEffect(() => {
    const path = location.pathname
    setSelectedKey(path)

    if (path.startsWith('/settings')) {
      setOpenKeys(['settings'])
    }
  }, [location.pathname])

  useEffect(() => {
    if (isMobile) {
      setCollapsed(true)
    }
  }, [isMobile])

  useEffect(() => {
    if (!auth.isAuthenticated && !auth.hasToken) {
      navigate('/login', { replace: true })
      return
    }

    if (auth.hasToken && !auth.isAuthenticated) {
      auth.checkLogin().then((isValid) => {
        if (!isValid) {
          navigate('/login', { replace: true })
        } else {
          setMounted(true)
        }
      })
    } else if (auth.isAuthenticated) {
      setMounted(true)
    }
  }, [auth, navigate])

  const handleMenuClick = ({ key }: { key: string }) => {
    setSelectedKey(key)
    navigate(key)
    setMobileMenuOpen(false)
  }

  const handleOpenChange = (keys: string[]) => {
    setOpenKeys(keys)
  }

  const handleLogout = async () => {
    try {
      await auth.logout()
      message.success('已退出登录')
      navigate('/login', { replace: true })
    } catch {
      navigate('/login', { replace: true })
    }
  }

  const userMenuItems: MenuProps['items'] = [
    {
      key: 'user',
      label: (
        <div style={{ padding: '4px 0' }}>
          <div style={{ fontWeight: 500 }}>{auth.user?.name || '用户'}</div>
          <div style={{ fontSize: '12px', color: 'rgba(0,0,0,0.45)' }}>UID: {auth.user?.uid}</div>
        </div>
      ),
      disabled: true,
    },
    {
      type: 'divider',
    },
    {
      key: 'logout',
      icon: <LogoutOutlined />,
      label: '退出登录',
      onClick: handleLogout,
    },
  ]

  if (!mounted) {
    return null
  }

  const siderBgColor = isDark ? '#001529' : '#1890ff'
  const layoutBgColor = isDark ? '#141414' : '#f0f2f5'
  const headerBgColor = isDark ? '#1f1f1f' : '#ffffff'
  const headerBorderColor = isDark ? '#303030' : '#e8e8e8'
  const contentBgColor = isDark ? '#1f1f1f' : '#ffffff'
  const titleColor = isDark ? '#ffffff' : '#ffffff'
  const drawerBgColor = isDark ? '#001529' : '#1890ff'

  const menuContent = (
    <Menu
      mode="inline"
      selectedKeys={[selectedKey]}
      openKeys={openKeys}
      onOpenChange={handleOpenChange}
      items={menuItems}
      onClick={handleMenuClick}
      style={{ borderRight: 0, height: '100%', backgroundColor: 'transparent' }}
      theme={isDark ? 'dark' : 'dark'}
    />
  )

  return (
    <AntLayout style={{ minHeight: '100vh', backgroundColor: layoutBgColor }}>
      <Sider
        width={200}
        theme={isDark ? 'dark' : 'light'}
        collapsed={collapsed}
        breakpoint="lg"
        collapsedWidth={0}
        onBreakpoint={(broken) => {
          setCollapsed(broken)
        }}
        style={{
          position: 'fixed',
          left: 0,
          top: 0,
          bottom: 0,
          zIndex: 100,
          overflow: 'auto',
          backgroundColor: siderBgColor,
        }}
      >
        <div
          style={{
            padding: '16px',
            textAlign: 'center',
            borderBottom: '1px solid rgba(255,255,255,0.1)',
          }}
        >
          <h2 style={{ margin: 0, fontSize: '18px', fontWeight: 600, color: titleColor }}>
            CMS 管理后台
          </h2>
        </div>
        {menuContent}
      </Sider>
      <AntLayout
        style={{
          backgroundColor: layoutBgColor,
          marginLeft: collapsed ? 0 : 200,
          transition: 'margin-left 0.2s',
        }}
      >
        <Header
          style={{
            padding: '0 24px',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            position: 'sticky',
            top: 0,
            zIndex: 99,
            height: 64,
            backgroundColor: headerBgColor,
            borderBottom: `1px solid ${headerBorderColor}`,
            boxShadow: '0 2px 8px rgba(0,0,0,0.06)',
          }}
        >
          <Button
            type="text"
            icon={<MenuOutlined />}
            onClick={() => setMobileMenuOpen(true)}
            style={{
              display: collapsed ? 'flex' : 'none',
              alignItems: 'center',
            }}
          />
          <Space size="middle">
            <Dropdown menu={{ items: userMenuItems }} placement="bottomRight">
              <Button
                type="text"
                style={{
                  height: 'auto',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                }}
              >
                <Avatar icon={<UserOutlined />} size="small" />
                {!isMobile && <span>{auth.user?.name || '用户'}</span>}
              </Button>
            </Dropdown>
          </Space>
        </Header>
        <Content
          style={{
            margin: '24px',
            padding: '24px',
            backgroundColor: contentBgColor,
            minHeight: 'calc(100vh - 112px)',
            borderRadius: '4px',
            boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
          }}
        >
          <Outlet />
        </Content>
      </AntLayout>
      <Drawer
        title={
          <div style={{ fontSize: '18px', fontWeight: 600, color: titleColor }}>菜单</div>
        }
        placement="left"
        onClose={() => setMobileMenuOpen(false)}
        open={mobileMenuOpen}
        width={200}
        styles={{
          body: {
            padding: 0,
            backgroundColor: drawerBgColor,
          },
          header: {
            backgroundColor: drawerBgColor,
            borderBottom: '1px solid rgba(255,255,255,0.1)',
          },
        }}
      >
        {menuContent}
      </Drawer>
    </AntLayout>
  )
}
