<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anon Debug Console</title>
    <link rel="stylesheet" href="/anon/static/debug/css">
    <script src="/anon/static/vue"></script>
</head>
<body>
    <div id="app">
        <div class="header">
            <div class="header-left">
                <h1>Anon Debug Console</h1>
                <p>System debugging and monitoring interface</p>
            </div>
            <div class="header-right">
                <span class="user-info" v-if="userInfo">用户: {{ userInfo.name || userInfo.username }}</span>
                <button class="btn-logout" @click="handleLogout">退出</button>
            </div>
        </div>
        
        <div class="container">
            <nav class="sidebar">
                <a 
                    v-for="item in navItems" 
                    :key="item.id"
                    class="nav-item" 
                    :class="{ active: activeSection === item.id }"
                    @click="switchSection(item.id)"
                >
                    {{ item.label }}
                </a>
            </nav>
            
            <main class="content">
                <div v-if="loading" class="loading">加载中...</div>
                <div v-else>
                    <div v-show="activeSection === 'overview'" class="section active">
                        <div class="card">
                            <div class="card-header">系统状态</div>
                            <div class="card-body">
                                <p>调试模式: <span class="status-enabled">已启用</span></p>
                                <p>PHP版本: <?php echo PHP_VERSION; ?></p>
                                <p>内存使用: <?php echo round(memory_get_usage(true) / 1024 / 1024, 2); ?> MB</p>
                                <p>峰值内存: <?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 2); ?> MB</p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">快速操作</div>
                            <div class="card-body">
                                <button class="btn" @click="refreshData">刷新数据</button>
                                <button class="btn btn-danger" @click="clearDebugData">清理调试数据</button>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeSection === 'performance'" class="section">
                        <div class="card">
                            <div class="card-header">性能数据</div>
                            <div class="card-body">
                                <div v-if="sectionLoading" class="loading">加载中...</div>
                                <div v-else>
                                    <pre v-if="sectionData.performance">{{ JSON.stringify(sectionData.performance, null, 2) }}</pre>
                                    <p v-else>暂无数据</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeSection === 'logs'" class="section">
                        <div class="card">
                            <div class="card-header">系统日志</div>
                            <div class="card-body">
                                <div v-if="sectionLoading" class="loading">加载中...</div>
                                <div v-else-if="sectionData.logs && sectionData.logs.length > 0">
                                    <pre v-for="(log, index) in sectionData.logs" :key="index">{{ JSON.stringify(log, null, 2) }}</pre>
                                </div>
                                <div v-else>
                                    <p>暂无日志数据</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeSection === 'errors'" class="section">
                        <div class="card">
                            <div class="card-header">错误日志</div>
                            <div class="card-body">
                                <div v-if="sectionLoading" class="loading">加载中...</div>
                                <div v-else-if="sectionData.errors && sectionData.errors.length > 0">
                                    <pre v-for="(error, index) in sectionData.errors" :key="index" class="error">{{ JSON.stringify(error, null, 2) }}</pre>
                                </div>
                                <div v-else>
                                    <p>暂无错误数据</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeSection === 'hooks'" class="section">
                        <div class="card">
                            <div class="card-header">Hook调试信息</div>
                            <div class="card-body">
                                <div v-if="sectionLoading" class="loading">加载中...</div>
                                <div v-else-if="sectionData.hooks">
                                    <pre>{{ JSON.stringify(sectionData.hooks, null, 2) }}</pre>
                                </div>
                                <div v-else>
                                    <p>暂无数据</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeSection === 'tools'" class="section">
                        <div class="card">
                            <div class="card-header">调试工具</div>
                            <div class="card-body">
                                <button class="btn" @click="exportDebugData">导出调试数据</button>
                                <button class="btn btn-danger" @click="clearAllData">清空所有数据</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="/anon/static/debug/js"></script>
    <script>
        const { createApp, ref, reactive, onMounted } = Vue;
        createApp({
            setup: DebugConsole.setup
        }).mount('#app');
    </script>
</body>
</html>

