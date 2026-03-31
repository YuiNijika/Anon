# Redis 缓存配置与使用指南

## 快速开始

### 1. 配置已自动完成

配置文件 `app/useApp.php` 已更新为使用 Redis 缓存：

```php
'cache' => [
    'enabled' => true,
    'driver' => 'redis',  // 已设置为 redis
    'time' => 3600,
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'database' => 1,      // 已修改为数据库 1
        'prefix' => 'anon:',
        'timeout' => 2.5,     // 新增连接超时
    ],
    'file' => [               // 新增文件缓存配置（备用）
        'path' => __DIR__ . '/../../cache',
    ],
],
```

### 2. 确保 Redis 服务运行

**Windows:**
```bash
# 检查 Redis 是否运行
tasklist | findstr redis-server.exe

# 如果未运行，启动 Redis
redis-server.exe
```

**Linux:**
```bash
# 检查状态
systemctl status redis

# 启动 Redis
systemctl start redis

# 设置开机自启
systemctl enable redis
```

### 3. 测试缓存

**命令行测试:**
```bash
cd d:\Codes\Anon
php tools/test_cache.php
```

**Web 访问测试:**
在浏览器中访问（需开启调试模式）:
```
http://your-domain/anon/cache/status
```

## 使用方法

### 基础用法

```php
// 1. 设置缓存
Anon_Cache::set('key', 'value', 3600);  // 单位：秒

// 2. 获取缓存
$value = Anon_Cache::get('key');

// 3. 检查缓存是否存在
if (Anon_Cache::has('key')) {
    $value = Anon_Cache::get('key');
}

// 4. 删除缓存
Anon_Cache::delete('key');

// 5. 清空所有缓存
Anon_Cache::clear();
```

### 使用全局辅助函数（推荐）

```php
// 设置缓存
cache('site:config', $configData, 3600);

// 获取缓存
$config = cache('site:config');

// 获取缓存实例
$cacheInstance = cache();
```

### 使用 remember 方法（自动回源）

```php
// 如果缓存存在则返回，否则执行回调并缓存结果
$data = Anon_Cache::remember('heavy:query', function() {
    // 这里是耗时操作，只会执行一次
    return DB::table('users')->where('status', 1)->get();
}, 600);  // 缓存 10 分钟
```

### 在控制器中使用示例

```php
class ArticleController
{
    public function index()
    {
        // 尝试从缓存获取文章列表
        $articles = cache('articles:list', null);
        
        if ($articles === null) {
            // 缓存不存在，查询数据库
            $articles = DB::table('articles')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
            
            // 存入缓存，5 分钟过期
            cache('articles:list', $articles, 300);
        }
        
        return Anon_Http_Response::success($articles);
    }
    
    public function show($id)
    {
        // 使用 remember 方法简化代码
        $article = Anon_Cache::remember("article:{$id}", function() use ($id) {
            return DB::table('articles')->find($id);
        }, 600);
        
        if (!$article) {
            return Anon_Http_Response::error('文章不存在', null, 404);
        }
        
        return Anon_Http_Response::success($article);
    }
}
```

## 缓存驱动说明

### 支持的驱动类型

1. **Redis** (推荐)
   - 性能最好
   - 支持持久化
   - 支持分布式部署
   
2. **File** (默认备用)
   - 无需额外服务
   - 适合小型项目
   - 自动降级方案

3. **Memory** (开发测试)
   - 仅当前请求有效
   - 不持久化
   - 适合单元测试

### 切换驱动

修改 `app/useApp.php`:
```php
'driver' => 'redis',  // 可选：redis, file, memory
```

## 诊断命令

### Web 诊断接口

访问 `/anon/cache/status` (需开启调试模式):

```json
{
    "code": 200,
    "message": "缓存状态获取成功",
    "data": {
        "driver": "Anon_RedisCache",
        "enabled": true,
        "config": {
            "driver": "redis",
            "prefix": "anon:",
            "database": 1
        },
        "stats": {
            "connected": true,
            "redis_version": "7.0.0",
            "used_memory": "1.2M",
            "total_keys": 42
        }
    }
}
```

### CLI 测试脚本

```bash
php tools/test_cache.php
```

