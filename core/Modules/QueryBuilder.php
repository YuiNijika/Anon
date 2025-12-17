<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 查询构建器
 * 提供流畅的数据库查询接口
 */
class Anon_QueryBuilder
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
     * @param Anon_Database_Connection|mysqli $connection 数据库连接
     * @param string $table 表名
     */
    public function __construct($connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
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
        // 如果第一个参数是闭包，处理嵌套条件
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // 如果只有两个参数，默认使用 =
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
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
        $sql = $this->toSql();
        
        // 如果连接有 prepare 方法，使用预处理语句
        if (method_exists($this->connection, 'prepare')) {
            $stmt = $this->connection->prepare($sql, $this->bindings);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $result->free(); // 释放结果集
                $stmt->close(); // 关闭语句
                return $rows;
            }
        }
        
        // 回退到直接查询
        if (method_exists($this->connection, 'query')) {
            return $this->connection->query($sql);
        }
        
        throw new RuntimeException("不支持的数据库连接类型");
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

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";

        // 执行插入
        if (method_exists($this->connection, 'prepare')) {
            $stmt = $this->connection->prepare($sql, $values);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->execute();
                // 获取真实的mysqli连接
                $conn = $this->getMysqliConnection();
                $insertId = $conn->insert_id ?? null;
                $stmt->close(); // 关闭语句
                return $insertId !== null ? (int)$insertId : true;
            }
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
     * 批量插入
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

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);

        // 执行批量插入
        if (method_exists($this->connection, 'prepare')) {
            $stmt = $this->connection->prepare($sql, $values);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close(); // 关闭语句
                return $affected;
            }
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
        if (method_exists($this->connection, 'prepare')) {
            $stmt = $this->connection->prepare($sql, $bindings);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close(); // 关闭语句
                return $affected;
            }
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
        if (method_exists($this->connection, 'prepare')) {
            $stmt = $this->connection->prepare($sql, $this->bindings);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close(); // 关闭语句
                return $affected;
            }
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
}

