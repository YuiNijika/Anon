<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CMS 文章服务层
 */
class Anon_Cms_Services_Post
{
    /**
     * 获取单个文章
     * @param int $id 文章ID
     * @param string $contentFormat 内容格式：'html' 或 'markdown'
     * @return array|null
     */
    public static function getPostById(int $id, string $contentFormat = 'html'): ?array
    {
        $db = Anon_Database::getInstance();
        $post = $db->db('posts')->where('id', $id)->first();
        
        if (!$post) {
            return null;
        }
        
        return self::formatPost($post, $contentFormat);
    }

    /**
     * 获取文章列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string|null $type 文章类型
     * @param string|null $status 状态（管理员可指定，普通用户固定为 publish）
     * @param string|null $search 搜索关键词
     * @param bool $checkPermission 是否检查权限（RESTful API 使用 true，管理后台使用 false）
     * @param string $contentFormat 内容格式：'html' 或 'markdown'
     * @return array
     */
    public static function getPostList(int $page = 1, int $pageSize = 20, ?string $type = null, ?string $status = null, ?string $search = null, bool $checkPermission = true, string $contentFormat = 'html'): array
    {
        $params = Anon_System_Hook::apply_filters('cms_post_list_params', [
            'page' => $page,
            'page_size' => $pageSize,
            'type' => $type,
            'status' => $status,
            'search' => $search,
            'check_permission' => $checkPermission,
        ]);
        
        $page = $params['page'];
        $pageSize = $params['page_size'];
        $type = $params['type'];
        $status = $params['status'];
        $search = $params['search'];
        $checkPermission = $params['check_permission'];
        
        $db = Anon_Database::getInstance();
        $baseQuery = $db->db('posts');
        
        // 如果需要检查权限且非管理员，只能查看公开文章
        if ($checkPermission && !Anon_Cms_Admin::isAdmin()) {
            $baseQuery->where('status', 'publish');
        } elseif ($status) {
            // 管理员或不检查权限时可以指定状态
            $baseQuery->where('status', $status);
        }
        
        if ($type) {
            $baseQuery->where('type', $type);
        }
        
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $baseQuery->where(function($query) use ($searchTerm) {
                $query->where('title', 'LIKE', $searchTerm)
                      ->orWhere('slug', 'LIKE', $searchTerm)
                      ->orWhere('content', 'LIKE', $searchTerm);
            });
        }
        
        // 复制查询用于计数
        $countQuery = clone $baseQuery;
        $total = $countQuery->count();
        
        // 获取分页数据
        $posts = $baseQuery
            ->orderBy('created_at', 'DESC')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();
        
        // 格式化数据
        if (is_array($posts)) {
            foreach ($posts as &$post) {
                $post = self::formatPost($post, $contentFormat);
            }
            unset($post);
        }
        
        $result = [
            'list' => $posts ?: [],
            'total' => $total,
        ];
        
        return Anon_System_Hook::apply_filters('cms_post_list_result', $result, [
            'page' => $page,
            'page_size' => $pageSize,
            'type' => $type,
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * 创建文章
     * @param array $data 文章数据
     * @param int $userId 用户ID
     * @return array|null 创建后的文章数据
     */
    public static function createPost(array $data, int $userId): ?array
    {
        $db = Anon_Database::getInstance();
        
        // 处理标题和内容
        $title = trim($data['title']);
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
        $content = isset($data['content']) ? trim($data['content']) : '';
        
        // 添加 Markdown 标记
        if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
            $content = '<!--markdown-->' . $content;
        }
        
        if (self::checkPostSlugExists($slug, $type)) {
            throw new Exception('文章别名已存在');
        }
        
        if ($categoryId !== null && !self::checkCategoryExists($categoryId)) {
            throw new Exception('分类不存在');
        }
        
        $tagIds = self::processTags($tags);
        
        $insertData = Anon_System_Hook::apply_filters('cms_post_before_insert', [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $data['status'] ?? 'publish',
            'type' => $type,
            'author_id' => $userId,
            'category_id' => $categoryId,
            'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            'comment_status' => $data['comment_status'] ?? 'open',
        ], ['action' => 'create']);
        
        // 插入文章
        $postId = $db->db('posts')->insert($insertData);
        
        if (!$postId) {
            throw new Exception('创建文章失败');
        }
        
        Anon_System_Hook::do_action('cms_post_after_create', $postId, $insertData);
        
        return self::getPostById($postId);
    }

    /**
     * 更新文章
     * @param int $id 文章ID
     * @param array $data 更新数据
     * @return array|null 更新后的文章数据
     */
    public static function updatePost(int $id, array $data): ?array
    {
        $db = Anon_Database::getInstance();
        
        // 检查文章是否存在
        $existingPost = self::getPostById($id);
        if (!$existingPost) {
            throw new Exception('文章不存在');
        }
        
        // 处理标题和内容
        $title = isset($data['title']) ? trim($data['title']) : $existingPost['title'];
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
        $content = isset($data['content']) ? trim($data['content']) : $existingPost['content'];
        
        // 添加 Markdown 标记
        if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
            $content = '<!--markdown-->' . $content;
        }
        
        if (self::checkPostSlugExists($slug, $existingPost['type'], $id)) {
            throw new Exception('文章别名已存在');
        }
        
        if ($categoryId !== null && !self::checkCategoryExists($categoryId)) {
            throw new Exception('分类不存在');
        }
        
        $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $tagIds = self::processTags($tags);
        
        $updateData = Anon_System_Hook::apply_filters('cms_post_before_update', [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $data['status'] ?? $existingPost['status'],
            'category_id' => $categoryId,
            'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            'comment_status' => $data['comment_status'] ?? $existingPost['comment_status'],
        ], ['id' => $id, 'action' => 'update']);
        
        $result = $db->db('posts')->where('id', $id)->update($updateData);
        
        if ($result === false) {
            throw new Exception('更新文章失败');
        }
        
        Anon_System_Hook::do_action('cms_post_after_update', $id, $updateData);
        
        return self::getPostById($id);
    }

