# 性能优化

一句话：框架核心性能优化，提升响应速度和降低资源消耗。

---

## 数据库连接池化

### 单例模式

`Anon_Database` 已改为单例模式，确保同一请求生命周期内复用数据库连接，减少连接创建开销。

#### 使用方式

```php
// 推荐使用单例模式
$db = Anon_Database::getInstance();

// 获取查询构建器
$users = $db->db('users')->get();
```

#### 实现原理

- 私有构造函数防止外部实例化
- `getInstance()` 方法确保全局唯一实例
- 防止克隆和反序列化
- 同一请求内多次调用返回同一实例

#### 性能提升

- 减少数据库连接创建开销
- 降低内存占用
- 提高并发处理能力

---

## Session 优化

### CSRF Token 无状态设计

采用基于 HMAC 的无状态 CSRF Token，无需存储在 Session 中，减少 Session 锁竞争。

#### 配置方式

在 `useApp.php` 中配置：

```php
return [
    'app' => [
        'security' => [
            'csrf' => [
                'enabled' => true,
                'stateless' => true, // 启用无状态 Token
            ],
        ],
    ],
];
```

#### 工作原理

无状态 Token 包含：
- 时间戳：用于验证 Token 有效期
- 随机数：防止重放攻击
- Session ID：关联用户会话
- HMAC 签名：验证 Token 完整性

#### 优势

- 无需存储在 Session 中，减少锁竞争
- 支持分布式部署
- 提高并发性能
- Token 有效期 2 小时

#### 兼容性

框架同时支持：
- 无状态 Token（默认，推荐）
- 传统 Session 存储（可通过配置切换）

---

## 钩子系统轻量化

### 回调缓存机制

钩子系统增加了回调缓存机制，避免重复解析回调函数，提升执行效率。

#### 优化内容

1. **回调函数缓存**
   - 缓存已解析的回调函数信息
   - 避免重复反射和解析

2. **执行结果缓存**
   - 过滤器钩子结果缓存
   - 减少重复计算

3. **闭包警告**
   - 使用闭包时记录警告日志
   - 建议使用命名函数或类方法

#### 使用建议

```php
// 推荐：使用命名函数
function my_callback($data) {
    return $data;
}
Anon_System_Hook::add_filter('my_filter', 'my_callback');

// 推荐：使用类方法
class MyClass {
    public static function callback($data) {
        return $data;
    }
}
Anon_System_Hook::add_filter('my_filter', [MyClass::class, 'callback']);

// 不推荐：使用闭包（影响性能）
Anon_System_Hook::add_filter('my_filter', function($data) {
    return $data;
});
```

#### 性能提升

- 减少回调函数解析开销
- 降低内存占用
- 提高钩子执行速度

---

## 配置缓存

### 配置值缓存

`Anon_System_Env` 内部缓存已加载的配置，首次解析后存入内存，后续调用直接读取缓存。

#### 工作原理

```php
// 首次调用：解析配置并缓存
$value1 = Anon_System_Env::get('app.token.enabled');

// 后续调用：直接从缓存读取
$value2 = Anon_System_Env::get('app.token.enabled');
```

#### 清除缓存

```php
// 清除所有配置缓存
Anon_System_Env::clearCache();
```

#### 性能提升

- 减少配置解析开销
- 降低 CPU 使用率
- 提高配置读取速度

---

## Token 验证缓存

### 验证结果缓存

对已验证的 Token 暂存于内存缓存，避免重复计算验证逻辑。

#### 工作原理

1. Token 验证成功后，将结果存入缓存
2. 缓存有效期设置为 Token 剩余有效期的 80%
3. 后续相同 Token 的验证直接从缓存读取

#### 缓存策略

- 缓存键：基于 Token 内容的 SHA256 哈希
- 缓存值：验证结果和过期时间
- 自动过期：缓存过期时间不超过 Token 本身有效期

#### 性能提升

