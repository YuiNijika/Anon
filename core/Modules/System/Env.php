<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 环境配置管理
 */
class Anon_System_Env
{
    /**
     * @var array 配置数组
     */
    private static $config = [];

    /**
     * @var bool 初始化状态
     */
    private static $initialized = false;

    /**
     * @var array 配置缓存
     */
    private static $valueCache = [];

    /**
     * 初始化配置
     * @param array $config 配置数组
     */
    public static function init(array $config): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = $config;
        self::$initialized = true;

        self::defineConstants();
    }

    /**
     * 定义配置常量
     */
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
        self::defineIfNotExists('ANON_APP_MODE', defined('ANON_APP_MODE') ? ANON_APP_MODE : (self::$config['app']['mode'] ?? 'api'));
        self::defineIfNotExists('ANON_DEBUG', self::$config['app']['debug']['global'] ?? false);
        self::defineIfNotExists('ANON_ROUTER_DEBUG', self::$config['app']['debug']['router'] ?? false);
        self::defineIfNotExists('ANON_SITE_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    }

    /**
     * 定义常量
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
     * 获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if ($key === 'app.mode') {
            if (defined('ANON_APP_MODE')) {
                return ANON_APP_MODE;
            }
            return $default ?? 'api';
        }

        if (strpos($key, 'app.cms.') === 0) {
            if (!class_exists('Anon_Cms_Options')) {
                $optionsFile = __DIR__ . '/../Cms/Options.php';
                if (file_exists($optionsFile)) {
                    require_once $optionsFile;
                }
            }
            
            if (class_exists('Anon_Cms_Options')) {
                $optionName = str_replace('app.cms.', '', $key);
                if ($optionName === 'routes') {
                    $routes = Anon_Cms_Options::get('routes', $default);
                    if (is_string($routes)) {
                        $routes = json_decode($routes, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $routes = $default;
                        }
                    }
                    return $routes ?? $default;
                }
                return Anon_Cms_Options::get($optionName, $default);
            }
        }

        if (isset(self::$valueCache[$key])) {
            return self::$valueCache[$key];
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                self::$valueCache[$key] = $default;
                return $default;
            }
            $value = $value[$k];
        }

        self::$valueCache[$key] = $value;
        return $value;
    }

    /**
     * 清除缓存
     */
    public static function clearCache(): void
    {
        self::$valueCache = [];
    }

    /**
     * 获取所有配置
     * @return array
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * 检查初始化状态
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}

