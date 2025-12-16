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
        if (preg_match('/(union|select.*from|insert.*into|update.*set|delete.*from|drop|alter|create|exec|execute)/i', $sql) && 
            preg_match('/(\$|%|_|\'|"|`)/', $sql)) {
            error_log("警告：检测到可疑的 SQL 查询模式: " . substr($sql, 0, 100));
        }
        
        $result = $this->conn->query($sql);
        if (!$result) {
            error_log("SQL 查询错误: " . $this->conn->error);
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

    public function prepare($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL 预处理错误: " . $this->conn->error);
            throw new RuntimeException("SQL 预处理错误");
        }

        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            foreach ($params as $param) {
                if (is_null($param)) {
                    $types .= 's';
                    $bindParams[] = null;
                } else {
                    $types .= 's';
                    $bindParams[] = $param;
                }
            }
            $stmt->bind_param($types, ...$bindParams);
        }
        return $stmt;
    }

    public function db($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        return new Anon_Database_QueryBuilder($this->conn, ANON_DB_PREFIX . $table);
    }

}

class Anon_Database_QueryBuilder
{
    private $conn;
    private $table;
    private $type = 'select';
    private $columns = ['*'];
    private $data = [];
    private $where = [];
    private $joins = [];
    private $order = [];
    private $limit = null;
    private $offset = null;

    public function __construct(mysqli $conn, $table)
    {
        $this->conn = $conn;
        $this->table = $table;
    }

    public function select($cols = ['*'])
    {
        $this->type = 'select';
        $this->columns = is_array($cols) ? $cols : [$cols];
        foreach ($this->columns as $col) {
            if (!preg_match('/^[a-zA-Z0-9_`*]+$/', $col)) {
                throw new InvalidArgumentException("无效的列名: {$col}");
            }
        }
        return $this;
    }

    public function insert(array $data)
    {
        $this->type = 'insert';
        $this->data = $data;
        return $this;
    }

    public function update(array $data)
    {
        $this->type = 'update';
        $this->data = $data;
        return $this;
    }

    public function delete()
    {
        $this->type = 'delete';
        return $this;
    }

    public function count()
    {
        $this->type = 'count';
        return $this;
    }

    public function exists()
    {
        $this->type = 'exists';
        return $this;
    }

