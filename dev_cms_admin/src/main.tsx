import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import { Toaster } from 'sonner'
import App from './App'
import { ThemeProvider } from './components/ThemeProvider'
import { ensureToken } from './hooks/useApi'
import './index.css'

async function initApp() {
  try {
    document.title = '管理后台 - Powered by AnonEcho'
    await ensureToken(true)
  } catch {
    // 静默失败
  }

  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <ThemeProvider>
        <HashRouter>
          <App />
          <Toaster richColors position="top-right" />
        </HashRouter>
      </ThemeProvider>
    </StrictMode>,
  )
}

initApp()
