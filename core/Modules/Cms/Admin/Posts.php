<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 文章管理类
 * 处理文章的增删改查功能
 */
class Anon_Cms_Admin_Posts
{
    /**
     * 获取单个文章
     * @param int $id 文章 ID
     * @return void
     */
    public static function getOne($id)
    {
        try {
            $db = Anon_Database::getInstance();
            $post = $db->db('posts')
                ->where('id', $id)
                ->first();
            
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            /**
             * 处理分类ID
             */
            $post['category'] = isset($post['category_id']) && $post['category_id'] > 0 ? (int)$post['category_id'] : null;
            
            /**
             * 处理标签ID数组
             */
            $tagIds = [];
            if (!empty($post['tag_ids'])) {
                $decoded = json_decode($post['tag_ids'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $tagIds = array_map('intval', $decoded);
                }
            }
            $post['tags'] = $tagIds;
            
            /**
             * 转换时间戳为Unix时间戳
             */
            if (isset($post['created_at'])) {
                $post['created_at'] = is_string($post['created_at']) ? strtotime($post['created_at']) : $post['created_at'];
            }
            if (isset($post['updated_at'])) {
                $post['updated_at'] = is_string($post['updated_at']) ? strtotime($post['updated_at']) : $post['updated_at'];
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
            $db = Anon_Database::getInstance();
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $type = isset($data['type']) ? trim($data['type']) : null;
            $status = isset($data['status']) ? trim($data['status']) : null;
            $search = isset($data['search']) ? trim($data['search']) : null;
            
            /**
             * 构建查询条件
             */
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
            
            /**
             * 获取总数
             */
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
            
            /**
             * 获取列表数据
             */
            $posts = $baseQuery
                ->orderBy('created_at', 'DESC')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();
            
            /**
             * 处理时间戳格式
             */
            if (is_array($posts)) {
                foreach ($posts as &$post) {
                    if (isset($post['created_at'])) {
                        $post['created_at'] = is_string($post['created_at']) ? strtotime($post['created_at']) : $post['created_at'];
                    }
                    if (isset($post['updated_at'])) {
                        $post['updated_at'] = is_string($post['updated_at']) ? strtotime($post['updated_at']) : $post['updated_at'];
                    }
                }
                unset($post);
            }
            
            Anon_Http_Response::success([
                'list' => $posts ?: [],
                'total' => $total,
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
            $db = Anon_Database::getInstance();
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
            
            /**
             * 如果内容不为空且没有 <!--markdown--> 前缀，则自动添加
             */
            if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
                $content = '<!--markdown-->' . $content;
            }
            
            $status = isset($data['status']) ? trim($data['status']) : 'publish';
            $type = $data['type'];
            $categoryId = isset($data['category']) && $data['category'] > 0 ? (int)$data['category'] : null;
            $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
            
            /**
             * 验证分类ID是否存在
             */
            if ($categoryId !== null) {
                $category = $db->db('metas')
                    ->where('id', $categoryId)
                    ->where('type', 'category')
                    ->first();
                
                if (!$category) {
                    Anon_Http_Response::error('分类不存在', 400);
                    return;
                }
            }
            
            /**
             * 处理标签：将标签名称转换为标签ID
             */
            $tagIds = [];
            if (!empty($tags)) {
                foreach ($tags as $tagItem) {
                    /**
                     * 如果已经是数字，直接使用
                     */
                    if (is_numeric($tagItem)) {
                        $tagId = (int)$tagItem;
                        $tag = $db->db('metas')
                            ->where('id', $tagId)
                            ->where('type', 'tag')
                            ->first();
                        
                        if ($tag) {
                            $tagIds[] = $tagId;
                        }
                        continue;
                    }
                    
                    /**
                     * 如果是字符串，查找或创建标签
                     */
                    $tagName = trim($tagItem);
                    if (empty($tagName)) {
                        continue;
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
                        
                        if ($tagId) {
                            $tagIds[] = $tagId;
                        }
                    } else {
                        $tagIds[] = (int)$tag['id'];
                    }
                }
            }
            
            /**
             * 检查别名是否已存在
             */
            $existing = $db->db('posts')
                ->where('slug', $slug)
                ->where('type', $type)
                ->first();
            
            if ($existing) {
                Anon_Http_Response::error('文章别名已存在', 400);
                return;
            }
            
            /**
             * 创建文章
             */
            $insertData = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'status' => $status,
                'type' => $type,
                'author_id' => $userId,
                'category_id' => $categoryId,
                'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            ];
            
            $result = $db->db('posts')->insert($insertData);
            
            if (!$result) {
                Anon_Http_Response::error('创建文章失败', 500);
                return;
            }
            
            $post = $db->db('posts')->where('id', $result)->first();
            
            /**
             * 转换时间戳为Unix时间戳
             */
            if (isset($post['created_at'])) {
                $post['created_at'] = is_string($post['created_at']) ? strtotime($post['created_at']) : $post['created_at'];
            }
            if (isset($post['updated_at'])) {
                $post['updated_at'] = is_string($post['updated_at']) ? strtotime($post['updated_at']) : $post['updated_at'];
            }
            
            /**
             * 处理返回数据格式
             */
            $post['category'] = $categoryId;
            $post['tags'] = $tagIds;
            
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
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('文章 ID 无效', 400);
                return;
            }
            
            if (empty($data['title'])) {
                Anon_Http_Response::error('文章标题不能为空', 400);
                return;
            }
            
            $post = $db->db('posts')->where('id', $id)->first();
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            $title = trim($data['title']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $title;
            $content = isset($data['content']) ? trim($data['content']) : '';
            
            /**
             * 如果内容不为空且没有 <!--markdown--> 前缀，则自动添加
             */
            if (!empty($content) && strpos($content, '<!--markdown-->') !== 0) {
                $content = '<!--markdown-->' . $content;
            }
            
            $status = isset($data['status']) ? trim($data['status']) : 'publish';
            $categoryId = isset($data['category']) && $data['category'] > 0 ? (int)$data['category'] : null;
            $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
            
            /**
             * 验证分类ID是否存在
             */
            if ($categoryId !== null) {
                $category = $db->db('metas')
                    ->where('id', $categoryId)
                    ->where('type', 'category')
                    ->first();
                
                if (!$category) {
                    Anon_Http_Response::error('分类不存在', 400);
                    return;
                }
            }
            
            /**
             * 处理标签：将标签名称转换为标签ID
             */
            $tagIds = [];
            if (!empty($tags)) {
                foreach ($tags as $tagItem) {
                    /**
                     * 如果已经是数字，直接使用
                     */
                    if (is_numeric($tagItem)) {
                        $tagId = (int)$tagItem;
                        $tag = $db->db('metas')
                            ->where('id', $tagId)
                            ->where('type', 'tag')
                            ->first();
                        
                        if ($tag) {
                            $tagIds[] = $tagId;
                        }
                        continue;
                    }
                    
                    /**
                     * 如果是字符串，查找或创建标签
                     */
                    $tagName = trim($tagItem);
                    if (empty($tagName)) {
                        continue;
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
                        
                        if ($tagId) {
                            $tagIds[] = $tagId;
                        }
                    } else {
                        $tagIds[] = (int)$tag['id'];
                    }
                }
            }
            
            /**
             * 检查别名是否已被其他文章使用
             */
            $existing = $db->db('posts')
                ->where('slug', $slug)
                ->where('type', $post['type'])
                ->where('id', '!=', $id)
                ->first();
            
            if ($existing) {
                Anon_Http_Response::error('文章别名已存在', 400);
                return;
            }
            
            /**
             * 更新文章
             */
            $updateData = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'status' => $status,
                'category_id' => $categoryId,
                'tag_ids' => !empty($tagIds) ? json_encode($tagIds, JSON_UNESCAPED_UNICODE) : null,
            ];
            
            $result = $db->db('posts')
                ->where('id', $id)
                ->update($updateData);
            
            if ($result === false) {
                Anon_Http_Response::error('更新文章失败', 500);
                return;
            }
            
            $updated = $db->db('posts')->where('id', $id)->first();
            
            /**
             * 转换时间戳为Unix时间戳
             */
            if (isset($updated['created_at'])) {
                $updated['created_at'] = is_string($updated['created_at']) ? strtotime($updated['created_at']) : $updated['created_at'];
            }
            if (isset($updated['updated_at'])) {
                $updated['updated_at'] = is_string($updated['updated_at']) ? strtotime($updated['updated_at']) : $updated['updated_at'];
            }
            
            /**
             * 处理返回数据格式
             */
            $updated['category'] = $categoryId;
            $updated['tags'] = $tagIds;
            
            Anon_Http_Response::success($updated, '更新文章成功');
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
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('文章 ID 无效', 400);
                return;
            }
            
            $post = $db->db('posts')->where('id', $id)->first();
            if (!$post) {
                Anon_Http_Response::error('文章不存在', 404);
                return;
            }
            
            /**
             * 删除文章时不需要处理分类和标签关联
             */
            
            /**
             * 删除文章
             */
            $result = $db->db('posts')->where('id', $id)->delete();
            
            if ($result) {
                Anon_Http_Response::success(null, '删除文章成功');
            } else {
                Anon_Http_Response::error('删除文章失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

