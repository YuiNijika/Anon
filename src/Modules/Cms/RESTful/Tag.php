<?php
namespace Anon\Modules\Cms\RESTful;

use Anon\Modules\Cms\ServicesTag as TagService;



use Exception;

use RESTful;

use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Hook;
use Admin;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Tag
{
    /**
     * 获取标签列表
     * GET /restful/v1/tags
     */
    public static function index(): void
    {
        try {
            $search = RequestHelper::get('search', null);
            
            $params = Hook::apply_filters('restful_tag_list_params', [
                'search' => $search,
            ]);
            
            // 调用服务层
            $result = TagService::getTagList($params['search']);
            
            $response = [
                'items' => $result['list'],
                'total' => $result['total'],
            ];
            
            $response = Hook::apply_filters('restful_tag_list_response', $response);
            
            ResponseHelper::success($response);
        } catch (Exception $e) {
            ResponseHelper::error('获取标签列表失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 获取单个标签
     * GET /restful/v1/tags/{id}
     */
    public static function show(int $id): void
    {
        try {
            // 调用服务层
            $tag = TagService::getTagById($id);
            
            if (!$tag) {
                ResponseHelper::error('标签不存在', null, 404);
            }
            
            ResponseHelper::success($tag);
        } catch (Exception $e) {
            ResponseHelper::error('获取标签失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 创建标签
     * POST /restful/v1/tags
     */
    public static function store(): void
    {
        try {
            $data = RequestHelper::getInput();
            
            if (empty($data['name'])) {
                ResponseHelper::error('名称不能为空', null, 400);
            }
            
            $data = Hook::apply_filters('restful_tag_before_create', $data);
            
            $tag = TagService::createTag($data);
            
            Hook::do_action('restful_tag_after_create', $tag);
            
            ResponseHelper::success($tag, '创建成功', 201);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 更新标签
     * PUT /restful/v1/tags/{id}
     */
    public static function update(int $id): void
    {
        try {
            $data = RequestHelper::getInput();
            
            $data = Hook::apply_filters('restful_tag_before_update', $data, ['id' => $id]);
            
            $tag = TagService::updateTag($id, $data);
            
            Hook::do_action('restful_tag_after_update', $id, $tag);
            
            ResponseHelper::success($tag, '更新成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 删除标签
     * DELETE /restful/v1/tags/{id}
     */
    public static function destroy(int $id): void
    {
        try {
            $tag = TagService::getTagById($id);
            
            Hook::do_action('restful_tag_before_delete', $id, $tag);
            
            TagService::deleteTag($id);
            
            Hook::do_action('restful_tag_after_delete', $id, $tag);
            
            ResponseHelper::success(null, '删除成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    
    /**
     * 批量删除标签
     * DELETE /restful/v1/tags/batch
     */
    public static function batchDestroy(): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $data = RequestHelper::getInput();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                ResponseHelper::error('请提供要删除的标签ID数组', null, 400);
            }
            
            $ids = $data['ids'];
            
            $result = TagService::batchDeleteTags($ids);
            
            Hook::do_action('restful_tag_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                ResponseHelper::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            ResponseHelper::success($result, "批量删除成功，共删除 {$result['success']} 个标签");
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
}
