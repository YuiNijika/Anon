import React from 'react'
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
  MessageCircle,
  Link,
} from 'lucide-react'

/** Nav icon key -> Lucide component */
export const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
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
  CommentOutlined: MessageCircle,
  LinkOutlined: Link,
}