    public function where($col, $op, $val = null)
    {
        if (!preg_match('/^[a-zA-Z0-9_`]+$/', $col)) {
            throw new InvalidArgumentException("无效的列名: {$col}");
        }
        $allowedOps = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'IN', 'IS', 'IS NOT'];
        if ($val !== null && !in_array(strtoupper($op), $allowedOps)) {
            throw new InvalidArgumentException("无效的操作符: {$op}");
        }
        if ($val === null) {
            if ($op === '=' || strtoupper($op) === 'IS') {
                $this->where[] = [$col, 'IS NULL', null];
            } else {
                $this->where[] = [$col, 'IS NOT NULL', null];
            }
        } else {
            $this->where[] = [$col, $op, $val];
        }
        return $this;
    }

    public function whereIn($col, array $vals)
    {
        $this->where[] = [$col, 'IN', $vals];
        return $this;
    }

    public function orderBy($col, $dir = 'ASC')
    {
        if (!preg_match('/^[a-zA-Z0-9_`]+$/', $col)) {
            throw new InvalidArgumentException("无效的列名: {$col}");
        }
        $this->order[] = [$col, strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = (int)$limit;
        $this->offset = $offset !== null ? (int)$offset : null;
        return $this;
    }

    public function join($table, $on, $type = 'INNER')
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        if (!preg_match('/^[a-zA-Z0-9_`.=\s]+$/', $on)) {
            throw new InvalidArgumentException("无效的 JOIN 条件: {$on}");
        }
        $this->joins[] = [ANON_DB_PREFIX . $table, $on, strtoupper($type)];
        return $this;
    }

    private function t($v)
    {
        if (is_int($v)) return 'i';
        if (is_float($v)) return 'd';
        return 's';
    }

    private function build()
    {
        $sql = '';
        $p = [];
        $ty = '';
        if ($this->type === 'select') {
            $sql = 'SELECT ' . implode(',', $this->columns) . ' FROM ' . $this->table;
        } elseif ($this->type === 'count') {
            $sql = 'SELECT COUNT(*) as count FROM ' . $this->table;
        } elseif ($this->type === 'exists') {
            $sql = 'SELECT 1 FROM ' . $this->table;
        } elseif ($this->type === 'insert') {
            $cols = array_keys($this->data);
            foreach ($cols as $col) {
                if (!preg_match('/^[a-zA-Z0-9_`]+$/', $col)) {
                    throw new InvalidArgumentException("无效的列名: {$col}");
                }
            }
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO ' . $this->table . ' (' . implode(',', $cols) . ') VALUES (' . $ph . ')';
            foreach ($this->data as $v) {
                $p[] = $v;
                $ty .= $this->t($v);
            }
        } elseif ($this->type === 'update') {
            $sets = [];
            foreach ($this->data as $k => $v) {
                if (!preg_match('/^[a-zA-Z0-9_`]+$/', $k)) {
                    throw new InvalidArgumentException("无效的列名: {$k}");
                }
                $sets[] = "$k = ?";
                $p[] = $v;
                $ty .= $this->t($v);
            }
            $sql = 'UPDATE ' . $this->table . ' SET ' . implode(',', $sets);
        } elseif ($this->type === 'delete') {
            $sql = 'DELETE FROM ' . $this->table;
        }
        foreach ($this->joins as $j) {
            $sql .= ' ' . $j[2] . ' JOIN ' . $j[0] . ' ON ' . $j[1];
        }
        if (!empty($this->where)) {
            $ws = [];
            foreach ($this->where as $w) {
                if (!preg_match('/^[a-zA-Z0-9_`]+$/', $w[0])) {
                    throw new InvalidArgumentException("无效的 WHERE 列名: {$w[0]}");
                }
                if ($w[1] === 'IS NULL' || $w[1] === 'IS NOT NULL') {
                    $ws[] = $w[0] . ' ' . $w[1];
                } elseif ($w[1] === 'IN' && is_array($w[2])) {
                    $ph = implode(',', array_fill(0, count($w[2]), '?'));
                    $ws[] = $w[0] . ' IN (' . $ph . ')';
                    foreach ($w[2] as $vv) {
                        $p[] = $vv;
                        $ty .= $this->t($vv);
                    }
                } else {
                    $ws[] = $w[0] . ' ' . $w[1] . ' ?';
                    $p[] = $w[2];
                    $ty .= $this->t($w[2]);
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $ws);
        }
        if ($this->type === 'select' || $this->type === 'exists') {
            if (!empty($this->order)) {
                $ords = [];
                foreach ($this->order as $o) {
                    if (!preg_match('/^[a-zA-Z0-9_`]+$/', $o[0])) {
                        throw new InvalidArgumentException("无效的排序列名: {$o[0]}");
                    }
                    $ords[] = $o[0] . ' ' . $o[1];
                }
                $sql .= ' ORDER BY ' . implode(', ', $ords);
            }
        }
        if (($this->type === 'select' || $this->type === 'exists') && $this->limit !== null) {
            $sql .= ' LIMIT ?';
            $p[] = $this->limit;
            $ty .= 'i';
            if ($this->offset !== null) {
                $sql .= ' OFFSET ?';
                $p[] = $this->offset;
                $ty .= 'i';
            }
        }
        return [$sql, $p, $ty];
    }

    private function stmt()
    {
        [$sql, $p, $ty] = $this->build();
        $st = $this->conn->prepare($sql);
        if (!$st) throw new RuntimeException('准备语句失败: ' . $this->conn->error);
        if (!empty($p)) {
            $st->bind_param($ty, ...$p);
        }
        return $st;
    }

    public function get()
    {
        $st = $this->stmt();
        if (!$st->execute()) throw new RuntimeException('执行失败: ' . $st->error);
        $res = $st->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $st->close();
        return $rows;
    }

    public function first()
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function scalar()
    {
        if ($this->type === 'count') {
            $st = $this->stmt();
            if (!$st->execute()) throw new RuntimeException('执行失败: ' . $st->error);
            $res = $st->get_result();
            $row = $res->fetch_assoc();
            $st->close();
            return (int)$row['count'];
        }
        if ($this->type === 'exists') {
            $this->limit(1);
            $st = $this->stmt();
            if (!$st->execute()) throw new RuntimeException('执行失败: ' . $st->error);
            $res = $st->get_result();
            $has = $res->num_rows > 0;
            $st->close();
            return $has;
        }
        return null;
    }

    public function execute()
    {
        $st = $this->stmt();
        if (!$st->execute()) throw new RuntimeException('执行失败: ' . $st->error);
        $id = $st->insert_id;
        $aff = $st->affected_rows;
        $st->close();
        return $this->type === 'insert' ? $id : $aff;
    }
}

