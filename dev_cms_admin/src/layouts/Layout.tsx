import React, { useEffect, useRef, useState } from 'react'
import { Outlet, useNavigate, useLocation, NavLink } from 'react-router-dom'
import { toast } from 'sonner'
import {
  LayoutDashboard,
  BarChart2,
  Pencil,
  Palette,
  Grid3X3,
  Folder,
  User,
  Tag,
  File,
  Settings,
  LogOut,
  Sun,
  Moon,
  Menu,
} from 'lucide-react'
import { useAuth } from '@/hooks'
import { AdminApi, type BasicSettings, type NavbarItem } from '@/services/admin'
import { useApiAdmin } from '@/hooks/useApiAdmin'
import { useTheme } from '@/components/ThemeProvider'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
  DashboardOutlined: LayoutDashboard,
  BarChartOutlined: BarChart2,
  EditOutlined: Pencil,
  BgColorsOutlined: Palette,
  AppstoreOutlined: Grid3X3,
  FolderOutlined: Folder,
  UserOutlined: User,
  TagsOutlined: Tag,
  FileOutlined: File,
  SettingOutlined: Settings,
}

interface SideMenuItem {
  key: string
  label: string
  icon?: string
  children?: SideMenuItem[]
}

const defaultSideMenuItems: SideMenuItem[] = [
  { key: '/console', label: '控制台', icon: 'DashboardOutlined' },
  { key: '/statistics', label: '统计', icon: 'BarChartOutlined' },
  { key: '/write', label: '撰写', icon: 'EditOutlined' },
  { key: '/themes', label: '主题', icon: 'BgColorsOutlined' },
  { key: '/plugins', label: '插件', icon: 'AppstoreOutlined' },
  {
    key: 'manage',
    label: '管理',
    icon: 'FolderOutlined',
    children: [
      { key: '/manage/posts', label: '文章', icon: 'EditOutlined' },
      { key: '/manage/users', label: '用户', icon: 'UserOutlined' },
      { key: '/manage/categories', label: '分类', icon: 'FolderOutlined' },
      { key: '/manage/tags', label: '标签', icon: 'TagsOutlined' },
      { key: '/manage/files', label: '附件', icon: 'FileOutlined' },
    ],
  },
  {
    key: 'settings',
    label: '设置',
    icon: 'SettingOutlined',
    children: [
      { key: '/settings/basic', label: '常规设置', icon: 'AppstoreOutlined' },
      { key: '/settings/permission', label: '权限设置', icon: 'SettingOutlined' },
    ],
  },
]

function convertNavbarItem(item: NavbarItem): SideMenuItem {
  const menuItem: SideMenuItem = { key: item.key, label: item.label }
  if (item.icon) menuItem.icon = item.icon
  if (item.children?.length) {
    menuItem.children = item.children.map(convertNavbarItem)
  }
  return menuItem
}

function useResponsive() {
  const [isMobile, setIsMobile] = useState(() => typeof window !== 'undefined' && window.innerWidth < 768)
  useEffect(() => {
    const handleResize = () => setIsMobile(window.innerWidth < 768)
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])
  return { isMobile }
}

const HEADER_HEIGHT = 64
const SIDER_WIDTH = 220

function NavItem({
  item,
  isActive,
  onNavigate,
}: {
  item: SideMenuItem
  isActive: boolean
  onNavigate: () => void
}) {
  const Icon = item.icon ? iconMap[item.icon] : null
  return (
    <NavLink
      to={item.key}
      end
      onClick={onNavigate}
      className={cn(
        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
        isActive
          ? 'bg-primary/10 text-primary'
          : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
      )}
    >
      {Icon && <Icon className="h-4 w-4 shrink-0" />}
      {item.label}
    </NavLink>
  )
}

function SidebarNav({
  items,
  pathname,
  onNavigate,
  className,
}: {
  items: SideMenuItem[]
  pathname: string
  onNavigate: () => void
  className?: string
}) {
  return (
    <nav className={cn('flex flex-col gap-1 p-4', className)}>
      {items.map((item) => {
        if (item.children?.length) {
          const isOpen =
            pathname.startsWith('/manage') && item.key === 'manage' ||
            pathname.startsWith('/settings') && item.key === 'settings'
          const Icon = item.icon ? iconMap[item.icon] : null
          return (
            <Collapsible key={item.key} defaultOpen={isOpen}>
              <CollapsibleTrigger className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground">
                {Icon && <Icon className="h-4 w-4 shrink-0" />}
                {item.label}
              </CollapsibleTrigger>
              <CollapsibleContent className="pl-4 pt-1">
                {item.children.map((child) => (
                  <NavItem
                    key={child.key}
                    item={child}
                    isActive={pathname === child.key}
                    onNavigate={onNavigate}
                  />
                ))}
              </CollapsibleContent>
            </Collapsible>
          )
        }
        return (
          <NavItem
            key={item.key}
            item={item}
            isActive={pathname === item.key}
            onNavigate={onNavigate}
          />
        )
      })}
    </nav>
  )
}

