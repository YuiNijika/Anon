<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$isInstallPath = strpos($requestPath, '/anon/install') === 0 || $requestPath === '/anon';
$isStaticPath = strpos($requestPath, '/anon/static/') === 0;

if (!Anon_System_Config::isInstalled() && !$isInstallPath && !$isStaticPath) {
    header('Location: /anon/install');
    exit;
}

class Anon_Common
{
    const NAME = 'Anon Framework';
    const VERSION = '3.2.0';
    const AUTHOR = '鼠子(YuiNijika)';
    const AUTHOR_URL = 'https://github.com/YuiNijika';
    const GITHUB = 'https://github.com/YuiNijika/Anon';
    const LICENSE = 'MIT';

    public static function LICENSE_TEXT(): string
    {
        $yearRange = '2024-' . date('Y');

        return <<<LICENSE
MIT License
Copyright (c) {$yearRange} 鼠子(YuiNijika)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;
    }

    public static function Ciallo(): string
    {
        return <<<CIALLO
            Ciallo～(∠・ω< )⌒☆
            𝑪𝒊𝒂𝒍𝒍𝒐～(∠・ω< )⌒☆
            𝓒𝓲𝓪𝓵𝓵𝓸～(∠・ω< )⌒☆
            𝐂𝐢𝐚𝐥𝐥𝐨～(∠・ω< )⌒☆
            ℂ𝕚𝕒𝕝𝕝𝕠～(∠・ω< )⌒☆
            𝘊𝘪𝘢𝘭𝘭𝘰～(∠・ω< )⌒☆
            𝗖𝗶𝗮𝗹𝗹𝗼～(∠・ω< )⌒☆
            𝙲𝚒𝚊𝚕𝚕𝚘～(∠・ω< )⌒☆
            ᴄɪᴀʟʟᴏ～(∠・ω< )⌒☆
            𝕮𝖎𝖆𝖑𝖑𝖔～(∠・ω< )⌒☆
            ℭ𝔦𝔞𝔩𝔩𝔬～(∠・ω< )⌒☆
            ᶜⁱᵃˡˡᵒ～(∠・ω< )⌒☆
            ᑕ⫯Ꭿ𝘭𝘭𝖮～(∠・ω< )⌒☆
            ☆⌒( >ω・∠)～ollɐıɔ
        CIALLO;
    }

