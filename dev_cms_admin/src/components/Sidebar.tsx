import { useMemo } from 'react'
import { Menu, Button } from 'antd'
import { CloseOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'

interface SidebarProps {
  selectedKey: string
  openKeys: string[]
  onMenuClick: (info: { key: string }) => void
  onOpenChange: (keys: string[]) => void
  menuItems: MenuProps['items']
  onClose?: () => void
}

export function Sidebar({
  selectedKey,
  openKeys,
  onMenuClick,
  onOpenChange,
  menuItems,
  onClose
}: SidebarProps) {
  // 确保 openKeys 是数组且去重
  const normalizedOpenKeys = useMemo(() => {
    return Array.isArray(openKeys) ? [...new Set(openKeys)] : []
  }, [openKeys])

  const handleMenuClick = (info: { key: string }) => {
    // 只处理非子菜单项的点击
    if (!info.key.startsWith('/')) {
      return
    }
    onMenuClick(info)
  }

  const handleOpenChange = (keys: string[]) => {
    // 确保 keys 是数组且去重
    const uniqueKeys = [...new Set(keys)]
    onOpenChange(uniqueKeys)
  }

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        minHeight: '100%',
        overflow: 'hidden',
        backgroundColor: 'transparent',
      }}
    >
      {/* 标题栏 */}
      <div
        style={{
          padding: '16px',
          textAlign: 'center',
          borderBottom: '1px solid rgba(255,255,255,0.1)',
          flexShrink: 0,
          position: 'relative',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        {onClose && (
          <Button
            type="text"
            icon={<CloseOutlined />}
            onClick={onClose}
            style={{
              position: 'absolute',
              left: 8,
              color: '#ffffff',
              fontSize: '16px',
              width: 32,
              height: 32,
              padding: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          />
        )}
        <h2
          style={{
            margin: 0,
            fontSize: '18px',
            fontWeight: 600,
            color: '#ffffff',
            whiteSpace: 'nowrap',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            flex: 1,
            textAlign: 'center',
          }}
        >
          AnonEcho
        </h2>
      </div>

      {/* 菜单区域 */}
      <div
        style={{
          flex: 1,
          overflowY: 'auto',
          overflowX: 'hidden',
          WebkitOverflowScrolling: 'touch',
        }}
      >
        <Menu
          mode="inline"
          selectedKeys={[selectedKey]}
          openKeys={normalizedOpenKeys}
          onOpenChange={handleOpenChange}
          items={menuItems}
          onClick={handleMenuClick}
          theme="dark"
          style={{
            borderRight: 0,
            backgroundColor: 'transparent',
            height: '100%',
          }}
          inlineIndent={16}
          getPopupContainer={(node) => node.parentElement || document.body}
          triggerSubMenuAction="click"
          subMenuOpenDelay={0}
          subMenuCloseDelay={0}
        />
      </div>
    </div>
  )
}
