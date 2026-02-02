(function () {
  'use strict';

  // 主题切换功能
  const themeToggle = document.getElementById('theme-toggle');
  if (!themeToggle) return;

  // 获取当前主题
  function getCurrentTheme() {
    const html = document.documentElement;
    return html.getAttribute('data-theme') || 'light';
  }

  // 设置主题
  function setTheme(theme) {
    const html = document.documentElement;
    html.setAttribute('data-theme', theme);
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {
      // localStorage 不可用时忽略
    }
    updateThemeIcon(theme);
  }

  // 更新主题图标
  function updateThemeIcon(theme) {
    const icon = themeToggle.querySelector('svg');
    if (!icon) return;

    const path = icon.querySelector('path');
    if (!path) return;

    if (theme === 'dark') {
      // 暗色模式：显示太阳图标
      path.setAttribute('d', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
    } else {
      // 亮色模式：显示月亮图标
      path.setAttribute('d', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z');
    }
  }

  // 切换主题
  function toggleTheme() {
    const current = getCurrentTheme();
    const newTheme = current === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
  }

  // 初始化主题
  function initTheme() {
    const current = getCurrentTheme();
    let saved = 'light';
    try {
      const stored = localStorage.getItem('theme');
      if (stored === 'light' || stored === 'dark') {
        saved = stored;
      }
    } catch (e) {
      // localStorage 不可用时使用默认值
    }

    // 如果当前主题与保存的主题不一致，更新它
    if (current !== saved) {
      setTheme(saved);
    } else {
      // 即使主题一致，也要更新图标
      updateThemeIcon(current);
    }
  }

  // 绑定点击事件
  themeToggle.addEventListener('click', toggleTheme);

  // 初始化（延迟执行，不阻塞页面渲染）
  function initWhenReady() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initTheme);
    } else {
      initTheme();
    }
  }

  // 使用 requestIdleCallback 或 setTimeout 延迟初始化
  if ('requestIdleCallback' in window) {
    requestIdleCallback(initWhenReady, { timeout: 1000 });
  } else {
    setTimeout(initWhenReady, 0);
  }
})();
