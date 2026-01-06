<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_Connection
{
    protected $conn;

    private static $instance = null;
    private static $connInstance = null;

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
                error_log("数据库连接失败: " . self::$connInstance->connect_error);
                throw new RuntimeException("数据库连接失败");
            }

            self::$connInstance->set_charset(ANON_DB_CHARSET);
        }
        
        $this->conn = self::$connInstance;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 执行原始 SQL 查询
     * 此方法直接执行 SQL，不进行参数绑定
     * 请确保 $sql 不包含用户输入，优先使用 prepare() 和 QueryBuilder
     * 
     * @param string $sql SQL 语句
     * @return array|int 查询结果数组或影响的行数
     * @throws RuntimeException
     */
    public function query($sql)
    {
        // 检测可能的 SQL 注入特征
        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            if (preg_match('/(union|select.*from|insert.*into|update.*set|delete.*from|drop|alter|create|exec|execute)/i', $sql) && 
                preg_match('/(\$|%|_|\'|"|`)/', $sql)) {
                error_log("警告：检测到可疑的 SQL 查询模式: " . substr($sql, 0, 100));
            }
        }
        
        $result = $this->conn->query($sql);
        if (!$result) {
            $errorMsg = self::sanitizeError($this->conn->error);
            error_log("SQL 查询错误: " . $errorMsg);
            throw new RuntimeException("SQL 查询错误");
        }

        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free(); // 释放结果集
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
    public function prepare($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            // 不记录完整的 SQL 错误信息，防止泄露敏感信息
            $errorMsg = self::sanitizeError($this->conn->error);
            error_log("SQL 预处理错误: " . $errorMsg);
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
                error_log("SQL 参数绑定错误: " . $errorMsg);
                $stmt->close();
                throw new RuntimeException("SQL 参数绑定错误");
            }
        }
        return $stmt;
    }

    /**
     * 清理错误信息，移除可能的敏感信息
     * @param string $error 原始错误信息
     * @return string 清理后的错误信息
     */
    private static function sanitizeError(string $error): string
    {
        // 检查是否允许记录详细错误
        $logDetailed = false;
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            $logDetailed = Anon_System_Env::get('app.debug.logDetailedErrors', false);
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
            $logDetailed = false; // 默认不记录详细错误
        }
        
        // 移除可能的敏感路径信息
        if (!$logDetailed) {
            $error = preg_replace('/\/[^\s]+\.php:\d+/', '[file]:[line]', $error);
        }
        
        // 移除可能的数据库名、表名等敏感信息
        if (!$logDetailed) {
            $error = preg_replace('/\b(?:database|table|column|user|password)\s*[=:]\s*[\'"]?[^\'"\s]+[\'"]?/i', '[sensitive]', $error);
        }
        
        return $error;
    }

    /**
     * 创建查询构建器实例
     * @param string $table 表名
     * @return Anon_Database_QueryBuilder 查询构建器实例
     * @throws InvalidArgumentException 当表名无效时抛出异常
     */
    public function db($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        return new Anon_Database_QueryBuilder($this, ANON_DB_PREFIX . $table);
    }

}

