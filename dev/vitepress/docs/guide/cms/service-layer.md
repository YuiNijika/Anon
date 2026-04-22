# Service 服务层开发指南

Service 层是 Anon Framework 的核心架构组件，负责封装业务逻辑，实现代码复用和职责分离。

## 为什么需要 Service 层？

### 传统方式的问题

```php
<?php
// ❌ 管理后台和 API 中重复相同的代码
class Admin\Posts {
    public static function create() {
        // 100 行业务逻辑代码
    }
}

class API_Posts {
    public static function create() {
        // 复制粘贴的 100 行业务逻辑代码
    }
}
```

### Service 层的优势

```php
<?php
use Anon\Modules\Cms\Services;

// ✅ 业务逻辑集中在服务层
class Services\Post {
    public static function createPost(array $data, int $userId): array {
        // 100 行业务逻辑代码
    }
}

// 管理后台调用
$post = Services\Post::createPost($data, $userId);

// RESTful API 调用
$post = Services\Post::createPost($data, $userId);
```

**优势：**
- ✅ 代码复用：避免重复
- ✅ 单一数据源：修改一处即可
- ✅ 易于测试：独立测试业务逻辑
- ✅ 职责清晰：控制器只负责请求处理

## 架构设计

### 目录结构

```
core/Modules/Cms/
├── Services.php             # 服务层入口（引入所有服务）
└── Services/
    ├── Post.php             # 文章服务
    ├── Category.php         # 分类服务（待实现）
    ├── Tag.php              # 标签服务（待实现）
    └── Comment.php          # 评论服务（待实现）
```

### 命名规范

- **类名**: `Services\XXX`
- **文件名**: `XXX.php`（单数形式）
- **方法名**: 使用动词开头，如 `getPostById`, `createPost`, `updatePost`

## 开发规范

### 1. 基本结构

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * XXX 服务层
 * 提供 XXX 相关的核心业务逻辑
 */
class Services\XXX
{
    /**
     * 获取列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string|null $filter 筛选条件
     * @return array ['list' => [], 'total' => 0]
     */
    public static function getList(int $page = 1, int $pageSize = 20, ?string $filter = null): array
    {
        // 实现逻辑
    }
    
    /**
     * 获取单个
     * @param int $id ID
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        // 实现逻辑
    }
    
    /**
     * 创建
     * @param array $data 数据
     * @param int $userId 用户ID
     * @return array 创建后的数据
     * @throws Exception 验证失败时抛出异常
     */
    public static function create(array $data, int $userId): array
    {
        // 实现逻辑
    }
    
    /**
     * 更新
     * @param int $id ID
     * @param array $data 更新数据
     * @return array 更新后的数据
     * @throws Exception 验证失败时抛出异常
     */
    public static function update(int $id, array $data): array
    {
        // 实现逻辑
    }
    
    /**
     * 删除
     * @param int $id ID
     * @return bool
     * @throws Exception 验证失败时抛出异常
     */
    public static function delete(int $id): bool
    {
        // 实现逻辑
    }
    
    // ========== 私有辅助方法 ==========
    
    /**
     * 格式化数据
     */
    private static function formatData(array $data): array
    {
        // 实现逻辑
    }
    
    /**
     * 验证数据
     */
    private static function validateData(array $data): void
    {
        // 实现逻辑
    }
}
```

### 2. 使用查询构建器

始终使用查询构建器，不要手写 SQL：

```php
<?php
use Anon\Modules\Database\Database;

// ✅ 推荐
public static function getList(int $page, int $pageSize): array
{
    $db = Database::getInstance();
    
    // 基础查询
    $query = $db->db('posts')->where('status', 'publish');
    
    // 计数（克隆查询避免影响原查询）
    $total = (clone $query)->count();
    
    // 分页查询
    $items = $query->orderBy('created_at', 'DESC')
                   ->offset(($page - 1) * $pageSize)
                   ->limit($pageSize)
                   ->get();
    
    return [
        'list' => $items ?: [],
        'total' => $total,
    ];
}

