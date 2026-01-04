# 大数据处理优化

一句话：游标分页、批量操作、查询缓存、关联优化，轻松处理百万级数据。

---

## 游标分页

替代传统的 LIMIT OFFSET 分页，解决百万数据偏移量过大导致的性能问题。

### 主键游标分页

```php
$db = Anon_Database::getInstance();

// 第一页
$result = $db->db('users')
    ->cursorPaginate(20); // 每页 20 条

// 返回: ['data' => [...], 'next_cursor' => 123, 'has_next' => true]

// 下一页
$result = $db->db('users')
    ->cursorPaginate(20, $result['next_cursor']);

// 使用自定义主键字段
$result = $db->db('users')
    ->cursorPaginate(20, null, 'uid');
```

### 时间戳游标分页

```php
// 按时间倒序分页
$result = $db->db('posts')
    ->cursorPaginateByTime(20); // 第一页

// 上一页
$result = $db->db('posts')
    ->cursorPaginateByTime(20, $result['prev_cursor']);

// 使用自定义时间字段
$result = $db->db('posts')
    ->cursorPaginateByTime(20, null, 'updated_at');
```

## 批量操作

### 批量插入

```php
$db = Anon_Database::getInstance();

// 批量插入 1000+ 条数据
$data = [];
for ($i = 0; $i < 5000; $i++) {
    $data[] = [
        'name' => "user_{$i}",
        'email' => "user_{$i}@example.com"
    ];
}

// 自动分批处理，每批 1000 条
$inserted = $db->batchInsert('users', $data, 1000);

// 或使用 QueryBuilder
$inserted = $db->db('users')->batchInsert($data, 1000);
```

### 批量更新

```php
$db = Anon_Database::getInstance();

// 批量更新数据
$data = [
    ['id' => 1, 'name' => 'User 1', 'status' => 'active'],
    ['id' => 2, 'name' => 'User 2', 'status' => 'inactive'],
    // ... 更多数据
];

// 自动分批处理
$updated = $db->batchUpdate('users', $data, 'id', 1000);

// 或使用 QueryBuilder
$updated = $db->db('users')->batchUpdate($data, 'id', 1000);
```

## 查询缓存

自动缓存热点数据，减少数据库查询。

```php
$db = Anon_Database::getInstance();

// 启用查询缓存，默认 1 小时
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->cache()
    ->get();

// 自定义缓存时间
$users = $db->db('users')
    ->cache(7200) // 缓存 2 小时
    ->get();

// 自定义缓存键
$users = $db->db('users')
    ->cache(3600, 'active_users')
    ->get();
```

## 关联查询优化

避免 N+1 查询问题，自动批量加载关联数据。

### 一对多关联

```php
$db = Anon_Database::getInstance();

// 获取用户列表
$users = $db->db('users')->limit(100)->get();

// 批量加载用户的订单（避免 N+1 查询）
$users = Anon_QueryOptimizer::eagerLoad(
    $users,
    'user_id',      // 外键字段
    'orders',       // 关联表名
    'id'            // 本地键字段
);

// 现在每个用户都有 orders 属性
foreach ($users as $user) {
    $orders = $user['orders'] ?? []; // 已预加载
}
```

### 一对一关联

```php
// 批量加载用户资料
$users = Anon_QueryOptimizer::eagerLoadOne(
    $users,
    'user_id',
    'profiles',
    'id'
);

// 每个用户都有 profile 属性
foreach ($users as $user) {
    $profile = $user['profiles'] ?? null;
}
```

### 自定义查询条件

```php
// 预加载时添加查询条件
$users = Anon_QueryOptimizer::eagerLoad(
    $users,
    'user_id',
    'orders',
    'id',
    function($query) {
        // 只加载未完成的订单
        $query->where('status', '!=', 'completed');
    }
);
```

## 慢查询检测

框架自动检测慢查询（超过 100ms）并建议索引。

在调试模式下，慢查询会自动记录到日志，并生成索引建议：

```php
// 慢查询会自动检测并记录
// 日志示例：
// 慢查询检测: SQL: SELECT * FROM users WHERE status = ? AND created_at > ?
// 建议索引: CREATE INDEX idx_status_created_at ON users (status, created_at)
```

## 分库分表

支持按主键、时间、哈希等规则分片。

### 配置分片规则

在 `server/app/useCode.php` 中配置：

```php
Anon_Sharding::init([
    'users' => [
        'shard_count' => 4,      // 分片数量
        'strategy' => 'id'       // 分片策略: id|time|hash
    ],
    'orders' => [
        'shard_count' => 12,     // 按月分片
        'strategy' => 'time'
    ]
]);
```

### 使用分片表

```php
$db = Anon_Database::getInstance();

// 根据分片键自动获取表名
$userId = 12345;
$tableName = Anon_Sharding::getTableName('users', $userId, 'id');
// 返回: users_1 (假设 userId % 4 = 1)

// 查询会自动路由到正确的分片表
$user = $db->db($tableName)
    ->where('id', '=', $userId)
    ->first();
```

### 查询所有分片

```php
// 获取所有分片表名
$tables = Anon_Sharding::getAllShardTables('users');
// 返回: ['users_0', 'users_1', 'users_2', 'users_3']

// 遍历所有分片查询
$allUsers = [];
foreach ($tables as $table) {
    $users = $db->db($table)->get();
    $allUsers = array_merge($allUsers, $users);
}
```

## 性能对比

### 传统分页 vs 游标分页

```php
// 传统分页 - 偏移量越大越慢
$users = $db->db('users')
    ->offset(100000)  // 查询第 100000 条开始
    ->limit(20)
    ->get();
// 耗时: ~500ms

// 游标分页 - 性能稳定
$result = $db->db('users')
    ->cursorPaginate(20, 100000);
// 耗时: ~10ms
```

### 批量操作性能

```php
// 单条插入 - 1000 条需要 1000 次数据库连接
for ($i = 0; $i < 1000; $i++) {
    $db->db('users')->insert(['name' => "user_{$i}"]);
}
// 耗时: ~5000ms

// 批量插入 - 只需 1 次数据库连接
$db->batchInsert('users', $data, 1000);
// 耗时: ~50ms (提升 100 倍)
```

