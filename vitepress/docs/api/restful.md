# RESTful API

RESTful API 提供标准化的 HTTP 接口，支持文章、分类、标签的 CRUD 操作。

**注意：** RESTful API 仅在 CMS 模式下可用。

## 启用配置

在后台设置中启用 RESTful API：

- `api_enabled` - 是否启用 API（默认关闭）
- `restful_api_token_required` - GET 请求是否需要 Token（默认需要）

API 基础路径为 `/restful/v1`，完整路径示例：`/anon/restful/v1/posts`

## 认证与权限

### Token 认证

根据配置，GET 请求可能需要携带 Token：

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://example.com/anon/restful/v1/posts
```

### 权限说明

- **读取操作** - 所有用户可访问（需 Token 取决于配置）
- **写入操作** - 仅管理员可执行（POST、PUT、DELETE）
- **批量操作** - 仅管理员可执行

非管理员尝试写入操作将返回 403 错误。

## 通用响应格式

### 成功响应

```json
{
  "code": 200,
  "message": "操作成功",
  "data": {}
}
```

### 错误响应

```json
{
  "code": 400,
  "message": "错误信息",
  "data": null
}
```

### 分页响应

列表接口返回分页信息：

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "page_size": 10,
      "total": 100,
      "total_pages": 10
    }
  }
}
```

## 文章接口

### 获取文章列表

```
GET /restful/v1/posts
```

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码，默认 1 |
| page_size | int | 否 | 每页数量，默认 10，最大 100 |
| type | string | 否 | 文章类型，默认 post |
| status | string | 否 | 状态，仅管理员可用 |
| search | string | 否 | 搜索关键词 |
| content_format | string | 否 | 内容格式：`html` 或 `markdown`，默认 `html` |

**示例：**

```bash
# 获取 HTML 格式（默认）
curl https://example.com/anon/restful/v1/posts?page=1&page_size=10

# 获取 Markdown 格式
curl https://example.com/anon/restful/v1/posts?content_format=markdown
```

### 获取单篇文章

```
GET /restful/v1/posts/{id}
```

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| content_format | string | 否 | 内容格式：`html` 或 `markdown`，默认 `html` |

**示例：**

```bash
# 获取 HTML 格式（默认）
curl https://example.com/anon/restful/v1/posts/1

# 获取 Markdown 格式
curl https://example.com/anon/restful/v1/posts/1?content_format=markdown
```

### 创建文章

```
POST /restful/v1/posts
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | string | 是 | 标题 |
| content | string | 否 | 内容 |
| slug | string | 否 | 别名，默认使用标题 |
| status | string | 否 | 状态，默认 publish |
| category | int | 否 | 分类 ID |
| tags | array | 否 | 标签 ID 数组或名称数组 |
| comment_status | string | 否 | 评论状态，默认 open |

**示例：**

```bash
curl -X POST https://example.com/anon/restful/v1/posts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "测试文章",
    "content": "文章内容",
    "category": 1,
    "tags": ["标签1", "标签2"]
  }'
```

### 更新文章

```
PUT /restful/v1/posts/{id}
```

**请求体：** 同创建文章，所有字段可选

**示例：**

```bash
curl -X PUT https://example.com/anon/restful/v1/posts/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "更新后的标题"
  }'
```

### 删除文章

```
DELETE /restful/v1/posts/{id}
```

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/posts/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 批量删除文章

```
DELETE /restful/v1/posts/batch
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| ids | array | 是 | 文章 ID 数组 |

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/posts/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3]
  }'
```

**响应：**

```json
{
  "code": 200,
  "message": "批量删除成功，共删除 3 个文章",
  "data": {
    "success": 3,
    "failed": 0,
    "errors": []
  }
}
```

## 分类接口

### 获取分类列表

```
GET /restful/v1/categories
```

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| search | string | 否 | 搜索关键词 |

**示例：**

```bash
curl https://example.com/anon/restful/v1/categories
```

### 获取单个分类

```
GET /restful/v1/categories/{id}
```

**示例：**

```bash
curl https://example.com/anon/restful/v1/categories/1
```

### 创建分类

```
POST /restful/v1/categories
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 分类名称 |
| slug | string | 否 | 别名，默认使用名称 |
| description | string | 否 | 描述 |
| parent_id | int | 否 | 父分类 ID |

**示例：**

