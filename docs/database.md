# 数据库操作

一句话：用链式调用写SQL，自动防注入，支持Repository模式。

## 快速开始

```php
$db = new Anon_Database();

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
$db = new Anon_Database();

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

---

[← 返回文档首页](../README.md)
