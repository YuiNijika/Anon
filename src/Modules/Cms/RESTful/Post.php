<?php
namespace Anon\Modules\Cms\RESTful;


use Anon\Modules\Cms\ServicesPost as PostService;



use Exception;
use RESTful;
use Admin;

use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Hook;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Post
{
    /**
     * 获取文章列表
     * GET /restful/v1/posts
     */
    public static function index(): void
    {
        try {
            $page = max(1, (int) RequestHelper::get('page', 1));
            $pageSize = min(100, max(1, (int) RequestHelper::get('page_size', 10)));
            $type = RequestHelper::get('type', 'post');
            $status = RequestHelper::get('status', null);
            $search = RequestHelper::get('search', null);
            $contentFormat = RequestHelper::get('content_format', 'html');
            
            if (!in_array($contentFormat, ['html', 'markdown'])) {
                $contentFormat = 'html';
            }
            
            if (!Admin::isAdmin()) {
                $status = 'publish';
            }
            
            $params = Hook::apply_filters('restful_post_list_params', [
                'page' => $page,
                'page_size' => $pageSize,
                'type' => $type,
                'status' => $status,
                'search' => $search,
                'content_format' => $contentFormat,
            ]);
            
            // 调用服务层
            $result = PostService::getPostList(
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
            
            $response = Hook::apply_filters('restful_post_list_response', $response);
            
            ResponseHelper::success($response);
        } catch (Exception $e) {
            ResponseHelper::error('获取文章列表失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 获取单篇文章
     * GET /cms/posts/{id}
     */
    public static function show(int $id): void
    {
        try {
            $contentFormat = RequestHelper::get('content_format', 'html');
            
            if (!in_array($contentFormat, ['html', 'markdown'])) {
                $contentFormat = 'html';
            }
            
            // 调用服务层
            $post = PostService::getPostById($id, $contentFormat);
            
            if (!$post) {
                ResponseHelper::error('文章不存在', null, 404);
            }
            
            ResponseHelper::success($post);
        } catch (Exception $e) {
            ResponseHelper::error('获取文章失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 创建文章
     * POST /restful/v1/posts
     */
    public static function store(): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $data = RequestHelper::getInput();
            $userId = RequestHelper::getUserId();
            
            if (empty($data['title'])) {
                ResponseHelper::error('标题不能为空', null, 400);
            }
            
            $data = Hook::apply_filters('restful_post_before_create', $data);
            
            $post = PostService::createPost($data, $userId);
            
            Hook::do_action('restful_post_after_create', $post);
            
            ResponseHelper::success($post, '创建成功', 201);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 更新文章
     * PUT /restful/v1/posts/{id}
     */
    public static function update(int $id): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $data = RequestHelper::getInput();
            
            $data = Hook::apply_filters('restful_post_before_update', $data, ['id' => $id]);
            
            $post = PostService::updatePost($id, $data);
            
            Hook::do_action('restful_post_after_update', $id, $post);
            
            ResponseHelper::success($post, '更新成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 删除文章
     * DELETE /restful/v1/posts/{id}
     */
    public static function destroy(int $id): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $post = PostService::getPostById($id);
            
            Hook::do_action('restful_post_before_delete', $id, $post);
            
            PostService::deletePost($id);
            
            Hook::do_action('restful_post_after_delete', $id, $post);
            
            ResponseHelper::success(null, '删除成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 批量删除文章
     * DELETE /restful/v1/posts/batch
     */
    public static function batchDestroy(): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $data = RequestHelper::getInput();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                ResponseHelper::error('请提供要删除的文章ID数组', null, 400);
            }
            
            $ids = $data['ids'];
            
            $result = PostService::batchDeletePosts($ids);
            
            Hook::do_action('restful_post_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                ResponseHelper::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            ResponseHelper::success($result, "批量删除成功，共删除 {$result['success']} 个文章");
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
}