    /**
     * 删除文章
     * @param int $id 文章ID
     * @return bool
     */
    public static function deletePost(int $id): bool
    {
        $db = Anon_Database::getInstance();
        
        if (!$post) {
            throw new Exception('文章不存在');
        }
        
        Anon_System_Hook::do_action('cms_post_before_delete', $id, $post);
        
        // 删除关联数据
        $db->db('post_tags')->where('post_id', $id)->delete();
        $db->db('comments')->where('post_id', $id)->delete();
        
        // 删除文章
        $result = $db->db('posts')->where('id', $id)->delete();
        
        if ($result !== false) {
            Anon_System_Hook::do_action('cms_post_after_delete', $id, $post);
        }
        
        return $result !== false;
    }

    /**
     * 批量删除文章
     * @param array $ids 文章ID数组
     * @return array 结果数组 ['success' => int, 'failed' => int, 'errors' => array]
     */
    public static function batchDeletePosts(array $ids): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            try {
                $id = (int)$id;
                if ($id <= 0) {
                    $failed++;
                    $errors[] = ['id' => $id, 'error' => '无效的文章ID'];
                    continue;
                }
                
                self::deletePost($id);
                $success++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * 格式化文章数据
     * @param array $post 原始文章数据
     * @param string $contentFormat 内容格式：'html' 或 'markdown'
     * @return array 格式化后的文章数据
     */
    private static function formatPost(array $post, string $contentFormat = 'html'): array
    {
        // 处理内容
        if (isset($post['content']) && is_string($post['content'])) {
            $rawContent = self::stripMarkdownMarker($post['content']);
            
            if ($contentFormat === 'html') {
                $parser = new Parsedown();
                $post['content'] = $parser->text($rawContent);
            } else {
                $post['content'] = $rawContent;
            }
        }
        
        // 处理分类
        $post['category'] = isset($post['category_id']) && $post['category_id'] > 0 
            ? (int)$post['category_id'] 
            : null;
        
        // 处理标签
        $tagIds = [];
        if (!empty($post['tag_ids'])) {
            $decoded = json_decode($post['tag_ids'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tagIds = array_map('intval', $decoded);
            }
        }
        $post['tags'] = $tagIds;
        
        // 处理时间
        if (isset($post['created_at'])) {
            $post['created_at'] = is_string($post['created_at']) 
                ? strtotime($post['created_at']) 
                : $post['created_at'];
        }
        if (isset($post['updated_at'])) {
            $post['updated_at'] = is_string($post['updated_at']) 
                ? strtotime($post['updated_at']) 
                : $post['updated_at'];
        }
        
        return $post;
    }

    /**
     * 检查分类是否存在
     * @param int $categoryId 分类ID
     * @return bool
     */
    private static function checkCategoryExists(int $categoryId): bool
    {
        $db = Anon_Database::getInstance();
        $category = $db->db('metas')
            ->where('id', $categoryId)
            ->where('type', 'category')
            ->first();
        
        return $category !== null;
    }

    /**
     * 处理标签（获取或创建）
     * @param array $tags 标签数组
     * @return array 标签ID数组
     */
    private static function processTags(array $tags): array
    {
        $tagIds = [];
        
        if (empty($tags)) {
            return $tagIds;
        }
        
        foreach ($tags as $tagItem) {
            $tagId = self::getOrCreateTag($tagItem);
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }
        
        return $tagIds;
    }

    /**
     * 获取或创建标签
     * @param mixed $tagItem 标签项（可以是ID或名称）
     * @return int|null 标签ID
     */
    private static function getOrCreateTag($tagItem): ?int
    {
        $db = Anon_Database::getInstance();
        
        // 如果是数字，直接查找
        if (is_numeric($tagItem)) {
            $tagId = (int)$tagItem;
            $tag = $db->db('metas')
                ->where('id', $tagId)
                ->where('type', 'tag')
                ->first();
            
            return $tag ? $tagId : null;
        }
        
        // 字符串标签名
        $tagName = trim($tagItem);
        if (empty($tagName)) {
            return null;
        }
        
        // 查找现有标签
        $tag = $db->db('metas')
            ->where('slug', $tagName)
            ->where('type', 'tag')
            ->first();
        
        if (!$tag) {
            // 创建新标签
            $tagId = $db->db('metas')->insert([
                'name' => $tagName,
                'slug' => $tagName,
                'type' => 'tag',
            ]);
            
            return $tagId ? (int)$tagId : null;
        }
        
        return (int)$tag['id'];
    }

    /**
     * 检查文章别名是否已存在
     * @param string $slug 别名
     * @param string $type 类型
     * @param int $excludeId 排除的文章ID（更新时使用）
     * @return bool
     */
    private static function checkPostSlugExists(string $slug, string $type, int $excludeId = 0): bool
    {
        $db = Anon_Database::getInstance();
        $query = $db->db('posts')
            ->where('slug', $slug)
            ->where('type', $type);
        
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->first() !== null;
    }

    /**
     * 移除 Markdown 标记
     * @param string $content 内容
     * @return string
     */
    private static function stripMarkdownMarker(string $content): string
    {
        $content = ltrim($content);
        if (strpos($content, '<!--markdown-->') === 0) {
            $content = substr($content, strlen('<!--markdown-->'));
        }
        return ltrim($content);
    }
}
