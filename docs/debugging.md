# 调试工具

一句话：记录日志、监控性能、查看SQL查询，支持Web调试控制台。

## 代码调试

### 日志记录

```php
// 通用日志方法
Anon_Debug::log('INFO', '消息', ['context' => 'data']);
Anon_Debug::log('ERROR', '错误', ['file' => 'test.php']);

// 快捷方法
Anon_Debug::debug('调试消息', ['key' => 'value']);
Anon_Debug::info('信息消息');
Anon_Debug::warn('警告消息');
Anon_Debug::error('错误消息', ['context' => 'data']);
Anon_Debug::fatal('致命错误', ['error' => 'details']);
```

### 性能监控

```php
// 开始性能监控
Anon_Debug::startPerformance('operation_name');
// ... 执行操作 ...
Anon_Debug::endPerformance('operation_name', ['data' => 'value']);

// 或使用单次调用
$startTime = microtime(true);
// ... 执行操作 ...
Anon_Debug::performance('operation_name', $startTime, ['data' => 'value']);
```

### SQL查询记录

```php
// 记录SQL查询
Anon_Debug::query('SELECT * FROM users', ['id' => 1], 0.12);
// 参数：SQL语句，参数数组，执行时间秒数
```

### 调试数据管理

```php
// 获取调试数据
$data = Anon_Debug::getData();
// 返回: ['logs' => [...], 'performance' => [...], 'queries' => [...], ...]

// 清空调试数据
Anon_Debug::clear();

// 检查是否启用
$enabled = Anon_Debug::isEnabled();
```

## Web调试控制台

启用调试模式后，访问调试控制台：

```
http://localhost:8080/anon/debug/console
```

**功能：**
- 系统信息：PHP版本、服务器信息、框架信息
- 性能监控：请求耗时、内存使用、数据库查询统计
- 日志查看：系统日志、错误日志
- 钩子监控：已注册的钩子和执行统计
- 工具集：清理调试数据、导出日志等

**登录：**
调试控制台需要登录才能访问，使用系统管理员账号登录。

---

[← 返回文档首页](../README.md)
