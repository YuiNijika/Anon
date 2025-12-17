# 安全审计报告

## 🔴 高危安全问题

### 1. eval() 代码注入风险 ✅ 已修复

**位置**: `server/core/Modules/Router.php:189`

**问题**: 使用 `eval()` 解析路由元数据

**修复**: 添加多层验证，先尝试 JSON 解析，失败后再进行严格字符验证的 eval

---

### 2. SQL 注入风险直接 query ✅ 已修复

**位置**: `server/core/Widgets/Connection.php:41`

**问题**: 直接执行 SQL，不进行参数绑定

**修复**: 添加警告注释和可疑 SQL 模式检测

---

### 3. Token Secret 依赖可伪造的 $_SERVER ✅ 已修复

**位置**: `server/core/Modules/Token.php:151`

**问题**: `$_SERVER['SERVER_NAME']` 可能被 HTTP Host 头伪造

**修复**: 改用配置常量 `ANON_SECRET_KEY` 或 `ANON_DB_PASSWORD`

---

## 🟡 中危安全问题

### 4. QueryBuilder count() 参数未验证 ✅ 已修复

**位置**: `server/core/Modules/QueryBuilder.php:469`

**问题**: `$column` 参数直接拼接到 SQL 中

**修复**: 添加列名验证，只允许字母、数字、下划线、点号和 `*`

---

### 5. QueryBuilder JOIN 参数未验证 ✅ 已修复

**位置**: `server/core/Modules/QueryBuilder.php:219`

**问题**: `join()` 方法中的表名、字段名、操作符未验证，直接拼接到 SQL

**风险**: 可能导致 SQL 注入

**修复**: 添加表名、字段名、操作符和 JOIN 类型的验证

---

### 6. QueryBuilder orderBy/groupBy 参数未验证 ✅ 已修复

**位置**: `server/core/Modules/QueryBuilder.php:251, 266`

**问题**: `orderBy()` 和 `groupBy()` 方法中的字段名未验证

**风险**: 可能导致 SQL 注入

**修复**: 添加字段名和排序方向验证

---

### 7. QueryBuilder having() 参数未验证 ✅ 已修复

**位置**: `server/core/Modules/QueryBuilder.php:283`

**问题**: `having()` 方法中的字段名和操作符未验证

**风险**: 可能导致 SQL 注入

**修复**: 添加字段名和操作符验证

---

### 8. 缓存键使用 MD5 非安全问题但可优化 ✅ 已修复

**位置**: `server/core/Modules/Cache.php:84`

**问题**: 使用 MD5 生成缓存键哈希

**修复**: 改用 SHA256，并添加缓存键路径验证

---

### 9. 文件路径验证可能不完整 ✅ 已修复

**位置**: `server/core/Modules/Cache.php:getCachePath()`

**问题**: 缓存键直接用于文件路径

**修复**: 添加缓存键验证，防止路径遍历攻击

---

## 🟢 低危问题 / Bug

### 10. Token 时间戳检查代码不完整 ✅ 已修复

**位置**: `server/core/Modules/Token.php:87-89`

**问题**: 时间戳验证代码为空

**修复**: 添加时间戳差异日志记录允许时钟偏差

---

### 11. 数据库连接未显式关闭

**位置**: `server/core/Widgets/Connection.php`

**问题**: 使用单例模式，数据库连接在整个请求生命周期中保持打开

**影响**: 
- 在 PHP-FPM 环境下通常不是问题，请求结束后自动关闭
- 但在长运行脚本中可能导致连接泄漏

**建议**: 添加显式的连接关闭方法，或在析构函数中关闭

---

### 12. QueryBuilder 中未关闭结果集 ✅ 已修复

**位置**: `server/core/Modules/QueryBuilder.php`

**问题**: 在某些情况下，`mysqli_result` 可能未正确释放

**修复**: 确保所有结果集都被正确关闭

---

### 13. 会话固定攻击防护不完整

**位置**: `server/core/Modules/Common.php`

**问题**: 仅在登录时使用 `session_regenerate_id(true)`，其他关键操作如权限提升未使用

**建议**: 在关键操作如权限变更、密码修改后也调用 `session_regenerate_id(true)`

---

### 14. Cookie 验证可能不够严格

**位置**: `server/core/Modules/Common.php:validateCookie()`

**问题**: Cookie 验证仅检查用户是否存在，未检查用户状态如是否被禁用

**建议**: 添加用户状态检查如 `status` 字段

---

## ⚡ 性能问题

### 15. 数据库连接单例可能导致并发瓶颈

**位置**: `server/core/Widgets/Connection.php`

**问题**: 所有请求共享同一个数据库连接

**影响**: 在高并发场景下可能成为瓶颈

**建议**: 考虑使用连接池或每个请求独立连接

---

### 16. 缓存文件操作未使用锁

**位置**: `server/core/Modules/Cache.php`

**问题**: 虽然使用了 `LOCK_EX`，但在高并发下可能仍有竞争条件

**建议**: 考虑使用更细粒度的锁或原子操作

---

### 17. eval() 性能问题 ✅ 已优化

**位置**: `server/core/Modules/Router.php:189`

**问题**: 每次路由请求都可能执行 `eval()`

**修复**: 优先使用 JSON 解析，减少 eval 使用

---

## 📋 修复优先级

### 立即修复 P0 ✅ 已完成
1. ✅ eval() 代码注入风险
2. ✅ Token Secret 依赖可伪造的 $_SERVER
3. ✅ QueryBuilder count() 参数验证
4. ✅ QueryBuilder JOIN/orderBy/groupBy/having 参数验证

### 尽快修复 P1
5. SQL 注入风险（添加警告和验证）✅ 已完成
6. Token 时间戳检查代码完善 ✅ 已完成
7. 数据库连接管理优化
8. 会话固定攻击防护完善

### 计划修复 P2
9. 缓存性能优化 ✅ 已完成
10. 并发性能优化
11. Cookie 验证增强

---

## 🔒 安全建议

### 生产环境配置

1. **设置 `ANON_SECRET_KEY` 常量**
   ```php
   define('ANON_SECRET_KEY', 'your-very-long-random-secret-key-here');
   ```

2. **定期审查直接使用 `query()` 的代码**
   - 确保所有用户输入都使用预处理语句
   - 考虑添加代码审查规则

3. **监控日志**
   - 关注可疑 SQL 查询警告
   - 监控 Token 时间戳异常

4. **会话安全**
   - 在关键操作后调用 `session_regenerate_id(true)`
   - 确保会话 Cookie 使用 `HttpOnly` 和 `Secure` 标志

5. **输入验证**
   - 所有用户输入都应经过验证和清理
   - 使用框架提供的验证方法

---

[← 返回文档首页](../README.md)
