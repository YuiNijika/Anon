# RESTful API 开发指南

Anon Framework 提供了完整的 RESTful API 支持，采用服务层架构设计，实现了业务逻辑与接口层的分离。

## 架构设计

### 目录结构

```
core/Modules/Cms/
├── RESTful.php              # RESTful API 入口文件（路由注册）
├── RESTful/
│   └── Post.php             # 文章 API 控制器
├── Services.php             # 服务层入口文件
├── Services/
│   └── Post.php             # 文章服务层（业务逻辑）
└── Admin/
    └── Manage/
        └── Posts.php        # 管理后台控制器
```

### 三层架构

1. **路由层** (`RESTful.php`)
   - 负责路由注册和权限配置
   - 根据 HTTP 方法分发请求
   - 检查 API 启用状态

2. **控制器层** (`RESTful/Post.php`)
   - 处理 HTTP 请求和响应
   - 参数验证和格式化
   - 调用服务层执行业务逻辑

3. **服务层** (`Services/Post.php`)
   - 核心业务逻辑实现
   - 数据库操作
   - 数据验证和处理
   - 可被多个控制器复用

## 快速开始

### 启用 RESTful API

在后台 **设置 > 权限设置** 中：

1. 启用 **启用 API** 选项
2. 配置 **API 前缀**（默认 `/api`）
3. 选择 **RESTful API Token 验证**（建议启用）

访问地址：`{API前缀}/restful/v1/*`

例如：
- 如果 API 前缀为 `/api`：`/api/restful/v1/posts`
- 如果 API 前缀为 `/`：`/restful/v1/posts`

### 认证方式

RESTful API 使用用户登录 Token 进行认证：

```javascript
// 请求头中添加 Token
fetch('/api/restful/v1/posts', {
  headers: {
    'X-API-Token': 'your-token-here'
  }
})
```

**权限控制策略：**

| 配置项 | GET 请求 | POST/PUT/DELETE 请求 |
|--------|----------|---------------------|
| Token 验证启用 | 需要 Token | 需要 Token + 管理员权限 |
| Token 验证禁用 | 公开访问 | 需要 Token + 管理员权限 |

> **注意**：写操作（POST/PUT/DELETE）始终需要管理员权限，无论 Token 验证是否启用。

## 文章 API 示例

### 获取文章列表

```http
GET /api/restful/v1/posts?page=1&page_size=10&type=post
```

**查询参数：**
- `page`: 页码（默认 1）
- `page_size`: 每页数量（默认 10，最大 100）
- `type`: 文章类型（post/page，默认 post）
- `status`: 状态筛选（仅管理员可用，普通用户固定返回 publish 状态）
- `search`: 搜索关键词（可选）

**权限说明：**
- **普通用户**：只能获取 `status=publish` 的公开文章，无法通过参数修改
- **管理员**：可以指定 `status` 参数获取不同状态的文章（publish/draft）

**响应示例：**
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "title": "文章标题",
        "slug": "article-slug",
        "content": "文章内容",
        "status": "publish",
        "type": "post",
        "category": 1,
        "tags": [1, 2, 3],
        "created_at": 1234567890,
        "updated_at": 1234567890
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 10,
      "total": 100,
      "total_pages": 10
    }
  }
}
```

### 获取单篇文章

```http
GET /api/restful/v1/posts/1
```

### 创建文章

```http
POST /api/restful/v1/posts
Content-Type: application/json
X-API-Token: your-token

{
  "title": "新文章",
  "slug": "new-article",
  "content": "文章内容",
  "status": "publish",
  "type": "post",
  "category": 1,
  "tags": [1, 2],
  "comment_status": "open"
}
```

**响应：**
```json
{
  "code": 201,
  "message": "创建成功",
  "data": {
    "id": 2,
    "title": "新文章",
    ...
  }
}
```

### 更新文章

```http
PUT /api/restful/v1/posts/1
Content-Type: application/json
X-API-Token: your-token

