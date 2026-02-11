import { useEffect, useRef, useState } from 'react'
import { Outlet, useNavigate, useLocation, NavLink } from 'react-router-dom'
import { toast } from 'sonner'
import { LogOut, Sun, Moon, Menu, ChevronDown, Github } from 'lucide-react'
import { iconMap } from '@/icons/navIcons'
import { useAuth } from '@/hooks'
import { AdminApi, type BasicSettings, type NavbarItem } from '@/services/admin'
import { useApiAdmin } from '@/hooks/useApiAdmin'
import { useTheme } from '@/components/ThemeProvider'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'

interface SideMenuItem {
  key: string
  label: string
  icon?: string
  children?: SideMenuItem[]
}

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

function SidebarItem({
  item,
  location,
  onNavigate,
}: {
  item: SideMenuItem
  location: { pathname: string; search: string }
  onNavigate: () => void
}) {
  const currentPath = location.pathname + location.search

  if (item.children?.length) {
    const isActiveRecursively = (items: SideMenuItem[]): boolean => {
      return items.some((child) => {
        const isChildActive = child.key.includes('?')
          ? currentPath === child.key
          : location.pathname.startsWith(child.key)
        if (isChildActive) return true
        if (child.children) return isActiveRecursively(child.children)
        return false
      })
    }
    const isOpen = isActiveRecursively(item.children)
    const Icon = item.icon ? iconMap[item.icon] : null

    return (
      <Collapsible key={item.key} defaultOpen={isOpen}>
        <CollapsibleTrigger className="group flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground">
          {Icon && <Icon className="h-4 w-4 shrink-0" />}
          <span className="flex-1 text-left">{item.label}</span>
          <ChevronDown className="h-4 w-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" />
        </CollapsibleTrigger>
        <CollapsibleContent className="pl-4 pt-1">
          {item.children.map((child) => (
            <SidebarItem
              key={child.key}
              item={child}
              location={location}
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
      isActive={item.key.includes('?') ? currentPath === item.key : location.pathname === item.key}
      onNavigate={onNavigate}
    />
  )
}

function SidebarNav({
  items,
  location,
  onNavigate,
  className,
}: {
  items: SideMenuItem[]
  location: { pathname: string; search: string }
  onNavigate: () => void
  className?: string
}) {
  return (
    <nav className={cn('flex flex-col gap-1 p-4', className)}>
      {items.map((item) => (
        <SidebarItem
          key={item.key}
          item={item}
          location={location}
          onNavigate={onNavigate}
        />
      ))}
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
  const [sideMenuItems, setSideMenuItems] = useState<SideMenuItem[]>([])
  const [headerItems, setHeaderItems] = useState<NavbarItem[]>([])
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
        if (res.data?.header?.length) {
          setHeaderItems(res.data.header)
        }
      })
      .catch(() => { })
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

    // 检查用户组权限
    if (!location.pathname.startsWith('/error') && auth.user && auth.user.group !== 'admin' && auth.user.group !== 'editor') {
      navigate('/error', { replace: true })
      return
    }

    setMounted(true)
  }, [auth, navigate, location])

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
          {isMobile && sideMenuItems.length > 0 ? (
            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="flex w-[280px] flex-col p-0">
                <div className="flex-1 overflow-y-auto pt-6">
                  <SidebarNav
                    items={sideMenuItems}
                    location={location}
                    onNavigate={() => setSheetOpen(false)}
                    className="px-4"
                  />
                </div>
                <div className="border-t p-4">
                  <Popover>
                    <PopoverTrigger asChild>
                      <button
                        type="button"
                        className="flex w-full cursor-pointer items-center gap-3 rounded-md p-1 text-left outline-none hover:bg-accent/50"
                      >
                        <Avatar className="h-9 w-9">
                          <AvatarImage src={auth.user?.avatar} />
                          <AvatarFallback className="text-sm">
                            {(auth.user?.display_name || auth.user?.name || 'U').slice(0, 1)}
                          </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium text-foreground">
                            {auth.user?.display_name || auth.user?.name || '用户'}
                          </p>
                          <p className="truncate text-xs text-muted-foreground">{auth.user?.email || ''}</p>
                        </div>
                      </button>
                    </PopoverTrigger>
                    <PopoverContent side="top" align="start" className="w-[var(--radix-popover-trigger-width)]">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="w-full justify-start gap-2 text-muted-foreground"
                        onClick={() => {
                          setSheetOpen(false)
                          handleLogout()
                        }}
                      >
                        <LogOut className="h-4 w-4" />
                        退出登录
                      </Button>
                    </PopoverContent>
                  </Popover>
                </div>
              </SheetContent>
            </Sheet>
          ) : null}
          <span className="text-lg font-semibold text-foreground">{siteTitle}</span>
          {!isMobile && headerItems.length > 0 ? (
            <nav className="flex items-center gap-1">
              {headerItems.map((item) => (
                <NavLink
                  key={item.key}
                  to={item.key}
                  className={({ isActive }) =>
                    cn(
                      'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                      isActive
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    )
                  }
                >
                  {item.label}
                </NavLink>
              ))}
            </nav>
          ) : null}
          <div className="flex-1" />
          <div className="flex items-center">
            <Button variant="ghost" size="icon" onClick={toggleTheme} title={isDark ? '切换到浅色' : '切换到深色'}>
              {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon">
                  <Avatar className="h-5 w-5">
                    <AvatarImage src={auth.user?.avatar} alt={auth.user?.name} />
                    <AvatarFallback>{(auth.user?.display_name || auth.user?.name || 'U').slice(0, 1)}</AvatarFallback>
                  </Avatar>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent className="w-56" align="end" forceMount>
                <DropdownMenuLabel className="font-normal">
                  <div className="flex flex-col space-y-1">
                    <p className="text-sm font-medium leading-none">{auth.user?.display_name || auth.user?.name || '用户'}</p>
                    <p className="text-xs leading-none text-muted-foreground">
                      {auth.user?.email}
                    </p>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={handleLogout}>
                  <LogOut className="mr-2 h-4 w-4" />
                  <span>退出登录</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <div className="mx-1 h-4 w-px shrink-0 bg-border" />
            <Button variant="ghost" size="sm" asChild title="前往 GitHub">
              <a href="https://github.com/YuiNijika/Anon" target="_blank" rel="noreferrer" className="flex items-center gap-2">
                <Github className="h-5 w-5" />
                {!isMobile && <span className="text-sm">GitHub</span>}
              </a>
            </Button>
          </div>
        </div>
      </header>

      <div className="flex" style={{ marginTop: HEADER_HEIGHT }}>
        {!isMobile && sideMenuItems.length > 0 && (
          <aside
            className="fixed left-0 top-14 bottom-0 flex w-[220px] flex-col border-r bg-background"
            style={{ width: SIDER_WIDTH }}
          >
            <div className="flex-1 overflow-y-auto">
              <SidebarNav items={sideMenuItems} location={location} onNavigate={() => { }} />
            </div>
            <div className="border-t p-4">
              <Popover>
                <PopoverTrigger asChild>
                  <button
                    type="button"
                    className="flex w-full cursor-pointer items-center gap-3 rounded-md p-1 text-left outline-none hover:bg-accent/50"
                  >
                    <Avatar className="h-9 w-9">
                      <AvatarImage src={auth.user?.avatar} />
                      <AvatarFallback className="text-sm">
                        {(auth.user?.display_name || auth.user?.name || 'U').slice(0, 1)}
                      </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-foreground">
                        {auth.user?.display_name || auth.user?.name || '用户'}
                      </p>
                      <p className="truncate text-xs text-muted-foreground">{auth.user?.email || ''}</p>
                    </div>
                  </button>
                </PopoverTrigger>
                <PopoverContent side="top" align="start" className="w-[var(--radix-popover-trigger-width)]">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start gap-2 text-muted-foreground"
                    onClick={handleLogout}
                  >
                    <LogOut className="h-4 w-4" />
                    退出登录
                  </Button>
                </PopoverContent>
              </Popover>
            </div>
          </aside>
        )}
        <main
          className="min-h-[calc(100vh-64px)] flex-1 overflow-auto p-6"
          style={!isMobile && sideMenuItems.length > 0 ? { marginLeft: SIDER_WIDTH } : undefined}
        >
          <Outlet />
        </main>
      </div>
    </div>
  )
}
