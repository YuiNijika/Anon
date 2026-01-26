import { type ReactNode, useState, useEffect, useLayoutEffect, createContext, useContext } from 'react'
import { ConfigProvider, Button, theme, App as AntdApp } from 'antd'
import { SunOutlined, MoonOutlined } from '@ant-design/icons'
import zhCN from 'antd/locale/zh_CN'
import type { ThemeConfig } from 'antd'

interface ThemeProviderProps {
    children: ReactNode
}

interface ThemeContextType {
    isDark: boolean
    toggleTheme: () => void
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined)

const lightTheme: ThemeConfig = {
    token: {
        colorPrimary: '#1890ff',
    },
    algorithm: theme.defaultAlgorithm,
}

const darkTheme: ThemeConfig = {
    token: {
        colorPrimary: '#1890ff',
    },
    algorithm: theme.darkAlgorithm,
}

export function ThemeProvider({ children }: ThemeProviderProps) {
    const [isDark, setIsDark] = useState(() => {
        const saved = localStorage.getItem('theme')
        if (saved) {
            return saved === 'dark'
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches
    })

    useLayoutEffect(() => {
        localStorage.setItem('theme', isDark ? 'dark' : 'light')

        if (isDark) {
            document.documentElement.style.backgroundColor = '#141414'
            document.body.style.backgroundColor = '#141414'
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.style.backgroundColor = '#f0f2f5'
            document.body.style.backgroundColor = '#f0f2f5'
            document.documentElement.classList.remove('dark')
        }
    }, [isDark])

    const toggleTheme = () => {
        setIsDark(!isDark)
    }

    const currentTheme = isDark ? darkTheme : lightTheme

    return (
        <ThemeContext.Provider value={{ isDark, toggleTheme }}>
            <ConfigProvider locale={zhCN} theme={currentTheme}>
                <AntdApp>
                    {children}
                    <ThemeToggle isDark={isDark} onToggle={toggleTheme} />
                </AntdApp>
            </ConfigProvider>
        </ThemeContext.Provider>
    )
}

export function useTheme() {
    const context = useContext(ThemeContext)
    if (context === undefined) {
        throw new Error('useTheme must be used within ThemeProvider')
    }
    return context
}

function ThemeToggle({ isDark, onToggle }: { isDark: boolean; onToggle: () => void }) {
    return (
        <Button
            type="text"
            icon={isDark ? <SunOutlined /> : <MoonOutlined />}
            onClick={onToggle}
            style={{
                position: 'fixed',
                bottom: '24px',
                right: '24px',
                width: '48px',
                height: '48px',
                borderRadius: '50%',
                boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
                zIndex: 1000,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
            }}
            title={isDark ? '切换到浅色模式' : '切换到深色模式'}
        />
    )
}
