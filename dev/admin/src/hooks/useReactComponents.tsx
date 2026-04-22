import { useEffect, useRef } from 'react'
import { createRoot } from 'react-dom/client'

// React 组件注册表
const componentRegistry = new Map<string, React.ComponentType<any>>()

/**
 * 注册 React 组件
 * @param name 组件名称
 * @param component React 组件
 */
export function registerReactComponent(name: string, component: React.ComponentType<any>) {
  componentRegistry.set(name, component)
}

/**
 * 获取已注册的组件
 * @param name 组件名称
 * @returns React 组件或 undefined
 */
export function getRegisteredComponent(name: string): React.ComponentType<any> | undefined {
  return componentRegistry.get(name)
}

/**
 * 自动挂载页面上的所有 React 组件
 * 扫描带有 .anon-react-component 类的元素并渲染对应的 React 组件
 */
export function mountReactComponents() {
  const elements = document.querySelectorAll('.anon-react-component')
  
  elements.forEach((element) => {
    const componentName = element.getAttribute('data-component')
    const propsJson = element.getAttribute('data-props')
    
    if (!componentName) return
    
    const Component = componentRegistry.get(componentName)
    if (!Component) {
      console.warn(`React component "${componentName}" is not registered`)
      return
    }

    let props = {}
    if (propsJson) {
      try {
        props = JSON.parse(propsJson)
      } catch (e) {
        console.error(`Failed to parse props for component "${componentName}":`, e)
      }
    }

    // 检查是否已经挂载
    const rootKey = `__react_root_${element.id}`
    if ((element as any)[rootKey]) {
      return // 已经挂载，跳过
    }

    // 创建 React Root 并挂载
    const root = createRoot(element)
    root.render(<Component {...props} />)
    
    // 保存 root 引用以便后续清理
    ;(element as any)[rootKey] = root
  })
}

/**
 * React Hook：在组件挂载后自动扫描并渲染 React 组件
 * 适用于页面切换或动态内容加载后重新挂载组件
 */
export function useReactComponentMounter(dependencies: any[] = []) {
  const mountedRef = useRef(false)

  useEffect(() => {
    if (!mountedRef.current) {
      // 延迟执行，确保 DOM 已更新
      setTimeout(() => {
        mountReactComponents()
      }, 0)
      mountedRef.current = true
    }
  }, dependencies)

  // 提供手动触发方法
  const remount = () => {
    mountReactComponents()
  }

  return { remount }
}