    /**
     * 从 Anon_Env 读取配置并定义常量
     * 如果 Anon_Env 已初始化，使用其配置；否则使用默认值
     */
    public static function defineConstantsFromEnv(): void
    {
        if (Anon_System_Env::isInitialized()) {
            self::defineIfNotExists('ANON_DB_HOST', Anon_System_Env::get('system.db.host', 'localhost'));
            self::defineIfNotExists('ANON_DB_PORT', Anon_System_Env::get('system.db.port', 3306));
            self::defineIfNotExists('ANON_DB_PREFIX', Anon_System_Env::get('system.db.prefix', ''));
            self::defineIfNotExists('ANON_DB_USER', Anon_System_Env::get('system.db.user', 'root'));
            self::defineIfNotExists('ANON_DB_PASSWORD', Anon_System_Env::get('system.db.password', ''));
            self::defineIfNotExists('ANON_DB_DATABASE', Anon_System_Env::get('system.db.database', ''));
            self::defineIfNotExists('ANON_DB_CHARSET', Anon_System_Env::get('system.db.charset', 'utf8mb4'));
            self::defineIfNotExists('ANON_INSTALLED', Anon_System_Env::get('system.installed', false));
            self::defineIfNotExists('ANON_DEBUG', Anon_System_Env::get('app.debug.global', false));
            self::defineIfNotExists('ANON_ROUTER_DEBUG', Anon_System_Env::get('app.debug.router', false));
            self::defineIfNotExists('ANON_TOKEN_ENABLED', Anon_System_Env::get('app.token.enabled', false));
            self::defineIfNotExists('ANON_TOKEN_WHITELIST', Anon_System_Env::get('app.token.whitelist', []));
            self::defineIfNotExists('ANON_CAPTCHA_ENABLED', Anon_System_Env::get('app.captcha.enabled', false));
        } else {
            // Anon_Env未初始化时使用默认值
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
            self::defineIfNotExists('ANON_CAPTCHA_ENABLED', false);
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
     * 检查登录状态，未登录则直接返回 401
     * 通常与 Header() 一起使用
     * @param string|null $message 自定义未登录消息，默认使用钩子或默认消息
     */
    public static function RequireLogin(?string $message = null): void
    {
        if (!Anon_Check::isLoggedIn()) {
            self::Header(401);

            // 如果提供了自定义消息，直接使用
            if ($message !== null) {
                Anon_Http_Response::unauthorized($message);
                return;
            }

            // 尝试通过钩子获取自定义消息
            $customMessage = Anon_System_Hook::apply_filters('require_login_message', '请先登录');
            Anon_Http_Response::unauthorized($customMessage);
            return;
        }
    }

    /**
     * 设置 CORS 头
     * 生产环境必须配置允许的来源域名
     */
    public static function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $allowedOrigins = self::getAllowedCorsOrigins();

        if ($origin) {
            // 如果配置了允许的来源列表，则验证来源
            if (!empty($allowedOrigins)) {
                if (in_array($origin, $allowedOrigins, true)) {
                    header("Access-Control-Allow-Origin: " . $origin);
                } else {
                    // 来源不在允许列表中，不设置 CORS 头
                    // 浏览器将阻止跨域请求
                    return;
                }
            } else {
                // 未配置允许列表，使用请求来源，仅限开发环境
                $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
                if ($isDebug) {
                    header("Access-Control-Allow-Origin: " . $origin);
                } else {
                    // 生产环境未配置 CORS，使用当前主机
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    if (!empty($host)) {
                        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        header("Access-Control-Allow-Origin: " . $scheme . "://" . $host);
                    }
                    return;
                }
            }
        } else {
            // 没有 Origin 头，设置为当前主机
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (!empty($host)) {
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                header("Access-Control-Allow-Origin: " . $scheme . "://" . $host);
            }
        }

        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Token, X-CSRF-Token");
        header("Access-Control-Max-Age: 3600");
    }

    /**
     * 获取允许的 CORS 来源域名列表
     * @return array
     */
    private static function getAllowedCorsOrigins(): array
    {
        // 优先从 Anon_System_Env 获取
        if (Anon_System_Env::isInitialized()) {
            $origins = Anon_System_Env::get('app.security.cors.origins', []);
            if (!empty($origins)) {
                return is_array($origins) ? $origins : [$origins];
            }
        }

        // 从常量获取
        if (defined('ANON_CORS_ORIGINS') && is_array(ANON_CORS_ORIGINS)) {
            return ANON_CORS_ORIGINS;
        }

        return [];
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
                'name' => self::NAME,
                'version' => self::VERSION,
                'author' => self::AUTHOR,
                'author_url' => self::AUTHOR_URL,
                'github' => self::GITHUB,
                'license' => self::LICENSE,
                'license_text' => self::LICENSE_TEXT(),
                'copyright' => '© 2024-' . date('Y') . ' ' . self::AUTHOR,
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

    public static function Components(string $name): void
    {
        $path = __DIR__ . '/../Components/' . $name . '.php';
        if (file_exists($path)) {
            require $path;
            return;
        }
        throw new RuntimeException("组件未找到: {$name}");
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

        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        if (!empty($_COOKIE['user_id']) && !empty($_COOKIE['username'])) {
            if (self::validateCookie($_COOKIE['user_id'], $_COOKIE['username'])) {
                // 从Cookie恢复登录时重新生成Session ID防止固定攻击
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$_COOKIE['user_id'];
                $_SESSION['username'] = $_COOKIE['username'];
                return true;
            } else {
                self::clearAuthCookies();
            }
        }

        return false;
    }

    /**
     * 用户注销
     */
    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        Anon_System_Hook::do_action('auth_before_logout', $userId);

        self::startSessionIfNotStarted();

        $_SESSION = [];

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

        self::clearAuthCookies();

        Anon_System_Hook::do_action('auth_after_logout', $userId ?? null);
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
        Anon_System_Hook::do_action('auth_before_set_cookies', $userId, $username, $rememberMe);

        $isHttps = defined('ANON_SITE_HTTPS') && ANON_SITE_HTTPS;

        $cookieOptions = [
            'path'     => '/',
            'httponly' => true,
            'secure'   => $isHttps,
            'samesite' => 'Lax'
        ];

        if ($rememberMe) {
            $cookieOptions['expires'] = time() + (86400 * 30);
        } else {
            $cookieOptions['expires'] = 0;
        }

        $cookieOptions = Anon_System_Hook::apply_filters('auth_cookie_options', $cookieOptions, $userId, $username);

        // 生成 Cookie 签名，防止 Cookie 被篡改
        $secret = self::getCookieSecret();
        $expires = $cookieOptions['expires'] ?? 0;
        $signature = hash_hmac('sha256', (string)$userId . '|' . $username . '|' . $expires, $secret);

        setcookie('user_id', (string)$userId, $cookieOptions);
        setcookie('username', $username, $cookieOptions);
        setcookie('auth_signature', $signature, $cookieOptions);
        setcookie('auth_expires', (string)$expires, $cookieOptions);

        Anon_System_Hook::do_action('auth_after_set_cookies', $userId, $username, $rememberMe);
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
        setcookie('auth_signature', '', $cookieOptions);
        setcookie('auth_expires', '', $cookieOptions);
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

        // 验证 Cookie 签名，防止 Cookie 被篡改
        $signature = $_COOKIE['auth_signature'] ?? '';
        if (empty($signature)) {
            return false;
        }

        $cookieExpires = isset($_COOKIE['user_id']) ? ($_COOKIE['auth_expires'] ?? 0) : 0;
        $secret = self::getCookieSecret();
        $expectedSignature = hash_hmac('sha256', (string)$userId . '|' . $username . '|' . $cookieExpires, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // 查询数据库验证用户是否存在且用户名匹配
        $db = Anon_Database::getInstance();
        $userInfo = $db->getUserInfo((int)$userId);

        return $userInfo && $userInfo['name'] === $username;
    }

    /**
     * 获取 Cookie 签名密钥
     * 优先使用 ANON_APP_KEY，确保每个部署实例唯一
     * @return string
     */
    private static function getCookieSecret(): string
    {
        // 优先使用 APP_KEY
        if (defined('ANON_APP_KEY') && !empty(ANON_APP_KEY)) {
            return hash('sha256', ANON_APP_KEY . '_cookie');
        }

        // 尝试从 Env 获取
        if (Anon_System_Env::isInitialized()) {
            $appKey = Anon_System_Env::get('app.key');
            if (!empty($appKey)) {
                return hash('sha256', $appKey . '_cookie');
            }
        }

        // 如果没有配置 APP_KEY，抛出异常或返回特定标识，安装模式除外
        // 在安装模式下，可能还没有 APP_KEY，使用临时 Key
        if (defined('ANON_INSTALL_MODE') && ANON_INSTALL_MODE) {
            return 'anon_install_mode_key';
        }

        // 严重安全警告：未配置 APP_KEY
        Anon_Debug::warn('Security Warning: ANON_APP_KEY not configured!');

        return 'anon_default_insecure_key';
    }

    /**
     * 如果会话未启动，则启动会话
     */
    public static function startSessionIfNotStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

            session_start([
                'cookie_httponly' => true,
                'cookie_secure'   => $isHttps,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true
            ]);
        }
    }
}