// ❌ 不推荐
public static function getList(int $page, int $pageSize): array
{
    $db = Database::getInstance();
    $sql = "SELECT * FROM posts WHERE status = 'publish' ORDER BY created_at DESC LIMIT ?, ?";
    // ...
}
```

### 3. 异常处理

服务层遇到错误时抛出异常，让控制器决定如何响应：

```php
public static function create(array $data, int $userId): array
{
    // 验证必填字段
    if (empty($data['title'])) {
        throw new Exception('标题不能为空');
    }
    
    // 验证分类是否存在
    if (isset($data['category_id']) && !self::checkCategoryExists($data['category_id'])) {
        throw new Exception('分类不存在');
    }
    
    // 检查 slug 唯一性
    if (self::checkSlugExists($data['slug'])) {
        throw new Exception('别名已存在');
    }
    
    // 执行创建...
}
```

控制器捕获异常并返回适当的 HTTP 响应：

```php
<?php
use Anon\Modules\Cms\Services;
use Anon\Modules\Http\ResponseHelper;

try {
    $post = Services\Post::createPost($data, $userId);
    return ResponseHelper::success($post, '创建成功', 201);
} catch (Exception $e) {
    return ResponseHelper::error($e->getMessage(), 400);
}
```

### 4. 数据格式化

在服务层统一处理数据格式化：

```php
<?php
use Anon\Modules\Database\Database;

public static function getById(int $id): ?array
{
    $db = Database::getInstance();
    $post = $db->db('posts')->where('id', $id)->first();
    
    if (!$post) {
        return null;
    }
    
    // 格式化数据
    return self::formatPost($post);
}

