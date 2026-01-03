import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  lang: 'zh-CN',
  title: "Anon Framework",
  description: "一个简单快速的 PHP API 开发框架",
  head: [
    [
      'link', { rel: 'icon', href: './sets/favicon.jpg' } // 站点图标
    ],
  ],
  base: '/Anon/',
  lastUpdated: true,
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config

    // Logo
    logo: '/assets/anon.gif',

    // 启用搜索
    search: {
      provider: 'local',
      options: {
        translations: {
          button: {
            buttonText: '搜索',
            buttonAriaLabel: '搜索',
          },
          modal: {
            noResultsText: '没有找到结果',
            resetButtonTitle: '重置搜索',
            footer: {
              selectText: '选择',
              navigateText: '导航',
              closeText: '关闭',
            },
          },
        }
      }
    },

    // 顶部栏
    nav: [
      { text: "快速开始", link: "/guide/quick-start" },
      { text: "API 参考", link: "/api/api-reference" },
      {
        text: "指南",
        items: [
          { text: "配置说明", link: "/guide/configuration" },
          { text: "路由系统", link: "/guide/routing" },
          { text: "数据库操作", link: "/guide/database" },
          { text: "用户认证", link: "/guide/authentication" },
          { text: "安全功能", link: "/guide/security" },
        ]
      },
      {
        text: "高级功能",
        items: [
          { text: "大数据处理", link: "/guide/big-data" },
          { text: "调试工具", link: "/guide/debugging" },
          { text: "现代特性", link: "/guide/modern-features" },
        ]
      },
    ],

    // 侧边栏
    sidebar: {
      '/guide/': [
        {
          base: '/guide/',
          text: '入门指南',
          items: [
            { text: '快速开始', link: 'quick-start' },
            { text: '配置说明', link: 'configuration' },
            { text: '路由系统', link: 'routing' },
            { text: '数据库操作', link: 'database' }
          ]
        },
        {
          text: '核心功能',
          base: '/guide/',
          items: [
            { text: '请求与响应', link: 'request-response' },
            { text: '用户认证', link: 'authentication' },
            { text: 'Token 策略', link: 'token-strategy' },
            { text: '安全功能', link: 'security' }
          ]
        },
        {
          text: '高级功能',
          base: '/guide/',
          items: [
            { text: '大数据处理', link: 'big-data' },
            { text: '调试工具', link: 'debugging' },
            { text: '自定义代码', link: 'custom-code' },
            { text: '现代特性', link: 'modern-features' },
            { text: '高级用法', link: 'advanced' }
          ]
        },
        {
          text: '开发规范',
          base: '/guide/',
          items: [
            { text: '代码规范', link: 'coding-standards' },
            { text: '工具说明', link: 'tools' }
          ]
        }
      ],
      '/api/': [
        {
          base: '/api/',
          text: 'API 参考',
          items: [
            { text: 'API 参考文档', link: 'reference' },
            { text: 'API 端点', link: 'endpoints' }
          ]
        }
      ],
    },

    // 社交链接
    socialLinks: [
      { icon: 'github', link: 'https://github.com/YuiNijika/Anon' }
    ],

    // 页脚
    footer: {
      message: 'Released under the MIT License.',
      copyright: `Copyright © 2024-${new Date().getFullYear()} Anon Framework`
    },

    // 编辑链接
    editLink: {
      pattern: ({ filePath }) => {
        return `https://github.com/YuiNijika/Anon/edit/main/vitepress/docs/${filePath}`
      },
      text: '在 GitHub 上编辑此页面',
    },

    // 翻译
    // 文章翻页
    docFooter: {
      prev: '上一篇',
      next: '下一篇'
    },

    // 外观
    darkModeSwitchLabel: '外观',

    // 当前页面
    outline: {
      label: '当前页面',
    },

    // 返回顶部
    returnToTopLabel: '返回顶部',

    // menu
    sidebarMenuLabel: '菜单',

    // 搜索

    // 404
    notFound: {
      title: '页面未找到',
      quote: 'HTTP 404 - Page Not Found',
      linkText: '返回首页'
    }

  },

  ignoreDeadLinks: true
})