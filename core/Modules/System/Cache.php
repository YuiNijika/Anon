<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 缓存接口定义
 */
interface Anon_CacheInterface
{
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 清空缓存
     * @return bool
     */
    public function clear(): bool;

    /**
     * 检查缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool;
}

/**
 * 文件缓存实现
 */
class Anon_System_Cache implements Anon_CacheInterface
{
    /**
     * @var string 缓存目录
     */
    private $cacheDir;

    /**
     * @var int 默认过期时间
     */
    private $defaultTtl;

    /**
     * @param string|null $cacheDir 缓存目录
     * @param int $defaultTtl 默认过期时间
     */
    public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?? (__DIR__ . '/../../../cache');
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            @chmod($this->cacheDir, 0755);
        }
    }

    /**
     * 获取缓存路径
     * @param string $key 缓存键
     * @return string
     */
    private function getCachePath(string $key): string
    {
        // 验证缓存键安全性
        if (!preg_match('/^[a-zA-Z0-9_\-\.:]+$/', $key)) {
            throw new InvalidArgumentException("无效的缓存键");
        }
        
        // 使用 SHA256 哈希
        $hash = hash('sha256', $key);
        $subDir = substr($hash, 0, 2);
        $dir = $this->cacheDir . '/' . $subDir;
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            @chmod($dir, 0755);
        }

        return $dir . '/' . $hash . '.cache';
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $path = $this->getCachePath($key);

        if (!file_exists($path)) {
            return $default;
        }

        // 使用 JSON 反序列化
        $content = @file_get_contents($path);
        if ($content === false) {
            return $default;
        }

        $data = @json_decode($content, true);
        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            // 兼容旧格式
            $data = @unserialize($content, ['allowed_classes' => false]);
            if ($data === false) {
                return $default;
            }
        }

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $path = $this->getCachePath($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $data = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];

        // 使用 JSON 序列化
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $result = file_put_contents($path, $json, LOCK_EX);
        if ($result !== false) {
            @chmod($path, 0600);
        }

        return $result !== false;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete(string $key): bool
    {
        $path = $this->getCachePath($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    /**
     * 清空缓存
     * @return bool
     */
    public function clear(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                unlink($file->getPathname());
            }
        }

        return true;
    }

    /**
     * 检查缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        $path = $this->getCachePath($key);

        if (!file_exists($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $data = @json_decode($content, true);
        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            $data = @unserialize($content, ['allowed_classes' => false]);
            if ($data === false) {
                return false;
            }
        }

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }
}

/**
 * 内存缓存实现
 */
class Anon_MemoryCache implements Anon_CacheInterface
{
    /**
     * @var array 缓存数据
     */
    private static $cache = [];

    /**
     * @var array 过期时间
     */
    private static $expires = [];

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (isset(self::$expires[$key]) && self::$expires[$key] < time()) {
            unset(self::$cache[$key], self::$expires[$key]);
            return $default;
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        self::$cache[$key] = $value;
        
        if ($ttl !== null && $ttl > 0) {
            self::$expires[$key] = time() + $ttl;
        } else {
            unset(self::$expires[$key]);
        }

        return true;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset(self::$cache[$key], self::$expires[$key]);
        return true;
    }

    /**
     * 清空缓存
     * @return bool
     */
    public function clear(): bool
    {
        self::$cache = [];
        self::$expires = [];
        return true;
    }

    /**
     * 检查缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        if (isset(self::$expires[$key]) && self::$expires[$key] < time()) {
            unset(self::$cache[$key], self::$expires[$key]);
            return false;
        }

        return isset(self::$cache[$key]);
    }
}

/**
 * 缓存管理器
 */
class Anon_Cache
{
    /**
     * @var Anon_CacheInterface 缓存实例
     */
    private static $instance = null;

    /**
     * @var string 默认驱动
     */
    private static $defaultDriver = 'file';

    /**
     * 初始化缓存
     * @param string $driver 驱动类型
     * @param array $config 配置
     */
    public static function init(string $driver = 'file', array $config = []): void
    {
        self::$defaultDriver = $driver;

        switch ($driver) {
            case 'file':
                $cacheDir = $config['dir'] ?? (__DIR__ . '/../../../cache');
                $defaultTtl = $config['ttl'] ?? 3600;
                self::$instance = new Anon_System_Cache($cacheDir, $defaultTtl);
                break;

            case 'memory':
                self::$instance = new Anon_MemoryCache();
                break;

            default:
                throw new RuntimeException("不支持的缓存驱动: {$driver}");
        }
    }

    /**
     * 获取缓存实例
     * @return Anon_CacheInterface
     */
    private static function getInstance(): Anon_CacheInterface
    {
        if (self::$instance === null) {
            self::init(self::$defaultDriver);
        }

        return self::$instance;
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public static function delete(string $key): bool
    {
        return self::getInstance()->delete($key);
    }

    /**
     * 清空缓存
     * @return bool
     */
    public static function clear(): bool
    {
        return self::getInstance()->clear();
    }

    /**
     * 检查缓存
     * @param string $key 缓存键
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    /**
     * 记住缓存
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }
}

