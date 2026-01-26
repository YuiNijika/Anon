<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 查询构建器
 * 提供流畅的数据库查询接口
 */
class Anon_Database_QueryBuilder
{
    /**
     * @var Anon_Database_Connection|mysqli 数据库连接
     */
    private $connection;

    /**
     * @var string 表名
     */
    private $table;

    /**
     * @var array SELECT 字段
     */
    private $selects = [];

    /**
     * @var array WHERE 条件
     */
    private $wheres = [];

    /**
     * @var array JOIN 子句
     */
    private $joins = [];

    /**
     * @var array ORDER BY 子句
     */
    private $orders = [];

    /**
     * @var array GROUP BY 子句
     */
    private $groups = [];

    /**
     * @var string|null HAVING 子句
     */
    private $having = null;

    /**
     * @var int|null LIMIT 限制
     */
    private $limit = null;

    /**
     * @var int|null OFFSET 偏移
     */
    private $offset = null;

    /**
     * @var array 绑定参数
     */
    private $bindings = [];

    /**
     * @var bool 是否启用查询缓存
     */
    private $cacheEnabled = false;

    /**
     * @var int|null 查询缓存时间（秒）
     */
    private $cacheTtl = null;

    /**
     * @var string|null 查询缓存键
     */
    private $cacheKey = null;

