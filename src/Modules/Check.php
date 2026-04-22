<?php
namespace AnonModules;




use RuntimeException;

use Modules;
use Anon\Modules\System\Env;
use Anon\Modules\System\Hook;
use Anon\Modules\Database\Database;
use Anon\Modules\Debug;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Check
{
    public static function isLoggedIn(): bool
    {
        self::startSessionIfNotStarted();

        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        if (!empty($_COOKIE['user_id']) && !empty($_COOKIE['username'])) {
            if (self::validateCookie($_COOKIE['user_id'], $_COOKIE['username'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $_COOKIE['user_id'];
                $_SESSION['username'] = $_COOKIE['username'];
                return true;
            }
            self::clearAuthCookies();
        }

        return false;
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        Hook::do_action('auth_before_logout', $userId);

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

        Hook::do_action('auth_after_logout', $userId ?? null);
    }

    public static function setAuthCookies(int $userId, string $username, bool $rememberMe = false): void
    {
        Hook::do_action('auth_before_set_cookies', $userId, $username, $rememberMe);

        $isHttps = defined('ANON_SITE_HTTPS') && ANON_SITE_HTTPS;

        $cookieOptions = [
            'path' => '/',
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax'
        ];

        $cookieOptions['expires'] = $rememberMe ? (time() + (86400 * 30)) : 0;

        $cookieOptions = Hook::apply_filters('auth_cookie_options', $cookieOptions, $userId, $username);

        $secret = self::getCookieSecret();
        $expires = $cookieOptions['expires'] ?? 0;
        $signature = hash_hmac('sha256', (string) $userId . '|' . $username . '|' . $expires, $secret);

        setcookie('user_id', (string) $userId, $cookieOptions);
        setcookie('username', $username, $cookieOptions);
        setcookie('auth_signature', $signature, $cookieOptions);
        setcookie('auth_expires', (string) $expires, $cookieOptions);

        Hook::do_action('auth_after_set_cookies', $userId, $username, $rememberMe);
    }

    public static function clearAuthCookies(): void
    {
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => defined('ANON_SITE_HTTPS') ? ANON_SITE_HTTPS : false,
            'samesite' => 'Lax'
        ];

        setcookie('user_id', '', $cookieOptions);
        setcookie('username', '', $cookieOptions);
        setcookie('auth_signature', '', $cookieOptions);
        setcookie('auth_expires', '', $cookieOptions);
    }

    private static function validateCookie($userId, string $username): bool
    {
        if (!is_numeric($userId) || (int) $userId <= 0) {
            return false;
        }

        if ($username === '') {
            return false;
        }

        $signature = $_COOKIE['auth_signature'] ?? '';
        if ($signature === '') {
            return false;
        }

        $cookieExpires = isset($_COOKIE['user_id']) ? ($_COOKIE['auth_expires'] ?? 0) : 0;
        $secret = self::getCookieSecret();
        $expectedSignature = hash_hmac('sha256', (string) $userId . '|' . $username . '|' . $cookieExpires, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $db = Database::getInstance();
        $userInfo = $db->getUserInfo((int) $userId);
        return $userInfo && ($userInfo['name'] ?? '') === $username;
    }

    private static function getCookieSecret(): string
    {
        if (defined('ANON_APP_KEY') && !empty(ANON_APP_KEY)) {
            return hash('sha256', ANON_APP_KEY . '_cookie');
        }

        if (Env::isInitialized()) {
            $appKey = Env::get('app.key');
            if (!empty($appKey)) {
                return hash('sha256', $appKey . '_cookie');
            }
        }

        if (defined('ANON_INSTALL_MODE') && ANON_INSTALL_MODE) {
            return 'anon_install_mode_key';
        }

        Debug::warn('Security Warning: ANON_APP_KEY not configured!');
        return 'anon_default_insecure_key';
    }

    public static function startSessionIfNotStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

            $fallback = (defined('ANON_ROOT') ? ANON_ROOT : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR)
                . 'logs' . DIRECTORY_SEPARATOR . 'sessions';
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0755, true);
            }
            if (is_dir($fallback)) {
                ini_set('session.save_path', $fallback);
            }

            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isHttps,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true
            ]);
        }
    }
}
