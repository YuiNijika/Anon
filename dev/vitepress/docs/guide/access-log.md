# 访问日志系统

访问日志系统提供灵活的网站访问记录功能，支持 CMS 和 API 双模式，可独立控制启用状态。

::: tip 代码示例说明
本文档中的代码示例使用了简化的类名（如 `AccessLog`、`Database` 等）。在实际使用时，需要在文件顶部添加相应的 `use` 语句：

```php
use Anon\Modules\System\AccessLog;
use Anon\Modules\Database\Database;
```
:::

## 系统架构

### 继承关系

```
AccessLog (基类 - 通用功能)
    ↓
CMS_AccessLog (CMS 模式扩展)
```

### 设计特点

- **通用性**：基类 `AccessLog` 提供核心功能
- **可扩展**：CMS 模式可继承并扩展特定功能
- **独立性**：与评论统计、文章阅读量等功能完全独立

## 快速开始

### 启用访问日志

```php
use Anon\Modules\System\AccessLog;

// 方法 1：代码启用
AccessLog::enable();

// 方法 2：数据库设置
UPDATE options SET value = '1' WHERE name = 'access_log_enabled';
```

### 禁用访问日志

```php
use Anon\Modules\System\AccessLog;

AccessLog::disable();
```

### 检查状态

```php
use Anon\Modules\System\AccessLog;

if (AccessLog::isEnabled()) {
    // 访问日志已启用
}
```

## 配置选项

### 数据库配置

访问日志配置存储在 `options` 表中：

| 配置项 | 键名 | 类型 | 默认值 | 说明 |
|--------|------|------|--------|------|
| 启用状态 | `access_log_enabled` | boolean | true | 是否启用访问日志 |
| 排除路径 | `access_log_exclude_paths` | array | [] | 不记录的路径列表 |
| 保留天数 | `access_log_retention_days` | int | 30 | 日志保留天数 |

### CMS 模式配置

在 CMS 管理后台「设置」→「权限设置」中可视化配置：

- **访问日志开关**：控制 `access_log_enabled`
- **排除路径设置**：每行一个路径，支持通配符
- **统计设置**：配置统计数据的更新频率

## 记录内容

每条访问日志包含以下字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | int | 日志 ID |
| `ip` | string | 访问者 IP 地址 |
| `path` | string | 访问路径 |
| `method` | string | HTTP 方法 |
| `user_agent` | string | 用户代理 |
| `referer` | string | 来源 URL |
| `created_at` | datetime | 访问时间 |
| `type` | string | 访问类型（page, api, static） |

### 示例数据

```json
{
    "id": 12345,
    "ip": "192.168.1.100",
    "path": "/post/123",
    "method": "GET",
    "user_agent": "Mozilla/5.0...",
    "referer": "https://google.com",
    "created_at": "2024-03-22 10:30:00",
    "type": "page"
}
```

## 自动排除规则

系统会自动排除以下路径，不记录访问日志：

### 默认排除

```php
$excludePaths = [
    '/anon/static/*',      // 静态资源
    '/anon/debug/*',       // 调试相关
    '*.css',               // CSS 文件
    '*.js',                // JS 文件
    '*.png', '*.jpg',      // 图片文件
    '*.gif', '*.svg',      // 其他图片
    '*.ico', '*.woff*',    // 字体文件
];
```

### 自定义排除

```php
<?php
use Anon\Modules\System\AccessLog;

// 添加排除路径
AccessLog::addExcludedPath('/api/health');
AccessLog::addExcludedPath('/admin/ajax/*');

// 批量添加
AccessLog::setExcludedPaths([
    '/api/health',
    '/admin/ajax/*',
    '/cron/*',
]);

// 移除排除路径
AccessLog::removeExcludedPath('/api/health');

// 清空排除路径
AccessLog::clearExcludedPaths();
```

## 使用场景

### 1. 流量分析

```php
use Anon\Modules\Database\Database;

// 获取今日访问量
$db = Database::getInstance();
$today = date('Y-m-d');

$count = $db->db('access_logs')
    ->where('DATE(created_at)', '=', $today)
    ->count();

echo "今日访问：{$count}";
```

### 2. 热门页面统计

```php
<?php
use Anon\Modules\Database\Database;

$db = Database::getInstance();

// 获取最热门的 10 个页面
$popular = $db->db('access_logs')
    ->select('path, COUNT(*) as views')
    ->where('type', '=', 'page')
    ->group('path')
    ->order('views', 'DESC')
    ->limit(10)
    ->all();

foreach ($popular as $page) {
    echo "{$page['path']}: {$page['views']} 次访问\n";
}
```