```bash
curl -X POST https://example.com/anon/restful/v1/categories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "技术",
    "slug": "tech",
    "description": "技术相关分类"
  }'
```

### 更新分类

```
PUT /restful/v1/categories/{id}
```

**请求体：** 同创建分类，所有字段可选

**示例：**

```bash
curl -X PUT https://example.com/anon/restful/v1/categories/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "更新后的名称"
  }'
```

### 删除分类

```
DELETE /restful/v1/categories/{id}
```

**注意：** 有子分类的分类无法删除

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/categories/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 批量删除分类

```
DELETE /restful/v1/categories/batch
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| ids | array | 是 | 分类 ID 数组 |

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/categories/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3]
  }'
```

## 标签接口

### 获取标签列表

```
GET /restful/v1/tags
```

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| search | string | 否 | 搜索关键词 |

**示例：**

```bash
curl https://example.com/anon/restful/v1/tags
```

### 获取单个标签

```
GET /restful/v1/tags/{id}
```

**示例：**

```bash
curl https://example.com/anon/restful/v1/tags/1
```

### 创建标签

```
POST /restful/v1/tags
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 标签名称 |
| slug | string | 否 | 别名，默认使用名称 |

**注意：** 如果标签已存在，返回现有标签

**示例：**

```bash
curl -X POST https://example.com/anon/restful/v1/tags \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "PHP",
    "slug": "php"
  }'
```

### 更新标签

```
PUT /restful/v1/tags/{id}
```

**请求体：** 同创建标签，所有字段可选

**示例：**

```bash
curl -X PUT https://example.com/anon/restful/v1/tags/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "更新后的标签名"
  }'
```

### 删除标签

```
DELETE /restful/v1/tags/{id}
```

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/tags/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 批量删除标签

```
DELETE /restful/v1/tags/batch
```

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| ids | array | 是 | 标签 ID 数组 |

**示例：**

```bash
curl -X DELETE https://example.com/anon/restful/v1/tags/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3]
  }'
```

## 钩子扩展

RESTful API 提供丰富的钩子用于扩展功能。

### 文章钩子

**过滤器：**

- `restful_post_list_params` - 修改列表查询参数
- `restful_post_list_response` - 修改列表响应数据
- `restful_post_before_create` - 创建前修改数据
- `restful_post_before_update` - 更新前修改数据

**动作：**

- `restful_post_after_create` - 创建后触发
- `restful_post_after_update` - 更新后触发
- `restful_post_before_delete` - 删除前触发
- `restful_post_after_delete` - 删除后触发
- `restful_post_after_batch_delete` - 批量删除后触发

### 分类钩子

**过滤器：**

- `restful_category_list_params` - 修改列表查询参数
- `restful_category_list_response` - 修改列表响应数据
- `restful_category_before_create` - 创建前修改数据
- `restful_category_before_update` - 更新前修改数据

**动作：**

- `restful_category_after_create` - 创建后触发
- `restful_category_after_update` - 更新后触发
- `restful_category_before_delete` - 删除前触发
- `restful_category_after_delete` - 删除后触发
- `restful_category_after_batch_delete` - 批量删除后触发

### 标签钩子

**过滤器：**

- `restful_tag_list_params` - 修改列表查询参数
- `restful_tag_list_response` - 修改列表响应数据
- `restful_tag_before_create` - 创建前修改数据
- `restful_tag_before_update` - 更新前修改数据

**动作：**

- `restful_tag_after_create` - 创建后触发
- `restful_tag_after_update` - 更新后触发
- `restful_tag_before_delete` - 删除前触发
- `restful_tag_after_delete` - 删除后触发
- `restful_tag_after_batch_delete` - 批量删除后触发

## 错误码

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 201 | 创建成功 |
| 400 | 请求参数错误 |
| 403 | 权限不足 |
| 404 | 资源不存在 |
| 405 | 请求方法不支持 |
| 500 | 服务器内部错误 |

## 最佳实践

### 1. 合理使用缓存

对于频繁读取的数据，建议在客户端实现缓存机制。

### 2. 处理批量操作结果

批量操作可能部分成功，务必检查响应中的 `success` 和 `failed` 字段。

### 3. 错误处理

始终检查响应的 `code` 字段，妥善处理各种错误情况。

### 4. 权限管理

确保只有授权用户可以执行写入操作，不要在客户端存储敏感凭证。
