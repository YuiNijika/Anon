<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

if (!Anon_Config::isInstalled()) {
    header('Location: /anon/install');
    exit;
}

class Anon_Common
{
    /**
     * 从 Anon_Env 读取配置并定义常量
     * 如果 Anon_Env 已初始化，使用其配置；否则使用默认值
     */
    public static function defineConstantsFromEnv(): void
    {
        if (class_exists('Anon_Env') && Anon_Env::isInitialized()) {
            self::defineIfNotExists('ANON_DB_HOST', Anon_Env::get('system.db.host', 'localhost'));
            self::defineIfNotExists('ANON_DB_PORT', Anon_Env::get('system.db.port', 3306));
            self::defineIfNotExists('ANON_DB_PREFIX', Anon_Env::get('system.db.prefix', ''));
            self::defineIfNotExists('ANON_DB_USER', Anon_Env::get('system.db.user', 'root'));
            self::defineIfNotExists('ANON_DB_PASSWORD', Anon_Env::get('system.db.password', ''));
            self::defineIfNotExists('ANON_DB_DATABASE', Anon_Env::get('system.db.database', ''));
            self::defineIfNotExists('ANON_DB_CHARSET', Anon_Env::get('system.db.charset', 'utf8mb4'));
            self::defineIfNotExists('ANON_INSTALLED', Anon_Env::get('system.installed', false));
            self::defineIfNotExists('ANON_DEBUG', Anon_Env::get('app.debug.global', false));
            self::defineIfNotExists('ANON_ROUTER_DEBUG', Anon_Env::get('app.debug.router', false));
            self::defineIfNotExists('ANON_TOKEN_ENABLED', Anon_Env::get('app.token.enabled', false));
            self::defineIfNotExists('ANON_TOKEN_WHITELIST', Anon_Env::get('app.token.whitelist', []));
        } else {
            // 如果 Anon_Env 未初始化，使用默认值
            self::defineIfNotExists('ANON_DB_HOST', 'localhost');
            self::defineIfNotExists('ANON_DB_PORT', 3306);
            self::defineIfNotExists('ANON_DB_PREFIX', '');
            self::defineIfNotExists('ANON_DB_USER', 'root');
            self::defineIfNotExists('ANON_DB_PASSWORD', '');
            self::defineIfNotExists('ANON_DB_DATABASE', '');
            self::defineIfNotExists('ANON_DB_CHARSET', 'utf8mb4');
            self::defineIfNotExists('ANON_INSTALLED', false);
            self::defineIfNotExists('ANON_ROUTER_DEBUG', false);
            self::defineIfNotExists('ANON_DEBUG', false);
            self::defineIfNotExists('ANON_TOKEN_ENABLED', false);
            self::defineIfNotExists('ANON_TOKEN_WHITELIST', []);
        }

        // 站点配置
        self::defineIfNotExists('ANON_SITE_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    }

    /**
     * 如果未定义，则定义常量
     * @param string $name 常量名
     * @param mixed $value 常量值
     */
    private static function defineIfNotExists(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
    /**
     * 通用Header
     * @param int $code HTTP状态码
     * @param bool $response 是否设置JSON响应头
     * @param bool $cors 是否设置CORS头
     */
    public static function Header($code = 200, $response = true, $cors = true): void
    {
        http_response_code($code);
        
        if ($cors) {
            self::setCorsHeaders();
        }
        
        if ($response) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }

    /**
     * 设置 CORS 头
     */
    private static function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Max-Age: 3600");
    }

    /**
     * 系统信息
     */
    public static function SystemInfo(): array
    {
        return [
            'system' => [
                'PHP_VERSION' => PHP_VERSION,
                'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
                'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            ],
            'copyright' => [
                'name' => 'Anon API Framework',
                'version' => '1.0.0',
                'author' => '鼠子(YuiNijika)',
                'github' => 'https://github.com/YuiNijika/Anon',
            ],
        ];
    }

    /**
     * 获取客户端真实IP
     * @return string
     */
    public static function GetClientIp()
    {
        // 可能的IP来源数组
        $sources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($sources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];

                // 处理X-Forwarded-For可能有多个IP的情况
                if ($source === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    // 将IPv6本地回环地址转换为IPv4格式
                    if ($ip === '::1') {
                        return '127.0.0.1';
                    }
                    return $ip;
                }
            }
        }

        // 所有来源都找不到有效IP时返回默认值
        return null;
    }
}

class Anon_Check
{
    /**
     * 检查用户是否已登录
     * 
     * @return bool 返回是否已登录
     */
    public static function isLoggedIn(): bool
    {
        self::startSessionIfNotStarted();

        // 检查会话中的用户ID
        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        // 检查Cookie中的用户ID和用户名
        if (!empty($_COOKIE['user_id']) && !empty($_COOKIE['username'])) {
            // 验证Cookie值是否有效
            if (self::validateCookie($_COOKIE['user_id'], $_COOKIE['username'])) {
                $_SESSION['user_id'] = (int)$_COOKIE['user_id'];
                $_SESSION['username'] = $_COOKIE['username'];
                return true;
            }
        }

        return false;
    }

