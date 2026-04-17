<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_RESTful_Post
{
    /**
     * 获取文章列表
     * GET /restful/v1/posts
     */
    public static function index(): array
    {
        try {
            $page = max(1, intval(Anon_Http_Request::get('page', 1)));
            $pageSize = min(100, max(1, intval(Anon_Http_Request::get('page_size', 10))));
            $type = Anon_Http_Request::get('type', 'post');
            $status = Anon_Http_Request::get('status', null);
            $search = Anon_Http_Request::get('search', null);
            $contentFormat = Anon_Http_Request::get('content_format', 'html');
            
            if (!in_array($contentFormat, ['html', 'markdown'])) {
                $contentFormat = 'html';
            }
            
            if (!Anon_Cms_Admin::isAdmin()) {
                $status = 'publish';
            }
            
            $params = Anon_System_Hook::apply_filters('restful_post_list_params', [
                'page' => $page,
                'page_size' => $pageSize,
                'type' => $type,
                'status' => $status,
                'search' => $search,
                'content_format' => $contentFormat,
            ]);
            
            // 调用服务层
            $result = Anon_Cms_Services_Post::getPostList(
                $params['page'],
                $params['page_size'],
                $params['type'],
                $params['status'],
                $params['search'],
                true,
                $params['content_format'] ?? 'html'
            );
            
            $response = [
                'items' => $result['list'],
                'pagination' => [
                    'page' => $params['page'],
                    'page_size' => $params['page_size'],
                    'total' => $result['total'],
                    'total_pages' => ceil($result['total'] / $params['page_size']),
                ]
            ];
            
            $response = Anon_System_Hook::apply_filters('restful_post_list_response', $response);
            
            return Anon_Http_Response::success($response);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取文章列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取单篇文章
     * GET /cms/posts/{id}
     */
    public static function show(int $id): array
    {
        try {
            $contentFormat = Anon_Http_Request::get('content_format', 'html');
            
            if (!in_array($contentFormat, ['html', 'markdown'])) {
                $contentFormat = 'html';
            }
            
            // 调用服务层
            $post = Anon_Cms_Services_Post::getPostById($id, $contentFormat);
            
            if (!$post) {
                return Anon_Http_Response::error('文章不存在', 404);
            }
            
            return Anon_Http_Response::success($post);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取文章失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 创建文章
     * POST /restful/v1/posts
     */
    public static function store(): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            $userId = Anon_Check::getUserId();
            
            if (empty($data['title'])) {
                return Anon_Http_Response::error('标题不能为空', 400);
            }
            
            $data = Anon_System_Hook::apply_filters('restful_post_before_create', $data);
            
            $post = Anon_Cms_Services_Post::createPost($data, $userId);
            
            Anon_System_Hook::do_action('restful_post_after_create', $post);
            
            return Anon_Http_Response::success($post, '创建成功', 201);
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 更新文章
     * PUT /restful/v1/posts/{id}
     */
    public static function update(int $id): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            $data = Anon_System_Hook::apply_filters('restful_post_before_update', $data, ['id' => $id]);
            
            $post = Anon_Cms_Services_Post::updatePost($id, $data);
            
            Anon_System_Hook::do_action('restful_post_after_update', $id, $post);
            
            return Anon_Http_Response::success($post, '更新成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 删除文章
     * DELETE /restful/v1/posts/{id}
     */
    public static function destroy(int $id): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $post = Anon_Cms_Services_Post::getPostById($id);
            
            Anon_System_Hook::do_action('restful_post_before_delete', $id, $post);
            
            Anon_Cms_Services_Post::deletePost($id);
            
            Anon_System_Hook::do_action('restful_post_after_delete', $id, $post);
            
            return Anon_Http_Response::success(null, '删除成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 批量删除文章
     * DELETE /restful/v1/posts/batch
     */
    public static function batchDestroy(): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                return Anon_Http_Response::error('请提供要删除的文章ID数组', 400);
            }
            
            $ids = $data['ids'];
            
            $result = Anon_Cms_Services_Post::batchDeletePosts($ids);
            
            Anon_System_Hook::do_action('restful_post_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                return Anon_Http_Response::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            return Anon_Http_Response::success($result, "批量删除成功，共删除 {$result['success']} 个文章");
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