private static function formatPost(array $post): array
{
    // 处理 Markdown 标记
    if (isset($post['content'])) {
        $post['content'] = self::stripMarkdownMarker($post['content']);
    }
    
    // 处理分类
    $post['category'] = isset($post['category_id']) && $post['category_id'] > 0 
        ? (int)$post['category_id'] 
        : null;
    
    // 处理标签（JSON 解码）
    $post['tags'] = [];
    if (!empty($post['tag_ids'])) {
        $decoded = json_decode($post['tag_ids'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $post['tags'] = array_map('intval', $decoded);
        }
    }
    
    // 处理时间戳
    if (isset($post['created_at'])) {
        $post['created_at'] = is_string($post['created_at']) 
            ? strtotime($post['created_at']) 
            : $post['created_at'];
    }
    
    return $post;
}
```

### 5. 复杂业务逻辑封装

将复杂的业务逻辑拆分为多个私有方法：

```php
public static function createPost(array $data, int $userId): array
{
    // 1. 数据预处理
    $processedData = self::processData($data);
    
    // 2. 数据验证
    self::validateData($processedData);
    
    // 3. 处理关联数据（标签、分类等）
    $relatedData = self::processRelatedData($processedData);
    
    // 4. 插入主表
    $postId = self::insertPost($processedData, $userId);
    
    // 5. 处理关联关系
    self::saveRelations($postId, $relatedData);
    
    // 6. 返回格式化后的数据
    return self::getById($postId);
}

private static function processData(array $data): array
{
    // 数据清洗和预处理
    return [
        'title' => trim($data['title']),
        'slug' => isset($data['slug']) ? trim($data['slug']) : null,
        'content' => isset($data['content']) ? trim($data['content']) : '',
        // ...
    ];
}

private static function validateData(array $data): void
{
    // 验证逻辑
    if (empty($data['title'])) {
        throw new Exception('标题不能为空');
    }
}

private static function processRelatedData(array $data): array
{
    // 处理标签、分类等
    return [
        'category_id' => self::validateCategory($data['category_id'] ?? null),
        'tag_ids' => self::processTags($data['tags'] ?? []),
    ];
}

private static function insertPost(array $data, int $userId): int
{
    $db = Database::getInstance();
    return $db->db('posts')->insert([
        'title' => $data['title'],
        'author_id' => $userId,
        // ...
    ]);
}

private static function saveRelations(int $postId, array $relations): void
{
    // 保存关联关系
}
```

## 完整示例：文章服务层

参考 `core/Modules/Cms/Services/Post.php` 的完整实现：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 文章服务层
 */
class Services\Post
{
    /**
     * 获取文章列表
     */
    public static function getPostList(int $page = 1, int $pageSize = 20, ?string $type = null, ?string $status = null, ?string $search = null): array
    {
        $db = Database::getInstance();
        $baseQuery = $db->db('posts');
        
        // 筛选条件
        if ($type) {
            $baseQuery->where('type', $type);
        }
        
        if ($status) {
            $baseQuery->where('status', $status);
        }
        
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $baseQuery->where(function($query) use ($searchTerm) {
                $query->where('title', 'LIKE', $searchTerm)
                      ->orWhere('slug', 'LIKE', $searchTerm)
                      ->orWhere('content', 'LIKE', $searchTerm);
            });
        }
        
        // 计数
        $countQuery = clone $baseQuery;
        $total = $countQuery->count();
        
        // 分页查询
        $posts = $baseQuery->orderBy('created_at', 'DESC')
                          ->offset(($page - 1) * $pageSize)
                          ->limit($pageSize)
                          ->get();
        
        // 格式化数据
        if (is_array($posts)) {
            foreach ($posts as &$post) {
                $post = self::formatPost($post);
            }
            unset($post);
        }
        
        return [
            'list' => $posts ?: [],
            'total' => $total,
        ];
    }
    
    /**
     * 获取单个文章
     */
    public static function getPostById(int $id): ?array
    {
        $db = Database::getInstance();
        $post = $db->db('posts')->where('id', $id)->first();
        
        if (!$post) {
            return null;
        }
        
        return self::formatPost($post);
    }
    
    /**
     * 创建文章
     */
    public static function createPost(array $data, int $userId): array
    {
        $db = Database::getInstance();
        
        // 数据处理
        $title = trim($data['title']);
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
        $content = isset($data['content']) ? trim($data['content']) : '';
        
        // 添加 Markdown 标记
        if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
            $content = '<!--markdown-->' . $content;
        }
        
        // 验证 slug 唯一性
        $type = $data['type'] ?? 'post';
        if (self::checkPostSlugExists($slug, $type)) {
            throw new Exception('文章别名已存在');
        }
        
        // 验证分类
        $categoryId = isset($data['category']) && $data['category'] > 0 ? (int)$data['category'] : null;
        if ($categoryId !== null && !self::checkCategoryExists($categoryId)) {
            throw new Exception('分类不存在');
        }
        
        // 处理标签
        $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $tagIds = self::processTags($tags);
        
        // 插入文章
        $postId = $db->db('posts')->insert([
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $data['status'] ?? 'publish',
            'type' => $type,
            'author_id' => $userId,
            'category_id' => $categoryId,
            'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            'comment_status' => $data['comment_status'] ?? 'open',
        ]);
        
        if (!$postId) {
            throw new Exception('创建文章失败');
        }
        
        return self::getPostById($postId);
    }
    
    /**
     * 更新文章
     */
    public static function updatePost(int $id, array $data): array
    {
        $db = Database::getInstance();
        
        // 检查文章是否存在
        $existingPost = self::getPostById($id);
        if (!$existingPost) {
            throw new Exception('文章不存在');
        }
        
        // 数据处理和验证（类似 createPost）
        // ...
        
        // 更新文章
        $updateData = [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $data['status'] ?? $existingPost['status'],
            'category_id' => $categoryId,
            'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            'comment_status' => $data['comment_status'] ?? $existingPost['comment_status'],
        ];
        
        $result = $db->db('posts')->where('id', $id)->update($updateData);
        
        if ($result === false) {
            throw new Exception('更新文章失败');
        }
        
        return self::getPostById($id);
    }
    
    /**
     * 删除文章
     */
    public static function deletePost(int $id): bool
    {
        $db = Database::getInstance();
        
        // 检查文章是否存在
        $post = self::getPostById($id);
        if (!$post) {
            throw new Exception('文章不存在');
        }
        
        // 删除关联数据
        $db->db('post_tags')->where('post_id', $id)->delete();
        $db->db('comments')->where('post_id', $id)->delete();
        
        // 删除文章
        $result = $db->db('posts')->where('id', $id)->delete();
        
        return $result !== false;
    }
    
    // ========== 私有辅助方法 ==========
    
    private static function formatPost(array $post): array
    {
        // 格式化逻辑
    }
    
    private static function checkCategoryExists(int $categoryId): bool
    {
        $db = Database::getInstance();
        $category = $db->db('metas')
            ->where('id', $categoryId)
            ->where('type', 'category')
            ->first();
        
        return $category !== null;
    }
    
    private static function processTags(array $tags): array
    {
        $tagIds = [];
        foreach ($tags as $tagItem) {
            $tagId = self::getOrCreateTag($tagItem);
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }
        return $tagIds;
    }
    
    private static function getOrCreateTag($tagItem): ?int
    {
        // 获取或创建标签的逻辑
    }
    
    private static function checkPostSlugExists(string $slug, string $type, int $excludeId = 0): bool
    {
        $db = Database::getInstance();
        $query = $db->db('posts')
            ->where('slug', $slug)
            ->where('type', $type);
        
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->first() !== null;
    }
    
    private static function stripMarkdownMarker(string $content): string
    {
        $content = ltrim($content);
        if (strpos($content, '<!--markdown-->') === 0) {
            $content = substr($content, strlen('<!--markdown-->'));
        }
        return ltrim($content);
    }
}
```

## 在控制器中使用服务层

### 管理后台控制器

```php
<?php
use Anon\Modules\Cms\Services;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

class Admin\Posts
{
    public static function create()
    {
        try {
            $data = RequestHelper::getInput();
            $userId = RequestHelper::getUserId();
            
            // 参数验证
            if (empty($data['title'])) {
                ResponseHelper::error('文章标题不能为空', 400);
                return;
            }
            
            // 调用服务层
            $post = Services\Post::createPost($data, $userId);
            
            ResponseHelper::success($post, '创建文章成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
```

### RESTful API 控制器

```php
<?php
use Anon\Modules\Cms\Services;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

class RESTful\Post
{
    public static function store(): array
    {
        try {
            // 权限验证
            if (!RequestHelper::isAdmin()) {
                return ResponseHelper::error('需要管理员权限', 403);
            }
            
            $data = RequestHelper::all();
            $userId = RequestHelper::getUserId();
            
            // 参数验证
            if (empty($data['title'])) {
                return ResponseHelper::error('标题不能为空', 400);
            }
            
            // 调用服务层（与管理后台相同）
            $post = Services\Post::createPost($data, $userId);
            
            return ResponseHelper::success($post, '创建成功', 201);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
```

## 最佳实践总结

### ✅ 应该做的

1. **使用查询构建器**：避免手写 SQL
2. **抛出异常**：让控制器决定如何响应
3. **数据格式化**：在服务层统一处理
4. **拆分方法**：复杂逻辑拆分为多个私有方法
5. **类型提示**：使用严格的类型声明
6. **文档注释**：为每个公共方法添加完整的 PHPDoc
7. **使用钩子**：通过钩子扩展功能，而不是修改核心代码

### ❌ 不应该做的

1. **不要在服务层直接输出响应**：应该返回数据或抛出异常
2. **不要在手写 SQL**：使用查询构建器
3. **不要复制代码**：提取公共逻辑到服务层
4. **不要在服务层验证权限**：权限验证应该在控制器或路由层
5. **不要忽略错误**：遇到错误应该抛出异常

## 钩子系统

服务层提供了丰富的钩子，允许开发者在不修改核心代码的情况下扩展功能。

### 过滤器钩子（Filter）

过滤器用于修改数据，必须返回修改后的值：

```php
<?php
use Anon\Modules\System\Hook;

// 修改文章列表查询参数
Hook::add_filter('cms_post_list_params', function($params) {
    // 强制只显示公开文章
    $params['status'] = 'publish';
    return $params;
});

// 修改返回结果
Hook::add_filter('cms_post_list_result', function($result, $context) {
    // 添加自定义字段
    foreach ($result['list'] as &$post) {
        $post['read_time'] = calculateReadTime($post['content']);
    }
    return $result;
}, 10, 2); // 10 是优先级，2 是参数数量
```

### 动作钩子（Action）

动作用于执行操作，不需要返回值：

```php
<?php
use Anon\Modules\System\Hook;
use Anon\Modules\System\Cache;
use Anon\Modules\System\Debug;

// 文章创建后发送通知
Hook::add_action('cms_post_after_create', function($postId, $data) {
    // 发送邮件通知
    sendEmailNotification("新文章已创建: {$postId}");
    
    // 清除缓存
    Cache::delete('post_list_cache');
    
    // 记录日志
    Debug::info("文章创建: {$postId}", $data);
}, 10, 2);

// 文章删除前检查
Hook::add_action('cms_post_before_delete', function($id, $post) {
    // 检查是否有评论
    $commentCount = getCommentCount($id);
    if ($commentCount > 0) {
        throw new Exception("该文章有 {$commentCount} 条评论，无法删除");
    }
}, 10, 2);
```

### 可用钩子列表

**文章列表：**
- `cms_post_list_params` - 查询参数（过滤器）
- `cms_post_list_result` - 返回结果（过滤器）

**文章创建：**
- `cms_post_before_insert` - 插入前数据（过滤器）
- `cms_post_after_create` - 创建后（动作）

**文章更新：**
- `cms_post_before_update` - 更新前数据（过滤器）
- `cms_post_after_update` - 更新后（动作）

**文章删除：**
- `cms_post_before_delete` - 删除前（动作）
- `cms_post_after_delete` - 删除后（动作）

### 钩子最佳实践

1. **使用命名空间避免冲突**：
```php
<?php
namespace MyPlugin;

use Anon\Modules\System\Hook;

Hook::add_filter('cms_post_list_params', [Hooks::class, 'modifyParams']);
```

2. **合理设置优先级**：
   - 默认优先级为 10
   - 需要优先执行的设置为 1-9
   - 需要延后执行的设置为 11-20

3. **限制参数数量**：只接受需要的参数数量
```php
<?php
use Anon\Modules\System\Hook;

// 只需要 1 个参数
Hook::add_action('cms_post_after_create', function($postId) {
    // ...
}, 10, 1);
```

4. **避免在钩子中执行耗时操作**：
```php
// ❌ 不推荐：耗时操作
Hook::add_action('cms_post_after_create', function($postId) {
    $data = file_get_contents('large-file.json'); // 慢
});

// ✅ 推荐：异步处理
Hook::add_action('cms_post_after_create', function($postId) {
    // 添加到队列异步处理
    addToQueue('process_post', $postId);
});
```

## 常见问题

### Q: 服务层应该包含权限验证吗？

A: 不应该。权限验证应该在控制器或路由层进行，服务层只负责业务逻辑。

### Q: 如何处理事务？

A: 在需要事务的操作中，使用数据库事务：

```php
public static function createPostWithTags(array $data, int $userId): array
{
    $db = Database::getInstance();
    $db->beginTransaction();
    
    try {
        // 创建文章
        $postId = $db->db('posts')->insert([...]);
        
        // 创建标签关系
        foreach ($data['tags'] as $tagId) {
            $db->db('post_tags')->insert([
                'post_id' => $postId,
                'tag_id' => $tagId,
            ]);
        }
        
        $db->commit();
        return self::getPostById($postId);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

### Q: 如何缓存服务层的结果？

A: 可以使用 Anon 的缓存系统：

```php
public static function getPostById(int $id): ?array
{
    $cacheKey = "post_{$id}";
    $cached = Cache::get($cacheKey);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $post = self::fetchPostFromDatabase($id);
    
    if ($post) {
        Cache::set($cacheKey, $post, 3600); // 缓存 1 小时
    }
    
    return $post;
}
```

### Q: 服务层可以调用其他服务层吗？

A: 可以，但要注意避免循环依赖：

```php
public static function createPost(array $data, int $userId): array
{
    // 调用分类服务验证分类
    $category = Services\Category::getById($data['category_id']);
    if (!$category) {
        throw new Exception('分类不存在');
    }
    
    // 继续创建文章...
}
```

## 总结

Service 层是 Anon Framework 实现代码复用和职责分离的关键组件。通过遵循上述规范和最佳实践，您可以：

- 减少代码重复
- 提高代码可维护性
- 简化测试流程
- 实现清晰的架构分层

记住核心原则：**服务层只负责业务逻辑，不涉及 HTTP 请求处理和权限验证**。