    /**
     * @param Anon_Database_Connection|mysqli $connection 数据库连接
     * @param string $table 表名
     */
    public function __construct($connection, string $table)
    {
        $this->connection = $connection;
        // 验证表名安全性
        if (!preg_match('/^[a-zA-Z0-9_`]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        $this->table = $table;
    }

    /**
     * 准备预处理语句
     * 兼容 Anon_Database_Connection 和 mysqli 两种连接类型
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return mysqli_stmt|null 预处理语句对象，失败时返回 null
     */
    private function prepareStatement($sql, $params = [])
    {
        if ($this->connection instanceof Anon_Database_Connection) {
            return $this->connection->prepare($sql, $params);
        } elseif ($this->connection instanceof mysqli) {
            // 使用 mysqli 的 prepare 方法，mysqli 只接受一个参数需要手动绑定
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return null;
            }
            // 手动绑定参数到预处理语句
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
                    $stmt->close();
                    return null;
                }
            }
            return $stmt;
        }
        return null;
    }

    /**
     * 选择字段
     * @param string|array $columns 字段名
     * @return $this
     */
    public function select($columns = ['*'])
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        // 验证所有字段名安全性
        foreach ($columns as $column) {
            // 允许 * 和带反引号的字段名
            if ($column !== '*' && !preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
                throw new InvalidArgumentException("无效的字段名: {$column}");
            }
        }

        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    /**
     * WHERE 条件
     * @param string|callable $column 字段名或闭包
     * @param string|null $operator 操作符
     * @param mixed $value 值
     * @param string $boolean 逻辑连接符 (AND|OR)
     * @return $this
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'AND')
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (is_string($column) && !preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (is_string($operator)) {
            $allowedOps = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'];
            if (!in_array(strtoupper($operator), $allowedOps)) {
                throw new InvalidArgumentException("无效的操作符: {$operator}");
            }
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * OR WHERE 条件
     * @param string|callable $column 字段名或闭包
     * @param string|null $operator 操作符
     * @param mixed $value 值
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE IN 条件
     * @param string $column 字段名
     * @param array $values 值数组
     * @param string $boolean 逻辑连接符
     * @return $this
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND')
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * WHERE NULL 条件
     * @param string $column 字段名
     * @param string $boolean 逻辑连接符
     * @return $this
     */
    public function whereNull(string $column, string $boolean = 'AND')
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * WHERE NOT NULL 条件
     * @param string $column 字段名
     * @param string $boolean 逻辑连接符
     * @return $this
     */
    public function whereNotNull(string $column, string $boolean = 'AND')
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * 嵌套 WHERE 条件
     * @param callable $callback 回调函数
     * @param string $boolean 逻辑连接符
     * @return $this
     */
    public function whereNested(callable $callback, string $boolean = 'AND')
    {
        $query = new self($this->connection, $this->table);
        $callback($query);

        $this->wheres[] = [
            'type' => 'nested',
            'query' => $query,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $query->bindings);
        return $this;
    }

    /**
     * JOIN 连接
     * @param string $table 表名
     * @param string $first 第一个字段
     * @param string $operator 操作符
     * @param string $second 第二个字段
     * @param string $type JOIN 类型
     * @return $this
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER')
    {
        // 验证表名安全性
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $first)) {
            throw new InvalidArgumentException("无效的字段名: {$first}");
        }
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $second)) {
            throw new InvalidArgumentException("无效的字段名: {$second}");
        }
        
        // 验证操作符
        $allowedOps = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE'];
        if (!in_array(strtoupper($operator), $allowedOps)) {
            throw new InvalidArgumentException("无效的操作符: {$operator}");
        }
        
        // 验证 JOIN 类型
        $allowedTypes = ['INNER', 'LEFT', 'RIGHT', 'FULL'];
        $type = strtoupper($type);
        if (!in_array($type, $allowedTypes)) {
            throw new InvalidArgumentException("无效的 JOIN 类型: {$type}");
        }
        
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * LEFT JOIN
     * @param string $table 表名
     * @param string $first 第一个字段
     * @param string $operator 操作符
     * @param string $second 第二个字段
     * @return $this
     */
    public function leftJoin(string $table, string $first, string $operator, string $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * ORDER BY 排序
     * @param string $column 字段名
     * @param string $direction 排序方向
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC')
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }
        
        // 验证排序方向
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new InvalidArgumentException("无效的排序方向: {$direction}");
        }
        
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction
        ];

        return $this;
    }

    /**
     * GROUP BY 分组
     * @param string|array $columns 字段名
     * @return $this
     */
    public function groupBy($columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        
        // 验证所有字段名安全性
        foreach ($columns as $column) {
            if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
                throw new InvalidArgumentException("无效的字段名: {$column}");
            }
        }

        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    /**
     * HAVING 条件
     * @param string $column 字段名
     * @param string $operator 操作符
     * @param mixed $value 值
     * @return $this
     */
    public function having(string $column, string $operator, $value)
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }
        
        // 验证操作符
        $allowedOps = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE'];
        if (!in_array(strtoupper($operator), $allowedOps)) {
            throw new InvalidArgumentException("无效的操作符: {$operator}");
        }
        
        $this->having = "{$column} {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * LIMIT 限制
     * @param int $limit 限制数量
     * @return $this
     */
    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET 偏移
     * @param int $offset 偏移量
     * @return $this
     */
    public function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * 构建 SQL 语句
     * @return string
     */
    private function toSql(): string
    {
        $sql = 'SELECT ';

        // SELECT 子句
        if (empty($this->selects)) {
            $sql .= '*';
        } else {
            $sql .= implode(', ', $this->selects);
        }

        // FROM 子句
        $sql .= ' FROM ' . $this->table;

        // JOIN 子句
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // WHERE 子句
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        // GROUP BY 子句
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        // HAVING 子句
        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }

        // ORDER BY 子句
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // LIMIT 子句
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        // OFFSET 子句
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * 构建 WHERE 子句
     * @return string
     */
    private function buildWhereClause(): string
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index > 0 ? $where['boolean'] . ' ' : '';

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = "{$boolean}{$where['column']} {$where['operator']} ?";
                    break;

                case 'in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $clauses[] = "{$boolean}{$where['column']} IN ({$placeholders})";
                    break;

                case 'null':
                    $clauses[] = "{$boolean}{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $clauses[] = "{$boolean}{$where['column']} IS NOT NULL";
                    break;

                case 'nested':
                    $nestedSql = $where['query']->buildWhereClause();
                    $clauses[] = "{$boolean}({$nestedSql})";
                    break;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * 获取所有记录
     * @return array
     */
    public function get(): array
    {
        // 检查查询缓存
        if ($this->cacheEnabled) {
            $cacheKey = $this->getCacheKey();
            $cached = Anon_Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $startTime = microtime(true);
        $sql = $this->toSql();
        
        // 如果连接有 prepare 方法，使用预处理语句
        $stmt = $this->prepareStatement($sql, $this->bindings);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            $stmt->close();
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            // 记录查询性能
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::query($sql, $this->bindings, $duration);
                
                // 慢查询检测和索引建议，超过 100ms 视为慢查询
                if ($duration > 100) {
                    self::analyzeSlowQuery($sql, $this->table, $duration);
                }
            }
            
            // 保存到缓存
            if ($this->cacheEnabled) {
                $cacheKey = $this->getCacheKey();
                Anon_Cache::set($cacheKey, $rows, $this->cacheTtl);
            }
            
            return $rows;
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            $result = $this->connection->query($sql);
            $duration = (microtime(true) - $startTime) * 1000;
            
            // 记录查询性能
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::query($sql, $this->bindings, $duration);
                
                // 慢查询检测，超过 100ms 视为慢查询
                if ($duration > 100) {
                    self::analyzeSlowQuery($sql, $this->table, $duration);
                }
            }
            
            // 保存到缓存
            if ($this->cacheEnabled && is_array($result)) {
                $cacheKey = $this->getCacheKey();
                Anon_Cache::set($cacheKey, $result, $this->cacheTtl);
            }
            
            return $result;
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
    }

    /**
     * 分析慢查询并建议索引
     * @param string $sql SQL 语句
     * @param string $table 表名
     * @param float $duration 查询耗时（毫秒）
     */
    private static function analyzeSlowQuery(string $sql, string $table, float $duration): void
    {
        // 提取 WHERE 条件中的字段
        $suggestedIndexes = [];
        
        // 检测 WHERE 条件中的字段
        if (preg_match('/WHERE\s+([^ORDER|GROUP|LIMIT]+)/i', $sql, $matches)) {
            $whereClause = $matches[1];
            
            // 提取字段名
            if (preg_match_all('/(\w+)\s*[=<>!]+/i', $whereClause, $fieldMatches)) {
                foreach ($fieldMatches[1] as $field) {
                    $field = trim($field);
                    if (!in_array($field, ['AND', 'OR', 'IN', 'NOT', 'NULL', 'IS'])) {
                        $suggestedIndexes[] = $field;
                    }
                }
            }
        }
        
        // 检测 ORDER BY 中的字段
        if (preg_match('/ORDER\s+BY\s+(\w+)/i', $sql, $matches)) {
            $orderField = trim($matches[1]);
            if (!in_array($orderField, $suggestedIndexes)) {
                $suggestedIndexes[] = $orderField;
            }
        }
        
        if (!empty($suggestedIndexes)) {
            $indexSuggestion = "CREATE INDEX idx_" . implode('_', $suggestedIndexes) . " ON {$table} (" . implode(', ', $suggestedIndexes) . ")";
            
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::warn("慢查询检测", [
                    'sql' => substr($sql, 0, 200),
                    'duration' => round($duration, 2) . 'ms',
                    'table' => $table,
                    'suggested_index' => $indexSuggestion
                ]);
            }
        }
    }

    /**
     * 获取第一条记录
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * 获取单条记录的值
     * @param string $column 字段名
     * @return mixed
     */
    public function value(string $column)
    {
        // 验证字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }
        
        $this->selects = [$column];
        $result = $this->first();
        return $result[$column] ?? null;
    }

    /**
     * 统计数量
     * @param string $column 字段名，默认为 *
     * @return int
     */
    public function count(string $column = '*'): int
    {
        // 验证列名安全性 只允许字母、数字、下划线、点号和 *
        if ($column !== '*' && !preg_match('/^[a-zA-Z0-9_`\.]+$/', $column)) {
            throw new InvalidArgumentException("无效的列名: {$column}");
        }
        
        $this->selects = ["COUNT({$column}) as count"];
        $result = $this->first();
        return (int)($result['count'] ?? 0);
    }

    /**
     * 检查是否存在
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 插入数据
     * @param array $data 数据数组
     * @return int|false 插入的ID或false
     */
    public function insert(array $data)
    {
        if (empty($data)) {
            return false;
        }

        // 处理批量插入
        if (isset($data[0]) && is_array($data[0])) {
            return $this->insertBatch($data);
        }

        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        // 为字段名添加反引号，避免保留关键字冲突
        $escapedColumns = array_map(function($col) {
            return "`{$col}`";
        }, $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $escapedColumns) . ") VALUES ({$placeholders})";

        // 执行插入
        $stmt = $this->prepareStatement($sql, $values);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            // 获取真实的mysqli连接
            $conn = $this->getMysqliConnection();
            $insertId = $conn->insert_id ?? null;
            $stmt->close(); // 关闭语句
            return $insertId !== null ? (int)$insertId : true;
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            $result = $this->connection->query($sql);
            $conn = $this->getMysqliConnection();
            $insertId = $conn->insert_id ?? null;
            return $insertId !== null ? (int)$insertId : ($result !== false);
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
    }

    /**
     * 批量插入（内部方法）
     * @param array $data 数据数组
     * @return int|false 插入的行数或false
     */
    private function insertBatch(array $data)
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data[0]);
        $values = [];
        $placeholders = [];

        foreach ($data as $row) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = $row[$column] ?? null;
                $values[] = $row[$column] ?? null;
            }
            $placeholders[] = '(' . str_repeat('?,', count($rowValues) - 1) . '?)';
        }
        
        // 为字段名添加反引号，避免保留关键字冲突
        $escapedColumns = array_map(function($col) {
            return "`{$col}`";
        }, $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $escapedColumns) . ") VALUES " . implode(', ', $placeholders);

        // 执行批量插入
        $stmt = $this->prepareStatement($sql, $values);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            $result = $this->connection->query($sql);
            $conn = $this->getMysqliConnection();
            return $result !== false ? $conn->affected_rows : false;
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
    }

    /**
     * 更新数据
     * @param array $data 数据数组
     * @return int|false 影响的行数或false
     */
    public function update(array $data)
    {
        if (empty($data)) {
            return false;
        }

        $setClauses = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
            $bindings = array_merge($bindings, $this->bindings);
        }

        // 执行更新
        $stmt = $this->prepareStatement($sql, $bindings);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            $result = $this->connection->query($sql);
            $conn = $this->getMysqliConnection();
            return $result !== false ? $conn->affected_rows : false;
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
    }

    /**
     * 删除数据
     * @return int|false 影响的行数或false
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        // 执行删除
        $stmt = $this->prepareStatement($sql, $this->bindings);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            $result = $this->connection->query($sql);
            $conn = $this->getMysqliConnection();
            return $result !== false ? $conn->affected_rows : false;
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
    }

    /**
     * 获取用于调试的原始 SQL
     * @return string
     */
    public function toRawSql(): string
    {
        $sql = $this->toSql();
        foreach ($this->bindings as $binding) {
            $value = is_string($binding) ? "'{$binding}'" : $binding;
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }

    /**
     * 获取真实的 mysqli 连接对象
     * @return mysqli
     */
    private function getMysqliConnection(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }
        
        // 如果是 Anon_Database_Connection，尝试通过反射获取 conn 属性
        if ($this->connection instanceof Anon_Database_Connection) {
            $reflection = new ReflectionClass($this->connection);
            $property = $reflection->getProperty('conn');
            $property->setAccessible(true);
            return $property->getValue($this->connection);
        }
        
        throw new RuntimeException("无法获取 mysqli 连接对象");
    }

    /**
     * 游标分页 - 使用主键游标
     * @param int $limit 每页数量
     * @param int|string|null $cursor 游标值（主键ID）
     * @param string $cursorColumn 游标字段名，默认为 id
     * @return array 包含 data 和 next_cursor
     */
    public function cursorPaginate(int $limit = 20, $cursor = null, string $cursorColumn = 'id'): array
    {
        // 验证游标字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $cursorColumn)) {
            throw new InvalidArgumentException("无效的游标字段名: {$cursorColumn}");
        }

        // 如果有游标，添加 WHERE 条件
        if ($cursor !== null) {
            $this->where($cursorColumn, '>', $cursor);
        }

        // 确保按游标字段排序
        $hasOrder = false;
        foreach ($this->orders as $order) {
            if ($order['column'] === $cursorColumn) {
                $hasOrder = true;
                break;
            }
        }
        if (!$hasOrder) {
            $this->orderBy($cursorColumn, 'ASC');
        }

        // 查询 limit + 1 条，用于判断是否有下一页
        $this->limit($limit + 1);
        $results = $this->get();

        // 判断是否有下一页
        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results); // 移除多余的一条
        }

        // 获取下一页游标
        $nextCursor = null;
        if ($hasNext && !empty($results)) {
            $lastItem = end($results);
            $nextCursor = $lastItem[$cursorColumn] ?? null;
        }

        return [
            'data' => $results,
            'next_cursor' => $nextCursor,
            'has_next' => $hasNext
        ];
    }

    /**
     * 游标分页 - 使用时间戳游标
     * @param int $limit 每页数量
     * @param int|string|null $cursor 游标值（时间戳）
     * @param string $cursorColumn 游标字段名，默认为 created_at
     * @return array 包含 data 和 next_cursor
     */
    public function cursorPaginateByTime(int $limit = 20, $cursor = null, string $cursorColumn = 'created_at'): array
    {
        // 验证游标字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $cursorColumn)) {
            throw new InvalidArgumentException("无效的游标字段名: {$cursorColumn}");
        }

        // 如果有游标，添加 WHERE 条件
        if ($cursor !== null) {
            $this->where($cursorColumn, '<', $cursor);
        }

        // 确保按时间戳降序排序
        $hasOrder = false;
        foreach ($this->orders as $order) {
            if ($order['column'] === $cursorColumn) {
                $hasOrder = true;
                break;
            }
        }
        if (!$hasOrder) {
            $this->orderBy($cursorColumn, 'DESC');
        }

        // 查询 limit + 1 条，用于判断是否有上一页
        $this->limit($limit + 1);
        $results = $this->get();

        // 判断是否有上一页
        $hasPrev = count($results) > $limit;
        if ($hasPrev) {
            array_pop($results); // 移除多余的一条
        }

        // 获取上一页游标
        $prevCursor = null;
        if ($hasPrev && !empty($results)) {
            $lastItem = end($results);
            $prevCursor = $lastItem[$cursorColumn] ?? null;
        }

        return [
            'data' => $results,
            'prev_cursor' => $prevCursor,
            'has_prev' => $hasPrev
        ];
    }

    /**
     * 启用查询缓存
     * @param int|null $ttl 缓存时间（秒），null 表示使用默认值
     * @param string|null $key 自定义缓存键，null 表示自动生成
     * @return $this
     */
    public function cache(?int $ttl = null, ?string $key = null)
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl ?? 3600; // 默认 1 小时
        $this->cacheKey = $key;
        return $this;
    }

    /**
     * 批量插入数据
     * @param array $data 数据数组，支持单次插入 1000+ 条
     * @param int $batchSize 批次大小，默认 1000
     * @return int 成功插入的总行数
     */
    public function batchInsert(array $data, int $batchSize = 1000): int
    {
        if (empty($data)) {
            return 0;
        }

        $totalInserted = 0;
        $batches = array_chunk($data, $batchSize);

        foreach ($batches as $batch) {
            $result = $this->insertBatch($batch);
            if ($result !== false) {
                $totalInserted += $result;
            }
        }

        return $totalInserted;
    }

    /**
     * 批量更新数据
     * @param array $data 数据数组，格式：[['id' => 1, 'name' => 'xxx'], ...]
     * @param string $keyColumn 主键字段名，默认为 id
     * @param int $batchSize 批次大小，默认 1000
     * @return int 成功更新的总行数
     */
    public function batchUpdate(array $data, string $keyColumn = 'id', int $batchSize = 1000): int
    {
        if (empty($data)) {
            return 0;
        }

        // 验证主键字段名安全性
        if (!preg_match('/^[a-zA-Z0-9_`\.]+$/', $keyColumn)) {
            throw new InvalidArgumentException("无效的主键字段名: {$keyColumn}");
        }

        $totalUpdated = 0;
        $batches = array_chunk($data, $batchSize);

        foreach ($batches as $batch) {
            $updated = 0;
            foreach ($batch as $row) {
                if (!isset($row[$keyColumn])) {
                    continue;
                }

                $keyValue = $row[$keyColumn];
                unset($row[$keyColumn]);

                if (empty($row)) {
                    continue;
                }

                // 创建新的查询构建器实例
                $query = new self($this->connection, $this->table);
                $result = $query->where($keyColumn, '=', $keyValue)->update($row);
                if ($result !== false) {
                    $updated += $result;
                }
            }
            $totalUpdated += $updated;
        }

        return $totalUpdated;
    }

    /**
     * 获取查询缓存键
     * @return string
     */
    private function getCacheKey(): string
    {
        if ($this->cacheKey !== null) {
            return $this->cacheKey;
        }

        // 基于 SQL 和参数生成缓存键
        $sql = $this->toSql();
        $key = 'query:' . hash('sha256', $sql . serialize($this->bindings));
        return $key;
    }

    /**
     * 创建数据表
     * @param array $columns 字段定义数组，格式：['column_name' => 'type options', ...]
     * @param array $options 表选项，如：['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'ifNotExists' => true]
     * @return bool 是否创建成功
     */
    public function createTable(array $columns, array $options = []): bool
    {
        if (empty($columns)) {
            throw new InvalidArgumentException('表字段不能为空');
        }

        // 验证表选项安全性
        $engine = $this->validateTableOption($options['engine'] ?? 'InnoDB', ['InnoDB', 'MyISAM', 'MEMORY']);
        $charset = $this->validateTableOption($options['charset'] ?? 'utf8mb4', ['utf8mb4', 'utf8', 'latin1']);
        $collate = $this->validateTableOption($options['collate'] ?? 'utf8mb4_unicode_ci', ['utf8mb4_unicode_ci', 'utf8mb4_general_ci', 'utf8_general_ci']);
        $ifNotExists = $options['ifNotExists'] ?? true;

        $columnDefinitions = [];
        foreach ($columns as $name => $definition) {
            // 验证字段名安全性
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                throw new InvalidArgumentException("无效的字段名: {$name}");
            }
            
            if (is_string($definition)) {
                // 验证字符串定义中不包含危险字符
                if (preg_match('/[;\'"]|--|\/\*|\*\/|DROP|DELETE|TRUNCATE/i', $definition)) {
                    throw new InvalidArgumentException("字段定义包含不安全的字符: {$name}");
                }
                $columnDefinitions[] = "`{$name}` {$definition}";
            } elseif (is_array($definition)) {
                $type = $definition['type'] ?? 'VARCHAR(255)';
                // 验证类型安全性
                if (preg_match('/[;\'"]|--|\/\*|\*\/|DROP|DELETE|TRUNCATE/i', $type)) {
                    throw new InvalidArgumentException("字段类型包含不安全的字符: {$name}");
                }
                $null = isset($definition['null']) && $definition['null'] ? 'NULL' : 'NOT NULL';
                $default = isset($definition['default']) ? "DEFAULT " . $this->formatDefault($definition['default']) : '';
                $autoIncrement = isset($definition['autoIncrement']) && $definition['autoIncrement'] ? 'AUTO_INCREMENT' : '';
                $primary = isset($definition['primary']) && $definition['primary'] ? 'PRIMARY KEY' : '';
                $comment = isset($definition['comment']) ? "COMMENT '" . $this->escapeComment($definition['comment']) . "'" : '';
                
                $columnDef = "`{$name}` {$type} {$null}";
                if ($default) $columnDef .= " {$default}";
                if ($autoIncrement) $columnDef .= " {$autoIncrement}";
                if ($primary) $columnDef .= " {$primary}";
                if ($comment) $columnDef .= " {$comment}";
                
                $columnDefinitions[] = trim($columnDef);
            }
        }

        $ifNotExistsClause = $ifNotExists ? 'IF NOT EXISTS' : '';
        $sql = "CREATE TABLE {$ifNotExistsClause} `{$this->table}` (" . 
               implode(', ', $columnDefinitions) . 
               ") ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate}";

        return $this->executeSchemaQuery($sql);
    }

    /**
     * 添加字段
     * @param string $column 字段名
     * @param string|array $definition 字段定义
     * @param string|null $after 在哪个字段之后（可选）
     * @return bool 是否添加成功
     */
    public function addColumn(string $column, $definition, ?string $after = null): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $columnDef = $this->buildColumnDefinition($column, $definition);
        
        // 验证 after 字段名安全性
        if ($after !== null) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $after)) {
                throw new InvalidArgumentException("无效的字段名: {$after}");
            }
        }
        
        $afterClause = $after ? " AFTER `{$after}`" : '';
        $sql = "ALTER TABLE `{$this->table}` ADD COLUMN {$columnDef}{$afterClause}";

        return $this->executeSchemaQuery($sql);
    }

    /**
     * 修改字段
     * @param string $column 字段名
     * @param string|array $definition 新的字段定义
     * @return bool 是否修改成功
     */
    public function modifyColumn(string $column, $definition): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $columnDef = $this->buildColumnDefinition($column, $definition);
        $sql = "ALTER TABLE `{$this->table}` MODIFY COLUMN {$columnDef}";

        return $this->executeSchemaQuery($sql);
    }

    /**
     * 删除字段
     * @param string $column 字段名
     * @return bool 是否删除成功
     */
    public function dropColumn(string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("无效的字段名: {$column}");
        }

        $sql = "ALTER TABLE `{$this->table}` DROP COLUMN `{$column}`";

        return $this->executeSchemaQuery($sql);
    }

    /**
     * 删除表
     * @param bool $ifExists 是否使用 IF EXISTS
     * @return bool 是否删除成功
     */
    public function dropTable(bool $ifExists = true): bool
    {
        $ifExistsClause = $ifExists ? 'IF EXISTS' : '';
        $sql = "DROP TABLE {$ifExistsClause} `{$this->table}`";

        return $this->executeSchemaQuery($sql);
    }

    /**
     * 检查表是否存在
     * @return bool
     */
    public function tableExists(): bool
    {
        $conn = $this->getMysqliConnection();
        $database = $conn->query("SELECT DATABASE()")->fetch_row()[0];
        $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
        
        $stmt = $conn->prepare($sql);
        $tableName = $this->table;
        $stmt->bind_param('ss', $database, $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];
        $stmt->close();
        
        return (int)$count > 0;
    }

    /**
     * 构建字段定义
     * @param string $column 字段名
     * @param string|array $definition 字段定义
     * @return string
     */
    private function buildColumnDefinition(string $column, $definition): string
    {
        if (is_string($definition)) {
            // 验证字符串定义中不包含危险字符
            if (preg_match('/[;\'"]|--|\/\*|\*\/|DROP|DELETE|TRUNCATE/i', $definition)) {
                throw new InvalidArgumentException("字段定义包含不安全的字符: {$column}");
            }
            return "`{$column}` {$definition}";
        }

        if (is_array($definition)) {
            $type = $definition['type'] ?? 'VARCHAR(255)';
            // 验证类型安全性
            if (preg_match('/[;\'"]|--|\/\*|\*\/|DROP|DELETE|TRUNCATE/i', $type)) {
                throw new InvalidArgumentException("字段类型包含不安全的字符: {$column}");
            }
            $null = isset($definition['null']) && $definition['null'] ? 'NULL' : 'NOT NULL';
            $default = isset($definition['default']) ? "DEFAULT " . $this->formatDefault($definition['default']) : '';
            $autoIncrement = isset($definition['autoIncrement']) && $definition['autoIncrement'] ? 'AUTO_INCREMENT' : '';
            $primary = isset($definition['primary']) && $definition['primary'] ? 'PRIMARY KEY' : '';
            $comment = isset($definition['comment']) ? "COMMENT '" . $this->escapeComment($definition['comment']) . "'" : '';
            
            $columnDef = "`{$column}` {$type} {$null}";
            if ($default) $columnDef .= " {$default}";
            if ($autoIncrement) $columnDef .= " {$autoIncrement}";
            if ($primary) $columnDef .= " {$primary}";
            if ($comment) $columnDef .= " {$comment}";
            
            return trim($columnDef);
        }

        throw new InvalidArgumentException("无效的字段定义");
    }

    /**
     * 验证表选项安全性
     * @param string $value 选项值
     * @param array $allowed 允许的值列表
     * @return string
     */
    private function validateTableOption(string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("无效的表选项值: {$value}，允许的值: " . implode(', ', $allowed));
        }
        return $value;
    }

    /**
     * 转义注释内容
     * @param string $comment 注释内容
     * @return string
     */
    private function escapeComment(string $comment): string
    {
        // 移除单引号、反斜杠等危险字符
        $comment = str_replace(['\'', '\\', "\0", "\n", "\r"], ['', '', '', '', ''], $comment);
        // 使用 mysqli_real_escape_string 进一步转义
        $conn = $this->getMysqliConnection();
        return mysqli_real_escape_string($conn, $comment);
    }

    /**
     * 格式化默认值
     * @param mixed $value 默认值
     * @return string
     */
    private function formatDefault($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_string($value)) {
            // 使用 mysqli_real_escape_string 转义
            $conn = $this->getMysqliConnection();
            return "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            // 验证是否为有效数字
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("无效的默认值: " . var_export($value, true));
            }
            return (string)$value;
        }
        // 其他类型转换为字符串并转义
        $conn = $this->getMysqliConnection();
        return "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
    }

    /**
     * 执行表结构操作 SQL
     * @param string $sql SQL 语句
     * @return bool
     */
    private function executeSchemaQuery(string $sql): bool
    {
        $conn = $this->getMysqliConnection();
        $result = $conn->query($sql);
        
        if ($result === false) {
            throw new RuntimeException("SQL 执行失败: " . $conn->error . " | SQL: " . $sql);
        }
        
        return true;
    }

    /**
     * 创建查询构建器实例
     * @param string $table 表名
     * @return self 查询构建器实例
     */
    public static function table(string $table): self
    {
        if (!class_exists('Anon_Database')) {
            throw new RuntimeException('Anon_Database class not found');
        }
        
        $db = Anon_Database::getInstance();
        if (!$db) {
            throw new RuntimeException('Failed to get Anon_Database instance');
        }
        
        $queryBuilder = $db->db($table);
        if (!($queryBuilder instanceof self)) {
            throw new RuntimeException('Failed to create QueryBuilder instance');
        }
        
        return $queryBuilder;
    }
}

