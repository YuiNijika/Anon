# 请求与响应

## 请求处理

```php
// 检查请求方法
Anon_RequestHelper::requireMethod('POST');
Anon_RequestHelper::requireMethod(['GET', 'POST']);

// 获取输入
$data = Anon_RequestHelper::getInput();  // JSON 或 POST
$username = Anon_RequestHelper::get('username', 'default');

// 验证必需参数
$data = Anon_RequestHelper::validate([
    'username' => '用户名不能为空',
    'password' => '密码不能为空',
]);
```

## 响应处理

```php
// 成功响应
Anon_ResponseHelper::success($data, '操作成功');
Anon_ResponseHelper::success($data, '操作成功', 201);

// 错误响应
Anon_ResponseHelper::error('错误消息');
Anon_ResponseHelper::error('错误消息', $data, 400);

// HTTP 状态码响应
Anon_ResponseHelper::unauthorized('未授权访问');
Anon_ResponseHelper::forbidden('禁止访问');
Anon_ResponseHelper::notFound('资源未找到');
Anon_ResponseHelper::serverError('服务器内部错误');
Anon_ResponseHelper::methodNotAllowed('GET, POST');
Anon_ResponseHelper::validationError('参数验证失败', $errors);

// 处理异常
Anon_ResponseHelper::handleException($e, '自定义错误消息');
```

## HTTP 响应头

```php
Anon_Common::Header();              // 200, JSON, CORS
Anon_Common::Header(404);          // 404, JSON, CORS
Anon_Common::Header(200, false);   // 200, 非JSON, CORS
Anon_Common::Header(200, true, false); // 200, JSON, 非CORS
```

---

[← 返回文档首页](../README.md)