{
  "title": "更新后的标题",
  "content": "更新后的内容"
}
```

### 删除文章

```http
DELETE /api/restful/v1/posts/1
X-API-Token: your-token
```

**响应：**
```json
{
  "code": 200,
  "message": "删除成功",
  "data": null
}
```

## 开发新的 RESTful API

### 步骤 1：创建服务层

在 `core/Modules/Cms/Services/` 下创建服务类：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XXX 服务层
 */
class Anon_Cms_Services_XXX
{
    /**
     * 获取列表
     */
    public static function getList(int $page = 1, int $pageSize = 20): array
    {
        $db = Anon_Database::getInstance();
        
        // 使用查询构建器
        $query = $db->db('table_name');
        
        $total = (clone $query)->count();
        $items = $query->orderBy('created_at', 'DESC')
                      ->offset(($page - 1) * $pageSize)
                      ->limit($pageSize)
                      ->get();
        
        return [
            'list' => $items ?: [],
            'total' => $total,
        ];
    }
    
    /**
     * 创建
     */
    public static function create(array $data): array
    {
        $db = Anon_Database::getInstance();
        
        // 数据验证
        if (empty($data['name'])) {
            throw new Exception('名称不能为空');
        }
        
        // 插入数据
        $id = $db->db('table_name')->insert([
            'name' => $data['name'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        return self::getById($id);
    }
    
    /**
     * 更新
     */
    public static function update(int $id, array $data): array
    {
        $db = Anon_Database::getInstance();
        
        // 检查是否存在
        $item = self::getById($id);
        if (!$item) {
            throw new Exception('记录不存在');
        }
        
        // 更新数据
        $db->db('table_name')->where('id', $id)->update($data);
        
        return self::getById($id);
    }
    
    /**
     * 删除
     */
    public static function delete(int $id): bool
    {
        $db = Anon_Database::getInstance();
        
        // 检查是否存在
        $item = self::getById($id);
        if (!$item) {
            throw new Exception('记录不存在');
        }
        
        return $db->db('table_name')->where('id', $id)->delete();
    }
    
    /**
     * 获取单个
     */
    public static function getById(int $id): ?array
    {
        $db = Anon_Database::getInstance();
        return $db->db('table_name')->where('id', $id)->first();
    }
}
```

### 步骤 2：创建控制器

在 `core/Modules/Cms/RESTful/` 下创建控制器：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XXX RESTful API 控制器
 */
