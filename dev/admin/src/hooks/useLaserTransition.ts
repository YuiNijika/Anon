import { useCallback, useRef } from 'react'

interface UseLaserTransitionOptions {
    onThemeToggle: () => void
}

export function useLaserTransition({ onThemeToggle }: UseLaserTransitionOptions) {
    const isTransitioning = useRef(false)

    const applyLaserTransition = useCallback(
        (event: MouseEvent) => {
            if (isTransitioning.current) return

            isTransitioning.current = true
            const x = event.clientX
            const y = event.clientY
            const endRadius = Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y)
            )

            // 使用 requestAnimationFrame 确保动画流畅
            requestAnimationFrame(() => {
                // 创建激光扫描动画层
                const laserLayer = document.createElement('div')
                laserLayer.className = 'laser-transition-layer'

                // 预先设置所有样式，避免多次重排
                const styles = `
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          pointer-events: none;
          z-index: 9999;
          background: radial-gradient(circle at ${x}px ${y}px, 
            rgba(136, 132, 216, 0.6) 0%, 
            rgba(136, 132, 216, 0.3) 40%,
            rgba(136, 132, 216, 0.1) 70%,
            transparent 100%);
          clip-path: circle(0px at ${x}px ${y}px);
          opacity: 1;
          will-change: clip-path;
          transform: translateZ(0);
          backface-visibility: hidden;
        `
                laserLayer.style.cssText = styles
                document.body.appendChild(laserLayer)

                // 强制重绘
                laserLayer.offsetHeight

                // 使用 Web Animations API 获得更好的性能
                const animation = laserLayer.animate(
                    [
                        { clipPath: `circle(0px at ${x}px ${y}px)` },
                        { clipPath: `circle(${endRadius * 1.2}px at ${x}px ${y}px)` }
                    ],
                    {
                        duration: 450,
                        easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                        fill: 'forwards'
                    }
                )

                // 在动画进行到 50% 时切换主题
                setTimeout(() => {
                    onThemeToggle()
                }, 225)

                // 动画结束后清理
                animation.onfinish = () => {
                    // 快速淡出效果
                    laserLayer.style.transition = 'opacity 100ms ease-out'
                    laserLayer.style.opacity = '0'

                    setTimeout(() => {
                        laserLayer.remove()
                        isTransitioning.current = false
                    }, 100)
                }
            })
        },
        [onThemeToggle]
    )

    return { applyLaserTransition, isTransitioning: isTransitioning.current }
}
