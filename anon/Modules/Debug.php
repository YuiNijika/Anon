<?php

/**
 * 调试系统核心引擎
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Debug
{
    /**
     * @var bool 是否已初始化
     */
    private static $initialized = false;

    /**
     * @var array 调试数据收集器
     */
    private static $collectors = [];

    /**
     * @var string 当前请求ID
     */
    private static $requestId = null;

    /**
     * @var float 请求开始时间
     */
    private static $requestStartTime = null;

    /**
     * @var int 请求开始内存使用
     */
    private static $requestStartMemory = null;

    /**
     * @var array 调试配置
     */
    private static $config = [
        'max_log_size' => 50, // MB
        'max_log_files' => 10,
        'log_levels' => ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL']
    ];

    /**
     * @var array 性能数据
     */
    private static $performance = [];

    /**
     * @var array 错误数据
     */
    private static $errors = [];

    /**
     * @var array 数据库查询记录
     */
    private static $queries = [];

    /**
     * 初始化调试系统
     */
    public static function init()
    {
        if (self::$initialized || !defined('ANON_DEBUG') || !ANON_DEBUG) {
            return;
        }

        self::$initialized = true;
        self::$requestId = uniqid('req_', true);
        self::$requestStartTime = microtime(true);
        self::$requestStartMemory = memory_get_usage(true);

        // 注册错误处理器
        set_error_handler([__CLASS__, 'errorHandler']);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
        register_shutdown_function([__CLASS__, 'shutdownHandler']);

        self::log('DEBUG', 'Debug system initialized', [
            'request_id' => self::$requestId,
            'memory_start' => self::formatBytes(self::$requestStartMemory),
            'time_start' => date('Y-m-d H:i:s', (int)self::$requestStartTime)
        ]);
    }

    /**
     * 记录日志
     */
    public static function log($level, $message, $context = [])
    {
        if (!self::isEnabled() || !in_array($level, self::$config['log_levels'])) {
            return;
        }

        $logData = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request_id' => self::$requestId,
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        // 写入日志文件
        self::writeToFile($logData);

        // 添加到收集器
        self::$collectors['logs'][] = $logData;
    }

    /**
     * 记录性能数据
     */
    public static function performance($name, $startTime = null, $data = [])
    {
        if (!self::isEnabled()) return;

        $endTime = microtime(true);
        $duration = $startTime ? ($endTime - $startTime) * 1000 : 0; // ms

        $perfData = [
            'name' => $name,
            'duration' => $duration,
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => $endTime,
            'data' => $data
        ];

        self::$performance[] = $perfData;
        self::log('DEBUG', "Performance: {$name}", $perfData);
    }

    /**
     * @var array 性能监控开始时间记录
     */
    private static $performanceStartTimes = [];

    /**
     * 开始性能监控
     * @param string $name 监控名称
     */
    public static function startPerformance($name)
    {
        if (!self::isEnabled()) return;
        
        self::$performanceStartTimes[$name] = microtime(true);
        self::log('DEBUG', "Performance monitoring started: {$name}");
    }

    /**
     * 结束性能监控
     * @param string $name 监控名称
     * @param array $data 附加数据
     */
    public static function endPerformance($name, $data = [])
    {
        if (!self::isEnabled()) return;
        
        $startTime = self::$performanceStartTimes[$name] ?? null;
        if ($startTime === null) {
            self::log('WARN', "Performance monitoring end called without start: {$name}");
            return;
        }
        
        // 调用现有的 performance 方法记录数据
        self::performance($name, $startTime, $data);
        
        // 清理开始时间记录
        unset(self::$performanceStartTimes[$name]);
    }

    /**
     * 记录数据库查询
     */
    public static function query($sql, $params = [], $duration = 0)
    {
        if (!self::isEnabled()) return;

        $queryData = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];

        self::$queries[] = $queryData;
        self::log('DEBUG', 'Database Query', $queryData);
    }

    /**
     * 错误处理器
     */
    public static function errorHandler($severity, $message, $file, $line)
    {
        if (!self::isEnabled()) return false;

        $errorData = [
            'type' => 'error',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace()
        ];

        self::$errors[] = $errorData;
        self::log('ERROR', "PHP Error: {$message}", $errorData);

        return false; // 让PHP继续处理错误
    }

    /**
     * 异常处理器
     */
    public static function exceptionHandler($exception)
    {
        if (!self::isEnabled()) return;

        $errorData = [
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => microtime(true)
        ];

        self::$errors[] = $errorData;
        self::log('FATAL', "Uncaught Exception: " . $exception->getMessage(), $errorData);
    }

    /**
     * 关闭处理器
     */
    public static function shutdownHandler()
    {
        if (!self::isEnabled()) return;

        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }

        // 记录请求结束信息
        $endTime = microtime(true);
        $duration = ($endTime - self::$requestStartTime) * 1000;
        $memoryUsed = memory_get_usage(true) - self::$requestStartMemory;

        self::log('INFO', 'Request completed', [
            'duration' => round($duration, 2) . 'ms',
            'memory_used' => self::formatBytes($memoryUsed),
            'peak_memory' => self::formatBytes(memory_get_peak_usage(true)),
            'queries_count' => count(self::$queries),
            'errors_count' => count(self::$errors)
        ]);
    }

    /**
     * 获取调试数据
     */
    public static function getData()
    {
        if (!self::isEnabled()) return [];

        return [
            'request_id' => self::$requestId,
            'start_time' => self::$requestStartTime,
            'current_time' => microtime(true),
            'duration' => (microtime(true) - self::$requestStartTime) * 1000,
            'memory' => [
                'start' => self::$requestStartMemory,
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'used' => memory_get_usage(true) - self::$requestStartMemory
            ],
            'logs' => self::$collectors['logs'] ?? [],
            'performance' => self::$performance,
            'queries' => self::$queries,
            'errors' => self::$errors,
            'system' => [
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s'),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        ];
    }

    /**
     * 清理调试数据
     */
    public static function clear()
    {
        self::$collectors = [];
        self::$performance = [];
        self::$queries = [];
        self::$errors = [];
    }

    /**
     * 检查是否启用调试
     */
    public static function isEnabled()
    {
        return defined('ANON_DEBUG') && ANON_DEBUG === true;
    }

    /**
     * 写入日志文件
     */
    private static function writeToFile($logData)
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/debug_' . date('Y-m-d') . '.log';
        $logLine = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s', (int)$logData['timestamp']),
            $logData['level'],
            $logData['request_id'],
            $logData['message'],
            !empty($logData['context']) ? json_encode($logData['context'], JSON_UNESCAPED_UNICODE) : ''
        );

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        // 检查日志文件大小
        self::rotateLogIfNeeded($logFile);
    }

    /**
     * 日志轮转
     */
    private static function rotateLogIfNeeded($logFile)
    {
        if (!file_exists($logFile)) return;

        $maxSize = self::$config['max_log_size'] * 1024 * 1024; // 转换为字节
        if (filesize($logFile) > $maxSize) {
            $backupFile = $logFile . '.' . time();
            rename($logFile, $backupFile);

            // 清理旧日志文件
            self::cleanOldLogs(dirname($logFile));
        }
    }

    /**
     * 清理旧日志文件
     */
    private static function cleanOldLogs($logDir)
    {
        $files = glob($logDir . '/debug_*.log.*');
        if (count($files) > self::$config['max_log_files']) {
            // 按修改时间排序
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // 删除最旧的文件
            $filesToDelete = array_slice($files, 0, count($files) - self::$config['max_log_files']);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * 格式化字节数
     */
    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 便捷方法
     */
    public static function debug($message, $context = []) { self::log('DEBUG', $message, $context); }
    public static function info($message, $context = []) { self::log('INFO', $message, $context); }
    public static function warn($message, $context = []) { self::log('WARN', $message, $context); }
    public static function error($message, $context = []) { self::log('ERROR', $message, $context); }
    public static function fatal($message, $context = []) { self::log('FATAL', $message, $context); }

    /**
     * 检查debug权限
     * @return bool
     */
    public static function checkPermission()
    {
        return defined('ANON_DEBUG') && ANON_DEBUG === true && self::isEnabled();
    }

    /**
     * 返回403错误
     */
    public static function return403()
    {
        Anon_Common::Header(403);
        echo json_encode([
            'code' => 403,
            'message' => 'Debug mode is disabled or not allowed'
        ]);
        exit;
    }

    /**
     * 调试信息API
     */
    public static function debugInfo()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 获取调试数据
        $debugData = self::getData();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Debug info retrieved successfully',
            'data' => $debugData
        ]);
    }

    /**
     * 性能监控API
     */
    public static function performanceApi()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 获取性能数据
        $performanceData = self::getPerformanceData();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Performance data retrieved successfully',
            'data' => $performanceData
        ]);
    }

    /**
     * 获取性能数据
     */
    public static function getPerformanceData()
    {
        return self::$performance;
    }

    /**
     * 日志API
     */
    public static function logs()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 获取日志数据
        $logs = self::getLogs();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * 获取日志数据
     */
    public static function getLogs()
    {
        return self::$collectors['logs'] ?? [];
    }

    /**
     * 错误日志API
     */
    public static function errors()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 获取错误数据
        $errors = self::getErrors();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Errors retrieved successfully',
            'data' => $errors
        ]);
    }

    /**
     * 获取错误数据
     */
    public static function getErrors()
    {
        return self::$errors;
    }

    /**
     * Hook调试API
     */
    public static function hooks()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 获取Hook数据
        $hooks = self::getHookData();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Hook data retrieved successfully',
            'data' => $hooks
        ]);
    }

    /**
     * 获取Hook数据
     */
    public static function getHookData()
    {
        // 如果Hook类存在，获取其统计数据
        if (class_exists('Anon_Hook')) {
            return Anon_Hook::getStats();
        }
        return [];
    }

    /**
     * 调试工具API
     */
    public static function tools()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        $tools = [
            'system_info' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => php_sapi_name(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'timezone' => date_default_timezone_get(),
                'current_time' => date('Y-m-d H:i:s'),
            ],
            'debug_tools' => [
                'clear_debug_data' => [
                    'name' => '清理调试数据',
                    'description' => '清理所有调试日志、性能数据和错误记录',
                    'endpoint' => '/anon/debug/clear',
                    'method' => 'POST'
                ],
                'export_debug_info' => [
                    'name' => '导出调试信息',
                    'description' => '导出完整的调试信息JSON数据',
                    'endpoint' => '/anon/debug/info',
                    'method' => 'GET'
                ],
                'performance_monitor' => [
                    'name' => '性能监控',
                    'description' => '查看系统性能数据和执行时间统计',
                    'endpoint' => '/anon/debug/performance',
                    'method' => 'GET'
                ],
                'system_logs' => [
                    'name' => '系统日志',
                    'description' => '查看系统运行日志',
                    'endpoint' => '/anon/debug/logs',
                    'method' => 'GET'
                ],
                'error_logs' => [
                    'name' => '错误日志',
                    'description' => '查看系统错误和异常记录',
                    'endpoint' => '/anon/debug/errors',
                    'method' => 'GET'
                ],
                'hook_debug' => [
                    'name' => 'Hook调试',
                    'description' => '查看Hook系统的执行统计',
                    'endpoint' => '/anon/debug/hooks',
                    'method' => 'GET'
                ]
            ],
            'environment' => [
                'debug_enabled' => defined('ANON_DEBUG') && ANON_DEBUG,
                'installed' => defined('ANON_INSTALLED') && ANON_INSTALLED,
                'request_id' => self::$requestId,
                'request_start_time' => self::$requestStartTime,
                'current_memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'loaded_extensions' => get_loaded_extensions(),
            ]
        ];

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Debug tools retrieved successfully',
            'data' => $tools
        ]);
    }

    /**
     * 清理调试数据API
     */
    public static function clearData()
    {
        if (!self::checkPermission()) {
            self::return403();
        }

        // 清理调试数据
        self::clear();

        Anon_Common::Header();
        echo json_encode([
            'code' => 200,
            'message' => 'Debug data cleared successfully'
        ]);
    }

    /**
     * 调试控制台Web界面
     */
    public static function console()
    {
        // 检查 debug 权限
        if (!self::checkPermission()) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('Debug 模式未启用');
        }

        // 检查用户是否登录
        if (!Anon_Check::isLoggedIn()) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('请先登录');
        }

        // 检查用户是否为管理员
        $userId = Anon_RequestHelper::getUserId();
        if (!$userId) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('无法获取用户信息');
        }

        $db = new Anon_Database();
        if (!$db->isUserAdmin($userId)) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('仅管理员可访问 Debug 控制台');
        }

        // 输出调试控制台HTML
        self::renderConsole();
    }

    /**
     * 渲染调试控制台HTML
     */
    private static function renderConsole()
    {
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KonFans Debug Console</title>
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
        }
        
        .header h1 {
            color: #569cd6;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #9cdcfe;
            font-size: 14px;
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
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
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
        
        .btn:hover {
            background: #1177bb;
        }
        
        .btn-danger {
            background: #d73a49;
        }
        
        .btn-danger:hover {
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
    <div class="header">
        <h1>KonFans Debug Console</h1>
        <p>System debugging and monitoring interface</p>
    </div>
    
    <div class="container">
        <nav class="sidebar">
            <a href="#" class="nav-item active" data-section="overview">系统概览</a>
            <a href="#" class="nav-item" data-section="performance">性能监控</a>
            <a href="#" class="nav-item" data-section="logs">系统日志</a>
            <a href="#" class="nav-item" data-section="errors">错误日志</a>
            <a href="#" class="nav-item" data-section="hooks">Hook调试</a>
            <a href="#" class="nav-item" data-section="tools">调试工具</a>
        </nav>
        
        <main class="content">
            <div id="overview" class="section active">
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
                        <button class="btn" onclick="refreshData()">刷新数据</button>
                        <button class="btn btn-danger" onclick="clearDebugData()">清理调试数据</button>
                    </div>
                </div>
            </div>
            
            <div id="performance" class="section">
                <div class="card">
                    <div class="card-header">性能数据</div>
                    <div class="card-body">
                        <div class="loading">加载中...</div>
                    </div>
                </div>
            </div>
            
            <div id="logs" class="section">
                <div class="card">
                    <div class="card-header">系统日志</div>
                    <div class="card-body">
                        <div class="loading">加载中...</div>
                    </div>
                </div>
            </div>
            
            <div id="errors" class="section">
                <div class="card">
                    <div class="card-header">错误日志</div>
                    <div class="card-body">
                        <div class="loading">加载中...</div>
                    </div>
                </div>
            </div>
            
            <div id="hooks" class="section">
                <div class="card">
                    <div class="card-header">Hook调试信息</div>
                    <div class="card-body">
                        <div class="loading">加载中...</div>
                    </div>
                </div>
            </div>
            
            <div id="tools" class="section">
                <div class="card">
                    <div class="card-header">调试工具</div>
                    <div class="card-body">
                        <button class="btn" onclick="exportDebugData()">导出调试数据</button>
                        <button class="btn btn-danger" onclick="clearAllData()">清空所有数据</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // 导航切换
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // 移除所有active类
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
                
                // 添加active类
                this.classList.add('active');
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
                
                // 加载对应数据
                loadSectionData(sectionId);
            });
        });
        
        // 加载区块数据
        function loadSectionData(section) {
            const sectionElement = document.getElementById(section);
            const cardBody = sectionElement.querySelector('.card-body');
            
            if (section === 'overview') return; // 概览页面不需要异步加载
            
            cardBody.innerHTML = '<div class="loading">加载中...</div>';
            
            fetch(`/anon/debug/${section}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        displayData(section, data.data, cardBody);
                    } else {
                        cardBody.innerHTML = `<div class="error">加载失败: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    cardBody.innerHTML = `<div class="error">网络错误: ${error.message}</div>`;
                });
        }
        
        // 显示数据
        function displayData(section, data, container) {
            let html = '';
            
            switch (section) {
                case 'performance':
                    html = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                    break;
                case 'logs':
                    if (Array.isArray(data) && data.length > 0) {
                        html = data.map(log => `<pre>${JSON.stringify(log, null, 2)}</pre>`).join('');
                    } else {
                        html = '<p>暂无日志数据</p>';
                    }
                    break;
                case 'errors':
                    if (Array.isArray(data) && data.length > 0) {
                        html = data.map(error => `<pre class="error">${JSON.stringify(error, null, 2)}</pre>`).join('');
                    } else {
                        html = '<p>暂无错误数据</p>';
                    }
                    break;
                case 'hooks':
                    html = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                    break;
                default:
                    html = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            }
            
            container.innerHTML = html;
        }
        
        // 刷新数据
        function refreshData() {
            const activeSection = document.querySelector('.nav-item.active').getAttribute('data-section');
            if (activeSection !== 'overview') {
                loadSectionData(activeSection);
            }
            location.reload();
        }
        
        // 清理调试数据
        function clearDebugData() {
            if (confirm('确定要清理调试数据吗？')) {
                fetch('/anon/debug/clear', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 200) {
                            alert('调试数据已清理');
                            refreshData();
                        } else {
                            alert('清理失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('网络错误: ' + error.message);
                    });
            }
        }
        
        // 导出调试数据
        function exportDebugData() {
            window.open('/anon/debug/info', '_blank');
        }
        
        // 清空所有数据
        function clearAllData() {
            if (confirm('确定要清空所有调试数据吗？此操作不可恢复！')) {
                clearDebugData();
            }
        }
    </script>
</body>
</html>
        <?php
    }
}