export default function Layout() {
  const auth = useAuth()
  const apiAdmin = useApiAdmin()
  const { isDark, toggleTheme } = useTheme()
  const { isMobile } = useResponsive()
  const navigate = useNavigate()
  const location = useLocation()
  const [mounted, setMounted] = useState(false)
  const [siteTitle, setSiteTitle] = useState('管理后台')
  const [siteSubtitle, setSiteSubtitle] = useState('')
  const siteFetchingRef = useRef(false)
  const [sideMenuItems, setSideMenuItems] = useState<SideMenuItem[]>(defaultSideMenuItems)
  const navbarFetchingRef = useRef(false)

  useEffect(() => {
    if (auth.initializing || !auth.isAuthenticated || siteFetchingRef.current) return
    siteFetchingRef.current = true
    AdminApi.getBasicSettings(apiAdmin)
      .then((res) => {
        const settings = res.data as BasicSettings | undefined
        if (settings?.title) setSiteTitle(settings.title)
        if (settings?.subtitle != null) setSiteSubtitle(settings.subtitle)
      })
      .finally(() => {
        siteFetchingRef.current = false
      })
  }, [apiAdmin, auth.initializing, auth.isAuthenticated])

  useEffect(() => {
    const subtitle = siteSubtitle || 'Powered by AnonEcho'
    document.title = `${siteTitle} - ${subtitle}`
  }, [siteSubtitle, siteTitle])

  useEffect(() => {
    if (auth.initializing || !auth.isAuthenticated || navbarFetchingRef.current) return
    navbarFetchingRef.current = true
    AdminApi.getNavbar(apiAdmin)
      .then((res) => {
        if (res.data?.sidebar?.length) {
          setSideMenuItems(res.data.sidebar.map(convertNavbarItem))
        }
      })
      .catch(() => {
        setSideMenuItems(defaultSideMenuItems)
      })
      .finally(() => {
        navbarFetchingRef.current = false
      })
  }, [apiAdmin, auth.initializing, auth.isAuthenticated])

  const [sheetOpen, setSheetOpen] = useState(false)

  useEffect(() => {
    if (auth.initializing) return
    if (!auth.isAuthenticated) {
      navigate('/login', { replace: true })
      return
    }
    setMounted(true)
  }, [auth, navigate])

  const handleLogout = async () => {
    try {
      await auth.logout()
      toast.success('已退出登录')
      navigate('/login', { replace: true })
    } catch {
      navigate('/login', { replace: true })
    }
  }

  if (!mounted) return null

  return (
    <div className="min-h-screen bg-background">
      <header
        className="fixed left-0 right-0 top-0 z-50 flex h-14 items-center border-b bg-background px-4"
        style={{ height: HEADER_HEIGHT }}
      >
        <div className="flex w-full items-center gap-4">
          {isMobile ? (
            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-[280px] p-0">
                <SidebarNav
                  items={sideMenuItems}
                  pathname={location.pathname}
                  onNavigate={() => setSheetOpen(false)}
                  className="pt-6"
                />
              </SheetContent>
            </Sheet>
          ) : null}
          <span className="text-lg font-semibold text-foreground">{siteTitle}</span>
          <div className="flex-1" />
          <Button variant="ghost" size="icon" onClick={toggleTheme} title={isDark ? '切换到浅色' : '切换到深色'}>
            {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="flex items-center gap-2">
                <Avatar className="h-8 w-8">
                  <AvatarImage src={auth.user?.avatar} />
                  <AvatarFallback>
                    {(auth.user?.display_name || auth.user?.name || 'U').slice(0, 1)}
                  </AvatarFallback>
                </Avatar>
                {!isMobile && (
                  <span className="text-sm">{auth.user?.display_name || auth.user?.name || 'Unknown'}</span>
                )}
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={handleLogout}>
                <LogOut className="mr-2 h-4 w-4" />
                退出登录
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </header>

      <div className="flex" style={{ marginTop: HEADER_HEIGHT }}>
        {!isMobile && (
          <aside
            className="fixed left-0 top-14 bottom-0 w-[220px] overflow-y-auto border-r bg-background"
            style={{ width: SIDER_WIDTH }}
          >
            <SidebarNav items={sideMenuItems} pathname={location.pathname} onNavigate={() => { }} />
          </aside>
        )}
        <main
          className="min-h-[calc(100vh-64px)] flex-1 overflow-auto p-6"
          style={!isMobile ? { marginLeft: SIDER_WIDTH } : undefined}
        >
          <Outlet />
        </main>
      </div>
    </div>
  )
}
