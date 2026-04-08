import { registerReactComponent } from '@/hooks/useReactComponents'
import { MarkdownEditor } from './MarkdownEditor'
import { ImageGallery } from './ImageGallery'
import { AlertBox } from './AlertBox'

/**
 * 注册所有可用的React组件供短代码调用
 */
export function registerAllComponents() {
  registerReactComponent('MarkdownEditor', MarkdownEditor)
  registerReactComponent('ImageGallery', ImageGallery)
  registerReactComponent('AlertBox', AlertBox)
}

export { registerReactComponent }
