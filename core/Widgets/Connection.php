<?php

/**
 * 数据库连接组件
 *
 * 封装 mysqli 连接，提供基础的查询和预处理功能。
 *
 * @package Anon/Core/Widgets
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_Connection
{
    /**
     * @var mysqli|null 数据库连接实例
     */
    protected $conn;

    /**
     * @var Anon_Database_Connection|null 单例实例
     */
    private static $instance = null;

    /**
     * @var mysqli|null 共享的 mysqli 连接
     */
    private static $connInstance = null;

    /**
     * @var int SQL查询计数
     */
    private $queryCount = 0;

    /**
     * 构造函数
     */
    protected function __construct()
    {
        if (self::$connInstance === null) {
            self::$connInstance = new mysqli(
                ANON_DB_HOST,
                ANON_DB_USER,
                ANON_DB_PASSWORD,
                ANON_DB_DATABASE,
                ANON_DB_PORT
            );

            if (self::$connInstance->connect_error) {
                Anon_Debug::error("数据库连接失败", ['error' => self::$connInstance->connect_error]);
                throw new RuntimeException("数据库连接失败");
            }

            self::$connInstance->set_charset(ANON_DB_CHARSET);
        }

        $this->conn = self::$connInstance;
    }

    /**
     * 获取单例实例
     * @return Anon_Database_Connection
     */
    public static function getInstance(): Anon_Database_Connection
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 执行原始 SQL 查询
     * @param string $sql SQL 语句
     * @return array|int 查询结果数组或影响的行数
     * @throws RuntimeException
     */
    public function query(string $sql)
    {
        $this->queryCount++;

        if (
            preg_match('/(union|select.*from|insert.*into|update.*set|delete.*from|drop|alter|create|exec|execute)/i', $sql) &&
            preg_match('/(\$|%|_|\'|"|`)/', $sql)
        ) {
            Anon_Debug::warn("警告：检测到可疑的 SQL 查询模式", ['sql_preview' => substr($sql, 0, 100)]);
        }

        $result = $this->conn->query($sql);
        if (!$result) {
            $errorMsg = self::sanitizeError($this->conn->error);
            Anon_Debug::error("SQL 查询错误", ['error' => $errorMsg]);
            throw new RuntimeException("SQL 查询错误");
        }

        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }

        return $this->conn->affected_rows;
    }

    /**
     * 准备预处理语句并绑定参数
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return mysqli_stmt 预处理语句对象
     * @throws RuntimeException 当预处理失败时抛出异常
     */
    public function prepare(string $sql, array $params = []): mysqli_stmt
    {
        $this->queryCount++;

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $errorMsg = self::sanitizeError($this->conn->error);
            Anon_Debug::error("SQL 预处理错误", ['error' => $errorMsg]);
            throw new RuntimeException("SQL 预处理错误");
        }

        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            foreach ($params as $param) {
                if (is_null($param)) {
                    $types .= 's';
                    $bindParams[] = null;
                } elseif (is_int($param)) {
                    $types .= 'i';
                    $bindParams[] = $param;
                } elseif (is_float($param)) {
                    $types .= 'd';
                    $bindParams[] = $param;
                } elseif (is_bool($param)) {
                    $types .= 'i';
                    $bindParams[] = $param ? 1 : 0;
                } else {
                    $types .= 's';
                    $bindParams[] = $param;
                }
            }
            if (!$stmt->bind_param($types, ...$bindParams)) {
                $errorMsg = self::sanitizeError($this->conn->error);
                Anon_Debug::error("SQL 参数绑定错误", ['error' => $errorMsg]);
                $stmt->close();
                throw new RuntimeException("SQL 参数绑定错误");
            }
        }
        return $stmt;
    }

    /**
     * 获取查询次数
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * 清理错误信息，移除可能的敏感信息
     * @param string $error 原始错误信息
     * @return string 清理后的错误信息
     */
    private static function sanitizeError(string $error): string
    {
        $logDetailed = false;
        if (Anon_System_Env::isInitialized()) {
            $logDetailed = Anon_System_Env::get('app.debug.logDetailedErrors', false);
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
            $logDetailed = false;
        }

        if (!$logDetailed) {
            $error = preg_replace('/\/[^\s]+\.php:\d+/', '[file]:[line]', $error);
            $error = preg_replace('/\b(?:database|table|column|user|password)\s*[=:]\s*[\'"]?[^\'"\s]+[\'"]?/i', '[sensitive]', $error);
        }

        return $error;
    }

    /**
     * 获取带前缀的表名
     * @param string $table 表名
     * @return string
     */
    private function getTableName(string $table): string
    {
        $prefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';
        return $prefix . $table;
    }

    /**
     * 创建查询构建器实例
     * @param string $table 表名
     * @return Anon_Database_QueryBuilder 查询构建器实例
     * @throws InvalidArgumentException 当表名无效时抛出异常
     */
    public function db(string $table): Anon_Database_QueryBuilder
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        return new Anon_Database_QueryBuilder($this, $this->getTableName($table));
    }
}
