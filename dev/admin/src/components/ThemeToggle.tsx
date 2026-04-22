import { Moon, Sun } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useTheme } from './ThemeProvider'
import { useLaserTransition } from '@/hooks/useLaserTransition'

export function ThemeToggle() {
    const { isDark, toggleTheme } = useTheme()
    const { applyLaserTransition } = useLaserTransition({
        onThemeToggle: toggleTheme,
    })

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        // 添加按钮点击反馈
        e.currentTarget.style.transform = 'scale(0.95)'
        setTimeout(() => {
            e.currentTarget.style.transform = 'scale(1)'
        }, 100)

        applyLaserTransition(e.nativeEvent)
    }

    return (
        <Button
            variant="ghost"
            size="icon"
            onClick={handleClick}
            className="relative overflow-hidden transition-transform duration-100"
            title={isDark ? '切换到亮色主题' : '切换到暗色主题'}
        >
            <Sun className="h-5 w-5 rotate-0 scale-100 transition-all duration-300 dark:-rotate-90 dark:scale-0" />
            <Moon className="absolute h-5 w-5 rotate-90 scale-0 transition-all duration-300 dark:rotate-0 dark:scale-100" />
            <span className="sr-only">切换主题</span>
        </Button>
    )
}
