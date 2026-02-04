<?php

/**
 * 调试模块
 *
 * 负责系统的调试信息收集、日志记录、性能监控和错误处理。
 *
 * @package Anon/Core/Modules
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
     * @var string|null 当前请求ID
     */
    private static $requestId = null;

    /**
     * @var float|null 请求开始时间
     */
    private static $requestStartTime = null;

    /**
     * @var int|null 请求开始内存使用
     */
    private static $requestStartMemory = null;

    /**
     * @var array 调试配置
     */
    private static $config = [
        'max_log_size' => 50, // MB
        'max_log_files' => 10,
        'log_levels' => ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'],
        'capture_backtrace' => true,
        'backtrace_levels' => ['ERROR', 'FATAL', 'WARN']
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
     * @var array 性能监控开始时间记录
     */
    private static $performanceStartTimes = [];

    /**
     * @var array 日志缓冲区
     */
    private static $logBuffer = [];

    /**
     * @var int 日志缓冲区最大大小
     */
    private static $logBufferMaxSize = 50;

    /**
     * @var bool 是否已注册关闭函数来刷新缓冲区
     */
    private static $shutdownRegistered = false;

    /**
     * 初始化调试系统
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized || !defined('ANON_DEBUG') || !ANON_DEBUG) {
            return;
        }

        self::$initialized = true;
        self::$requestId = uniqid('req_', true);
        self::$requestStartTime = microtime(true);
        self::$requestStartMemory = memory_get_usage(true);

        set_error_handler([__CLASS__, 'errorHandler']);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
        register_shutdown_function([__CLASS__, 'shutdownHandler']);

        self::log('DEBUG', 'Debug system initialized', [
            'memory' => self::formatBytes(self::$requestStartMemory)
        ]);
    }

    /**
     * 记录日志
     * @param string $level 日志级别
     * @param string $message 消息内容
     * @param array $context 上下文数据
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
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
            'peak_memory' => memory_get_peak_usage(true)
        ];

        if (self::$config['capture_backtrace'] && in_array($level, self::$config['backtrace_levels'])) {
            $logData['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }

        // 添加到缓冲区而不是立即写入文件
        self::$logBuffer[] = $logData;
        if (count(self::$logBuffer) >= self::$logBufferMaxSize) {
            self::flushLogBuffer();
        }

        // 注册关闭函数来确保缓冲区被刷新
        if (!self::$shutdownRegistered) {
            register_shutdown_function([__CLASS__, 'flushLogBuffer']);
            self::$shutdownRegistered = true;
        }

        if (!isset(self::$collectors['logs'])) {
            self::$collectors['logs'] = [];
        }

        if (count(self::$collectors['logs']) < 1000) {
            self::$collectors['logs'][] = $logData;
        }
    }

    /**
     * 记录性能数据
     * @param string $name 监控名称
     * @param float|null $startTime 开始时间
     * @param array $data 附加数据
     * @return void
     */
    public static function performance(string $name, ?float $startTime = null, array $data = []): void
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
     * 开始性能监控
     * @param string $name 监控名称
     * @return void
     */
    public static function startPerformance(string $name): void
    {
        if (!self::isEnabled()) return;
        self::$performanceStartTimes[$name] = microtime(true);
    }

    /**
     * 结束性能监控
     * @param string $name 监控名称
     * @param array $data 附加数据
     * @return void
     */
    public static function endPerformance(string $name, array $data = []): void
    {
        if (!self::isEnabled()) return;

        $startTime = self::$performanceStartTimes[$name] ?? null;
        if ($startTime === null) {
            self::log('WARN', "Performance monitoring end called without start: {$name}");
            return;
        }

        self::performance($name, $startTime, $data);
        unset(self::$performanceStartTimes[$name]);
    }

    /**
     * 记录数据库查询
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param float $duration 执行耗时，单位为毫秒
     * @return void
     */
    public static function query(string $sql, array $params = [], float $duration = 0): void
    {
        if (!self::isEnabled()) return;

        $queryData = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'timestamp' => microtime(true)
        ];

        if ($duration > 100) {
            $queryData['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $queryData['slow'] = true;
        }

        if (count(self::$queries) < 500) {
            self::$queries[] = $queryData;
        }

        if ($duration > 100) {
            self::log('WARN', 'Slow Database Query: ' . round($duration, 2) . 'ms', $queryData);
        }
    }

    /**
     * 错误处理器
     * @param int $severity 错误级别
     * @param string $message 错误信息
     * @param string $file 文件名
     * @param int $line 行号
     * @return bool
     */
    public static function errorHandler(int $severity, string $message, string $file, int $line): bool
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

        return false;
    }

    /**
     * 异常处理器
     * @param Throwable $exception 异常对象
     * @return void
     */
    public static function exceptionHandler(Throwable $exception): void
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
     * @return void
     */
    public static function shutdownHandler(): void
    {
        if (!self::isEnabled()) return;

        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }

        $endTime = microtime(true);
        $duration = ($endTime - self::$requestStartTime) * 1000;
        $memoryUsed = memory_get_usage(true) - self::$requestStartMemory;

        self::log('INFO', 'Request completed', [
            'duration' => round($duration, 2) . 'ms',
            'memory' => self::formatBytes($memoryUsed),
            'peak' => self::formatBytes(memory_get_peak_usage(true)),
            'queries' => count(self::$queries),
            'errors' => count(self::$errors)
        ]);

        // 刷新日志缓冲区
        self::flushLogBuffer();
    }

    /**
     * 获取调试数据
     * @return array
     */
    public static function getData(): array
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
     * @return void
     */
    public static function clear(): void
    {
        self::$collectors = [];
        self::$performance = [];
        self::$queries = [];
        self::$errors = [];
    }

    /**
     * 检查是否启用调试
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (!defined('ANON_DEBUG')) {
            return false;
        }
        $value = ANON_DEBUG;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * 刷新日志缓冲区到文件
     * @return void
     */
    public static function flushLogBuffer(): void
    {
        if (empty(self::$logBuffer)) {
            return;
        }

        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/debug_' . date('Y-m-d') . '.log';
        $logLines = [];

        foreach (self::$logBuffer as $logData) {
            $logLines[] = self::formatLogLine($logData);
        }

        if (!empty($logLines)) {
            @file_put_contents($logFile, implode('', $logLines), FILE_APPEND | LOCK_EX);
            self::rotateLogIfNeeded($logFile);
        }

        self::$logBuffer = [];
    }

    /**
     * 格式化单行日志
     * @param array $logData 日志数据
     * @return string
     */
    private static function formatLogLine(array $logData): string
    {
        $timestamp = $logData['timestamp'];
        $dateTime = date('Y-m-d H:i:s', (int)$timestamp);
        $microseconds = sprintf('%03d', (int)(($timestamp - (int)$timestamp) * 1000));
        $formattedTime = "{$dateTime}.{$microseconds}";

        $requestId = $logData['request_id'] ?? 'unknown';
        $shortRequestId = substr($requestId, 0, 20);
        $memory = self::formatBytes($logData['memory'] ?? 0);
        $peakMemory = self::formatBytes($logData['peak_memory'] ?? 0);

        $contextStr = '';
        if (!empty($logData['context'])) {
            $context = $logData['context'];
            unset($context['request_id']);

            if (!self::shouldLogDetailedInfo()) {
                $context = self::sanitizeLogContext($context);
            }

            if (!empty($context)) {
                $contextParts = [];
                foreach ($context as $key => $value) {
                    if (is_array($value)) {
                        $jsonStr = self::safeJsonEncode($value, 200);
                        $contextParts[] = "{$key}={$jsonStr}";
                    } elseif (is_object($value)) {
                        $contextParts[] = "{$key}=" . get_class($value);
                    } else {
                        $strValue = (string)$value;
                        if (strlen($strValue) > 200) {
                            $strValue = substr($strValue, 0, 200) . '...';
                        }
                        $contextParts[] = "{$key}={$strValue}";
                    }
                }
                $contextStr = ' | ' . implode(' | ', $contextParts);
            }
        }

        return sprintf(
            "[%s] %-12s [%s] %s | mem:%s peak:%s%s\n",
            $formattedTime,
            $logData['level'],
            $shortRequestId,
            $logData['message'],
            $memory,
            $peakMemory,
            $contextStr
        );
    }

    /**
     * 写入单条日志到文件（已废弃，使用缓冲写入）
     * @param array $logData 日志数据
     * @return void
     * @deprecated 使用 flushLogBuffer 代替
     */
    private static function writeToFile(array $logData): void
    {
        // 兼容旧代码，直接添加到缓冲区
        self::$logBuffer[] = $logData;
        if (count(self::$logBuffer) >= self::$logBufferMaxSize) {
            self::flushLogBuffer();
        }
    }

    /**
     * 日志轮转
     * @param string $logFile 日志文件路径
     * @return void
     */
    private static function rotateLogIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) return;

        $maxSize = self::$config['max_log_size'] * 1024 * 1024;
        if (@filesize($logFile) > $maxSize) {
            $backupFile = $logFile . '.' . time();
            @rename($logFile, $backupFile);
            self::cleanOldLogs(dirname($logFile));
        }
    }

    /**
     * 清理旧日志文件
     * @param string $logDir 日志目录
     * @return void
     */
    private static function cleanOldLogs(string $logDir): void
    {
        $files = glob($logDir . '/debug_*.log.*');
        if (count($files) > self::$config['max_log_files']) {
            usort($files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $filesToDelete = array_slice($files, 0, count($files) - self::$config['max_log_files']);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * 格式化字节数
     * @param int $bytes 字节数
     * @return string
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 检查是否应该记录详细信息
     * @return bool
     */
    private static function shouldLogDetailedInfo(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.debug.logDetailedErrors', false);
        }
        return defined('ANON_DEBUG') && ANON_DEBUG;
    }

    /**
     * 清理日志上下文中的敏感信息
     * @param array $context 原始上下文
     * @return array
     */
    private static function sanitizeLogContext(array $context, int $depth = 0, int $maxDepth = 5): array
    {
        if ($depth >= $maxDepth) {
            return ['[MAX_DEPTH_REACHED]'];
        }

        $sensitiveKeys = [
            'password',
            'passwd',
            'pwd',
            'secret',
            'token',
            'csrf_token',
            'api_key',
            'db_password',
            'db_user',
            'db_host',
            'db_name',
            'file_path',
            'file',
            'path',
            'realpath',
            'sql',
            'query',
            'connection'
        ];

        $sanitized = [];
        $count = 0;
        $maxItems = 50;
        foreach ($context as $key => $value) {
            if ($count >= $maxItems) {
                $sanitized['[TRUNCATED]'] = '...';
                break;
            }
            $count++;

            $keyLower = strtolower($key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($keyLower, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeLogContext($value, $depth + 1, $maxDepth);
            } elseif (is_string($value)) {
                if (strlen($value) > 500) {
                    $sanitized[$key] = substr($value, 0, 500) . '...[TRUNCATED]';
                } elseif (preg_match('/(?:password|passwd|pwd|secret|token|api[_-]?key)\s*[=:]\s*[\'"]([^\'"]+)[\'"]/i', $value)) {
                    $sanitized[$key] = preg_replace('/(?:password|passwd|pwd|secret|token|api[_-]?key)\s*[=:]\s*[\'"]([^\'"]+)[\'"]/i', '$1=[FILTERED]', $value);
                } elseif (preg_match('/[a-zA-Z]:\\\\[^\\\\]+|\\/[^\\/]+/', $value) && strlen($value) > 50) {
                    $sanitized[$key] = '[PATH_FILTERED]';
                } else {
                    $sanitized[$key] = $value;
                }
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    private static function safeJsonEncode($data, int $maxLength = 200, int $depth = 0, int $maxDepth = 5): string
    {
        if ($depth >= $maxDepth) {
            return '[MAX_DEPTH]';
        }

        if (is_array($data)) {
            $count = 0;
            $maxItems = 20;
            $limited = [];
            foreach ($data as $key => $value) {
                if ($count >= $maxItems) {
                    $limited['[TRUNCATED]'] = '...';
                    break;
                }
                $count++;
                if (is_array($value) || is_object($value)) {
                    $limited[$key] = self::safeJsonEncode($value, $maxLength, $depth + 1, $maxDepth);
                } else {
                    $limited[$key] = $value;
                }
            }
            $jsonStr = @json_encode($limited, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonStr === false || json_last_error() !== JSON_ERROR_NONE) {
                return '[JSON_ENCODE_ERROR]';
            }
            if (strlen($jsonStr) > $maxLength) {
                return substr($jsonStr, 0, $maxLength) . '...';
            }
            return $jsonStr;
        }

        if (is_object($data)) {
            return get_class($data);
        }

        $strValue = (string)$data;
        if (strlen($strValue) > $maxLength) {
            return substr($strValue, 0, $maxLength) . '...';
        }
        return $strValue;
    }

    // 便捷方法 - 自动检查是否启用
    public static function debug($message, $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }
        if (!self::$initialized) {
            self::init();
        }
        self::log('DEBUG', $message, $context);
    }

    public static function info($message, $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }
        if (!self::$initialized) {
            self::init();
        }
        self::log('INFO', $message, $context);
    }

    public static function warn($message, $context = [])
    {
        if (!self::isEnabled()) {
            // 即使调试未启用，警告也应该记录到 error_log
            $logMessage = is_array($context) && !empty($context)
                ? $message . ' - ' . self::safeJsonEncode($context, 500)
                : $message;
            error_log('[WARN] ' . $logMessage);
            return;
        }
        if (!self::$initialized) {
            self::init();
        }
        self::log('WARN', $message, $context);
    }

    public static function error($message, $context = [])
    {
        if (!self::isEnabled()) {
            // 即使调试未启用，错误也应该记录到 error_log
            $logMessage = is_array($context) && !empty($context)
                ? $message . ' - ' . self::safeJsonEncode($context, 500)
                : $message;
            error_log('[ERROR] ' . $logMessage);
            return;
        }
        if (!self::$initialized) {
            self::init();
        }
        self::log('ERROR', $message, $context);
    }

    public static function fatal($message, $context = [])
    {
        if (!self::isEnabled()) {
            // 即使调试未启用，致命错误也应该记录到 error_log
            $logMessage = is_array($context) && !empty($context)
                ? $message . ' - ' . self::safeJsonEncode($context, 500)
                : $message;
            error_log('[FATAL] ' . $logMessage);
            return;
        }
        if (!self::$initialized) {
            self::init();
        }
        self::log('FATAL', $message, $context);
    }

    /**
     * 检查是否开启调试
     * @return bool
     */
    public static function checkDebugEnabled(): bool
    {
        return self::isEnabled();
    }

    /**
     * 检查调试权限
     * @return bool|string
     */
    public static function checkPermission()
    {
        if (!self::checkDebugEnabled()) {
            return false;
        }

        $userId = null;
        if (class_exists('Anon_Auth_Token') && Anon_Auth_Token::isEnabled()) {
            try {
                $token = Anon_Auth_Token::getTokenFromRequest();
                if ($token) {
                    $payload = Anon_Auth_Token::verify($token);
                    if ($payload && isset($payload['data']['user_id'])) {
                        $userId = (int)$payload['data']['user_id'];

                        Anon_Check::startSessionIfNotStarted();
                        $_SESSION['user_id'] = $userId;
                        if (isset($payload['data']['username'])) {
                            $_SESSION['username'] = $payload['data']['username'];
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }

        if (!$userId && Anon_Check::isLoggedIn()) {
            $userId = Anon_Http_Request::getUserId();
        }

        if (!$userId) {
            return 'not_logged_in';
        }

        $db = Anon_Database::getInstance();
        return $db->isUserAdmin($userId) ? true : false;
    }

    /**
     * 返回 403 错误
     * @return void
     */
    public static function return403(): void
    {
        Anon_Common::Header(403);
        Anon_Http_Response::forbidden('需要管理员权限才能访问 Debug 控制台');
        exit;
    }

    /**
     * 调试信息接口
     * @return void
     */
    public static function debugInfo(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        $debugData = self::getData();
        Anon_Http_Response::success($debugData, 'Debug info retrieved successfully');
    }

    /**
     * 性能监控接口
     * @return void
     */
    public static function performanceApi(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        Anon_Http_Response::success(self::$performance, 'Performance data retrieved successfully');
    }

    /**
     * 日志接口
     * @return void
     */
    public static function logs(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        Anon_Http_Response::success(self::$collectors['logs'] ?? [], 'Logs retrieved successfully');
    }

    /**
     * 错误日志接口
     * @return void
     */
    public static function errors(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        Anon_Http_Response::success(self::$errors, 'Errors retrieved successfully');
    }

    /**
     * Hook 调试接口
     * @return void
     */
    public static function hooks(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        $allHooks = Anon_System_Hook::getAllHooks();
        $stats = Anon_System_Hook::getHookStats();

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

        Anon_Http_Response::success([
            'actions' => $actions,
            'filters' => $filters,
            'total_actions' => count($actions),
            'total_filters' => count($filters),
            'stats' => $stats
        ], 'Hook data retrieved successfully');
    }

    /**
     * 调试工具接口
     * @return void
     */
    public static function tools(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
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
                'current_time' => date('Y-m-d H:i:s'),
            ],
            'debug_tools' => [
                'clear_debug_data' => ['name' => '清理调试数据', 'method' => 'POST'],
                'export_debug_info' => ['name' => '导出调试信息', 'method' => 'GET'],
                'performance_monitor' => ['name' => '性能监控', 'method' => 'GET'],
                'system_logs' => ['name' => '系统日志', 'method' => 'GET'],
                'error_logs' => ['name' => '错误日志', 'method' => 'GET'],
                'hook_debug' => ['name' => 'Hook调试', 'method' => 'GET']
            ],
            'environment' => [
                'debug_enabled' => defined('ANON_DEBUG') && ANON_DEBUG,
                'request_id' => self::$requestId,
                'current_memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]
        ];
        Anon_Http_Response::success($tools, 'Debug tools retrieved successfully');
    }

    /**
     * 清理数据接口
     * @return void
     */
    public static function clearData(): void
    {
        $permissionResult = self::checkPermission();
        if ($permissionResult !== true) {
            if ($permissionResult === 'not_logged_in') {
                Anon_Common::Header(401);
                Anon_Http_Response::unauthorized('请先登录');
            } else {
                self::return403();
            }
            exit;
        }

        Anon_Common::Header();
        self::clear();
        Anon_Http_Response::success(null, 'Debug data cleared successfully');
    }

    /**
     * 登录页面
     * @return void
     */
    public static function login(): void
    {
        if (!self::checkDebugEnabled()) {
            Anon_Common::Header(403);
            Anon_Http_Response::forbidden('Debug 模式未启用');
        }
        self::setCacheHeaders();
        header('Content-Type: text/html; charset=utf-8');

        try {
            Anon_Common::Components('Debug/Login');
        } catch (RuntimeException $e) {
            Anon_Common::Header(500);
            Anon_Http_Response::error('登录页面文件不存在', null, 500);
        }
        exit;
    }

    /**
     * 控制台页面
     * @return void
     */
    public static function console(): void
    {
        if (!self::checkDebugEnabled()) {
            Anon_Common::Header(403);
            Anon_Http_Response::forbidden('Debug 模式未启用');
        }

        $permissionResult = self::checkPermission();
        if ($permissionResult === 'not_logged_in') {
            header('Location: /anon/debug/login');
            exit;
        }
        if ($permissionResult !== true) {
            Anon_Common::Header(403);
            Anon_Http_Response::forbidden('需要管理员权限才能访问 Debug 控制台');
        }

        self::setCacheHeaders();
        header('Content-Type: text/html; charset=utf-8');

        try {
            Anon_Common::Components('Debug/Console');
        } catch (RuntimeException $e) {
            Anon_Common::Header(500);
            Anon_Http_Response::error('控制台页面文件不存在', null, 500);
        }
        exit;
    }

    /**
     * 设置缓存头
     * @return void
     */
    private static function setCacheHeaders(): void
    {
        $cacheEnabled = Anon_System_Env::get('app.debug.cache.enabled', false);
        $cacheTime = Anon_System_Env::get('app.debug.cache.time', 0);

        if ($cacheEnabled && $cacheTime > 0) {
            header('Cache-Control: public, max-age=' . $cacheTime);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        } else {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}
