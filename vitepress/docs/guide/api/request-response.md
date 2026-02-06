# 请求与响应

一句话：获取请求数据、验证参数、返回JSON响应。

## 获取请求数据

```php
// 获取请求输入数据，支持JSON和表单数据
$data = Anon_Http_Request::getInput();
// 返回包含键值对的数组

// 从GET或POST获取参数
$value = Anon_Http_Request::get('key', 'default');
$value = Anon_Http_Request::get('username'); // 不存在返回null

// 获取POST参数
$value = Anon_Http_Request::post('key', 'default');

// 获取GET参数
$value = Anon_Http_Request::getParam('key', 'default');

// 获取必需参数（不存在则返回错误）
$value = Anon_Http_Request::require('username', '用户名不能为空');
```

## 验证请求

```php
// 验证必需参数
$data = Anon_Http_Request::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空'
]);
// 验证失败自动返回400错误

// 要求特定HTTP方法
Anon_Http_Request::requireMethod('POST');
Anon_Http_Request::requireMethod(['POST', 'PUT']);

// 检查请求方法
$method = Anon_Http_Request::method(); // 'GET'、'POST'等
$isPost = Anon_Http_Request::isPost();
$isGet = Anon_Http_Request::isGet();
```

## 用户认证

```php
// 从会话或Cookie获取当前用户ID
$userId = Anon_Http_Request::getUserId();
// 返回: int|null

// 获取需要登录的当前用户信息
$userInfo = Anon_Http_Request::requireAuth();
// 未登录自动返回401错误
// 返回: ['uid' => 1, 'name' => 'admin', 'email' => '...', ...]

// 验证API Token（防止API被刷）
Anon_Http_Request::requireToken();
// Token无效自动返回403错误
```

## Token 生成

```php
// 获取或生成Token
$token = Anon_Http_Request::getUserToken($userId, $username, $rememberMe);

// 登录时总是生成新Token
$token = Anon_Http_Request::generateUserToken($userId, $username, $rememberMe);
```

## 成功响应

```php
// 基本成功响应
Anon_Http_Response::success($data, '操作成功', 200);
Anon_Http_Response::success(['id' => 1, 'name' => 'test'], '创建成功');

// 分页响应
Anon_Http_Response::paginated($data, $pagination, '获取数据成功', 200);
// $pagination = ['page' => 1, 'per_page' => 10, 'total' => 100]
```

## 错误响应

```php
// 基本错误响应
Anon_Http_Response::error('操作失败', $data, 400);

// 验证错误
Anon_Http_Response::validationError('参数验证失败', $errors);
// $errors = ['field1' => '错误消息1', 'field2' => '错误消息2']

// 未授权，返回401
Anon_Http_Response::unauthorized('请先登录');

// 禁止访问，返回403
Anon_Http_Response::forbidden('权限不足');

// 未找到，返回404
Anon_Http_Response::notFound('资源不存在');

// 方法不允许，返回405
Anon_Http_Response::methodNotAllowed('GET, POST');

// 服务器错误，返回500
Anon_Http_Response::serverError('服务器内部错误', $data);
```

## 异常处理

```php
try {
    // 业务逻辑
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '操作时发生错误');
    // 自动根据异常类型返回合适的HTTP状态码
}
```

## HTTP 响应头

```php
// 设置HTTP响应头
Anon_Common::Header(200, true, true);              // 200, JSON, CORS
Anon_Common::Header404();                          // 404, JSON, CORS
Anon_Common::Header(200, false);                  // 200, 非JSON, CORS
Anon_Common::Header(200, true, false);            // 200, JSON, 非CORS
// 参数：HTTP状态码，是否设置JSON响应头，是否设置CORS头

// 要求登录，未登录返回401
Anon_Common::RequireLogin();

// 获取系统信息
$info = Anon_Common::SystemInfo();
// 返回：['system' => [...], 'copyright' => [...]]

// 获取客户端真实IP
$ip = Anon_Common::GetClientIp();
// 返回：string|null
```

## 完整示例

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_Http_RouterMeta = [
    'header' => true,
    'requireLogin' => false,
    'method' => 'POST',
];

try {
    // 验证请求方法
    Anon_Http_Request::requireMethod('POST');
    
    // 获取并验证参数
    $data = Anon_Http_Request::validate([
        'username' => '用户名不能为空',
        'email' => '邮箱不能为空'
    ]);
    
    // 业务逻辑处理
    $result = [
        'id' => 1,
        'username' => $data['username'],
        'email' => $data['email']
    ];
    
    // 返回成功响应
    Anon_Http_Response::success($result, '创建成功', 201);
    
} catch (Exception $e) {
    Anon_Http_Response::handleException($e, '创建用户时发生错误');
}
```

