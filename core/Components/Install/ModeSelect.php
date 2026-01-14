<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导 - 选择模式</title>
    <link rel="stylesheet" href="/anon/static/install/css">
    <script src="/anon/static/vue"></script>
</head>
<body>
    <div id="app" class="install-container">
        <div class="login-header">
            <h1>系统安装向导</h1>
            <p>选择安装模式</p>
        </div>
        
        <div v-if="error" class="error">{{ error }}</div>
        <div v-if="success" class="success">{{ success }}</div>
        
        <form @submit.prevent="handleSubmit">
            <div class="form-group">
                <label>安装模式</label>
                <select v-model="form.app_mode" required :disabled="loading">
                    <option value="api">API 模式</option>
                    <option value="cms">CMS 模式</option>
                </select>
                <div class="requirements">API 模式：纯 API 接口，不加载主题系统<br>CMS 模式：内容管理系统，支持主题和页面</div>
            </div>

            <button type="submit" :disabled="loading" class="btn">
                {{ loading ? '处理中...' : '下一步' }}
            </button>
        </form>
    </div>

    <script src="/anon/static/install/js"></script>
    <script>
        const { createApp } = Vue;
        createApp({
            setup: InstallModeSelect.setup
        }).mount('#app');
    </script>
</body>
</html>
