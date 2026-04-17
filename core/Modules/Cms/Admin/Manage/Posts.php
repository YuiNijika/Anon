<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CMS 文章管理
 */
class Anon_Cms_Admin_Posts
{
    /**
     * 获取单个文章
     * @param int $id
     * @return void
     */
    public static function getOne($id)
    {
        try {
            $post = Anon_Cms_Services_Post::getPostById($id);
            
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
            $getParams = $_GET;
            $postData = Anon_Http_Request::getInput();
            $data = array_merge($getParams, $postData);
            
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $type = isset($data['type']) ? trim($data['type']) : null;
            $status = isset($data['status']) ? trim($data['status']) : null;
            $search = isset($data['search']) && !empty($data['search']) ? trim($data['search']) : null;
            
            // 调用服务层（不检查权限，管理后台可以查看所有状态）
            $result = Anon_Cms_Services_Post::getPostList($page, $pageSize, $type, $status, $search, false);
            
            $message = '获取文章列表成功';
            if ($search && $result['total'] === 0) {
                $message = '未找到匹配的文章';
            }
            
            Anon_Http_Response::success([
                'list' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'page_size' => $pageSize,
            ], $message);
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
            
            // 调用服务层
            $post = Anon_Cms_Services_Post::createPost($data, $userId);
            
            Anon_Http_Response::success($post, '创建文章成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
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
            
            // 调用服务层
            $post = Anon_Cms_Services_Post::updatePost($id, $data);
            
            Anon_Http_Response::success($post, '更新文章成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
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
            
            // 调用服务层
            Anon_Cms_Services_Post::deletePost($id);
            
            Anon_Http_Response::success(null, '删除文章成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
