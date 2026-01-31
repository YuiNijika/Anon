<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_AccessLog
{
    /**
     * @var array 需要排除的路径
     */
    private static $excludedPaths = [
        '/anon-dev-server',
        '/anon/cms/admin',
        '/anon/static',
        '/assets',
        '/static',
        '/favicon.ico',
        '/robots.txt',
    ];

    /**
     * 检查是否启用访问日志
     * @return bool
     */
    private static function isEnabled(): bool
    {
        $enabled = Anon_Cms_Options::get('access_log_enabled', '1');
        return $enabled === '1' || $enabled === 1 || $enabled === true;
    }

    /**
     * 记录访问日志
     * @param array $options 可选参数
     * @return void
     */
    public static function log(array $options = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!self::shouldLog()) {
            return;
        }

        try {
            $logData = self::collectLogData($options);
            self::saveLog($logData);
        } catch (Exception $e) {
            error_log("访问日志记录失败: " . $e->getMessage());
        }
    }

    /**
     * 检查是否应该记录日志
     * @return bool
     */
    private static function shouldLog(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';
        
        // 排除静态资源和后台 API
        foreach (self::$excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) === 0) {
                return false;
            }
        }

        // 排除API
        if (strpos($path, '/anon/cms') === 0) {
            return false;
        }
        $apiPrefix = Anon_Cms_Options::get('apiPrefix', '/api');
        if (empty($apiPrefix)) {
            $apiPrefix = '/api';
        }
        if ($apiPrefix[0] !== '/') {
            $apiPrefix = '/' . $apiPrefix;
        }
        if (strpos($path, $apiPrefix) === 0) {
            return false;
        }

        return true;
    }

    /**
     * 收集日志数据
     * @param array $options 可选参数
     * @return array 日志数据
     */
    private static function collectLogData(array $options = []): array
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ip = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $statusCode = http_response_code() ?: 200;
        
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';
        
        $responseTime = null;
        if (isset($options['start_time'])) {
            $responseTime = (int)((microtime(true) - $options['start_time']) * 1000);
        }

        // 判断请求类型
        $type = self::detectRequestType($path);

        return [
            'url' => $requestUri,
            'path' => $path,
            'method' => $requestMethod,
            'type' => $type,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'status_code' => $statusCode,
            'response_time' => $responseTime,
        ];
    }

    /**
     * 检测请求类型
     * @param string $path 请求路径
     * @return string 'api' 或 'page'
     */
    private static function detectRequestType(string $path): string
    {
        // 静态资源路径
        if (strpos($path, '/assets') === 0 || strpos($path, '/static') === 0 || strpos($path, '/anon/static') === 0) {
            return 'static';
        }

        // 获取 API 前缀配置
        $apiPrefix = Anon_Cms_Options::get('apiPrefix', '/api');
        if (empty($apiPrefix)) {
            $apiPrefix = '/api';
        }
        if ($apiPrefix[0] !== '/') {
            $apiPrefix = '/' . $apiPrefix;
        }

        // API 路径特征
        $apiPatterns = [
            $apiPrefix . '/',
        ];

        foreach ($apiPatterns as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return 'api';
            }
        }

        // 检查 Accept 头
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return 'api';
        }

        // 默认是页面请求
        return 'page';
    }

    /**
     * 获取客户端 IP 地址
     * @return string
     */
    private static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 保存日志到数据库
     * @param array $logData 日志数据
     * @return void
     */
    private static function saveLog(array $logData): void
    {
        try {
            $db = Anon_Database::getInstance();
            $db->db('access_logs')->insert([
                'url' => $logData['url'],
                'path' => $logData['path'],
                'method' => $logData['method'],
                'type' => $logData['type'] ?? 'page',
                'ip' => $logData['ip'],
                'user_agent' => $logData['user_agent'],
                'referer' => $logData['referer'],
                'status_code' => $logData['status_code'],
                'response_time' => $logData['response_time'],
            ]);
        } catch (Exception $e) {
            throw new RuntimeException("保存访问日志失败: " . $e->getMessage());
        }
    }

    /**
     * 启用访问日志
     * @return bool
     */
    public static function enable(): bool
    {
        return Anon_Cms_Options::set('access_log_enabled', '1');
    }

    /**
     * 禁用访问日志
     * @return bool
     */
    public static function disable(): bool
    {
        return Anon_Cms_Options::set('access_log_enabled', '0');
    }

    /**
     * 设置排除的路径
     * @param array $paths 路径数组
     * @return void
     */
    public static function setExcludedPaths(array $paths): void
    {
        self::$excludedPaths = $paths;
    }

    /**
     * 构建带条件的查询构建器
     * @param Anon_Database $db 数据库实例
     * @param array $options 查询选项
     * @return Anon_Database_QueryBuilder 查询构建器
     */
    private static function buildQueryWithOptions($db, array $options): Anon_Database_QueryBuilder
    {
        $query = $db->db('access_logs');
        
        if (isset($options['start_date'])) {
            $query->where('created_at', '>=', $options['start_date']);
        }
        if (isset($options['end_date'])) {
            $query->where('created_at', '<=', $options['end_date']);
        }
        if (isset($options['ip'])) {
            $query->where('ip', '=', $options['ip']);
        }
        if (isset($options['path'])) {
            $query->where('path', 'LIKE', $options['path'] . '%');
        }
        if (isset($options['type'])) {
            $query->where('type', '=', $options['type']);
        }
        
        return $query;
    }

    /**
     * 获取访问日志统计
     * @param array $options 查询选项
     * @return array 统计结果
     */
    public static function getStatistics(array $options = []): array
    {
        $db = Anon_Database::getInstance();
        
        // 获取总记录数
        $totalQuery = self::buildQueryWithOptions($db, $options);
        $total = $totalQuery->count();
        
        // 获取独立IP数
        $uniqueIpsQuery = self::buildQueryWithOptions($db, $options);
        $uniqueIpsList = $uniqueIpsQuery
            ->select(['ip'])
            ->groupBy('ip')
            ->get();
        $uniqueIps = count($uniqueIpsList);
        
        // 获取热门页面
        $topPagesQuery = self::buildQueryWithOptions($db, $options);
        $allPaths = $topPagesQuery
            ->select(['path'])
            ->get();
        
        // 统计每个路径的访问次数
        $pathCounts = [];
        foreach ($allPaths as $row) {
            $path = $row['path'] ?? '/';
            if (!isset($pathCounts[$path])) {
                $pathCounts[$path] = 0;
            }
            $pathCounts[$path]++;
        }
        
        // 按访问次数排序并取前10
        arsort($pathCounts);
        $topPages = [];
        $index = 0;
        foreach ($pathCounts as $path => $count) {
            if ($index >= 10) {
                break;
            }
            $topPages[] = [
                'path' => $path,
                'count' => $count
            ];
            $index++;
        }

        return [
            'total' => $total,
            'unique_ips' => $uniqueIps,
            'top_pages' => $topPages,
        ];
    }

    /**
     * 清理旧日志按时间删除
     * @param int $days 保留天数默认90天
     * @return int 删除的记录数
     */
    public static function cleanOldLogs(int $days = 90): int
    {
        try {
            $db = Anon_Database::getInstance();
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $deleted = $db->db('access_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
            
            return $deleted;
        } catch (Exception $e) {
            error_log("清理访问日志失败: " . $e->getMessage());
            return 0;
        }
    }
}