输出示例:
```
=== Anon 缓存系统测试 ===

1. 测试获取缓存实例...
   ✓ 缓存实例类型：Anon_RedisCache

2. 测试基本缓存操作...
   设置缓存：✓ 成功
   获取缓存：✓ 成功，数据一致
   缓存数据：{
       "name": "Anon",
       "version": "4.0",
       "timestamp": 1711094400
   }

3. 测试全局 cache() 函数...
   设置缓存：✓ 成功
   获取缓存：✓ 成功
   缓存值：这是一个全局函数测试

4. 测试 remember 方法...
   第一次调用（应执行回调）:
   结果：{"data":"from callback","time":1711094400}
   第二次调用（应从缓存读取）:
   结果：{"data":"from callback","time":1711094400}

5. 测试删除缓存...
   删除缓存：✓ 成功
   再次检查：✓ 已删除

6. Redis 连接信息...
   ✓ Redis 版本：7.0.0
   ✓ 内存使用：1.2M
   ✓ 键数量：15
   ✓ 连接状态：正常

7. 清理测试数据...
   ✓ 清理完成

=== 测试完成 ===
```

## 常见问题

### Q: Redis 连接失败怎么办？

A: 
1. 检查 Redis 服务是否运行
2. 检查端口是否正确（默认 6379）
3. 检查防火墙设置
4. 如果使用密码认证，确保配置了 password 字段

### Q: 如何查看 Redis 中的缓存数据？

A: 使用 Redis 命令行工具:
```bash
redis-cli
> select 1                    # 选择数据库 1
> keys anon:*                 # 查看所有 anon 前缀的键
> get anon:site:config        # 获取特定键的值
> ttl anon:site:config        # 查看过期时间
```

### Q: 缓存不生效怎么办？

A:
1. 检查 `app.useApp.php` 中 `'enabled' => true`
2. 检查 Redis 服务是否正常运行
3. 访问 `/anon/cache/status` 查看状态
4. 运行 `php tools/test_cache.php` 进行测试

### Q: 如何在生产环境使用？

A:
1. 确保 Redis 服务稳定运行
2. 配置 Redis 持久化（RDB/AOF）
3. 设置合理的过期时间
4. 定期监控 Redis 内存使用

### Q: 如何实现缓存预热？

A:
```php
// 在系统启动时预加载常用数据
function warmupCache() {
    cache('site:config', loadSiteConfig(), 86400);
    cache('nav:menu', buildNavMenu(), 3600);
    cache('widgets:sidebar', buildSidebarWidgets(), 1800);
}
```

## 最佳实践

### 1. 使用有意义的键名
```php
// 推荐
cache('user:profile:123', $data);
cache('article:comments:456', $comments);

// 不推荐
cache('key1', $data);
cache('temp', $comments);
```

### 2. 设置合理的过期时间
```php
// 频繁变化的数据：短过期
cache('visitor:count', $count, 300);  // 5 分钟

// 配置类数据：长过期
cache('site:settings', $settings, 86400);  // 1 天

// 永久数据：不过期
cache('system:version', $version, null);
```

### 3. 使用命名空间分隔
```php
$namespace = 'shop:';
cache($namespace . 'products', $products, 3600);
cache($namespace . 'categories', $categories, 7200);
```

### 4. 批量操作时的优化
```php
// 避免循环设置缓存
foreach ($items as $item) {
    cache("item:{$item['id']}", $item, 3600);  // 不推荐
}

// 推荐：批量存储
cache('items:batch', $items, 3600);
// 或者使用哈希结构
foreach ($items as $item) {
    $redis->hSet('items:map', $item['id'], json_encode($item));
}
```

## 性能对比

| 驱动类型 | 读取速度 | 写入速度 | 适用场景 |
|---------|---------|---------|---------|
| Redis   | ~0.5ms  | ~0.5ms  | 生产环境、高并发 |
| File    | ~2ms    | ~5ms    | 开发环境、小流量 |
| Memory  | ~0.01ms | ~0.01ms | 单元测试、临时数据 |

## 更新日志

### v4.0 - 2026-03-22
- ✅ 添加 Redis 缓存驱动支持
- ✅ 添加全局 `cache()` 辅助函数
- ✅ 添加缓存状态诊断接口 `/anon/cache/status`
- ✅ 优化 `Anon_Cache::getInstance()` 为公共方法
- ✅ 添加缓存测试脚本
- ✅ 配置文件默认使用 Redis 驱动

---

**提示**: 如有问题请访问 `/anon/cache/status` 查看状态或运行 `php tools/test_cache.php` 测试
