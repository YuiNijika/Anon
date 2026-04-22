<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useData } from 'vitepress'

export interface CodeFile {
    name: string
    language: string
    code: string
    icon?: string
}

const props = defineProps<{
    files: CodeFile[]
    defaultTab?: string
}>()

const { isDark } = useData()
const activeTab = ref(props.defaultTab || props.files[0]?.name || '')
const highlightedCode = ref('')

// 获取当前激活的文件
const activeFile = computed(() => 
    props.files.find(f => f.name === activeTab.value) || props.files[0]
)

// 高亮代码
onMounted(async () => {
    const shiki = await import('shiki')
    const { codeToHtml } = shiki
    
    highlightedCode.value = await codeToHtml(activeFile.value.code.trim(), {
        lang: activeFile.value.language,
        theme: isDark.value ? 'github-dark' : 'github-light',
        defaultColor: false
    })
})

// 切换 tab
const switchTab = async (tabName: string) => {
    activeTab.value = tabName
    const file = props.files.find(f => f.name === tabName)
    if (!file) return
    
    const shiki = await import('shiki')
    const { codeToHtml } = shiki
    
    highlightedCode.value = await codeToHtml(file.code.trim(), {
        lang: file.language,
        theme: isDark.value ? 'github-dark' : 'github-light',
        defaultColor: false
    })
}

// 监听暗黑模式切换
onMounted(async () => {
    const shiki = await import('shiki')
    const { codeToHtml } = shiki
    
    const reHighlight = async () => {
        highlightedCode.value = await codeToHtml(activeFile.value.code.trim(), {
            lang: activeFile.value.language,
            theme: isDark.value ? 'github-dark' : 'github-light',
            defaultColor: false
        })
    }
    
    // 监听主题变化
    const observer = new MutationObserver(() => {
        reHighlight()
    })
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    })
})
</script>

<template>
    <div class="code-tabs-container">
        <div class="tabs-header">
            <button
                v-for="file in files"
                :key="file.name"
                class="tab-button"
                :class="{ active: activeTab === file.name }"
                @click="switchTab(file.name)"
            >
                <span v-if="file.icon" class="tab-icon">{{ file.icon }}</span>
                <span class="tab-name">{{ file.name }}</span>
            </button>
        </div>
        
        <div class="code-content">
            <div v-html="highlightedCode" class="code-block"></div>
        </div>
    </div>
</template>

<style scoped>
.code-tabs-container {
    margin: 1.5em 0;
    border-radius: 12px;
    overflow: hidden;
    background: var(--vp-c-bg-soft);
    border: 1px solid var(--vp-c-divider);
}

.tabs-header {
    display: flex;
    gap: 0;
    padding: 8px 8px 0;
    background: var(--vp-c-bg-soft);
    border-bottom: 1px solid var(--vp-c-divider);
}

.tab-button {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 13px;
    color: var(--vp-c-text-2);
    transition: all 0.2s ease;
    border-radius: 6px 6px 0 0;
}

.tab-button:hover {
    color: var(--vp-c-text-1);
    background: var(--vp-c-bg-mute);
}

.tab-button.active {
    color: var(--vp-c-brand);
    border-bottom-color: var(--vp-c-brand);
    background: var(--vp-c-bg);
}

.tab-icon {
    font-size: 14px;
}

.tab-name {
    font-weight: 500;
}

.code-content {
    padding: 16px;
    overflow-x: auto;
    background: var(--vp-c-bg);
}

.code-block {
    margin: 0;
    padding: 0;
    width: 100%;
}

:deep(pre) {
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
}

:deep(code) {
    background: transparent !important;
}
</style>
