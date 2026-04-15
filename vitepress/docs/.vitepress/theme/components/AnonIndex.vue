<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { VPButton } from 'vitepress/theme'

const isVisible = ref(false)
const activeTab = ref('API 路由')
const highlightedCode = ref('')

// Tab 切换功能
const switchTab = async (tabName: string) => {
    activeTab.value = tabName
    const file = codeFiles.find(f => f.name === tabName)
    if (!file) return

    const shiki = await import('shiki')
    const { codeToHtml } = shiki

    highlightedCode.value = await codeToHtml(file.code.trim(), {
        lang: file.language,
        theme: 'github-dark',
        defaultColor: false
    })
}

onMounted(async () => {
    // 初始化高亮
    await switchTab('API 路由')

    setTimeout(() => {
        isVisible.value = true
    }, 100)
})

// 核心特性
const features = [
    {
        icon: '⚡',
        title: '易上手',
        description: '最适合新手小白初学者的 PHP 框架',
        color: 'from-yellow-500 to-amber-500'
    },
    {
        icon: '🔄',
        title: '双模式架构',
        description: 'API 与 CMS 灵活切换或并存，一套代码满足多种场景',
        color: 'from-blue-500 to-cyan-500'
    },
    {
        icon: '🏗️',
        title: 'RESTful',
        description: '路由-控制器-服务层分离，业务逻辑清晰可复用',
        color: 'from-purple-500 to-pink-500'
    },
    {
        icon: '🎨',
        title: '主题系统',
        description: '类 Typecho 模板机制，Vite 热更新，开发效率高',
        color: 'from-orange-500 to-red-500'
    },
    {
        icon: '🔌',
        title: '插件生态',
        description: '钩子、中间件、Widget、短代码，四大扩展机制',
        color: 'from-green-500 to-emerald-500'
    },
    {
        icon: '🔒',
        title: '安全防护',
        description: 'CSRF/XSS 防护、Token 验证、权限管理，安全可靠',
        color: 'from-indigo-500 to-violet-500'
    }
]

// 技术栈标签
const techStack = [
    'PHP 8',
    'Vite',
    'Vue 3',
    'React 19',
    'RESTful API'
]

// 徽章数据
const badges = [
    { icon: '✨', text: 'API & CMS' },
    { icon: '🚀', text: 'Modern PHP' }
]

// Hero 数据
const hero = {
    title: {
        gradient: 'Anon ',
        plain: 'Framework'
    },
    description: `
        简洁优雅的 PHP 开发框架, 一套代码，双重能力。<br />
        This is an API & CMS development framework for PHP.
    `,
    buttons: [
        { text: '快速开始 →', href: '/guide/quick-start', theme: 'brand' },
        { text: 'GitHub', href: 'https://github.com/YuiNijika/Anon', theme: 'alt' }
    ]
}

// 代码窗口数据
const codeFiles = [
    {
        name: 'API 路由',
        language: 'php',
        code: `
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
// 快速创建 API 路由
Anon::route('/api/users', function(){
    try {
        Anon_Http_Response::success([
            'id' => 114514,
            'name' => 'YuiNijika',
            'url' => 'https://github.com/YuiNijika'
        ], '获取用户数据成功');
    } catch (Exception $e) {
        Anon_Http_Response::handleException($e, '获取用户数据发生错误');
    }
});
`,
    },
    {
        name: '插件扩展',
        language: 'php',
        code: `
<?php 
if (!defined('ANON_ALLOWED_ACCESS')) exit;
class Anon_Plugin_HelloWorld extends Anon_Plugin_Base 
{
    public function init() {
        Anon::route('/hello', function () {
            $greeting = $this->options()->get('greeting', 'Hello, World!', false, null);
            $mode = Anon_System_Plugin::isApiMode() ? 'API' : 'CMS';
            Anon::success([
                'message' => $greeting,
                'plugin' => 'HelloWorld',
                'mode' => $mode
            ], "Hello World from Plugin ({$mode} Mode)");
        }, [
            'header' => true,
            'requireLogin' => false,
            'method' => ['GET'],
            'token' => false,
            'cache' => ['enabled' => true, 'time' => 3600],
        ]);
    }
}
        `,
    },
    {
        name: '数据库操作',
        language: 'php',
        code: `
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
$db = Anon_Database::getInstance();
// 查询所有用户
$users = $db->db('users')->get();
// 查询单个用户
$user = $db->db('users')->where('id', '=', 1)->first();
// 插入数据
$userId = $db->db('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);
// 更新数据
$db->db('users')
    ->where('id', '=', 1)
    ->update(['name' => 'John Doe']);
// 删除数据
$db->db('users')->where('id', '=', 1)->delete();
`,
    }
]

