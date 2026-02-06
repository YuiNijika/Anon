# 数据库操作

一句话：用链式调用写SQL，自动防注入，支持Repository模式。

---

## 快速开始

推荐使用单例模式获取数据库实例，确保同一请求生命周期内复用连接以提升性能。

```php
$db = Anon_Database::getInstance();

// 查询所有用户
$users = $db->db('users')->get();

// 查询单个用户
$user = $db->db('users')->where('id', '=', 1)->first();

// 插入数据
$userId = $db->db('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// 更新数据
$db->db('users')
    ->where('id', '=', 1)
    ->update(['name' => 'John Doe']);

// 删除数据
$db->db('users')->where('id', '=', 1)->delete();
```

## 查询操作

### 基本查询

```php
// 查询所有记录
$users = $db->db('users')->get();

// 查询单条记录
$user = $db->db('users')->where('id', '=', 1)->first();

// 查询指定字段
$users = $db->db('users')
    ->select(['id', 'name', 'email'])
    ->get();

// 条件查询
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->where('age', '>', 18)
    ->get();

// 排序和分页
$users = $db->db('users')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(0)
    ->get();
```

### 复杂查询

```php
// WHERE IN
$users = $db->db('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// WHERE NULL
$users = $db->db('users')
    ->whereNull('deleted_at')
    ->get();

// 嵌套条件
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->whereNested(function($query) {
        $query->where('age', '>', 18)
              ->orWhere('vip', '=', 1);
    })
    ->get();

// JOIN 查询
$posts = $db->db('posts')
    ->select(['posts.*', 'users.name as author'])
    ->leftJoin('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.status', '=', 'published')
    ->get();
```

## 增删改操作

```php
// 插入单条
$userId = $db->db('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// 批量插入
$db->db('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);

// 更新
$db->db('users')
    ->where('id', '=', 1)
    ->update(['name' => 'John Doe']);

// 删除
$db->db('users')
    ->where('id', '=', 1)
    ->delete();
```

## 聚合查询

```php
// 统计数量
$count = $db->db('users')
    ->where('status', '=', 'active')
    ->count();

// 检查是否存在
$exists = $db->db('users')
    ->where('email', '=', 'test@example.com')
    ->exists();

// 获取单个值
$name = $db->db('users')
    ->where('id', '=', 1)
    ->value('name');

// 求和
$total = $db->db('orders')
    ->where('status', '=', 'paid')
    ->sum('amount');
```

## Repository 模式

一句话：把数据库操作封装到类里，自动发现和使用。

### 创建 Repository

创建 `server/app/Database/User.php`：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_UserRepository extends Anon_Database_Connection
{
    public function getUserInfo(int $uid)
    {
        return $this->db('users')
            ->where('uid', '=', $uid)
            ->first();
    }
    
    public function getUserInfoByName(string $name)
    {
        return $this->db('users')
            ->where('name', '=', $name)
            ->first();
    }
}
```

### 使用 Repository

```php
$db = Anon_Database::getInstance();

// 自动转发到 UserRepository
$user = $db->getUserInfo(1);

// 直接访问 Repository
$user = $db->userRepository->getUserInfo(1);
```

## 调试 SQL

```php
$query = $db->db('users')
    ->where('status', '=', 'active')
    ->where('age', '>', 18);

// 获取带参数值的原始 SQL
echo $query->toRawSql();
// 输出: SELECT * FROM users WHERE status = 'active' AND age > 18
```

## 原始 SQL（不推荐）

```php
// 执行原始 SQL
$result = $db->query('SELECT * FROM users WHERE id = 1');

// 预处理语句
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?', [1]);
```

## 游标分页

替代传统 LIMIT OFFSET 分页，解决百万数据性能问题。

```php
// 主键游标分页
$result = $db->db('users')->cursorPaginate(20, $cursor);
// 返回: ['data' => [...], 'next_cursor' => 123, 'has_next' => true]

// 时间戳游标分页
$result = $db->db('posts')->cursorPaginateByTime(20, $cursor);
// 返回: ['data' => [...], 'prev_cursor' => 1234567890, 'has_prev' => true]
```

## 批量操作

```php
// 批量插入 1000+ 条数据
$inserted = $db->batchInsert('users', $data, 1000);

// 批量更新
$updated = $db->batchUpdate('users', $data, 'id', 1000);
```

## 查询缓存

```php
// 启用查询缓存
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->cache(3600) // 缓存 1 小时
    ->get();
```

## 关联查询优化

```php
// 避免 N+1 查询
$users = $db->db('users')->limit(100)->get();
$users = Anon_Database_QueryOptimizer::eagerLoad($users, 'user_id', 'orders', 'id');
```

## 表结构操作

使用查询构建器创建和管理数据表结构，无需手写 SQL。

### 创建表

```php
$db = Anon_Database::getInstance();

// 使用数组定义字段
$db->createTable('user_stats', [
    'user_id' => [
        'type' => 'INT',
        'null' => false,
        'primary' => true,
    ],
    'login_count' => [
        'type' => 'INT',
        'null' => false,
        'default' => 0,
    ],
    'last_login' => [
        'type' => 'DATETIME',
        'null' => true,
    ],
], [
    'engine' => 'InnoDB',
    'charset' => 'utf8mb4',
    'ifNotExists' => true,
]);

// 使用字符串定义字段（更灵活）
$db->createTable('posts', [
    'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
    'title' => "VARCHAR(255) NOT NULL COMMENT '标题'",
    'content' => 'TEXT',
    'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
]);
```

### 字段定义选项

```php
[
    'type' => 'VARCHAR(255)',      // 字段类型
    'null' => false,               // 是否允许 NULL
    'default' => 'default_value',  // 默认值
    'autoIncrement' => true,       // 是否自增
    'primary' => true,           // 是否主键
    'comment' => '字段说明',       // 字段注释
]
```

### 添加字段

```php
// 添加单个字段
$db->addColumn('users', 'avatar', [
    'type' => 'VARCHAR(255)',
    'null' => true,
    'comment' => '头像URL',
]);

// 在指定字段之后添加
$db->addColumn('users', 'nickname', 'VARCHAR(100)', 'name');
```

### 修改字段

```php
// 修改字段类型和属性
$db->modifyColumn('users', 'email', [
    'type' => 'VARCHAR(255)',
    'null' => false,
    'comment' => '邮箱地址',
]);
```

### 删除字段

```php
$db->dropColumn('users', 'old_field');
```

### 删除表

```php
$db->dropTable('old_table', true); // true 表示使用 IF EXISTS
```

### 检查表是否存在

```php
if (!$db->tableExists('user_stats')) {
    $db->createTable('user_stats', [...]);
}
```

## 分库分表

```php
// 配置分片规则
Anon_Database_Sharding::init([
    'users' => ['shard_count' => 4, 'strategy' => 'id']
]);

// 获取分片表名
$tableName = Anon_Database_Sharding::getTableName('users', $userId, 'id');
```

详细说明请参考 [大数据处理优化文档](./big-data.md)。

