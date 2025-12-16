# 数据库操作

## 基本使用

```php
$db = new Anon_Database();

// 用户操作（自动转发到 UserRepository）
$db->addUser('admin', 'admin@example.com', 'password', 'admin');
$user = $db->getUserInfo(1);
$user = $db->getUserInfoByName('admin');
$isAdmin = $db->isUserAdmin(1);
$db->updateUserGroup(1, 'admin');
```

## QueryBuilder（旧版）

```php
$db = new Anon_Database();

// 查询
$users = $db->db('users')
    ->select(['uid', 'name', 'email'])
    ->where('uid', '>', 10)
    ->orderBy('uid', 'DESC')
    ->limit(10)
    ->get();

// 单条查询
$user = $db->db('users')
    ->where('uid', '=', 1)
    ->first();

// 插入
$id = $db->db('users')
    ->insert(['name' => 'admin', 'email' => 'admin@example.com'])
    ->execute();

// 更新
$affected = $db->db('users')
    ->update(['email' => 'new@example.com'])
    ->where('uid', '=', 1)
    ->execute();

// 删除
$affected = $db->db('users')
    ->delete()
    ->where('uid', '=', 1)
    ->execute();

// 计数
$count = $db->db('users')
    ->where('group', '=', 'admin')
    ->count()
    ->scalar();

// 存在检查
$exists = $db->db('users')
    ->where('email', '=', 'admin@example.com')
    ->exists()
    ->scalar();
```

## 现代查询构建器

框架提供了流畅的查询构建器，支持链式调用和自动参数绑定。

### 基本查询

```php
use Anon_Database;

$db = new Anon_Database();

// 查询所有记录
$users = $db->db('users')->get();

// 条件查询
$user = $db->db('users')
    ->where('id', '=', 1)
    ->first();

// 多条件查询
$users = $db->db('users')
    ->where('status', '=', 'active')
    ->where('age', '>', 18)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
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

### 插入、更新、删除

```php
// 插入
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

### 聚合查询

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
```

### 调试 SQL

```php
$query = $db->db('users')
    ->where('status', '=', 'active')
    ->where('age', '>', 18);

// 获取原始 SQL（带参数值）
echo $query->toRawSql();
```

## 创建 Repository/Service

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

访问方式：

```php
$db = new Anon_Database();
$user = $db->getUserInfo(1);  // 自动转发
// 或
$user = $db->userRepository->getUserInfo(1);  // 直接访问
```

---

[← 返回文档首页](../README.md)

