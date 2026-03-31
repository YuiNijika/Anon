# 访问日志系统

访问日志系统用于记录所有 HTTP 请求的详细信息，支持 API 和 CMS 两种模式。

## 启用方式

访问日志在框架初始化时自动记录，无需手动调用。

**配置**（app/useApp.php）：

```php
'app' => [
    'accessLog' => [
        'enabled' => true,  // 是否启用访问日志
    ],
],
```

## 记录内容

每条访问日志包含以下信息：

- **url**: 完整请求 URL
- **path**: 请求路径
- **method**: HTTP 方法（GET/POST/PUT/DELETE 等）
- **type**: 请求类型（api/page/static）
- **ip**: 客户端 IP 地址
- **user_agent**: 用户代理
- **referer**: 来源页面
- **status_code**: HTTP 状态码
- **response_time**: 响应时间（毫秒）

## 自动排除规则

系统会自动排除以下请求，不记录日志：

### 排除的路径
- `/anon-dev-server`
- `/anon/cms/admin`
- `/anon/install`
- `/anon/static`
- `/anon/attachment`
- `/assets`
- `/static`
- `/.well-known`
- `/favicon.ico`
- `/robots.txt`

### 排除的工具请求
- curl
- wget
- python-requests
- go-http-client
- java/
- scanner
- bot

### 排除敏感文件
- 配置文件（Dockerfile, nginx.conf, .env 等）
- 密钥和证书文件（*.pem, *.key, *.crt 等）
- 系统文件（.git/, .bash_history 等）
- 备份文件（*.bak, *.backup, *.old 等）

## 使用示例

### 获取日志列表

```php
$logs = Anon_System_AccessLog::getLogs([
    'page' => 1,
    'page_size' => 20,
    'start_date' => '2026-03-01 00:00:00',
    'end_date' => '2026-03-22 23:59:59',
    'type' => 'api',
]);

// 返回结构
[
    'list' => [...],      // 日志列表
    'total' => 1250,      // 总记录数
    'page' => 1,          // 当前页
    'page_size' => 20,    // 每页数量
]
```

### 获取统计信息

```php
$stats = Anon_System_AccessLog::getStatistics([
    'start_date' => '2026-03-01 00:00:00',
    'end_date' => '2026-03-22 23:59:59',
]);

// 返回结构
[
    'total' => 1250,       // 总访问量
    'unique_ips' => 89,    // 独立 IP 数
    'top_pages' => [       // 热门页面 TOP 10
        ['path' => '/api/articles', 'count' => 350],
        ['path' => '/api/users', 'count' => 280],
        // ...
    ],
]
```

### 清理旧日志

```php
// 清理 90 天前的日志
$deletedCount = Anon_System_AccessLog::cleanOldLogs(90);

echo "已删除 {$deletedCount} 条旧日志";
```

### 设置排除路径

```php
// 自定义排除路径
Anon_System_AccessLog::setExcludedPaths([
    '/health-check',
    '/metrics',
    '/internal',
]);
```

## 数据库表结构

访问日志存储在 `access_logs` 表中：

```sql
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(2048) NOT NULL,
    path VARCHAR(512) NOT NULL,
    method VARCHAR(10) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'page',
    ip VARCHAR(50) NOT NULL,
    user_agent TEXT,
    referer VARCHAR(2048),
    status_code INT NOT NULL,
    response_time INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_path (path),
    INDEX idx_ip (ip),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
);
```

## 请求类型说明

系统自动检测请求类型：

- **api**: API 接口请求（路径以 `/api/` 或 `/anon/` 开头，或 Accept 头包含 `application/json`）
- **page**: 页面请求（HTML 页面）
- **static**: 静态资源（CSS/JS/图片等）

## 性能优化建议

### 1. 定期清理旧日志

```php
// 在定时任务中执行
Anon_System_AccessLog::cleanOldLogs(90);
```

### 2. 按需启用

开发环境可关闭访问日志，生产环境启用：

```php
// useApp.php
'app' => [
    'accessLog' => [
        'enabled' => Anon_System_Env::get('app.debug.global', false) ? false : true,
    ],
],
```

### 3. 排除不必要的路径

根据实际需求调整排除规则，减少日志量：

```php
Anon_System_AccessLog::setExcludedPaths([
    // ... 默认排除路径
    '/health',           // 健康检查
    '/metrics',          // 监控指标
]);
```

## 相关文档

- [缓存标签](cache-tags.md) - 缓存批量清除功能
- [缓存锁](cache-lock.md) - 防止缓存击穿
- [Redis 缓存](cache-redis-guide.md) - Redis 缓存使用指南
