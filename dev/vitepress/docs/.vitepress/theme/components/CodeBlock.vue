<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useData } from 'vitepress'

const props = defineProps<{
    code: string
    lang?: string
}>()

const { isDark } = useData()
const highlightedCode = ref('')

onMounted(async () => {
    const lang = props.lang || 'php'
    const code = props.code.trim()

    // 动态导入 shiki
    const shiki = await import('shiki')
    const { codeToHtml } = shiki

    highlightedCode.value = await codeToHtml(code, {
        lang: lang,
        theme: isDark.value ? 'github-dark' : 'github-light',
        defaultColor: false
    })
})
</script>

<template>
    <div v-html="highlightedCode" class="code-block"></div>
</template>

<style scoped>
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