// 特性区块数据
const featuresSection = {
    title: 'What is Anon?',
    description: `
        参考现代前端设计方案、以最直观快速的方式构建项目。<br />
        Refer to modern front-end design patterns to build your project quickly and intuitively.
    `
}
</script>

<template>
    <div class="anon-home" :class="{ visible: isVisible }">
        <section class="hero">
            <div class="hero-container">
                <div class="hero-grid">
                    <div class="hero-content">
                        <div class="badge-group">
                            <span v-for="(badge, index) in badges" :key="index" class="badge">
                                {{ badge.icon }} {{ badge.text }}
                            </span>
                        </div>

                        <h1 class="hero-title">
                            <span class="title-gradient">{{ hero.title.gradient }}</span>
                            <span class="title-plain">{{ hero.title.plain }}</span>
                        </h1>

                        <p class="hero-desc" v-html="hero.description"></p>

                        <div class="tech-tags">
                            <span v-for="(tech, index) in techStack" :key="index" class="tech-tag">
                                {{ tech }}
                            </span>
                        </div>

                        <div class="hero-buttons">
                            <VPButton v-for="(btn, index) in hero.buttons" :key="index" tag="a" :text="btn.text"
                                :href="btn.href" :theme="btn.theme" size="medium" />
                        </div>
                    </div>

                    <div class="hero-visual">
                        <div class="code-window">
                            <div class="code-header">
                                <div class="header-left">
                                    <div class="code-dots">
                                        <span class="dot red"></span>
                                        <span class="dot yellow"></span>
                                        <span class="dot green"></span>
                                    </div>
                                    <span class="code-title">Anon Framework</span>
                                </div>
                                <div class="tabs-header">
                                    <button v-for="file in codeFiles" :key="file.name" class="tab-button"
                                        :class="{ active: activeTab === file.name }" @click="switchTab(file.name)">
                                        {{ file.name }}
                                    </button>
                                </div>
                            </div>
                            <div class="code-content">
                                <div v-html="highlightedCode" class="code-block"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="features-container">
                <div class="section-header">
                    <h2 class="section-title">{{ featuresSection.title }}</h2>
                    <p class="section-desc" v-html="featuresSection.description"></p>
                </div>

                <div class="features-grid">
                    <div v-for="(feature, index) in features" :key="index" class="feature-card">
                        <div class="feature-icon" :class="`bg-gradient-${feature.color}`">
                            {{ feature.icon }}
                        </div>
                        <h3 class="feature-title">{{ feature.title }}</h3>
                        <p class="feature-desc">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>

<style scoped>
.anon-home {
    min-height: 100vh;
    background: var(--vp-c-bg);
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}

.anon-home.visible {
    opacity: 1;
    transform: translateY(0);
}

.hero {
    padding: 120px 24px 80px;
    position: relative;
    overflow: hidden;
}

.hero-container {
    max-width: 1200px;
    margin: 0 auto;
}

.hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.badge-group {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 16px;
    background: var(--vp-c-brand-soft);
    border: 1px solid var(--vp-c-brand);
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    color: var(--vp-c-brand);
}

.hero-title {
    font-size: clamp(48px, 6vw, 72px);
    font-weight: 800;
    line-height: 1.1;
    margin: 0 0 20px;
    letter-spacing: -0.02em;
}

.title-gradient {
    background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: #f59e0b;
}

.title-plain {
    color: var(--vp-c-text-1);
}

