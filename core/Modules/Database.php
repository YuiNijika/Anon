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

/**
 * 数据库操作
 */
class Anon_Database
{
    /**
     * @var Anon_Database|null 实例
     */
    private static $instance = null;

    /**
     * @var array 实例容器
     */
    protected $instances = [];

    /**
     * 构造函数
     */
    private function __construct()
    {
        $this->bootstrapInstances();
    }

    /**
     * 获取实例
     * @return Anon_Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 防止克隆
     */
    private function __clone()
    {
    }

    /**
     * 防止反序列化
     */
    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }

    private static $bootstrapedClasses = [];
    private static $bootstraped = false;

    /**
     * 自动发现实例
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

    /**
     * 获取连接
     * @return mixed
     */
    private function getConnection()
    {
        if (self::$connection === null) {
            self::$connection = Anon_Database_Connection::getInstance();
        }
        return self::$connection;
    }

    /**
     * 获取数据库实例
     * @param string $table 表名
     * @return Anon_Database_QueryBuilder
     */
    public function db($table)
    {
        if (Anon_Database_Sharding::isSharded($table)) {
            // 分片逻辑处理
        }

        return new Anon_Database_QueryBuilder($this->getConnection(), ANON_DB_PREFIX . $table);
    }

    /**
     * 批量插入
     * @param string $table 表名
     * @param array $data 数据
     * @param int $batchSize 批次大小
     * @return int
     */
    public function batchInsert(string $table, array $data, int $batchSize = 1000): int
    {
        return $this->db($table)->batchInsert($data, $batchSize);
    }

    /**
     * 批量更新
     * @param string $table 表名
     * @param array $data 数据
     * @param string $keyColumn 主键
     * @param int $batchSize 批次大小
     * @return int
     */
    public function batchUpdate(string $table, array $data, string $keyColumn = 'id', int $batchSize = 1000): int
    {
        return $this->db($table)->batchUpdate($data, $keyColumn, $batchSize);
    }

    /**
     * 执行查询
     * @param string $sql SQL语句
     * @param bool $allowRawSql 允许原生SQL
     * @return mixed
     */
    public function query($sql, bool $allowRawSql = false)
    {
        $rawSqlEnabled = Anon_System_Env::get('app.database.allowRawSql', false);
        
        if (!$allowRawSql && !$rawSqlEnabled) {
            throw new RuntimeException(
                "直接执行原生 SQL 已被禁用。请使用 QueryBuilder 构建查询。" .
                "如需启用，请在配置中设置 'app.database.allowRawSql' => true"
            );
        }
        
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Anon_Debug::warn("执行原生 SQL 查询（存在安全风险）", [
                'sql_preview' => substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : '')
            ]);
        }
        
        return $this->getConnection()->query($sql);
    }

    /**
     * 创建表
     * @param string $table 表名
     * @param array $columns 字段
     * @param array $options 选项
     * @return bool
     */
    public function createTable(string $table, array $columns, array $options = []): bool
    {
        return $this->db($table)->createTable($columns, $options);
    }

    /**
     * 添加字段
     * @param string $table 表名
     * @param string $column 字段名
     * @param mixed $definition 定义
     * @param string|null $after 之后
     * @return bool
     */
    public function addColumn(string $table, string $column, $definition, ?string $after = null): bool
    {
        return $this->db($table)->addColumn($column, $definition, $after);
    }

    /**
     * 修改字段
     * @param string $table 表名
     * @param string $column 字段名
     * @param mixed $definition 定义
     * @return bool
     */
    public function modifyColumn(string $table, string $column, $definition): bool
    {
        return $this->db($table)->modifyColumn($column, $definition);
    }

    /**
     * 删除字段
     * @param string $table 表名
     * @param string $column 字段名
     * @return bool
     */
    public function dropColumn(string $table, string $column): bool
    {
        return $this->db($table)->dropColumn($column);
    }

    /**
     * 删除表
     * @param string $table 表名
     * @param bool $ifExists 是否存在
     * @return bool
     */
    public function dropTable(string $table, bool $ifExists = true): bool
    {
        return $this->db($table)->dropTable($ifExists);
    }

    /**
     * 检查表
     * @param string $table 表名
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        return $this->db($table)->tableExists();
    }

    /**
     * 预处理
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return mixed
     */
    public function prepare($sql, $params = [])
    {
        return $this->getConnection()->prepare($sql, $params);
    }

    /**
     * 获取属性
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * 检查属性
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->instances[$name]);
    }

    /**
     * 调用方法
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $target = $this->resolveForwardTarget($name);
        if ($target && method_exists($target, $name)) {
            return call_user_func_array([$target, $name], $arguments);
        }
        foreach ($this->uniqueInstances() as $repo) {
            if ($repo && method_exists($repo, $name)) {
                return call_user_func_array([$repo, $name], $arguments);
            }
        }
        throw new BadMethodCallException("方法 '" . $name . "' 不存在于 Anon_Database 或其仓库/服务中");
    }

    /**
     * 解析目标
     * @param string $method
     * @return object|null
     */
    private function resolveForwardTarget($method)
    {
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
     * 获取唯一实例
     * @return array
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