### 3. IP 分析

```php
<?php
use Anon\Modules\Database\Database;

$db = Database::getInstance();

// 获取访问最多的 IP
$ips = $db->db('access_logs')
    ->select('ip, COUNT(*) as visits')
    ->group('ip')
    ->order('visits', 'DESC')
    ->limit(100)
    ->all();
```

### 4. 来源分析

```php
// 统计来源网站
$referers = $db->db('access_logs')
    ->select('referer')
    ->where('referer', '!=', '')
    ->all();

$domains = [];
foreach ($referers as $ref) {
    $domain = parse_url($ref['referer'], PHP_URL_HOST);
    if ($domain) {
        $domains[$domain] = ($domains[$domain] ?? 0) + 1;
    }
}

arsort($domains);
print_r(array_slice($domains, 0, 10));
```

## 性能优化

### 1. 批量插入

避免频繁单条插入，使用批量操作：

```php
class AccessLogBatch
{
    private $logs = [];
    private $batchSize = 100;
    
    public function log($data)
    {
        $this->logs[] = $data;
        
        if (count($this->logs) >= $this->batchSize) {
            $this->flush();
        }
    }
    
    public function flush()
    {
        if (empty($this->logs)) {
            return;
        }
        
        $db = Database::getInstance();
        foreach ($this->logs as $log) {
            $db->db('access_logs')->insert($log);
        }
        
        $this->logs = [];
    }
    
    public function __destruct()
    {
        $this->flush();
    }
}
```

### 2. 定期清理

设置定时任务清理过期日志：

```php
<?php
use Anon\Modules\System\Console;
use Anon\Modules\Database\Database;

Console::command('logs:clean', function($args) {
    $days = isset($args[0]) ? (int)$args[0] : 30;
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));
    
    $db = Database::getInstance();
    $deleted = $db->db('access_logs')
        ->where('created_at', '<', $cutoff)
        ->delete();
    
    Console::success("清理 {$deleted} 条旧日志");
    return 0;
}, '清理过期访问日志');
```

### 3. 索引优化

为常用查询字段添加索引：

```sql
-- 为日期查询添加索引
ALTER TABLE access_logs ADD INDEX idx_created_at (created_at);

-- 为路径查询添加索引
ALTER TABLE access_logs ADD INDEX idx_path (path);

-- 为 IP 查询添加索引
ALTER TABLE access_logs ADD INDEX idx_ip (ip);

-- 组合索引（用于统计查询）
ALTER TABLE access_logs ADD INDEX idx_date_type (created_at, type);
```

## 统计数据

### 基础统计

```php
class AccessLogStatistics
{
    /**
     * 获取今日访问量
     */
    public static function getTodayViews(): int
    {
        $db = Database::getInstance();
        return (int)$db->db('access_logs')
            ->where('DATE(created_at)', '=', date('Y-m-d'))
            ->count();
    }
    
    /**
     * 获取昨日访问量
     */
    public static function getYesterdayViews(): int
    {
        $db = Database::getInstance();
        return (int)$db->db('access_logs')
            ->where('DATE(created_at)', '=', date('Y-m-d', strtotime('-1 day')))
            ->count();
    }
    
    /**
     * 获取总访问量
     */
    public static function getTotalViews(): int
    {
        $db = Database::getInstance();
        return (int)$db->db('access_logs')->count();
    }
    
    /**
     * 获取独立访客数
     */
    public static function getUniqueVisitors(int $days = 30): int
    {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        
        return (int)$db->db('access_logs')
            ->select('COUNT(DISTINCT ip) as uv')
            ->where('created_at', '>=', $cutoff)
            ->first()['uv'];
    }
}
```

### 趋势分析

```php
/**
 * 获取最近 N 天的访问趋势
 */
public static function getTrend(int $days = 7): array
{
    $db = Database::getInstance();
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));
    
    return $db->db('access_logs')
        ->select('DATE(created_at) as date, COUNT(*) as views')
        ->where('created_at', '>=', $cutoff)
        ->group('DATE(created_at)')
        ->order('date', 'ASC')
        ->all();
}

// 使用示例
$trend = AccessLogStatistics::getTrend(30);
foreach ($trend as $day) {
    echo "{$day['date']}: {$day['views']} 次访问\n";
}
```

## API 接口

### 管理端 API

CMS 模式下提供完整的 RESTful API：

