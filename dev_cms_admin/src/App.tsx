import { useEffect } from 'react'
import { useRoutes } from 'react-router-dom'
import { routes } from './router'
import { registerAllComponents } from '@/components/ReactComponents'

function App() {
  const element = useRoutes(routes)

  // 初始化时注册所有 React 组件
  useEffect(() => {
    registerAllComponents()
  }, [])

  return element
}

export default App
