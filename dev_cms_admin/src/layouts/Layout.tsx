import { useEffect, useState } from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import {
  Layout as AntLayout,
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
  EditOutlined,
  FolderOutlined,
  TagsOutlined,
  FileOutlined,
} from '@ant-design/icons'
import { useAuth, useTheme } from '@/hooks'
import { Sidebar } from '@/components/Sidebar'

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
    key: '/write',
    icon: <EditOutlined />,
    label: '撰写',
  },
  {
    key: 'manage',
    icon: <FolderOutlined />,
    label: '管理',
    children: [
      {
        key: '/manage/categories',
        icon: <FolderOutlined />,
        label: '分类',
      },
      {
        key: '/manage/tags',
        icon: <TagsOutlined />,
        label: '标签',
      },
      {
        key: '/manage/files',
        icon: <FileOutlined />,
        label: '附件',
      },
      {
        key: '/manage/posts',
        icon: <EditOutlined />,
        label: '文章',
      },
    ],
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

// 路由标题映射
const routeTitleMap: Record<string, string> = {
  '/console': '控制台',
  '/statistics': '统计',
  '/write': '撰写',
  '/manage/categories': '管理分类',
  '/manage/tags': '管理标签',
  '/manage/files': '管理附件',
  '/manage/posts': '管理文章',
  '/settings/basic': '常规设置',
  '/settings/theme': '主题设置',
}

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

  const [pageTitle, setPageTitle] = useState('控制台')

  useEffect(() => {
    const path = location.pathname
    setSelectedKey(path)

    // 根据路径设置页面标题
    const title = routeTitleMap[path] || '控制台'
    setPageTitle(title)

    if (path.startsWith('/settings')) {
      setOpenKeys(['settings'])
    } else if (path.startsWith('/manage')) {
      setOpenKeys(['manage'])
    }
  }, [location.pathname])

  useEffect(() => {
    if (isMobile) {
      setCollapsed(true)
    }
  }, [isMobile])

  useEffect(() => {
    // 等待初始化完成
    if (auth.initializing) {
      return
    }

    // 初始化完成后，如果未登录则跳转到登录页
    if (!auth.isAuthenticated) {
      navigate('/login', { replace: true })
      return
    }

    // 已登录，显示页面
    setMounted(true)
  }, [auth, navigate])

  const handleMenuClick = ({ key }: { key: string }) => {
    setSelectedKey(key)
    navigate(key)
    setMobileMenuOpen(false)
  }

  const handleOpenChange = (keys: string[]) => {
    // 确保 keys 是数组且去重，避免重复触发
    const uniqueKeys = Array.isArray(keys) ? [...new Set(keys)] : []
    setOpenKeys(uniqueKeys)
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
  const drawerBgColor = isDark ? '#001529' : '#1890ff'

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
          overflow: 'hidden',
          backgroundColor: siderBgColor,
        }}
      >
        <Sidebar
          selectedKey={selectedKey}
          openKeys={openKeys}
          onMenuClick={handleMenuClick}
          onOpenChange={handleOpenChange}
          menuItems={menuItems}
        />
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
          <div style={{ display: 'flex', alignItems: 'center', flex: 1 }}>
            <Button
              type="text"
              icon={<MenuOutlined />}
              onClick={() => setMobileMenuOpen(true)}
              style={{
                display: collapsed ? 'flex' : 'none',
                alignItems: 'center',
                marginRight: collapsed ? 16 : 0,
              }}
            />
            <h1
              style={{
                margin: 0,
                fontSize: '20px',
                fontWeight: 600,
                color: isDark ? '#ffffff' : '#000000',
                lineHeight: '64px',
              }}
            >
              {pageTitle}
            </h1>
          </div>
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
        placement="left"
        onClose={() => setMobileMenuOpen(false)}
        open={mobileMenuOpen}
        size={isMobile ? '75%' : 240}
        closable={false}
        maskClosable={true}
        styles={{
          body: {
            padding: 0,
            backgroundColor: drawerBgColor,
            height: '100%',
            overflow: 'hidden',
          },
          header: {
            display: 'none',
          },
        }}
        style={{
          backgroundColor: drawerBgColor,
        }}
        zIndex={1000}
      >
        <Sidebar
          selectedKey={selectedKey}
          openKeys={openKeys}
          onMenuClick={handleMenuClick}
          onOpenChange={handleOpenChange}
          menuItems={menuItems}
          onClose={() => setMobileMenuOpen(false)}
        />
      </Drawer>
    </AntLayout>
  )
}
