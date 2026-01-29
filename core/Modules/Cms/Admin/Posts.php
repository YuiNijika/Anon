<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_Posts
{
    /**
     * 移除内容中的 Markdown 标记
     * @param string $content
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

    /**
     * 获取单个文章
     * @param int $id
     * @return void
     */
    public static function getOne($id)
    {
        try {
            $post = self::getPostById($id);
            
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            Anon_Http_Response::success($post, '获取文章成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取文章列表
     * @return void
     */
    public static function getList()
    {
        try {
            $data = Anon_Http_Request::getInput();
            
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $type = isset($data['type']) ? trim($data['type']) : null;
            $status = isset($data['status']) ? trim($data['status']) : null;
            $search = isset($data['search']) ? trim($data['search']) : null;
            
            $result = self::getPostList($page, $pageSize, $type, $status, $search);
            
            Anon_Http_Response::success([
                'list' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取文章列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 创建文章
     * @return void
     */
    public static function create()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $userId = Anon_Http_Request::getUserId();
            
            if (empty($data['title'])) {
                Anon_Http_Response::error('文章标题不能为空', 400);
                return;
            }
            
            if (empty($data['type']) || !in_array($data['type'], ['post', 'page'])) {
                Anon_Http_Response::error('文章类型无效', 400);
                return;
            }
            
            $title = trim($data['title']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
            $content = isset($data['content']) ? trim($data['content']) : '';
            
            if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
                $content = '<!--markdown-->' . $content;
            }
            
            $status = isset($data['status']) ? trim($data['status']) : 'publish';
            $type = $data['type'];
            $categoryId = isset($data['category']) && $data['category'] > 0 ? (int)$data['category'] : null;
            $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
            
            if ($categoryId !== null && !self::checkCategoryExists($categoryId)) {
                Anon_Http_Response::error('分类不存在', 400);
                return;
            }
            
            $tagIds = [];
            if (!empty($tags)) {
                foreach ($tags as $tagItem) {
                    $tagId = self::getOrCreateTag($tagItem);
                    if ($tagId) {
                        $tagIds[] = $tagId;
                    }
                }
            }
            
            if (self::checkPostSlugExists($slug, $type)) {
                Anon_Http_Response::error('文章别名已存在', 400);
                return;
            }
            
            $id = self::createPost([
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'status' => $status,
                'type' => $type,
                'author_id' => $userId,
                'category_id' => $categoryId,
                'tag_ids' => $tagIds,
            ]);
            
            if (!$id) {
                Anon_Http_Response::error('创建文章失败', 500);
                return;
            }
            
            $post = self::getPostById($id);
            Anon_Http_Response::success($post, '创建文章成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 更新文章
     * @return void
     */
    public static function update()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('文章 ID 无效', 400);
                return;
            }
            
            if (empty($data['title'])) {
                Anon_Http_Response::error('文章标题不能为空', 400);
                return;
            }
            
            $post = self::getPostById($id);
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            $title = trim($data['title']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
            $content = isset($data['content']) ? trim($data['content']) : '';
            
            if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
                $content = '<!--markdown-->' . $content;
            }
            
            $status = isset($data['status']) ? trim($data['status']) : 'publish';
            $categoryId = isset($data['category']) && $data['category'] > 0 ? (int)$data['category'] : null;
            $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
            
            if ($categoryId !== null && !self::checkCategoryExists($categoryId)) {
                Anon_Http_Response::error('分类不存在', 400);
                return;
            }
            
            $tagIds = [];
            if (!empty($tags)) {
                foreach ($tags as $tagItem) {
                    $tagId = self::getOrCreateTag($tagItem);
                    if ($tagId) {
                        $tagIds[] = $tagId;
                    }
                }
            }
            
            if (self::checkPostSlugExists($slug, $post['type'], $id)) {
                Anon_Http_Response::error('文章别名已存在', 400);
                return;
            }
            
            $updateData = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'status' => $status,
                'category_id' => $categoryId,
                'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            ];
            
            $result = self::updatePost($id, $updateData);
            
            if ($result) {
                $updated = self::getPostById($id);
                Anon_Http_Response::success($updated, '更新文章成功');
            } else {
                Anon_Http_Response::error('更新文章失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除文章
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('文章 ID 无效', 400);
                return;
            }
            
            $post = self::getPostById($id);
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            $result = self::deletePost($id);
            
            if ($result) {
                Anon_Http_Response::success(null, '删除文章成功');
            } else {
                Anon_Http_Response::error('删除文章失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取单个文章
     * @param int $id 文章ID
     * @return array|null
     */
    private static function getPostById($id)
    {
        $db = Anon_Database::getInstance();
        $post = $db->db('posts')->where('id', $id)->first();
        if (!$post) {
            return null;
        }

        if (isset($post['content']) && is_string($post['content'])) {
            $post['content'] = self::stripMarkdownMarker($post['content']);
        }
        
        $post['category'] = isset($post['category_id']) && $post['category_id'] > 0 ? (int)$post['category_id'] : null;
        
        $tagIds = [];
        if (!empty($post['tag_ids'])) {
            $decoded = json_decode($post['tag_ids'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tagIds = array_map('intval', $decoded);
            }
        }
        $post['tags'] = $tagIds;
        
        if (isset($post['created_at'])) {
            $post['created_at'] = is_string($post['created_at']) ? strtotime($post['created_at']) : $post['created_at'];
        }
        if (isset($post['updated_at'])) {
            $post['updated_at'] = is_string($post['updated_at']) ? strtotime($post['updated_at']) : $post['updated_at'];
        }
        
        return $post;
    }

    /**
     * 获取文章列表
     * @param int $page
     * @param int $pageSize
     * @param string|null $type
     * @param string|null $status
     * @param string|null $search
     * @return array
     */
    private static function getPostList($page = 1, $pageSize = 20, $type = null, $status = null, $search = null)
    {
        $db = Anon_Database::getInstance();
        $baseQuery = $db->db('posts');
        
        if ($type) {
            $baseQuery->where('type', $type);
        }
        
        if ($status) {
            $baseQuery->where('status', $status);
        }
        
        if ($search) {
            $baseQuery->where(function($query) use ($search) {
                $query->where('title', 'LIKE', '%' . $search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $search . '%');
            });
        }
        
        $countQuery = $db->db('posts');
        if ($type) {
            $countQuery->where('type', $type);
        }
        if ($status) {
            $countQuery->where('status', $status);
        }
        if ($search) {
            $countQuery->where(function($query) use ($search) {
                $query->where('title', 'LIKE', '%' . $search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $search . '%');
            });
        }
        $total = $countQuery->count();
        
        $posts = $baseQuery
            ->orderBy('created_at', 'DESC')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();
        
        if (is_array($posts)) {
            foreach ($posts as &$post) {
                if (isset($post['content']) && is_string($post['content'])) {
                    $post['content'] = self::stripMarkdownMarker($post['content']);
                }
                if (isset($post['created_at'])) {
                    $post['created_at'] = is_string($post['created_at']) ? strtotime($post['created_at']) : $post['created_at'];
                }
                if (isset($post['updated_at'])) {
                    $post['updated_at'] = is_string($post['updated_at']) ? strtotime($post['updated_at']) : $post['updated_at'];
                }
            }
            unset($post);
        }
        
        return [
            'list' => $posts ?: [],
            'total' => $total,
        ];
    }

    /**
     * 检查分类是否存在
     * @param int $categoryId
     * @return array|null
     */
    private static function checkCategoryExists($categoryId)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('id', $categoryId)
            ->where('type', 'category')
            ->first();
    }

    /**
     * 获取或创建标签
     * @param mixed $tagItem
     * @return int|null
     */
    private static function getOrCreateTag($tagItem)
    {
        $db = Anon_Database::getInstance();
        
        if (is_numeric($tagItem)) {
            $tagId = (int)$tagItem;
            $tag = $db->db('metas')
                ->where('id', $tagId)
                ->where('type', 'tag')
                ->first();
            
            if ($tag) {
                return $tagId;
            }
            return null;
        }
        
        $tagName = trim($tagItem);
        if (empty($tagName)) {
            return null;
        }
        
        $tag = $db->db('metas')
            ->where('slug', $tagName)
            ->where('type', 'tag')
            ->first();
        
        if (!$tag) {
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
     * @param string $slug
     * @param string $type
     * @param int $excludeId
     * @return array|null
     */
    private static function checkPostSlugExists($slug, $type, $excludeId = 0)
    {
        $db = Anon_Database::getInstance();
        $query = $db->db('posts')
            ->where('slug', $slug)
            ->where('type', $type);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    /**
     * 创建文章
     * @param array $data
     * @return int|false
     */
    private static function createPost($data)
    {
        $db = Anon_Database::getInstance();
        return $db->db('posts')->insert([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'],
            'status' => $data['status'],
            'type' => $data['type'],
            'author_id' => $data['author_id'],
            'category_id' => $data['category_id'] ?? null,
            'tag_ids' => !empty($data['tag_ids']) ? json_encode($data['tag_ids'], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * 更新文章
     * @param int $id
     * @param array $data
     * @return bool
     */
    private static function updatePost($id, $data)
    {
        $db = Anon_Database::getInstance();
        return $db->db('posts')
            ->where('id', $id)
            ->update($data) !== false;
    }

    /**
     * 删除文章
     * @param int $id
     * @return bool
     */
    private static function deletePost($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('posts')->where('id', $id)->delete();
    }
}