```
GET    /anon/cms/admin/access-logs          # 日志列表
GET    /anon/cms/admin/access-logs/{id}     # 日志详情
DELETE /anon/cms/admin/access-logs/{id}     # 删除日志
GET    /anon/cms/admin/access-logs/statistics # 统计数据
POST   /anon/cms/admin/access-logs/clean    # 清理日志
```

### 请求参数

#### 列表接口

```
GET /anon/cms/admin/access-logs?filter={
    "ip": "192.168.1.1",
    "path": "/post/*",
    "type": "page",
    "startDate": "2024-01-01",
    "endDate": "2024-01-31",
    "page": 1,
    "limit": 20
}
```

#### 统计接口

```json
{
    "total": 12345,
    "today": 567,
    "yesterday": 543,
    "uniqueVisitors": 8901,
    "topPages": [
        {"path": "/", "views": 1234},
        {"path": "/post/1", "views": 567}
    ],
    "topIps": [
        {"ip": "192.168.1.1", "visits": 100}
    ]
}
```

## 最佳实践

### 1. 合理设置保留期

根据存储容量设置合适的保留天数：

```php
// 小型网站：保留 90 天
Options::update('access_log_retention_days', 90);

// 中型网站：保留 30 天
Options::update('access_log_retention_days', 30);

// 大型网站：保留 7 天
Options::update('access_log_retention_days', 7);
```

### 2. 排除无关路径

减少不必要的日志记录：

```php
$excludes = [
    '/api/health',           // 健康检查
    '/cron/*',               // 定时任务
    '/admin/ajax/*',         // 后台 AJAX
    '/uploads/*',            // 上传文件
    '*.xml',                 // RSS 订阅
    '*.json',                // JSON 数据
];

AccessLog::setExcludedPaths($excludes);
```

### 3. 监控异常

设置告警监控异常访问：

```php
// 检测短时间内大量访问
$suspicious = $db->db('access_logs')
    ->select('ip, COUNT(*) as count')
    ->where('created_at', '>=', DATE_SUB(NOW(), INTERVAL 1 MINUTE))
    ->group('ip')
    ->having('count > 100')
    ->all();

foreach ($suspicious as $record) {
    Debug::warning("可疑 IP: {$record['ip']} - {$record['count']} 次/分钟");
}
```

### 4. 数据导出

定期导出历史数据：

```php
Console::command('logs:export', function($args) {
    $month = $args[0] ?? date('Y-m');
    $filename = "access_logs_{$month}.csv";
    
    $db = Database::getInstance();
    $logs = $db->db('access_logs')
        ->where('MONTH(created_at)', '=', (int)substr($month, 5, 2))
        ->where('YEAR(created_at)', '=', (int)substr($month, 0, 4))
        ->all();
    
    $fp = fopen(ANON_ROOT . 'exports/' . $filename, 'w');
    fputcsv($fp, ['id', 'ip', 'path', 'method', 'created_at']);
    
    foreach ($logs as $log) {
        fputcsv($fp, $log);
    }
    
    fclose($fp);
    Console::success("导出完成：{$filename}");
    return 0;
}, '导出访问日志为 CSV');
```

## 与其他系统的关系

### 独立性

访问日志系统与以下功能完全独立：

- ✅ **评论系统**：评论数量统计不受影响
- ✅ **文章阅读量**：`posts.views` 字段独立更新
- ✅ **用户会话**：不依赖 session 或 cookie
- ✅ **缓存系统**：日志记录不影响缓存

### 协同工作

可以与以下功能配合使用：

- 🔧 **安全审计**：记录所有访问用于安全分析
- 🔧 **性能监控**：结合慢查询日志分析性能
- 🔧 **SEO 分析**：分析搜索引擎爬虫访问
- 🔧 **用户行为分析**：了解用户访问路径

## 故障排查

### 常见问题

#### 1. 日志未记录

**检查点：**
- 确认 `access_log_enabled` 是否为 true
- 检查路径是否在排除列表中
- 验证数据库连接是否正常
- 检查 `access_logs` 表是否存在

#### 2. 日志量过大

**解决方案：**
- 增加排除路径
- 缩短保留期限
- 降低统计更新频率
- 使用抽样记录（如只记录 10% 的访问）

#### 3. 查询性能慢

**优化方案：**
- 添加合适的索引
- 分区存储（按月分表）
- 定期归档历史数据
- 使用专门的日志存储（如 Elasticsearch）

## 相关文档

- [管理后台](cms/admin.md) - 管理端功能
- [CLI 命令系统](cli-system.md) - 命令行维护
- [数据库操作](api/database.md) - 数据库访问
- [调试系统](debugging.md) - 调试与日志
