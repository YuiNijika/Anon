<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_AccessLog
{
    private static $excludedPaths = [
        '/anon-dev-server',
        '/anon/cms/admin',
        '/anon/install',
        '/anon/static',
        '/anon/attachment',
        '/assets',
        '/static',
        '/.well-known',
        '/favicon.ico',
        '/robots.txt',
    ];

    private static $sensitiveFilePatterns = [
        '/Dockerfile',
        '/docker-compose',
        '/.nvmrc',
        '/.yarnrc',
        '/httpd.conf',
        '/nginx.conf',
        '/.htaccess',
        '/.htpasswd',
        '/cert.pem',
        '/key.pem',
        '/ssl.key',
        '/selfsigned.crt',
        '/selfsigned.key',
        '/public.key',
        '/private.key',
        '/id_rsa',
        '/id_rsa.pub',
        '/.ssh/',
        '/.bash_history',
        '/.bashrc',
        '/.DS_Store',
        '/.git/',
        '/.hg/',
        '/.svn/',
        '/wp-config',
        '/config.php',
        '/.env',
        '/.env.local',
        './env.php',
        '/composer.json',
        '/package.json',
        '/yarn.lock',
        '/package-lock.json',
        '/.bak',
        '/.backup',
        '/.old',
        '/.tmp',
    ];

    private static function isEnabled(): bool
    {
        return Anon_Cms_Options::get('access_log_enabled', true);
    }

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
            Anon_Debug::error("访问日志记录失败", ['message' => $e->getMessage()]);
        }
    }

    private static function shouldLog(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';

        // 通过钩子允许插件或配置动态决定是否记录
        $shouldLog = Anon_System_Hook::apply_filters('access_log_should_log', null, [
            'path' => $path,
            'uri' => $requestUri
        ]);

        // 如果钩子返回了明确布尔值则直接使用
        if (is_bool($shouldLog)) {
            return $shouldLog;
        }

        foreach (self::$excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) === 0) {
                return false;
            }
        }

        // 检查是否记录 API 请求
        $logApi = Anon_Cms_Options::get('access_log_api', false);
        if (!$logApi && (strpos($path, '/api/') === 0 || strpos($path, '/anon/') === 0)) {
            return false;
        }

        // 检查是否记录静态资源
        $logStatic = Anon_Cms_Options::get('access_log_static', false);
        if (!$logStatic && self::isStaticResource($path)) {
            return false;
        }

        if (strpos($path, '/anon/') === 0) {
            return false;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!empty($userAgent)) {
            $userAgentLower = strtolower($userAgent);
            $excludedUserAgents = ['curl', 'wget', 'python-requests', 'go-http-client', 'java/', 'scanner', 'bot'];
            foreach ($excludedUserAgents as $excludedUA) {
                if (strpos($userAgentLower, $excludedUA) !== false) {
                    return false;
                }
            }
        }

        foreach (self::$sensitiveFilePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    private static function isStaticResource(string $path): bool
    {
        $staticExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot'];
        foreach ($staticExtensions as $ext) {
            if (substr($path, -strlen($ext)) === $ext) {
                return true;
            }
        }
        return strpos($path, '/assets') === 0 || strpos($path, '/static') === 0 || strpos($path, '/anon/static') === 0;
    }

    private static function collectLogData(array $options = []): array
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $statusCode = http_response_code() ?: 200;

        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';

        $responseTime = null;
        if (isset($options['start_time'])) {
            $responseTime = (int)((microtime(true) - $options['start_time']) * 1000);
        }

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

    private static function detectRequestType(string $path): string
    {
        if (strpos($path, '/assets') === 0 || strpos($path, '/static') === 0 || strpos($path, '/anon/static') === 0) {
            return 'static';
        }

        if (strpos($path, '/api/') === 0 || strpos($path, '/anon/') === 0) {
            return 'api';
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return 'api';
        }

        return 'page';
    }

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
            throw new RuntimeException("保存访问日志失败：" . $e->getMessage());
        }
    }

    public static function enable(): bool
    {
        return true;
    }

    public static function disable(): bool
    {
        return true;
    }

    public static function setExcludedPaths(array $paths): void
    {
        self::$excludedPaths = $paths;
    }

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
            $query->where('ip', 'LIKE', '%' . $options['ip'] . '%');
        }
        if (isset($options['path'])) {
            $query->where('path', 'LIKE', '%' . $options['path'] . '%');
        }
        if (isset($options['type'])) {
            $query->where('type', '=', $options['type']);
        }
        if (isset($options['user_agent'])) {
            $query->where('user_agent', 'LIKE', '%' . $options['user_agent'] . '%');
        }
        if (isset($options['status_code'])) {
            $query->where('status_code', '=', $options['status_code']);
        }

        return $query;
    }

    public static function getLogs(array $options = []): array
    {
        $db = Anon_Database::getInstance();

        $page = isset($options['page']) ? max(1, (int)$options['page']) : 1;
        $pageSize = isset($options['page_size']) ? max(1, min(100, (int)$options['page_size'])) : 20;
        $offset = ($page - 1) * $pageSize;

        $query = self::buildQueryWithOptions($db, $options);
        $total = $query->count();

        $listQuery = self::buildQueryWithOptions($db, $options);
        $list = $listQuery
            ->orderBy('created_at', 'DESC')
            ->limit($pageSize)
            ->offset($offset)
            ->get();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public static function getStatistics(array $options = []): array
    {
        $db = Anon_Database::getInstance();

        $totalQuery = self::buildQueryWithOptions($db, $options);
        $total = $totalQuery->count();

        $uniqueIpsQuery = self::buildQueryWithOptions($db, $options);
        $uniqueIpsList = $uniqueIpsQuery
            ->select(['ip'])
            ->groupBy('ip')
            ->get();
        $uniqueIps = count($uniqueIpsList);

        $topPagesQuery = self::buildQueryWithOptions($db, $options);
        $allPaths = $topPagesQuery
            ->select(['path'])
            ->get();

        $pathCounts = [];
        foreach ($allPaths as $row) {
            $path = $row['path'] ?? '/';
            if (!isset($pathCounts[$path])) {
                $pathCounts[$path] = 0;
            }
            $pathCounts[$path]++;
        }

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
            Anon_Debug::error("清理访问日志失败", ['message' => $e->getMessage()]);
            return 0;
        }
    }
}
