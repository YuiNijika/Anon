<?php
namespace Anon\Modules\Cms\AdminManage;

use Anon\Modules\Cms\ServicesPost as PostService;


use Exception;
use Manage;
use Post;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CMS 文章管理
 */
class Posts
{
    /**
     * 获取单个文章
     * @param int $id
     * @return void
     */
    public static function getOne($id)
    {
        try {
            $post = PostService::getPostById((int) $id);
            
            if (!$post) {
                ResponseHelper::error('文章不存在', null, 404);
            }
            
            ResponseHelper::success($post, '获取文章成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 获取文章列表
     * @return void
     */
    public static function getList()
    {
        try {
            $getParams = $_GET;
            $postData = RequestHelper::getInput();
            $data = array_merge($getParams, $postData);
            
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $type = isset($data['type']) ? trim($data['type']) : null;
            $status = isset($data['status']) ? trim($data['status']) : null;
            $search = isset($data['search']) && !empty($data['search']) ? trim($data['search']) : null;
            
            // 调用服务层（不检查权限，管理后台可以查看所有状态）
            $result = PostService::getPostList($page, $pageSize, $type, $status, $search, false);
            
            $message = '获取文章列表成功';
            if ($search && $result['total'] === 0) {
                $message = '未找到匹配的文章';
            }
            
            ResponseHelper::success([
                'list' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'page_size' => $pageSize,
            ], $message);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 创建文章
     * @return void
     */
    public static function create()
    {
        try {
            $data = RequestHelper::getInput();
            $userId = RequestHelper::getUserId();
            
            if (empty($data['title'])) {
                ResponseHelper::error('文章标题不能为空', null, 400);
            }
            
            if (empty($data['type']) || !in_array($data['type'], ['post', 'page'])) {
                ResponseHelper::error('文章类型无效', null, 400);
            }
            
            // 调用服务层
            $post = PostService::createPost($data, $userId);
            
            ResponseHelper::success($post, '创建文章成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }

    /**
     * 更新文章
     * @return void
     */
    public static function update()
    {
        try {
            $data = RequestHelper::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                ResponseHelper::error('文章 ID 无效', null, 400);
            }
            
            if (empty($data['title'])) {
                ResponseHelper::error('文章标题不能为空', null, 400);
            }
            
            // 调用服务层
            $post = PostService::updatePost($id, $data);
            
            ResponseHelper::success($post, '更新文章成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }

    /**
     * 删除文章
     * @return void
     */
    public static function delete()
    {
        try {
            $data = RequestHelper::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                ResponseHelper::error('文章 ID 无效', null, 400);
            }
            
            // 调用服务层
            PostService::deletePost($id);
            
            ResponseHelper::success(null, '删除文章成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
}
