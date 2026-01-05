# 开发规范

一句话：统一的代码风格、命名规范和最佳实践，确保代码质量和可维护性。

---

## 代码风格

### 基本格式

- **缩进**：使用 4 个空格，不使用 Tab
- **换行**：Unix 风格（LF），不使用 Windows 风格（CRLF）
- **编码**：UTF-8 without BOM
- **行尾空格**：删除所有行尾空格

### 代码示例

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
    ]);
    
    Anon_Http_Response::success($data, '操作成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

---

## 命名规范

### 类名

- 使用 `PascalCase`（大驼峰）
- 示例：`Anon_System_Config`、`Anon_Http_Request`、`Anon_Database_UserRepository`

```php
class Anon_System_Config
{
    // ...
}

class Anon_Database_UserRepository
{
    // ...
}
```

### 方法名

- 使用 `camelCase`（小驼峰）
- 示例：`getUserInfo()`、`addRoute()`、`requireAuth()`

```php
public function getUserInfo($uid)
{
    // ...
}

public function addRoute(string $path, callable $handler)
{
    // ...
}
```

### 常量名

- 使用 `UPPER_SNAKE_CASE`（全大写下划线）
- 示例：`ANON_ALLOWED_ACCESS`、`ANON_DB_HOST`、`Anon_RouterMeta`

```php
define('ANON_ALLOWED_ACCESS', true);
define('ANON_DB_HOST', 'localhost');

const Anon_Http_RouterMeta = [
    'header' => true,
];
```

### 变量名

- 使用 `camelCase`（小驼峰）
- 示例：`$userInfo`、`$requestPath`、`$userId`

```php
$userInfo = Anon_Http_Request::requireAuth();
$requestPath = self::getRequestPath();
$userId = (int)$_SESSION['user_id'];
```

### 文件名

- **路由文件**：使用 `PascalCase`，如 `Login.php`、`User/Info.php`
- **配置文件**：使用 `camelCase`，如 `useApp.php`、`useRouter.php`
- **类文件**：与类名保持一致

```
app/Router/
├── Auth/
│   ├── Login.php
│   ├── Logout.php
│   └── Register.php
└── User/
    └── Info.php

app/
├── useApp.php
├── useRouter.php
├── useSQL.php
└── useCode.php
```

---

## 注释规范

### 注释风格

- **注释风格**：使用直观、简洁的中文注释
- **避免括号**：注释中不要使用括号进行解释，直接说明
- **位置**：注释放在代码上方，与代码对齐

### 正确示例

```php
// 防止XSS清理用户名并限制长度最大255字符
if ($username !== null) {
    $username = mb_substr(trim($username), 0, 255, 'UTF-8');
}

// 获取用户ID从会话或Cookie
$userId = Anon_Http_Request::getUserId();

// 验证IP地址格式，无效IP设为默认值
$ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = '0.0.0.0';
}
```

### 错误示例

```php
// ❌ 错误：清理用户名（防止XSS），限制长度（最大255字符）
// ❌ 错误：获取用户ID（从会话或Cookie）
// ❌ 错误：验证IP地址格式（无效IP设为默认值）
```

### 方法注释

```php
/**
 * 获取用户信息
 * @param int $uid 用户ID
 * @return array 用户信息
 */
public function getUserInfo($uid)
{
    // ...
}
```

---

## 路由文件规范

### 文件结构

每个路由文件必须遵循以下结构：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'GET',
];

