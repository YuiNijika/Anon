# 调试工具

## 代码调试

```php
// 日志记录
Anon_Debug::log('INFO', '消息');
Anon_Debug::log('ERROR', '错误');
Anon_Debug::info('信息消息');
Anon_Debug::error('错误消息', ['context' => 'data']);

// 性能监控
Anon_Debug::startPerformance('operation_name');
// ... 执行操作 ...
Anon_Debug::endPerformance('operation_name');

// SQL 查询记录
Anon_Debug::query('SELECT * FROM users', ['id' => 1], 0.12);
```

## Web 调试控制台

启用调试模式后，访问调试控制台：

```
http://localhost:8080/anon/debug/console
```

**功能**：

- 系统信息：PHP 版本、服务器信息、框架信息
- 性能监控：请求耗时、内存使用、数据库查询统计
- 日志查看：系统日志、错误日志
- 钩子监控：已注册的钩子和执行统计
- 工具集：清理调试数据、导出日志等

**登录**：

调试控制台需要登录才能访问，使用系统管理员账号登录。

---

[← 返回文档首页](../README.md)

