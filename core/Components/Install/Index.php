<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <link rel="stylesheet" href="/anon/static/install/css">
    <script src="/anon/static/vue"></script>
</head>
<body>
    <div id="app"></div>
    <script src="/anon/static/install/js"></script>
    <script>
        if (typeof InstallApp !== 'undefined' && InstallApp.setup) {
            const { createApp } = Vue;
            createApp({
                setup: InstallApp.setup
            }).mount('#app');
        } else {
            console.error('InstallApp not found');
        }
    </script>
</body>
</html>

