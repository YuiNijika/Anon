<?php

/**
 * Debug Module
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
        if (!defined('ANON_DEBUG')) {
            return false;
        }
        
        $value = ANON_DEBUG;
        
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        
        return false;
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
        
        // 格式化时间戳，包含毫秒
        $timestamp = $logData['timestamp'];
        $dateTime = date('Y-m-d H:i:s', (int)$timestamp);
        $microseconds = sprintf('%03d', (int)(($timestamp - (int)$timestamp) * 1000));
        $formattedTime = "{$dateTime}.{$microseconds}";
        
        // 格式化日志级别，添加图标
        $levelIcons = [
            'DEBUG' => '🔍',
            'INFO' => 'ℹ️',
            'WARN' => '⚠️',
            'ERROR' => '❌',
            'FATAL' => '💀'
        ];
        $levelIcon = $levelIcons[$logData['level']] ?? '•';
        $formattedLevel = "{$levelIcon} {$logData['level']}";
        
        // 格式化请求ID
        $requestId = $logData['request_id'] ?? 'unknown';
        $shortRequestId = substr($requestId, 0, 20);
        
        // 格式化内存信息
        $memory = self::formatBytes($logData['memory'] ?? 0);
        $peakMemory = self::formatBytes($logData['peak_memory'] ?? 0);
        
        // 格式化上下文数据
        $contextStr = '';
        if (!empty($logData['context'])) {
            // 移除冗余的 request_id，因为已经在日志行中显示
            $context = $logData['context'];
            unset($context['request_id']);
            
            // 格式化上下文为更易读的格式
            if (!empty($context)) {
                $contextParts = [];
                foreach ($context as $key => $value) {
                    if (is_array($value)) {
                        // 对于数组，使用 JSON 但限制长度
                        $jsonStr = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (strlen($jsonStr) > 200) {
                            $jsonStr = substr($jsonStr, 0, 200) . '...';
                        }
                        $contextParts[] = "{$key}={$jsonStr}";
                    } elseif (is_object($value)) {
                        $contextParts[] = "{$key}=" . get_class($value);
                    } else {
                        $contextParts[] = "{$key}={$value}";
                    }
                }
                $contextStr = ' | ' . implode(' | ', $contextParts);
            }
        }
        
        // 构建日志行
        $logLine = sprintf(
            "[%s] %-12s [%s] %s | mem:%s peak:%s%s\n",
            $formattedTime,
            $formattedLevel,
            $shortRequestId,
            $logData['message'],
            $memory,
            $peakMemory,
            $contextStr
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
     * 检查 Debug 模式是否启用
     * @return bool
     */
    public static function checkDebugEnabled()
    {
        return self::isEnabled();
    }

    /**
     * 检查debug权限
     * @return bool|string 返回true表示有权限，返回'not_logged_in'表示未登录，返回false表示无权限
     */
    public static function checkPermission()
    {
        if (!self::checkDebugEnabled()) {
            return false;
        }

        $userId = null;
        
        if (class_exists('Anon_Token') && Anon_Token::isEnabled()) {
            try {
                $token = Anon_Token::getTokenFromRequest();
                if ($token) {
                    $payload = Anon_Token::verify($token);
                    if ($payload && isset($payload['data']['user_id'])) {
                        $userId = (int)$payload['data']['user_id'];
                        if (class_exists('Anon_Check')) {
                            Anon_Check::startSessionIfNotStarted();
                            $_SESSION['user_id'] = $userId;
                            if (isset($payload['data']['username'])) {
                                $_SESSION['username'] = $payload['data']['username'];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }
        
        if (!$userId && class_exists('Anon_Check') && Anon_Check::isLoggedIn()) {
            if (class_exists('Anon_RequestHelper')) {
                $userId = Anon_RequestHelper::getUserId();
            }
        }
        
        if (!$userId) {
            return 'not_logged_in';
        }

        $db = new Anon_Database();
        return $db->isUserAdmin($userId) ? true : false;
    }

    /**
     * 返回403错误
     */
    public static function return403()
    {
        Anon_Common::Header(403);
        Anon_ResponseHelper::forbidden('需要管理员权限才能访问 Debug 控制台');
        exit;
    }

    /**
     * 调试信息API
     */
    public static function debugInfo()
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 获取调试数据
        $debugData = self::getData();

        Anon_ResponseHelper::success($debugData, 'Debug info retrieved successfully');
    }

    /**
     * 性能监控API
     */
    public static function performanceApi()
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 获取性能数据
        $performanceData = self::getPerformanceData();

        Anon_ResponseHelper::success($performanceData, 'Performance data retrieved successfully');
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
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 获取日志数据
        $logs = self::getLogs();

        Anon_ResponseHelper::success($logs, 'Logs retrieved successfully');
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
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 获取错误数据
        $errors = self::getErrors();

        Anon_ResponseHelper::success($errors, 'Errors retrieved successfully');
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
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 获取Hook数据
        $hooks = self::getHookData();

        Anon_ResponseHelper::success($hooks, 'Hook data retrieved successfully');
    }

    /**
     * 获取Hook数据
     */
    public static function getHookData()
    {
        // 获取Hook统计数据
        $allHooks = Anon_Hook::getAllHooks();
        $stats = Anon_Hook::getHookStats();
            
            $actions = [];
            $filters = [];
            
            foreach ($allHooks as $hookName => $priorities) {
                foreach ($priorities as $priority => $hooks) {
                    foreach ($hooks as $hookId => $hookData) {
                        $hookInfo = [
                            'name' => $hookName,
                            'priority' => $priority,
                            'type' => $hookData['type'],
                            'added_at' => $hookData['added_at'] ?? null
                        ];
                        
                        if (isset($stats[$hookName])) {
                            $hookInfo['stats'] = $stats[$hookName];
                        }
                        
                        if ($hookData['type'] === 'action') {
                            $actions[] = $hookInfo;
                        } else {
                            $filters[] = $hookInfo;
                        }
                    }
                }
            }
            
        return [
            'actions' => $actions,
            'filters' => $filters,
            'total_actions' => count($actions),
            'total_filters' => count($filters),
            'stats' => $stats
        ];
    }

    /**
     * 调试工具API
     */
    public static function tools()
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

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
                    'endpoint' => '/anon/debug/api/clear',
                    'method' => 'POST'
                ],
                'export_debug_info' => [
                    'name' => '导出调试信息',
                    'description' => '导出完整的调试信息JSON数据',
                    'endpoint' => '/anon/debug/api/info',
                    'method' => 'GET'
                ],
                'performance_monitor' => [
                    'name' => '性能监控',
                    'description' => '查看系统性能数据和执行时间统计',
                    'endpoint' => '/anon/debug/api/performance',
                    'method' => 'GET'
                ],
                'system_logs' => [
                    'name' => '系统日志',
                    'description' => '查看系统运行日志',
                    'endpoint' => '/anon/debug/api/logs',
                    'method' => 'GET'
                ],
                'error_logs' => [
                    'name' => '错误日志',
                    'description' => '查看系统错误和异常记录',
                    'endpoint' => '/anon/debug/api/errors',
                    'method' => 'GET'
                ],
                'hook_debug' => [
                    'name' => 'Hook调试',
                    'description' => '查看Hook系统的执行统计',
                    'endpoint' => '/anon/debug/api/hooks',
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

        Anon_ResponseHelper::success($tools, 'Debug tools retrieved successfully');
    }

    /**
     * 清理调试数据API
     */
    public static function clearData()
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_ResponseHelper::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();

        // 清理调试数据
        self::clear();

        Anon_ResponseHelper::success(null, 'Debug data cleared successfully');
    }

    /**
     * 调试控制台登录页面
     */
    public static function login()
    {
        if (!self::checkDebugEnabled()) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('Debug 模式未启用');
        }
        
        $debugFile = __FILE__;
        $debugDir = dirname($debugFile);
        $viewPath = $debugDir . DIRECTORY_SEPARATOR . 'Debug' . DIRECTORY_SEPARATOR . 'Login.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            Anon_Common::Header(500);
            Anon_ResponseHelper::error('登录页面文件不存在: ' . $viewPath);
        }
        exit;
    }

    /**
     * 调试控制台Web界面
     */
    public static function console()
    {
        if (!self::checkDebugEnabled()) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('Debug 模式未启用');
            exit;
        }
        
        $permissionResult = self::checkPermission();
        
        if ($permissionResult === 'not_logged_in') {
            header('Location: /anon/debug/login');
            exit;
        }
        
        if ($permissionResult !== true) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('需要管理员权限才能访问 Debug 控制台');
            exit;
        }

        $debugFile = __FILE__;
        $debugDir = dirname($debugFile);
        $viewPath = $debugDir . DIRECTORY_SEPARATOR . 'Debug' . DIRECTORY_SEPARATOR . 'Console.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            Anon_Common::Header(500);
            Anon_ResponseHelper::error('控制台页面文件不存在: ' . $viewPath);
        }
        exit;
    }
}