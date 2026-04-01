import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  lang: 'zh-CN',
  title: "Anon Framework",
  description: "一个简单快速的 PHP API 开发框架",
  head: [
    [
      'link', { rel: 'icon', href: '/assets/favicon.jpg' } // 站点图标
    ],
  ],
  base: '/Anon/',
  lastUpdated: true,
  outDir: '../../docs',
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
      { text: "安装指南", link: "/guide/installation" },
      { text: "模式对比", link: "/guide/mode-overview" },
      {
        text: "通用功能",
        items: [
          { text: "插件系统", link: "/guide/plugin-system" },
          { text: "中间件扩展", link: "/guide/extension-system" },
          { text: "Widget 组件", link: "/guide/widget-system" },
          { text: "CLI 命令", link: "/guide/cli-system" },
          { text: "钩子系统", link: "/guide/hook-system" },
        ]
      },
      {
        text: "API 模式",
        items: [
          { text: "API 模式概述", link: "/guide/api/overview" },
          { text: "路由系统", link: "/guide/routing" },
          { text: "请求与响应", link: "/guide/api/request-response" },
          { text: "认证与安全", link: "/guide/api/authentication" },
          { text: "API 参考", link: "/api/reference" },
          { text: "API 端点", link: "/api/endpoints" },
        ]
      },
      {
        text: "CMS 模式",
        items: [
          { text: "CMS 模式概述", link: "/guide/cms/overview" },
          { text: "主题系统", link: "/guide/cms/theme-system" },
          { text: "评论功能", link: "/guide/cms/comments" },
          { text: "管理后台", link: "/guide/cms/admin" },
          { text: "高性能附件", link: "/guide/cms/high-performance-attachments" },
        ]
      },
      {
        text: "更多",
        items: [
          { text: "访问日志", link: "/guide/access-log" },
          { text: "配置管理", link: "/guide/configuration-management" },
          { text: "缓存系统", link: "/guide/cache-redis-guide" },
          { text: "调试工具", link: "/guide/api/debugging" },
          { text: "数据库", link: "/guide/api/database" },
          { text: "开发规范", link: "/guide/coding-standards" },
        ]
      },
    ],

    // 侧边栏
    sidebar: {
      '/guide/api/': [
        {
          base: '/guide/api/',
          text: 'API 模式',
          items: [
            { text: 'API 模式概述', link: 'overview' },
            { text: '路由系统', link: 'routing' },
            { text: '请求与响应', link: 'request-response' },
            { text: '用户认证', link: 'authentication' },
            { text: 'Token 策略', link: 'token-strategy' },
            { text: '安全功能', link: 'security' },
            { text: '数据库操作', link: 'database' },
            { text: '大数据处理', link: 'big-data' },
            { text: '调试工具', link: 'debugging' },
            { text: '性能优化', link: 'performance-optimization' },
            { text: '服务端模式 (Swoole)', link: 'server-mode' },
          ]
        }
      ],
      '/guide/cms/': [
        {
          base: '/guide/cms/',
          text: 'CMS 模式',
          items: [
            { text: 'CMS 模式概述', link: 'overview' },
            { text: '路由与页面', link: 'routes' },
            { text: '主题系统与开发', link: 'theme-system' },
            { text: '评论功能', link: 'comments' },
            { text: '管理后台', link: 'admin' },
            { text: '高性能附件系统', link: 'high-performance-attachments' },
          ]
        }
      ],
      '/guide/': [
        {
          base: '/guide/',
          text: '入门',
          items: [
            { text: '快速开始', link: 'quick-start' },
            { text: '安装指南', link: 'installation' },
            { text: '模式对比 API vs CMS', link: 'mode-overview' },
          ]
        },
        {
          text: '通用功能',
          base: '/guide/',
          items: [
            { text: '路由系统', link: 'routing' },
            { text: '附件系统', link: 'attachment-system' },
            { text: '插件系统', link: 'plugin-system' },
            { text: '中间件扩展', link: 'extension-system' },
            { text: 'Widget 组件', link: 'widget-system' },
            { text: 'CLI 命令', link: 'cli-system' },
            { text: '钩子系统', link: 'hook-system' },
          ]
        },
        {
          text: '系统管理',
          base: '/guide/',
          items: [
            { text: '访问日志', link: 'access-log' },
            { text: '配置管理', link: 'configuration-management' },
            { text: '缓存系统', link: 'cache-redis-guide' },
          ]
        },
        {
          text: '开发与工具',
          base: '/guide/',
          items: [
            { text: '数据库', link: 'api/database' },
            { text: '调试工具', link: 'api/debugging' },
            { text: '代码规范', link: 'coding-standards' },
            { text: '工具说明', link: 'tools' },
            { text: '自动化测试', link: 'testing' }
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
