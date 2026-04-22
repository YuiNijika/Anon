import { File, FileText, Image, Video, Music, Archive, Code, Database, Settings, Globe } from 'lucide-react'

interface FileIconProps {
  mimeType: string
  extension: string
  className?: string
}

const fileTypeIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  // 图片类型
  'image/': Image,
  'image/jpeg': Image,
  'image/png': Image,
  'image/gif': Image,
  'image/webp': Image,
  'image/svg+xml': Image,
  'image/bmp': Image,
  'image/tiff': Image,
  'image/avif': Image,
  'image/vnd.microsoft.icon': Image,
  
  // 视频类型
  'video/': Video,
  'video/mp4': Video,
  'video/quicktime': Video,
  'video/x-msvideo': Video,
  'video/x-ms-wmv': Video,
  'video/x-flv': Video,
  'video/webm': Video,
  'video/ogg': Video,
  
  // 音频类型
  'audio/': Music,
  'audio/mpeg': Music,
  'audio/wav': Music,
  'audio/ogg': Music,
  'audio/mp4': Music,
  'audio/aac': Music,
  'audio/flac': Music,
  'audio/x-ms-wma': Music,
  
  // 文档类型
  'application/pdf': FileText,
  'application/msword': FileText,
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': FileText,
  'application/vnd.ms-excel': FileText,
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': FileText,
  'application/vnd.ms-powerpoint': FileText,
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': FileText,
  'text/plain': FileText,
  
  // 压缩文件
  'application/zip': Archive,
  'application/x-rar-compressed': Archive,
  'application/x-7z-compressed': Archive,
  'application/gzip': Archive,
  
  // 可执行文件
  'application/vnd.microsoft.portable-executable': Settings,
  'application/x-msdownload': Settings,
  'application/x-apple-diskimage': Settings,
  
  // 代码文件
  'application/json': Code,
  'text/css': Code,
  'application/javascript': Code,
  'text/html': Code,
  'application/xml': Code,
  
  // 数据库文件
  'application/x-sqlite3': Database,
  

}

const extensionIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  // 可执行文件扩展名
  'exe': Settings,
  'msi': Settings,
  'bat': Settings,
  'cmd': Settings,
  'sh': Settings,
  'app': Settings,
  'dmg': Settings,
  
  // 代码文件扩展名
  'js': Code,
  'ts': Code,
  'jsx': Code,
  'tsx': Code,
  'json': Code,
  'css': Code,
  'scss': Code,
  'sass': Code,
  'less': Code,
  'html': Globe,
  'htm': Globe,
  'xml': Code,
  'php': Code,
  'py': Code,
  'java': Code,
  'cpp': Code,
  'c': Code,
  'cs': Code,
  'go': Code,
  'rs': Code,
  'rb': Code,
  
  // 数据库文件
  'sql': Database,
  'sqlite': Database,
  'db': Database,
  'mdb': Database,
  
  // 配置文件
  'ini': Settings,
  'cfg': Settings,
  'conf': Settings,
  'config': Settings,
  'yaml': Settings,
  'yml': Settings,
  'toml': Settings,
  'env': Settings,
}

export function FileIcon({ mimeType, extension, className }: FileIconProps) {
  // 首先根据扩展名查找图标
  const extLower = extension.toLowerCase()
  if (extensionIcons[extLower]) {
    const IconComponent = extensionIcons[extLower]
    return <IconComponent className={className} />
  }
  
  // 然后根据 MIME 类型查找图标
  if (fileTypeIcons[mimeType]) {
    const IconComponent = fileTypeIcons[mimeType]
    return <IconComponent className={className} />
  }
  
  // 检查 MIME 类型前缀
  for (const [prefix, IconComponent] of Object.entries(fileTypeIcons)) {
    if (mimeType.startsWith(prefix) && prefix.endsWith('/')) {
      return <IconComponent className={className} />
    }
  }
  
  // 默认文件图标
  return <File className={className} />
}