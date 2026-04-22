<?php
namespace Anon\Modules\System;


use System;
use Options;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Env
{
    private static $config = [];
    private static $initialized = false;
    private static $valueCache = [];

    public static function init(array $config): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = $config;
        self::$initialized = true;

        self::defineConstants();
    }

    private static function defineConstants(): void
    {
        self::defineIfNotExists('ANON_DB_HOST', self::$config['system']['db']['host'] ?? 'localhost');
        self::defineIfNotExists('ANON_DB_PORT', self::$config['system']['db']['port'] ?? 3306);
        self::defineIfNotExists('ANON_DB_PREFIX', self::$config['system']['db']['prefix'] ?? '');
        self::defineIfNotExists('ANON_DB_USER', self::$config['system']['db']['user'] ?? 'root');
        self::defineIfNotExists('ANON_DB_PASSWORD', self::$config['system']['db']['password'] ?? '');
        self::defineIfNotExists('ANON_DB_DATABASE', self::$config['system']['db']['database'] ?? '');
        self::defineIfNotExists('ANON_DB_CHARSET', self::$config['system']['db']['charset'] ?? 'utf8mb4');
        self::defineIfNotExists('ANON_INSTALLED', self::$config['system']['installed'] ?? false);

        self::defineIfNotExists(
            'ANON_APP_MODE',
            defined('ANON_APP_MODE') ? ANON_APP_MODE : (self::$config['app']['mode'] ?? 'api')
        );

        self::defineIfNotExists('ANON_DEBUG', self::get('app.debug.global', false));
        self::defineIfNotExists('ANON_ROUTER_DEBUG', self::get('app.debug.router', false));
        self::defineIfNotExists('ANON_SITE_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    }

    private static function defineIfNotExists(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    public static function get(string $key, $default = null)
    {
        if ($key === 'app.mode') {
            if (defined('ANON_APP_MODE')) {
                return ANON_APP_MODE;
            }
            return $default ?? 'api';
        }

        if (strpos($key, 'app.cms.') === 0) {
            $optionName = str_replace('app.cms.', '', $key);
            if ($optionName === 'routes') {
                $routes = Options::get('routes', $default);
                if (is_string($routes)) {
                    $decoded = json_decode($routes, true);
                    $routes = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
                }
                return $routes ?? $default;
            }
            if ($optionName === 'apiPrefix') {
                return Options::get('apiPrefix', $default);
            }
            if ($optionName === 'theme') {
                return Options::get('theme', $default);
            }
            return Options::get($optionName, $default);
        }

        if (isset(self::$valueCache[$key])) {
            return self::$valueCache[$key];
        }

        if ($key === 'app.autoRouter') {
            $mode = self::get('app.base.router.mode', null);
            if (is_string($mode)) {
                $value = strtolower($mode) === 'auto';
                self::$valueCache[$key] = $value;
                return $value;
            }
        }

        foreach (self::getKeyCandidates($key) as $candidate) {
            $found = false;
            $value = self::getConfigValue($candidate, $found);
            if ($found) {
                self::$valueCache[$key] = $value;
                return $value;
            }
        }

        self::$valueCache[$key] = $default;
        return $default;
    }

    private static function getKeyCandidates(string $key): array
    {
        $candidates = [$key];

        $exactMap = [
            'app.baseUrl' => 'app.base.router.prefix',
            'app.avatar' => 'app.base.gravatar',
            'app.cache' => 'app.base.cache',
            'app.token' => 'app.base.token',
            'app.captcha' => 'app.base.captcha',
            'app.rateLimit' => 'app.base.rateLimit',
            'app.security' => 'app.base.security',
        ];

        if (isset($exactMap[$key])) {
            array_unshift($candidates, $exactMap[$key]);
        }

        $prefixMap = [
            'app.cache.' => 'app.base.cache.',
            'app.token.' => 'app.base.token.',
            'app.captcha.' => 'app.base.captcha.',
            'app.rateLimit.' => 'app.base.rateLimit.',
            'app.security.' => 'app.base.security.',
        ];

        foreach ($prefixMap as $oldPrefix => $newPrefix) {
            if (strpos($key, $oldPrefix) === 0) {
                $mapped = $newPrefix . substr($key, strlen($oldPrefix));
                array_unshift($candidates, $mapped);
                break;
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function getConfigValue(string $key, ?bool &$found = null)
    {
        $found = false;
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        $found = true;
        return $value;
    }

    public static function clearCache(): void
    {
        self::$valueCache = [];
    }

    public static function all(): array
    {
        return self::$config;
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}