class Anon_Cms_RESTful_XXX
{
    /**
     * 获取列表
     * GET /api/restful/v1/xxx
     */
    public static function index(): array
    {
        try {
            $page = max(1, intval(Anon_Http_Request::input('page', 1)));
            $pageSize = min(100, max(1, intval(Anon_Http_Request::input('page_size', 10))));
            
            $result = Anon_Cms_Services_XXX::getList($page, $pageSize);
            
            return Anon_Http_Response::success([
                'items' => $result['list'],
                'pagination' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => $result['total'],
                    'total_pages' => ceil($result['total'] / $pageSize),
                ]
            ]);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取单个
     * GET /api/restful/v1/xxx/{id}
     */
    public static function show(int $id): array
    {
        try {
            $item = Anon_Cms_Services_XXX::getById($id);
            
            if (!$item) {
                return Anon_Http_Response::error('记录不存在', 404);
            }
            
            return Anon_Http_Response::success($item);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 创建
     * POST /api/restful/v1/xxx
     */
    public static function store(): array
    {
        try {
            // 验证管理员权限
            if (!Anon_Check::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            // 验证必填字段
            if (empty($data['name'])) {
                return Anon_Http_Response::error('名称不能为空', 400);
            }
            
            $item = Anon_Cms_Services_XXX::create($data);
            
            return Anon_Http_Response::success($item, '创建成功', 201);
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 更新
     * PUT /api/restful/v1/xxx/{id}
     */
    public static function update(int $id): array
    {
        try {
            // 验证管理员权限
            if (!Anon_Check::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            $item = Anon_Cms_Services_XXX::update($id, $data);
            
            return Anon_Http_Response::success($item, '更新成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 删除
     * DELETE /api/restful/v1/xxx/{id}
     */
    public static function destroy(int $id): array
    {
        try {
            // 验证管理员权限
            if (!Anon_Check::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            Anon_Cms_Services_XXX::delete($id);
            
            return Anon_Http_Response::success(null, '删除成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
```

### 步骤 3：注册路由

在 `core/Modules/Cms/RESTful.php` 的 `init()` 方法中添加路由：

```php
public static function init()
{
    $routePrefix = self::getRoutePrefix();
    
    // 检查是否要求 Token
    $tokenRequired = Anon_Cms_Options::get('restful_api_token_required', '1');
    $tokenConfig = ($tokenRequired === '1') ? true : false;
    
    // ... 其他路由 ...
    
    // XXX 列表和创建（GET/POST）
    self::addRoute($routePrefix . '/xxx', function () use ($tokenRequired) {
        if (!Anon_Cms_Options::get('api_enabled', '0')) {
            Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
            return;
        }
        
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($requestMethod === 'GET') {
            Anon_Cms_RESTful_XXX::index();
        } elseif ($requestMethod === 'POST') {
            if (!Anon_Check::isAdmin()) {
                Anon_Http_Response::error('需要管理员权限', 403);
                return;
            }
            Anon_Cms_RESTful_XXX::store();
        } else {
            Anon_Http_Response::error('不支持的请求方法', 405);
        }
    }, [
        'method' => ['GET', 'POST'],
        'token' => $tokenConfig,
    ]);
    
    // XXX 单个资源的获取、更新和删除（GET/PUT/DELETE）
    self::addRoute($routePrefix . '/xxx/{id}', function ($params) use ($tokenRequired) {
        if (!Anon_Cms_Options::get('api_enabled', '0')) {
            Anon_Http_Response::error('RESTful API 未启用，请在后台设置中启用', 403);
            return;
        }
        
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            Anon_Http_Response::error('ID 无效', 400);
            return;
        }
        
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($requestMethod === 'GET') {
            Anon_Cms_RESTful_XXX::show($id);
        } elseif ($requestMethod === 'PUT') {
            if (!Anon_Check::isAdmin()) {
                Anon_Http_Response::error('需要管理员权限', 403);
                return;
            }
            Anon_Cms_RESTful_XXX::update($id);
        } elseif ($requestMethod === 'DELETE') {
            if (!Anon_Check::isAdmin()) {
                Anon_Http_Response::error('需要管理员权限', 403);
                return;
            }
            Anon_Cms_RESTful_XXX::destroy($id);
        } else {
            Anon_Http_Response::error('不支持的请求方法', 405);
        }
    }, [
        'method' => ['GET', 'PUT', 'DELETE'],
        'token' => $tokenConfig,
    ]);
}
```

### 步骤 4：在入口文件中引入

在 `core/Modules/Cms/Services.php` 中添加：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 引入服务层文件
require_once __DIR__ . '/Services/Post.php';
require_once __DIR__ . '/Services/XXX.php';  // 新增

class Anon_Cms_Services {
    
}
```

在 `core/Modules/Cms/RESTful.php` 中添加：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 引入 RESTful API 文件
require_once __DIR__ . '/RESTful/Post.php';
require_once __DIR__ . '/RESTful/XXX.php';  // 新增

class Anon_Cms_RESTful 
{
    // ...
}
```

## 最佳实践

### 1. 使用查询构建器

始终使用查询构建器而非手写 SQL：

```php
// ✅ 推荐
$db->db('posts')->where('status', 'publish')->get();

// ❌ 不推荐
$db->query("SELECT * FROM posts WHERE status = 'publish'");
```

### 2. 服务层抛出异常

服务层遇到错误时抛出异常，由控制器捕获并返回适当的 HTTP 响应：

```php
// 服务层
public static function create(array $data): array
{
    if (empty($data['name'])) {
        throw new Exception('名称不能为空');
    }
    // ...
}

// 控制器
try {
    $item = Anon_Cms_Services_XXX::create($data);
    return Anon_Http_Response::success($item);
} catch (Exception $e) {
    return Anon_Http_Response::error($e->getMessage(), 400);
}
```

### 3. 统一的响应格式

使用 `Anon_Http_Response` 统一响应格式：

```php
// 成功响应
Anon_Http_Response::success($data, '操作成功', 200);

// 错误响应
Anon_Http_Response::error('错误信息', 400);
```

### 4. 代码复用

管理后台和 RESTful API 都调用同一个服务层，避免代码重复：

```php
// 管理后台
$post = Anon_Cms_Services_Post::createPost($data, $userId);

// RESTful API
$post = Anon_Cms_Services_Post::createPost($data, $userId);
```

### 5. 权限验证

- GET 请求：根据配置决定是否需要 Token
- POST/PUT/DELETE：始终验证管理员权限

```php
// 在控制器中验证
if (!Anon_Check::isAdmin()) {
    return Anon_Http_Response::error('需要管理员权限', 403);
}
```

### 6. 使用钩子扩展功能

RESTful API 和服务层提供了丰富的钩子，可以通过插件或自定义代码扩展功能：

**在服务层添加钩子：**

```php
// 修改文章列表查询参数
Anon_System_Hook::add_filter('cms_post_list_params', function($params) {
    // 强制只显示特定分类
    if (isset($_GET['category_id'])) {
        $params['type'] = 'post';
    }
    return $params;
});

// 修改文章列表返回结果
Anon_System_Hook::add_filter('cms_post_list_result', function($result, $context) {
    // 添加额外字段
    foreach ($result['list'] as &$post) {
        $post['custom_field'] = 'custom value';
    }
    return $result;
}, 10, 2);

// 文章创建前修改数据
Anon_System_Hook::add_filter('cms_post_before_insert', function($data, $context) {
    // 自动添加前缀
    if ($context['action'] === 'create') {
        $data['title'] = '[Auto] ' . $data['title'];
    }
    return $data;
}, 10, 2);

// 文章创建后执行操作
Anon_System_Hook::add_action('cms_post_after_create', function($postId, $data) {
    // 发送通知
    sendNotification("新文章已创建: {$postId}");
    
    // 清除缓存
    Anon_System_Cache::delete("post_list_cache");
}, 10, 2);
```

**在 RESTful API 中添加钩子：**

```php
// 修改 API 请求参数
Anon_System_Hook::add_filter('restful_post_list_params', function($params) {
    // 限制每页最大数量
    if ($params['page_size'] > 50) {
        $params['page_size'] = 50;
    }
    return $params;
});

// 修改 API 响应数据
Anon_System_Hook::add_filter('restful_post_list_response', function($response) {
    // 添加自定义字段
    $response['api_version'] = 'v1';
    $response['timestamp'] = time();
    return $response;
});

// 文章创建前验证
Anon_System_Hook::add_filter('restful_post_before_create', function($data) {
    // 自定义验证逻辑
    if (strlen($data['title']) < 5) {
        throw new Exception('标题长度不能少于 5 个字符');
    }
    return $data;
});

// 文章删除前检查
Anon_System_Hook::add_action('restful_post_before_delete', function($id, $post) {
    // 检查是否有依赖关系
    $relatedPosts = getRelatedPosts($id);
    if (!empty($relatedPosts)) {
        throw new Exception('该文章有关联内容，无法删除');
    }
}, 10, 2);
```

**可用钩子列表：**

**服务层钩子：**
- `cms_post_list_params` - 文章列表查询参数（过滤器）
- `cms_post_list_result` - 文章列表返回结果（过滤器）
- `cms_post_before_insert` - 文章插入前数据（过滤器）
- `cms_post_after_create` - 文章创建后（动作）
- `cms_post_before_update` - 文章更新前数据（过滤器）
- `cms_post_after_update` - 文章更新后（动作）
- `cms_post_before_delete` - 文章删除前（动作）
- `cms_post_after_delete` - 文章删除后（动作）

**RESTful API 钩子：**
- `restful_post_list_params` - API 列表请求参数（过滤器）
- `restful_post_list_response` - API 列表响应数据（过滤器）
- `restful_post_before_create` - API 创建前数据（过滤器）
- `restful_post_after_create` - API 创建后（动作）
- `restful_post_before_update` - API 更新前数据（过滤器）
- `restful_post_after_update` - API 更新后（动作）
- `restful_post_before_delete` - API 删除前（动作）
- `restful_post_after_delete` - API 删除后（动作）

## 常见问题

### Q: 如何禁用某个接口的 Token 验证？

A: 在路由配置中设置 `'token' => false`，但写操作仍需在控制器中验证管理员权限。

### Q: 如何处理分页？

A: 使用 `page` 和 `page_size` 参数，在响应中返回分页信息。

### Q: 如何实现搜索功能？

A: 在服务层使用查询构建器的 `where` 和 `orWhere` 方法实现模糊搜索。

### Q: API 未启用时访问会怎样？

A: 返回 403 错误，提示 "RESTful API 未启用，请在后台设置中启用"。