.hero-desc {
    font-size: 20px;
    line-height: 1.6;
    color: var(--vp-c-text-2);
    margin: 0 0 32px;
}

.tech-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 40px;
}

.tech-tag {
    padding: 6px 12px;
    background: var(--vp-c-bg-soft);
    border: 1px solid var(--vp-c-divider);
    border-radius: 6px;
    font-size: 13px;
    color: var(--vp-c-text-2);
}

.hero-buttons {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.hero-buttons :deep(.VPButton) {
    white-space: nowrap;
}

.hero-visual {
    position: relative;
}

.code-window {
    background: #1e1e1e;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid #333;
    width: 600px;
    max-height: 450px;
    display: flex;
    flex-direction: column;
}

.code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #2d2d2d;
    border-bottom: 1px solid #333;
    gap: 12px;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.tabs-header {
    display: flex;
    gap: 0;
    margin-left: auto;
    flex-shrink: 0;
}

.tab-button {
    padding: 6px 12px;
    background: transparent;
    border: 1px solid #333;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    color: #999;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.tab-button:hover {
    color: #ccc;
    border-color: #555;
    background: #3d3d3d;
}

.tab-button.active {
    color: #fff;
    border-color: #58a6ff;
    background: #58a6ff;
}

.code-dots {
    display: flex;
    gap: 6px;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.dot.red {
    background: #ff5f56;
}

.dot.yellow {
    background: #ffbd2e;
}

.dot.green {
    background: #27c93f;
}

.code-title {
    font-size: 13px;
    color: #999;
}

.code-content {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.code-block {
    padding: 16px;
    overflow: auto;
    background: #1e1e1e;
    height: 100%;
}

.code-block :deep(pre) {
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    font-size: 13px !important;
    line-height: 1.5 !important;
}

.code-block :deep(code) {
    background: transparent !important;
    font-family: 'Fira Code', 'Consolas', 'Monaco', monospace !important;
}

.features {
    padding: 0px 24px 80px;
}

.features-container {
    max-width: 1200px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-title {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 12px;
    color: var(--vp-c-text-1);
    border-top: 0px solid var(--vp-c-divider);
}

.section-desc {
    font-size: 18px;
    color: var(--vp-c-text-2);
    margin: 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
}

.feature-card {
    padding: 32px;
    background: var(--vp-c-bg);
    border: 1px solid var(--vp-c-divider);
    border-radius: 16px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--gradient-from), var(--gradient-to));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
    border-color: var(--vp-c-brand);
}

.feature-card:hover::before {
    opacity: 1;
}

.feature-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 28px;
    margin-bottom: 20px;
    background: var(--vp-c-brand-soft);
}

.feature-title {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 12px;
    color: var(--vp-c-text-1);
}

.feature-desc {
    font-size: 15px;
    line-height: 1.6;
    color: var(--vp-c-text-2);
    margin: 0;
}


@media (min-width: 1440px) {

    .hero-container,
    .features-container,
    .cta-container {
        max-width: 1400px;
    }

    .hero-grid {
        gap: 80px;
    }

    .features-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) and (max-width: 1439px) {
    .features-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 969px) and (max-width: 1023px) {
    .hero-grid {
        gap: 40px;
    }

    .code-window {
        width: 100%;
    }

    .features-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .hero-title {
        font-size: 48px;
    }

    .hero-desc {
        font-size: 18px;
    }
}

@media (min-width: 769px) and (max-width: 968px) {
    .hero {
        padding: 100px 32px 70px;
    }

    .hero-grid {
        grid-template-columns: 1fr;
        gap: 48px;
    }

    .hero-content {
        text-align: center;
    }

    .badge-group {
        justify-content: center;
    }

    .tech-tags {
        justify-content: center;
    }

    .hero-buttons {
        justify-content: center;
    }

    .code-window {
        width: 100%;
        max-width: 650px;
        margin: 0 auto;
    }

    .code-header {
        padding: 12px 14px;
    }

    .tab-button {
        padding: 5px 10px;
        font-size: 11px;
    }

    .features-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .hero-title {
        font-size: 44px;
    }

    .hero-desc {
        font-size: 18px;
    }

    .section-title {
        font-size: 32px;
    }

    .section-desc {
        font-size: 17px;
    }
}

@media (min-width: 641px) and (max-width: 768px) {
    .hero {
        padding: 80px 24px 60px;
    }

    .hero-grid {
        grid-template-columns: 1fr;
        gap: 40px;
    }

    .hero-content {
        margin-left: -24px;
        text-align: center;
    }

    .badge-group {
        justify-content: center;
    }

    .tech-tags {
        justify-content: center;
    }

    .hero-buttons {
        justify-content: center;
    }

    .code-window {
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
    }

    .code-header {
        padding: 10px 12px;
        gap: 8px;
    }

    .header-left {
        gap: 10px;
    }

    .code-dots {
        gap: 5px;
    }

    .dot {
        width: 11px;
        height: 11px;
    }

    .code-title {
        font-size: 12px;
    }

    .tabs-header {
        gap: 6px;
    }

    .tab-button {
        padding: 5px 10px;
        font-size: 11px;
        border-radius: 5px;
    }

    .code-block {
        padding: 14px;
    }

    .code-block :deep(pre) {
        font-size: 12.5px !important;
        line-height: 1.45 !important;
    }

    .hero-title {
        font-size: 42px;
    }

    .hero-desc {
        font-size: 17px;
    }

    .features-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .section-title {
        font-size: 28px;
    }

    .section-desc {
        font-size: 16px;
    }
}

@media (max-width: 640px) {
    .hero {
        padding: 50px 12px 40px;
    }

    .hero-container {
        padding: 0;
    }

    .hero-grid {
        grid-template-columns: 1fr;
        gap: 28px;
    }

    .hero-content {
        margin-left: -24px;
        text-align: center;
    }

    .badge-group {
        justify-content: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .badge {
        font-size: 11px;
        padding: 4px 10px;
    }

    .hero-title {
        font-size: 32px;
        margin-bottom: 12px;
        line-height: 1.2;
    }

    .hero-desc {
        font-size: 14px;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .tech-tags {
        justify-content: center;
        gap: 6px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .tech-tag {
        font-size: 11px;
        padding: 4px 8px;
    }

    .hero-buttons {
        justify-content: center;
        gap: 12px;
    }

    .hero-buttons :deep(.VPButton) {
        font-size: 13px;
        padding: 8px 20px;
        white-space: nowrap;
    }

    .code-window {
        width: 100%;
        max-width: 100%;
        margin: 0;
    }

    .code-header {
        padding: 8px 10px;
        gap: 6px;
        flex-direction: column;
        align-items: flex-start;
    }

    .header-left {
        gap: 8px;
        width: 100%;
    }

    .code-dots {
        gap: 4px;
    }

    .dot {
        width: 9px;
        height: 9px;
    }

    .code-title {
        font-size: 10px;
    }

    .tabs-header {
        width: 100%;
        margin-left: 0;
        gap: 5px;
        overflow-x: auto;
        padding-bottom: 3px;
        -webkit-overflow-scrolling: touch;
    }

    .tabs-header::-webkit-scrollbar {
        height: 0;
    }

    .tab-button {
        padding: 3px 7px;
        font-size: 9px;
        border-radius: 3px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .code-content {
        max-height: 320px;
    }

    .code-block {
        padding: 10px;
    }

    .code-block :deep(pre) {
        font-size: 10.5px !important;
        line-height: 1.35 !important;
    }

    .features {
        padding: 40px 12px;
    }

    .features-container {
        padding: 0;
    }

    .section-header {
        margin-bottom: 32px;
    }

    .section-title {
        font-size: 22px;
        margin-bottom: 8px;
    }

    .section-desc {
        font-size: 14px;
    }

    .features-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .feature-card {
        padding: 20px;
    }

    .feature-icon {
        width: 44px;
        height: 44px;
        font-size: 22px;
        margin-bottom: 14px;
    }

    .feature-title {
        font-size: 17px;
        margin-bottom: 8px;
    }

    .feature-desc {
        font-size: 13px;
        line-height: 1.5;
    }
}
</style>