try {
    // 业务逻辑
    Anon_Http_Response::success($data, '操作成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e);
}
```

### 必需元素

1. **安全检查**：文件开头必须包含 `if (!defined('ANON_ALLOWED_ACCESS')) exit;`
2. **路由元数据**：使用 `Anon_RouterMeta` 常量配置路由属性
3. **异常处理**：使用 `try-catch` 包裹业务逻辑，统一使用 `Anon_Http_Response::handleException()` 处理异常

### 完整示例

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => true,
    'method' => 'POST',
    'token' => false,
];

try {
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'password' => '密码不能为空',
    ]);
    
    $userInfo = Anon_Http_Request::requireAuth();
    
    // 业务逻辑处理
    $result = performBusinessLogic($data, $userInfo);
    
    Anon_Http_Response::success($result, '操作成功');
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '处理过程中发生错误');
}
```

---

## 错误处理规范

### 统一异常处理

- **统一使用**：`Anon_Http_Response::handleException($e)` 处理异常
- **自定义消息**：可传入第二个参数自定义错误消息

```php
try {
    // 业务逻辑
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '登录处理过程中发生错误');
}
```

### 参数验证

- **验证必需参数**：使用 `Anon_Http_Request::validate()` 进行参数验证

```php
$data = Anon_Http_Request::validate([
    'username' => '用户名不能为空',
    'email' => '邮箱不能为空',
    'password' => '密码不能为空',
]);
```

### 认证检查

- **登录检查**：使用 `Anon_Http_Request::requireAuth()` 进行登录检查

```php
$userInfo = Anon_Http_Request::requireAuth();
// 未登录自动返回401错误
```

### HTTP 方法检查

```php
Anon_Http_Request::requireMethod('POST');
Anon_Http_Request::requireMethod(['POST', 'PUT']);
```

---

## 安全规范

### 输入验证

- 所有用户输入必须验证和清理
- 使用 `Anon_Http_Request::validate()` 验证必需参数
- 防止 SQL 注入：使用查询构建器，不要拼接 SQL
- 防止 XSS：清理用户输入，限制字符串长度

```php
// ✅ 正确：使用查询构建器
$user = $this->db('users')
    ->where('name', '=', $username)
    ->first();

// ❌ 错误：拼接SQL
$sql = "SELECT * FROM users WHERE name = '{$username}'";
```

```php
// ✅ 正确：清理并限制长度
$username = mb_substr(trim($username), 0, 255, 'UTF-8');

// ✅ 正确：验证IP地址
$ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = '0.0.0.0';
}
```

### 输出处理

- JSON 响应避免返回 `null` 值，使用空数组或空字符串
- 使用 `Anon_Http_Response` 统一响应格式
- 敏感信息不要输出到响应中

```php
// ✅ 正确：返回空数组而不是null
Anon_Http_Response::success([], '操作成功');

// ❌ 错误：返回null
Anon_Http_Response::success(null, '操作成功');
```

### 数据库操作

- 使用查询构建器，不要直接拼接 SQL
- 使用事务处理多步操作
- 参数验证：IP 地址、字符串长度、数值范围等

```php
// ✅ 正确：使用事务
$this->conn->begin_transaction();
try {
    $id = $this->db('users')->insert([...])->execute();
    $this->db('user_meta')->insert([...])->execute();
    $this->conn->commit();
} catch (Exception $e) {
    $this->conn->rollback();
    throw $e;
}
```

---

## 代码组织规范

### 目录结构

```
server/
├── app/                    # 应用代码
│   ├── Router/            # 路由文件
│   │   ├── Auth/          # 认证相关路由
│   │   └── User/          # 用户相关路由
│   ├── Database/          # 数据库 Repository
│   │   └── User.php       # 用户数据操作
│   ├── useApp.php         # 应用配置
│   ├── useRouter.php      # 手动路由配置
│   ├── useSQL.php         # SQL 安装配置
│   └── useCode.php        # 自定义代码
├── core/                   # 核心代码
│   ├── Modules/           # 核心模块
│   │   ├── Router.php     # 路由处理
│   │   ├── Database.php   # 数据库连接
│   │   └── ...
│   └── Static/            # 静态资源
│       ├── debug.css
│       └── debug.js
└── docs/                   # 文档
```

### 配置管理

- **系统配置**：`env.php`（数据库、安装状态等）
- **应用配置**：`useApp.php`（路由、Token、验证码等）
- **SQL 配置**：`useSQL.php`（数据库表结构）
- **使用方式**：`Anon_System_Env::get()` 获取配置值

```php
// 获取配置
$enabled = Anon_System_Env::get('app.token.enabled', false);
$host = Anon_System_Env::get('system.db.host', 'localhost');
```

---

## Git 提交规范

### 提交信息格式

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Type 类型

- `feat`: 新功能
- `fix`: 修复 Bug
- `docs`: 文档更新
- `style`: 代码格式调整（不影响功能）
- `refactor`: 代码重构
- `perf`: 性能优化
- `test`: 测试相关
- `chore`: 构建/工具相关

### Scope 范围（可选）

- `auth`: 认证相关
- `router`: 路由相关
- `database`: 数据库相关
- `config`: 配置相关
- `docs`: 文档相关

### 提交示例

```
feat(auth): 添加用户登录日志记录域名功能

在登录日志表中添加 domain 字段，记录用户登录时使用的域名

fix(router): 修复静态文件路由 404 问题

docs: 更新开发规范文档
```

---

## 最佳实践

### 代码复用

- 使用 `Anon_Http_Response` 统一响应格式
- 使用 `Anon_Http_Request` 统一请求处理
- 使用 Repository 模式组织数据库操作

### 性能优化

- 避免在循环中执行数据库查询
- 使用缓存减少重复计算
- 合理使用索引优化查询

### 可维护性

- 保持方法简短，单一职责
- 使用有意义的变量名和方法名
- 添加必要的注释说明复杂逻辑
