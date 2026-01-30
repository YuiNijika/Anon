import React, { useEffect, useRef, useState } from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import {
  Layout as AntLayout,
  Avatar,
  Dropdown,
  Button,
  message,
  Menu,
  theme,
} from 'antd'
import type { MenuProps } from 'antd'
import {
  DashboardOutlined,
  SettingOutlined,
  BarChartOutlined,
  LogoutOutlined,
  UserOutlined,
  AppstoreOutlined,
  BgColorsOutlined,
  EditOutlined,
  FolderOutlined,
  TagsOutlined,
  FileOutlined,
} from '@ant-design/icons'
import { useAuth } from '@/hooks'
import { AdminApi, type BasicSettings } from '@/services/admin'
import { useApiAdmin } from '@/hooks/useApiAdmin'

const { Header, Content, Sider } = AntLayout

const sideMenuItems: MenuProps['items'] = [
  {
    key: '/console',
    icon: React.createElement(DashboardOutlined),
    label: '控制台',
  },
  {
    key: '/statistics',
    icon: React.createElement(BarChartOutlined),
    label: '统计',
  },
  {
    key: '/write',
    icon: React.createElement(EditOutlined),
    label: '撰写',
  },
  {
    key: 'manage',
    icon: React.createElement(FolderOutlined),
    label: '管理',
    children: [
      {
        key: '/manage/posts',
        icon: React.createElement(EditOutlined),
        label: '文章',
      },
      {
        key: '/manage/users',
        icon: React.createElement(UserOutlined),
        label: '用户',
      },
      {
        key: '/manage/categories',
        icon: React.createElement(FolderOutlined),
        label: '分类',
      },
      {
        key: '/manage/tags',
        icon: React.createElement(TagsOutlined),
        label: '标签',
      },
      {
        key: '/manage/files',
        icon: React.createElement(FileOutlined),
        label: '附件',
      },
    ],
  },
  {
    key: 'settings',
    icon: React.createElement(SettingOutlined),
    label: '设置',
    children: [
      {
        key: '/settings/basic',
        icon: React.createElement(AppstoreOutlined),
        label: '常规设置',
      },
      {
        key: '/settings/permission',
        icon: React.createElement(SettingOutlined),
        label: '权限设置',
      },
      {
        key: '/settings/theme',
        icon: React.createElement(BgColorsOutlined),
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
  const apiAdmin = useApiAdmin()
  const { isMobile } = useResponsive()
  const navigate = useNavigate()
  const location = useLocation()
  const [selectedKey, setSelectedKey] = useState('/console')
  const [openKeys, setOpenKeys] = useState<string[]>([])
  const [mounted, setMounted] = useState(false)

  const [siteTitle, setSiteTitle] = useState<string>('管理后台')
  const [siteSubtitle, setSiteSubtitle] = useState<string>('')
  const siteFetchingRef = useRef(false)

  const {
    token: { colorBgContainer, borderRadiusLG, colorBorder, colorText },
  } = theme.useToken()

  useEffect(() => {
    const path = location.pathname
    setSelectedKey(path)

    if (path.startsWith('/settings')) {
      setOpenKeys(['settings'])
    } else if (path.startsWith('/manage')) {
      setOpenKeys(['manage'])
    }
  }, [location.pathname])

  useEffect(() => {
    if (auth.initializing) {
      return
    }
    if (!auth.isAuthenticated) {
      return
    }
    if (siteFetchingRef.current) {
      return
    }

    const fetchSite = async () => {
      siteFetchingRef.current = true
      try {
        const res = await AdminApi.getBasicSettings(apiAdmin)
        const settings = res.data as BasicSettings | undefined
        if (settings?.title) {
          setSiteTitle(settings.title)
        }
        if (settings?.subtitle) {
          setSiteSubtitle(settings.subtitle)
        } else {
          setSiteSubtitle('')
        }
      } catch {
        // ignore
      } finally {
        siteFetchingRef.current = false
      }
    }

    fetchSite()
  }, [apiAdmin, auth.initializing, auth.isAuthenticated])

  useEffect(() => {
    const subtitle = siteSubtitle || 'Powered by AnonEcho'
    document.title = `${siteTitle} - ${subtitle}`
  }, [siteSubtitle, siteTitle])


  useEffect(() => {
    if (auth.initializing) {
      return
    }

    if (!auth.isAuthenticated) {
      navigate('/login', { replace: true })
      return
    }

    setMounted(true)
  }, [auth, navigate])

  const handleMenuClick = ({ key }: { key: string }) => {
    setSelectedKey(key)
    navigate(key)
  }

  const handleOpenChange = (keys: string[]) => {
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
      icon: React.createElement(LogoutOutlined),
      label: '退出登录',
      onClick: handleLogout,
    },
  ]


  if (!mounted) {
    return null
  }

  const topMenuItems: MenuProps['items'] = [
    {
      key: '/console',
      label: '控制台',
    },
    {
      key: '/statistics',
      label: '统计',
    },
    {
      key: '/write',
      label: '撰写',
    },
  ]

  const headerHeight = 64
  const siderWidth = 200

  return (
    <AntLayout style={{ minHeight: '100vh' }}>
      <Header
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          zIndex: 1000,
          display: 'flex',
          alignItems: 'center',
          padding: '0 24px',
          height: headerHeight,
          background: colorBgContainer,
          borderBottom: `1px solid ${colorBorder}`,
        }}
      >
        <div style={{ fontSize: 18, fontWeight: 600, marginRight: 24, color: colorText }}>
          {siteTitle}
        </div>
        <Menu
          mode="horizontal"
          selectedKeys={[selectedKey]}
          items={topMenuItems}
          style={{ flex: 1, minWidth: 0, borderBottom: 'none' }}
          onClick={handleMenuClick}
        />
        <Dropdown menu={{ items: userMenuItems }} placement="bottomRight">
          <Button
            type="text"
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 8,
              height: 'auto',
              padding: '4px 8px',
            }}
          >
            <Avatar src={auth.user?.avatar} size="small" />
            {!isMobile && <span>{auth.user?.display_name || auth.user?.name || 'Unknown'}</span>}
          </Button>
        </Dropdown>
      </Header>
      <AntLayout style={{ marginTop: headerHeight }}>
        <Sider
          width={siderWidth}
          style={{
            padding: '16px 0 16px 0',
            margin: 0,
            position: 'fixed',
            left: 0,
            top: headerHeight,
            bottom: 0,
            background: colorBgContainer,
            overflow: 'auto',
          }}
        >
          <Menu
            mode="inline"
            selectedKeys={[selectedKey]}
            openKeys={openKeys}
            onOpenChange={handleOpenChange}
            style={{ height: '100%', borderRight: 0 }}
            items={sideMenuItems}
            onClick={handleMenuClick}
          />
        </Sider>
        <AntLayout
          style={{
            marginLeft: siderWidth,
            padding: 24,
            minHeight: `calc(100vh - ${headerHeight}px)`,
            overflow: 'auto',
          }}
        >
          <Content
            style={{
              padding: 24,
              margin: 0,
              minHeight: 280,
              background: colorBgContainer,
              borderRadius: borderRadiusLG,
            }}
          >
            <Outlet />
          </Content>
        </AntLayout>
      </AntLayout>
    </AntLayout>
  )
}
