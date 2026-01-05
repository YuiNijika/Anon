# Anon Framework

> 一个简洁优雅的 PHP API 开发框架

[配套前端](https://github.com/YuiNijika/AnonClient) | [GitHub 仓库](https://github.com/YuiNijika/Anon)

## PHP 版本要求

PHP 7.4 - 8.4

> **注意**：当前分支代码为开发版，生产环境请使用[稳定版](https://github.com/YuiNijika/Anon/releases)

---

## 🚀 快速开始

### 创建第一个 API

```php
// server/app/Router/Hello/World.php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    Anon_Http_Response::success([
        'message' => 'Hello World!',
        'time' => date('Y-m-d H:i:s')
    ], '请求成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

访问：`GET /hello/world`

### 处理 POST 请求

```php
// server/app/Router/Api/User.php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'POST',
];

try {
    Anon_Http_Request::requireMethod('POST');
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空'
    ]);
    
    $userInfo = Anon_Http_Request::requireAuth();
    
    // 业务逻辑...
    
    Anon_Http_Response::success(['id' => 1], '创建成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

---

## 文档导航

### 快速入门

- [快速开始](https://yuinijika.github.io/Anon/guide/quick-start) - 5分钟上手
- [API 参考](https://yuinijika.github.io/Anon/api/api-reference) - 完整方法调用参考

### 核心功能

- [路由处理](https://yuinijika.github.io/Anon/guide/routing) - 自动路由、路由配置
- [数据库操作](https://yuinijika.github.io/Anon/guide/database) - 查询构建器、Repository模式
- [请求与响应](https://yuinijika.github.io/Anon/guide/request-response) - 请求处理、响应处理
- [用户认证](https://yuinijika.github.io/Anon/guide/authentication) - 登录检查、Token验证

### 工具与功能

- [工具类](https://yuinijika.github.io/Anon/guide/tools) - 辅助函数、工具集
- [高级功能](https://yuinijika.github.io/Anon/guide/advanced) - Widget组件、权限系统、钩子
- [现代特性](https://yuinijika.github.io/Anon/guide/modern-features) - 依赖注入、中间件、缓存

### 配置与调试

- [配置说明](https://yuinijika.github.io/Anon/guide/configuration) - 系统配置、应用配置
- [调试工具](https://yuinijika.github.io/Anon/guide/debugging) - 代码调试、Web控制台

### 参考文档

- [开发规范](https://yuinijika.github.io/Anon/guide/coding-standards) - 代码风格、命名规范、最佳实践
- [API 端点](https://yuinijika.github.io/Anon/api/api-endpoints) - 系统端点列表
- [自定义代码](https://yuinijika.github.io/Anon/guide/custom-code) - 在useCode.php中添加代码
- [Token策略](https://yuinijika.github.io/Anon/guide/token-strategy) - Token刷新策略说明
- [大数据处理](https://yuinijika.github.io/Anon/guide/big-data) - 游标分页、批量操作、查询优化
- [安全功能](https://yuinijika.github.io/Anon/guide/security) - CSRF防护、XSS过滤、SQL注入防护

---

## 📋 开发规范

详细的开发规范请参考：[开发规范文档](https://yuinijika.github.io/Anon/guide/coding-standards)

包含内容：

- **代码风格**：缩进、换行、编码等格式规范
- **命名规范**：类名、方法名、变量名、常量名等命名约定
- **注释规范**：注释风格和最佳实践
- **路由文件规范**：路由文件的标准结构和必需元素
- **错误处理规范**：统一的异常处理和错误响应
- **安全规范**：输入验证、输出处理、数据库操作安全
- **代码组织规范**：目录结构和配置管理
- **Git 提交规范**：提交信息格式和类型说明

---

## 📄 许可证

MIT License

Copyright (c) 2024-2025 鼠子(YuiNijika)

---

## 🔗 相关链接

- 📖 [在线文档](https://yuinijika.github.io/Anon/) - 完整的开发文档
- 💻 [GitHub 仓库](https://github.com/YuiNijika/Anon)
- 🎨 [配套前端](https://github.com/YuiNijika/AnonClient)
- 🐛 [问题反馈](https://github.com/YuiNijika/Anon/issues)