    /**
     * 用户注销
     */
    public static function logout(): void
    {
        self::startSessionIfNotStarted();

        // 清空会话数据
        $_SESSION = [];

        // 重置会话 Cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        // 清除认证Cookie
        self::clearAuthCookies();
    }

    /**
     * 设置认证Cookie
     * 
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param bool $rememberMe 是否记住登录状态
     */
    public static function setAuthCookies(int $userId, string $username, bool $rememberMe = false): void
    {
        // 检测是否为跨域请求
        $isCrossOrigin = !empty($_SERVER['HTTP_ORIGIN']) && 
                        parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '');
        
        $isHttps = defined('ANON_SITE_HTTPS') && ANON_SITE_HTTPS;
        
        // 跨域且 HTTPS 时使用 None，否则使用 Lax
        // SameSite=None 必须配合 Secure=true，只能在 HTTPS 下工作
        if ($isCrossOrigin && $isHttps) {
            $sameSite = 'None';
            $secure = true;
        } else {
            $sameSite = 'Lax';
            $secure = $isHttps;
        }
        
        $cookieOptions = [
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => $sameSite
        ];

        // 设置 cookie 过期时间
        if ($rememberMe) {
            $cookieOptions['expires'] = time() + (86400 * 30); // 30天
        } else {
            $cookieOptions['expires'] = 0; // 会话 cookie
        }

        setcookie('user_id', (string)$userId, $cookieOptions);
        setcookie('username', $username, $cookieOptions);
    }

    /**
     * 清除认证Cookie
     */
    public static function clearAuthCookies(): void
    {
        $cookieOptions = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => defined('ANON_SITE_HTTPS') ? ANON_SITE_HTTPS : false,
            'samesite' => 'Lax'
        ];

        setcookie('user_id', '', $cookieOptions);
        setcookie('username', '', $cookieOptions);
    }

    /**
     * 验证Cookie值是否有效
     * 
     * @param mixed $userId 用户ID
     * @param string $username 用户名
     * @return bool 返回是否有效
     */
    private static function validateCookie($userId, string $username): bool
    {
        // 验证用户ID是否为数字且大于0
        if (!is_numeric($userId) || (int)$userId <= 0) {
            return false;
        }

        // 验证用户名不为空
        if (empty($username)) {
            return false;
        }

        // 可以添加更严格的验证，例如查询数据库验证用户是否存在
        $db = new Anon_Database();
        $userInfo = $db->getUserInfo((int)$userId);

        return $userInfo && $userInfo['name'] === $username;
    }

    /**
     * 如果会话未启动，则启动会话
     */
    public static function startSessionIfNotStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // 检测是否为跨域请求
            $isCrossOrigin = !empty($_SERVER['HTTP_ORIGIN']) && 
                             parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '');
            
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            
            // 跨域且 HTTPS 时使用 None，否则使用 Lax
            if ($isCrossOrigin && $isHttps) {
                $sameSite = 'None';
                $secure = true;
            } else {
                $sameSite = 'Lax';
                $secure = $isHttps;
            }
            
            session_start([
                'cookie_httponly' => true,
                'cookie_secure'   => $secure,
                'cookie_samesite' => $sameSite
            ]);
        }
    }
}
