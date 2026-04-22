<?php
namespace Anon\Modules;





use RuntimeException;
use Modules;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Config;
use Anon\Modules\System\Env;
use Anon\Modules\System\Hook;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Common
{
    const NAME = 'Anon Framework';
    const VERSION = '4.0.0-next';
    const AUTHOR = '鼠子(YuiNijika)';
    const AUTHOR_URL = 'https://github.com/YuiNijika';
    const GITHUB = 'https://github.com/YuiNijika/Anon';
    const LICENSE = 'MIT';

    public static function enforceInstallRedirect(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $isInstallPath = strpos($requestPath, '/anon/install') === 0 || $requestPath === '/anon';
        $isStaticPath = strpos($requestPath, '/anon/static/') === 0;

        if (!Config::isInstalled() && !$isInstallPath && !$isStaticPath) {
            header('Location: /anon/install');
            exit;
        }
    }

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
            𝘊𝘪𝘢𝘭𝕝ｏ～(∠・ω< )⌒☆
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
     * 获取服务器信息
     * @param string $key 信息键名
     * @return string|int|bool
     */
    public static function server(string $key)
    {
        switch ($key) {
            case 'software':
            case 'name':
                return $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
            case 'version':
                // 尝试从 SERVER_SOFTWARE 提取版本，如 Apache/2.4.41 -> 2.4.41
                $software = $_SERVER['SERVER_SOFTWARE'] ?? '';
                if (preg_match('#/([0-9.]+)#', $software, $matches)) {
                    return $matches[1];
                }
                return 'Unknown';
            case 'php':
            case 'php_version':
                return PHP_VERSION;
            case 'os':
                return PHP_OS;
            case 'os_version':
            case 'os_info':
                return php_uname();
            case 'domain':
            case 'host':
                return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            case 'port':
                return (int)($_SERVER['SERVER_PORT'] ?? 80);
            case 'protocol':
                return $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
            case 'ip':
                return $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME'] ?? 'localhost');
            case 'url':
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                return $protocol . '://' . $host;
            case 'is_https':
            case 'ssl':
                return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            default:
                return '';
        }
    }

    /**
     * 从 Env 读取配置并定义常量
     * 如果 Env 已初始化，使用其配置；否则使用默认值
     */
    public static function defineConstantsFromEnv(): void
    {
        if (Env::isInitialized()) {
            self::defineIfNotExists('ANON_DB_HOST', Env::get('system.db.host', 'localhost'));
            self::defineIfNotExists('ANON_DB_PORT', Env::get('system.db.port', 3306));
            self::defineIfNotExists('ANON_DB_PREFIX', Env::get('system.db.prefix', ''));
            self::defineIfNotExists('ANON_DB_USER', Env::get('system.db.user', 'root'));
            self::defineIfNotExists('ANON_DB_PASSWORD', Env::get('system.db.password', ''));
            self::defineIfNotExists('ANON_DB_DATABASE', Env::get('system.db.database', ''));
            self::defineIfNotExists('ANON_DB_CHARSET', Env::get('system.db.charset', 'utf8mb4'));
            self::defineIfNotExists('ANON_INSTALLED', Env::get('system.installed', false));
            self::defineIfNotExists('ANON_DEBUG', Env::get('app.debug.global', false));
            self::defineIfNotExists('ANON_ROUTER_DEBUG', Env::get('app.debug.router', false));
            self::defineIfNotExists('ANON_TOKEN_ENABLED', Env::get('app.base.token.enabled', false));
            self::defineIfNotExists('ANON_TOKEN_WHITELIST', Env::get('app.base.token.whitelist', []));
            self::defineIfNotExists('ANON_CAPTCHA_ENABLED', Env::get('app.base.captcha.enabled', false));
        } else {
            // Env未初始化时使用默认值
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
        if (!Check::isLoggedIn()) {
            self::Header(401);

            // 如果提供了自定义消息，直接使用
            if ($message !== null) {
                ResponseHelper::unauthorized($message);
                return;
            }

            // 尝试通过钩子获取自定义消息
            $customMessage = Hook::apply_filters('require_login_message', '请先登录');
            ResponseHelper::unauthorized($customMessage);
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
                // 未配置允许列表时，仅在调试环境反射 Origin
                $isDebug = defined('ANON_DEBUG') && ANON_DEBUG;
                if ($isDebug) {
                    header("Access-Control-Allow-Origin: " . $origin);
                } else {
                    // 生产环境未配置 CORS 时使用当前主机
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    if (!empty($host)) {
                        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        header("Access-Control-Allow-Origin: " . $scheme . "://" . $host);
                    }
                    return;
                }
            }
        } else {
            // 没有 Origin 时设置为当前主机
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
        // 优先从 Env 获取
        if (Env::isInitialized()) {
            $origins = Env::get('app.base.security.cors.origins', []);
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
     * @return string|null
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

                // 处理 X-Forwarded-For 可能包含多个 IP
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

        // 所有来源都无法获取有效 IP 时返回 null
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
