# Anon Framework

> 一个简洁优雅的 PHP API 开发框架

[配套前端](https://github.com/YuiNijika/AnonClient) | [GitHub 仓库](https://github.com/YuiNijika/Anon)

**PHP 版本要求：7.4 - 8.4**

**当前分支代码为开发版，生产环境请使用[稳定版](https://github.com/YuiNijika/Anon/releases)**

---

## 🚀 快速开始

### 创建第一个 API

```php
// server/app/Router/Hello/World.php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    Anon_ResponseHelper::success([
        'message' => 'Hello World!',
        'time' => date('Y-m-d H:i:s')
    ], '请求成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

访问：`GET /hello/world`

### 处理 POST 请求

```php
// server/app/Router/Api/User.php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'POST',
];

try {
    Anon_RequestHelper::requireMethod('POST');
    $data = Anon_RequestHelper::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空'
    ]);
    
    $userInfo = Anon_RequestHelper::requireAuth();
    
    // 业务逻辑...
    
    Anon_ResponseHelper::success(['id' => 1], '创建成功');
} catch (Exception $e) {
    Anon_ResponseHelper::handleException($e);
}
```

---

## 📚 文档导航

### 🎯 快速入门

- [🚀 快速开始](./docs/quick-start.md) - 5分钟上手
- [📖 API 参考](./docs/api-reference.md) - 完整方法调用参考

### 核心功能

- [路由处理](./docs/routing.md) - 自动路由、路由配置
- [数据库操作](./docs/database.md) - 查询构建器、Repository模式
- [请求与响应](./docs/request-response.md) - 请求处理、响应处理
- [用户认证](./docs/authentication.md) - 登录检查、Token验证

### 工具与功能

- [工具类](./docs/tools.md) - 辅助函数、工具集
- [高级功能](./docs/advanced.md) - Widget组件、权限系统、钩子
- [现代特性](./docs/modern-features.md) - 依赖注入、中间件、缓存

### 配置与调试

- [配置说明](./docs/configuration.md) - 系统配置、应用配置
- [调试工具](./docs/debugging.md) - 代码调试、Web控制台

### 参考文档

- [API 端点](./docs/api-endpoints.md) - 系统端点列表
- [自定义代码](./docs/custom-code.md) - 在useCode.php中添加代码
- [Token策略](./docs/token-strategy.md) - Token刷新策略说明

---

## 📄 许可证

MIT License

Copyright (c) 2024-2025 鼠子(YuiNijika)

---

## 🔗 相关链接

- [GitHub 仓库](https://github.com/YuiNijika/Anon)
- [配套前端](https://github.com/YuiNijika/AnonClient)
- [问题反馈](https://github.com/YuiNijika/Anon/issues)
