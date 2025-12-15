<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anon Debug Console</title>
    <script src="/core/Static/vue.global.prod.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            line-height: 1.4;
        }
        
        .header {
            background: #2d2d30;
            padding: 15px 20px;
            border-bottom: 1px solid #3e3e42;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            color: #569cd6;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header-left p {
            color: #9cdcfe;
            font-size: 14px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            color: #9cdcfe;
            font-size: 14px;
        }
        
        .btn-logout {
            background: #d73a49;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-logout:hover {
            background: #e53e3e;
        }
        
        .container {
            display: flex;
            height: calc(100vh - 80px);
        }
        
        .sidebar {
            width: 250px;
            background: #252526;
            border-right: 1px solid #3e3e42;
            overflow-y: auto;
        }
        
        .nav-item {
            display: block;
            padding: 12px 20px;
            color: #cccccc;
            text-decoration: none;
            border-bottom: 1px solid #3e3e42;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: #2a2d2e;
            color: #ffffff;
        }
        
        .nav-item.active {
            background: #094771;
            color: #ffffff;
        }
        
        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        
        .card {
            background: #2d2d30;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: #383838;
            padding: 12px 16px;
            border-bottom: 1px solid #3e3e42;
            font-weight: bold;
            color: #ffffff;
        }
        
        .card-body {
            padding: 16px;
        }
        
        .btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:hover:not(:disabled) {
            background: #1177bb;
        }
        
        .btn:disabled {
            background: #3e3e42;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-danger {
            background: #d73a49;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #e53e3e;
        }
        
        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 12px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .status-enabled {
            color: #4ec9b0;
        }
        
        .status-disabled {
            color: #f44747;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #9cdcfe;
        }
        
        .error {
            color: #f44747;
            background: #2d1b1b;
            border: 1px solid #5a1e1e;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .success {
            color: #4ec9b0;
            background: #1b2d1b;
            border: 1px solid #1e5a1e;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
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
    
    <script>
        const { createApp, ref, reactive, onMounted } = Vue;
        
        createApp({
            setup() {
                const activeSection = ref('overview');
                const loading = ref(false);
                const sectionLoading = ref(false);
                const userInfo = ref(null);
                const sectionData = reactive({
                    performance: null,
                    logs: null,
                    errors: null,
                    hooks: null
                });
                
                const navItems = [
                    { id: 'overview', label: '系统概览' },
                    { id: 'performance', label: '性能监控' },
                    { id: 'logs', label: '系统日志' },
                    { id: 'errors', label: '错误日志' },
                    { id: 'hooks', label: 'Hook调试' },
                    { id: 'tools', label: '调试工具' }
                ];
                
                const fetchToken = async () => {
                    try {
                        const response = await fetch('/auth/token', {
                            credentials: 'include'
                        });
                        const data = await response.json();
                        if (data.success && data.data && data.data.token) {
                            localStorage.setItem('token', data.data.token);
                            return data.data.token;
                        }
                    } catch (err) {
                        console.debug('获取 Token 失败:', err);
                    }
                    return null;
                };
                
                const checkAuth = async () => {
                    try {
                        let token = localStorage.getItem('token');
                        
                        if (!token) {
                            token = await fetchToken();
                        }
                        
                        const headers = {};
                        if (token) {
                            headers['X-API-Token'] = token;
                        }
                        
                        const response = await fetch('/user/info', {
                            credentials: 'include',
                            headers: headers
                        });
                        const data = await response.json();
                        if (data.success && data.data) {
                            userInfo.value = data.data;
                            if (data.data.token) {
                                localStorage.setItem('token', data.data.token);
                            } else if (!token && data.success) {
                                token = await fetchToken();
                            }
                        } else {
                            window.location.href = '/anon/debug/login';
                        }
                    } catch (err) {
                        console.error('检查登录状态失败:', err);
                        window.location.href = '/anon/debug/login';
                    }
                };
                
                const switchSection = (sectionId) => {
                    activeSection.value = sectionId;
                    if (sectionId !== 'overview' && !sectionData[sectionId]) {
                        loadSectionData(sectionId);
                    }
                };
                
                const loadSectionData = async (section) => {
                    sectionLoading.value = true;
                    try {
                        const headers = {
                            'Content-Type': 'application/json'
                        };
                        
                        const token = localStorage.getItem('token');
                        if (token) {
                            headers['X-API-Token'] = token;
                        }
                        
                        const response = await fetch(`/anon/debug/api/${section}`, {
                            credentials: 'include',
                            headers: headers
                        });
                        const data = await response.json();
                        if (data.success && data.data) {
                            sectionData[section] = data.data;
                        } else {
                            console.error('加载数据失败:', data.message);
                        }
                    } catch (err) {
                        console.error('网络错误:', err);
                    } finally {
                        sectionLoading.value = false;
                    }
                };
                
                const refreshData = () => {
                    const currentSection = activeSection.value;
                    if (currentSection !== 'overview') {
                        sectionData[currentSection] = null;
                        loadSectionData(currentSection);
                    } else {
                        window.location.reload();
                    }
                };
                
                const clearDebugData = async () => {
                    if (!confirm('确定要清理调试数据吗？')) {
                        return;
                    }
                    try {
                        const headers = {
                            'Content-Type': 'application/json'
                        };
                        
                        const token = localStorage.getItem('token');
                        if (token) {
                            headers['X-API-Token'] = token;
                        }
                        
                        const response = await fetch('/anon/debug/api/clear', {
                            method: 'POST',
                            credentials: 'include',
                            headers: headers
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('调试数据已清理');
                            refreshData();
                        } else {
                            alert('清理失败: ' + data.message);
                        }
                    } catch (err) {
                        alert('网络错误: ' + err.message);
                    }
                };
                
                const exportDebugData = () => {
                    window.open('/anon/debug/api/info', '_blank');
                };
                
                const clearAllData = () => {
                    if (confirm('确定要清空所有调试数据吗？此操作不可恢复！')) {
                        clearDebugData();
                    }
                };
                
                const handleLogout = async () => {
                    try {
                        await fetch('/auth/logout', {
                            method: 'POST',
                            credentials: 'include'
                        });
                        localStorage.removeItem('token');
                        window.location.href = '/anon/debug/login';
                    } catch (err) {
                        console.error('退出失败:', err);
                        localStorage.removeItem('token');
                        window.location.href = '/anon/debug/login';
                    }
                };
                
                onMounted(() => {
                    checkAuth();
                });
                
                return {
                    activeSection,
                    loading,
                    sectionLoading,
                    userInfo,
                    sectionData,
                    navItems,
                    switchSection,
                    refreshData,
                    clearDebugData,
                    exportDebugData,
                    clearAllData,
                    handleLogout
                };
            }
        }).mount('#app');
    </script>
</body>
</html>