- 减少 Token 验证计算开销
- 降低 CPU 使用率
- 提高 API 响应速度

---

## 注释规范

### 开发规范要求

所有代码注释严格按照开发规范：

1. **使用简洁的中文注释**
   - 避免使用括号进行解释
   - 直接说明代码功能

2. **注释位置**
   - 注释放在代码上方
   - 与代码对齐

3. **注释内容**
   - 说明代码作用，不解释实现细节
   - 避免冗余信息

#### 正确示例

```php
// 获取用户ID从会话或Cookie
$userId = Anon_Http_Request::getUserId();

// 验证IP地址格式，无效IP设为默认值
$ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
```

#### 错误示例

```php
// ❌ 错误：获取用户ID（从会话或Cookie）
// ❌ 错误：验证IP地址格式（无效IP设为默认值）
```

---

## 性能指标

### 优化前

- 数据库连接：每次请求创建新连接
- CSRF Token：存储在 Session，存在锁竞争
- 钩子执行：每次解析回调函数
- 配置读取：每次解析配置路径
- Token 验证：每次完整验证计算

### 优化后

- 数据库连接：单例模式，请求内复用
- CSRF Token：无状态设计，无锁竞争
- 钩子执行：回调缓存，减少解析
- 配置读取：内存缓存，直接读取
- Token 验证：结果缓存，减少计算

### 预期提升

- **响应时间**：减少 20-30%
- **内存占用**：降低 15-25%
- **CPU 使用率**：降低 10-20%
- **并发能力**：提升 30-50%

---

## 最佳实践

### 数据库操作

```php
// 推荐：使用单例模式
$db = Anon_Database::getInstance();
$user = $db->getUserInfo($userId);
```

### CSRF Token

```php
// 推荐：使用无状态 Token（默认启用）
$token = Anon_Auth_Csrf::generateToken();
Anon_Auth_Csrf::verify($token);
```

### 钩子注册

```php
// 推荐：使用命名函数或类方法
Anon_System_Hook::add_action('my_action', 'my_function');
Anon_System_Hook::add_filter('my_filter', [MyClass::class, 'method']);
```

### 配置读取

```php
// 配置会自动缓存，无需特殊处理
$enabled = Anon_System_Env::get('app.token.enabled', false);
```

### Token 验证

```php
// Token 验证结果会自动缓存
$payload = Anon_Auth_Token::verify($token);
```

---

## 注意事项

### 数据库单例

- 单例模式确保请求内连接复用
- 不同请求之间连接会自动管理
- 无需手动关闭连接

### CSRF Token

- 无状态 Token 默认启用
- 可通过配置切换为传统模式
- Token 有效期 2 小时

### 钩子缓存

- 回调缓存自动管理
- 过滤器结果缓存可手动清除
- 建议避免使用闭包

### 配置缓存

- 配置缓存自动管理
- 配置更新后需清除缓存
- 开发环境建议禁用缓存

### Token 验证缓存

- 验证结果缓存自动管理
- 缓存过期时间自动计算
- 无需手动清除缓存

---

## 故障排查

### 数据库连接问题

如果遇到数据库连接问题，检查：
1. 是否正确使用 `getInstance()` 方法
2. 数据库配置是否正确
3. 连接池是否正常工作

### CSRF Token 问题

如果遇到 CSRF Token 验证失败：
1. 检查是否启用无状态模式
2. 验证 Token 是否过期
3. 检查 Session ID 是否匹配

### 钩子执行问题

如果钩子未执行：
1. 检查回调函数是否正确注册
2. 验证回调函数是否可调用
3. 查看调试日志获取详细信息

### 配置读取问题

如果配置读取异常：
1. 清除配置缓存：`Anon_System_Env::clearCache()`
2. 检查配置键是否正确
3. 验证配置文件格式

### Token 验证问题

如果 Token 验证失败：
1. 检查 Token 是否过期
2. 验证 Token 签名是否正确
3. 查看调试日志获取详细信息

