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
    private $cache = [];

    /**
     * @var array 过期时间
     */
    private $expires = [];

    /**
     * @var int 最大缓存条数限制
     */
    private $limit = 10000;

    /**
     * 验证缓存键
     * @param string $key
     */
    private function validateKey(string $key): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.:]+$/', $key)) {
            throw new InvalidArgumentException("无效的缓存键: {$key}");
        }
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->validateKey($key);
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->cache[$key], $this->expires[$key]);
            return $default;
        }

        return $this->cache[$key] ?? $default;
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
        $this->validateKey($key);
        // 简单的容量限制，防止内存泄漏
        if (count($this->cache) >= $this->limit) {
            array_shift($this->cache);
            // 清理对应的 expires (这里简化处理，可能清理不完全对应，但为了性能)
            if (!empty($this->expires)) {
                array_shift($this->expires);
            }
        }

        $this->cache[$key] = $value;

        if ($ttl !== null && $ttl > 0) {
            $this->expires[$key] = time() + $ttl;
        } else {
            unset($this->expires[$key]);
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
        $this->validateKey($key);
        unset($this->cache[$key], $this->expires[$key]);
        return true;
    }

    /**
     * 清空缓存
     * @return bool
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->expires = [];
        return true;
    }

    /**
     * 检查缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->cache[$key], $this->expires[$key]);
            return false;
        }

        return isset($this->cache[$key]);
    }
}

/**
 * Redis 缓存实现
 */
class Anon_RedisCache implements Anon_CacheInterface
{
    /**
     * @var Redis Redis 实例
     */
    private $redis;

    /**
     * @var string 键前缀
     */
    private $prefix;

    /**
     * @var int 默认过期时间
     */
    private $defaultTtl;

    /**
     * @param array $config 配置
     */
    public function __construct(array $config = [])
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis 扩展未安装');
        }

        $this->redis = new Redis();
        $host = (string)($config['host'] ?? '127.0.0.1');
        $port = (int)($config['port'] ?? 6379);
        $timeout = (float)($config['timeout'] ?? 2.0); // 默认超时改为 2.0 秒
        $maxRetries = 3;
        $retryInterval = 100000; // 100ms

        $connected = false;
        /** @var Throwable|null $lastException */
        $lastException = null;

        // 连接重试机制
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                if ($this->redis->connect($host, $port, $timeout)) {
                    $connected = true;
                    break;
                }
            } catch (Throwable $e) {
                $lastException = $e;
            }
            // 最后一次尝试失败不需要等待
            if ($i < $maxRetries - 1) {
                usleep($retryInterval);
            }
        }

        if (!$connected) {
            $msg = $lastException ? $lastException->getMessage() : "无法连接到 Redis 服务器: {$host}:{$port}";
            throw new RuntimeException($msg, 0, $lastException);
        }

        try {
            if (!empty($config['password'])) {
                if (!$this->redis->auth($config['password'])) {
                    throw new RuntimeException("Redis 认证失败");
                }
            }

            if (isset($config['database'])) {
                $this->redis->select((int)$config['database']);
            }

            // 确保连接可用
            try {
                $this->redis->ping();
            } catch (Throwable $e) {
                throw new RuntimeException("Redis PING 失败: " . $e->getMessage());
            }

            // 记录连接成功日志
            Anon_Debug::log('INFO', "Redis connected successfully", [
                'host' => $host,
                'port' => $port,
                'db' => $config['database'] ?? 0
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Redis 内部错误: " . $e->getMessage(), 0, $e);
        }

        $this->prefix = $config['prefix'] ?? 'anon:';
        $this->defaultTtl = $config['ttl'] ?? 3600;
    }

    /**
     * 获取带前缀的键
     * @param string $key 原始键
     * @return string
     */
    private function getKey(string $key): string
    {
        // 验证键名安全性，与文件缓存保持一致
        if (!preg_match('/^[a-zA-Z0-9_\-\.:]+$/', $key)) {
            throw new InvalidArgumentException("无效的缓存键: {$key}");
        }
        return $this->prefix . $key;
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        try {
            $value = $this->redis->get($this->getKey($key));

            if ($value === false) {
                return $default;
            }

            $data = @json_decode($value, true);
            if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
                // 如果 JSON 解析失败，视为无效数据，返回默认值，避免类型不一致
                if (defined('ANON_DEBUG') && ANON_DEBUG) {
                    Anon_Debug::warning("Redis cache key '{$key}' contains invalid JSON data.");
                }
                return $default;
            }

            return $data;
        } catch (RedisException $e) {
            // 发生异常时返回默认值，并记录错误
            Anon_Debug::error("Redis get error: " . $e->getMessage());
            return $default;
        }
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
        $ttl = $ttl ?? $this->defaultTtl;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return false;
        }

        try {
            if ($ttl > 0) {
                return $this->redis->setex($this->getKey($key), $ttl, $json);
            }
            return $this->redis->set($this->getKey($key), $json);
        } catch (RedisException $e) {
            Anon_Debug::error("Redis set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            return (bool)$this->redis->del($this->getKey($key));
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 清空缓存
     * 注意：这会清空当前数据库中的所有匹配前缀的键
     * @return bool
     */
    public function clear(): bool
    {
        $iterator = null;
        $pattern = $this->prefix . '*';
        $count = 100; // 每次扫描数量限制

        try {
            // 使用 do-while 循环正确处理 SCAN 游标
            do {
                // scan 返回 false 表示出错或结束，但 php-redis 中通常返回数组，iterator 引用被更新
                $keys = $this->redis->scan($iterator, $pattern, $count);

                if ($keys !== false && !empty($keys)) {
                    $this->redis->del($keys);
                }
            } while ($iterator > 0);
        } catch (RedisException $e) {
            Anon_Debug::error("Redis clear error: " . $e->getMessage());
            return false;
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
        return (bool)$this->redis->exists($this->getKey($key));
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

            case 'redis':
                $redisConfig = $config['redis'] ?? [];
                self::$instance = new Anon_RedisCache($redisConfig);
                break;

            case 'memory':
                self::$instance = new Anon_MemoryCache();
                break;

            default:
                throw new RuntimeException("不支持的缓存驱动: {$driver}");
        }

        /**
         * 缓存初始化完成
         * @param Anon_CacheInterface $instance 缓存实例
         * @param string $driver 驱动名称
         */
        Anon_System_Hook::do_action('cache_initialized', self::$instance, $driver);
    }

    /**
     * 获取缓存实例
     * @return Anon_CacheInterface
     */
    private static function getInstance(): Anon_CacheInterface
    {
        if (self::$instance === null) {
            $driver = Anon_System_Env::get('app.cache.driver', 'file');
            $config = Anon_System_Env::get('app.cache', []);

            /**
             * 过滤缓存驱动
             * @param string $driver 驱动名称
             */
            $driver = Anon_System_Hook::apply_filters('cache_driver', $driver);

            /**
             * 过滤缓存配置
             * @param array $config 配置数组
             * @param string $driver 驱动名称
             */
            $config = Anon_System_Hook::apply_filters('cache_config', $config, $driver);

            try {
                self::init($driver, $config);
            } catch (Throwable $e) {
                // 如果初始化失败，降级为文件缓存
                Anon_Debug::error("Cache driver '{$driver}' initialization failed, falling back to 'file'. Error: " . $e->getMessage());
                self::init('file', $config);
            }
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
