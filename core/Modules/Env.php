<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 环境配置管理类
 * 负责加载和定义环境变量
 */
class Anon_Env
{
    /**
     * 环境配置数组
     * @var array
     */
    private static $config = [];

    /**
     * 是否已初始化
     * @var bool
     */
    private static $initialized = false;

    /**
     * 配置值缓存
     * 首次解析后存入内存，后续调用直接读取缓存
     * @var array
     */
    private static $valueCache = [];

    /**
     * 初始化环境配置
     * @param array $config 配置数组
     */
    public static function init(array $config): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = $config;
        self::$initialized = true;

        // 定义常量
        self::defineConstants();
    }

    /**
     * 定义所有配置常量
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
        self::defineIfNotExists('ANON_DEBUG', self::$config['app']['debug']['global'] ?? false);
        self::defineIfNotExists('ANON_ROUTER_DEBUG', self::$config['app']['debug']['router'] ?? false);
        self::defineIfNotExists('ANON_SITE_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    }

    /**
     * 安全定义常量
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
        // 检查缓存
        if (isset(self::$valueCache[$key])) {
            return self::$valueCache[$key];
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                // 缓存默认值
                self::$valueCache[$key] = $default;
                return $default;
            }
            $value = $value[$k];
        }

        // 缓存解析结果
        self::$valueCache[$key] = $value;
        return $value;
    }

    /**
     * 清除配置缓存
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
     * 检查是否已初始化
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}

