<?php

if (!defined('ANON_ALLOWED_ACCESS')) exit;

const DatabaseDir = __DIR__ . '/../../app/Database';

/**
 * 递归引入 Database 文件
 */
function anon_require_all_database_files($baseDir)
{
    static $loadedFiles = [];
    
    if (!is_dir($baseDir)) {
        return;
    }
    
    $connectionFile = realpath($baseDir . '/Connection.php');
    
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($it as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $path = $fileInfo->getRealPath();
            
            if (!$path) {
                continue;
            }
            
            if ($path === $connectionFile) {
                continue;
            }
            
            if (isset($loadedFiles[$path])) {
                continue;
            }
            
            $loadedFiles[$path] = true;
            require_once $path;
        }
    }
}

anon_require_all_database_files(DatabaseDir);

class Anon_Database
{
    /**
     * 动态实例容器
     * 包含 Repository/Service 实例
     * 不再使用固定 private 属性
     */
    protected $instances = [];

    /**
     * 构造函数自动发现并实例化所有仓库与服务类
     */
    public function __construct()
    {
        $this->bootstrapInstances();
    }

    private static $bootstrapedClasses = [];
    private static $bootstraped = false;

    /**
     * 自动发现并实例化匹配类Anon_Database_*Repository / *Service
     */
    protected function bootstrapInstances()
    {
        if (self::$bootstraped) {
            $this->instances = self::$bootstrapedClasses;
            return;
        }
        
        $classesToCheck = [];
        foreach (get_declared_classes() as $class) {
            if (preg_match('/^Anon_Database_([A-Za-z0-9_]+)(Repository|Service)$/', $class, $m)) {
                $classesToCheck[] = ['class' => $class, 'matches' => $m];
            }
        }
        
        foreach ($classesToCheck as $item) {
            $class = $item['class'];
            $m = $item['matches'];
            
            if (!isset(self::$bootstrapedClasses[$class])) {
                $obj = new $class();
                $short = $m[1] . $m[2];
                $camel = lcfirst($short);
                
                self::$bootstrapedClasses[$class] = $obj;
                self::$bootstrapedClasses[$short] = $obj;
                self::$bootstrapedClasses[$camel] = $obj;
            }
        }
        
        $this->instances = self::$bootstrapedClasses;
        self::$bootstraped = true;
    }

    private static $connection = null;

    private function getConnection()
    {
        if (self::$connection === null) {
            self::$connection = Anon_Database_Connection::getInstance();
        }
        return self::$connection;
    }

    /**
     * 直接访问 QueryBuilder 的入口
     * @param string $table 表名
     * @return Anon_QueryBuilder|Anon_Database_QueryBuilder
     */
    public function db($table)
    {
        // 检查分库分表配置
        if (class_exists('Anon_Sharding') && Anon_Sharding::isSharded($table)) {
            // 如果启用了分片，需要在查询时指定分片键
            // 这里返回基础表名，实际分片在查询时处理
        }

        return new Anon_QueryBuilder($this->getConnection(), ANON_DB_PREFIX . $table);
    }

    /**
     * 批量插入数据
     * @param string $table 表名
     * @param array $data 数据数组
     * @param int $batchSize 批次大小
     * @return int 成功插入的总行数
     */
    public function batchInsert(string $table, array $data, int $batchSize = 1000): int
    {
        return $this->db($table)->batchInsert($data, $batchSize);
    }

    /**
     * 批量更新数据
     * @param string $table 表名
     * @param array $data 数据数组
     * @param string $keyColumn 主键字段名
     * @param int $batchSize 批次大小
     * @return int 成功更新的总行数
     */
    public function batchUpdate(string $table, array $data, string $keyColumn = 'id', int $batchSize = 1000): int
    {
        return $this->db($table)->batchUpdate($data, $keyColumn, $batchSize);
    }

    /**
     * 执行查询并返回结果
     */
    public function query($sql)
    {
        return $this->getConnection()->query($sql);
    }

    /**
     * 准备并返回预处理语句对象
     */
    public function prepare($sql, $params = [])
    {
        return $this->getConnection()->prepare($sql, $params);
    }

    /**
     * 动态属性访问
     * 兼容访问 userRepository / avatarService 等
     */
    public function __get($name)
    {
        return $this->instances[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->instances[$name]);
    }

    /**
     * 动态方法转发自动导出数据库与服务的方法
     * - 保持现有显式方法兼容
     * - 新增方法无需在此类重复导出
     */
    public function __call($name, $arguments)
    {
        $target = $this->resolveForwardTarget($name);
        if ($target && method_exists($target, $name)) {
            return call_user_func_array([$target, $name], $arguments);
        }
        // 回退遍历已发现的所有实例，若存在同名方法则调用
        foreach ($this->uniqueInstances() as $repo) {
            if ($repo && method_exists($repo, $name)) {
                return call_user_func_array([$repo, $name], $arguments);
            }
        }
        throw new BadMethodCallException("方法 '" . $name . "' 不存在于 Anon_Database 或其仓库/服务中");
    }

    /**
     * 根据方法名前缀解析目标仓库或服务
     */
    private function resolveForwardTarget($method)
    {
        // 根据方法名前缀解析目标
        // 如 getUser -> UserRepository / UserService
        if (preg_match('/^(get|is|add|update|delete)([A-Z][A-Za-z0-9_]*)/', $method, $m)) {
            $subject = $m[2];
            $candidates = [
                $subject . 'Repository',
                lcfirst($subject . 'Repository'),
                'Anon_Database_' . $subject . 'Repository',
                $subject . 'Service',
                lcfirst($subject . 'Service'),
                'Anon_Database_' . $subject . 'Service',
            ];
            foreach ($candidates as $key) {
                if (isset($this->instances[$key])) {
                    return $this->instances[$key];
                }
            }
        }
        return null;
    }

    /**
     * 去重并返回唯一实例列表
     */
    protected function uniqueInstances()
    {
        $seen = [];
        $uniq = [];
        foreach ($this->instances as $obj) {
            if (is_object($obj)) {
                $hash = spl_object_hash($obj);
                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    $uniq[] = $obj;
                }
            }
        }
        return $uniq;
    }
}
