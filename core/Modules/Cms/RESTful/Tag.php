<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_RESTful_Tag
{
    /**
     * 获取标签列表
     * GET /restful/v1/tags
     */
    public static function index(): array
    {
        try {
            $search = Anon_Http_Request::get('search', null);
            
            $params = Anon_System_Hook::apply_filters('restful_tag_list_params', [
                'search' => $search,
            ]);
            
            // 调用服务层
            $result = Anon_Cms_Services_Tag::getTagList($params['search']);
            
            $response = [
                'items' => $result['list'],
                'total' => $result['total'],
            ];
            
            $response = Anon_System_Hook::apply_filters('restful_tag_list_response', $response);
            
            return Anon_Http_Response::success($response);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取标签列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取单个标签
     * GET /restful/v1/tags/{id}
     */
    public static function show(int $id): array
    {
        try {
            // 调用服务层
            $tag = Anon_Cms_Services_Tag::getTagById($id);
            
            if (!$tag) {
                return Anon_Http_Response::error('标签不存在', 404);
            }
            
            return Anon_Http_Response::success($tag);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取标签失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 创建标签
     * POST /restful/v1/tags
     */
    public static function store(): array
    {
        try {
            $data = Anon_Http_Request::all();
            
            if (empty($data['name'])) {
                return Anon_Http_Response::error('名称不能为空', 400);
            }
            
            $data = Anon_System_Hook::apply_filters('restful_tag_before_create', $data);
            
            $tag = Anon_Cms_Services_Tag::createTag($data);
            
            Anon_System_Hook::do_action('restful_tag_after_create', $tag);
            
            return Anon_Http_Response::success($tag, '创建成功', 201);
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 更新标签
     * PUT /restful/v1/tags/{id}
     */
    public static function update(int $id): array
    {
        try {
            $data = Anon_Http_Request::all();
            
            $data = Anon_System_Hook::apply_filters('restful_tag_before_update', $data, ['id' => $id]);
            
            $tag = Anon_Cms_Services_Tag::updateTag($id, $data);
            
            Anon_System_Hook::do_action('restful_tag_after_update', $id, $tag);
            
            return Anon_Http_Response::success($tag, '更新成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 删除标签
     * DELETE /restful/v1/tags/{id}
     */
    public static function destroy(int $id): array
    {
        try {
            $tag = Anon_Cms_Services_Tag::getTagById($id);
            
            Anon_System_Hook::do_action('restful_tag_before_delete', $id, $tag);
            
            Anon_Cms_Services_Tag::deleteTag($id);
            
            Anon_System_Hook::do_action('restful_tag_after_delete', $id, $tag);
            
            return Anon_Http_Response::success(null, '删除成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 批量删除标签
     * DELETE /restful/v1/tags/batch
     */
    public static function batchDestroy(): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                return Anon_Http_Response::error('请提供要删除的标签ID数组', 400);
            }
            
            $ids = $data['ids'];
            
            $result = Anon_Cms_Services_Tag::batchDeleteTags($ids);
            
            Anon_System_Hook::do_action('restful_tag_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                return Anon_Http_Response::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            return Anon_Http_Response::success($result, "批量删除成功，共删除 {$result['success']} 个标签");
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
