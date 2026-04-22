<?php
namespace Anon\Modules\Cms\RESTful;

use Anon\Modules\Cms\ServicesCategory as CategoryService;



use Exception;

use RESTful;

use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Anon\Modules\System\Hook;
use Admin;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Category
{
    /**
     * 获取分类列表
     * GET /restful/v1/categories
     */
    public static function index(): void
    {
        try {
            $search = RequestHelper::get('search', null);
            
            $params = Hook::apply_filters('restful_category_list_params', [
                'search' => $search,
            ]);
            
            // 调用服务层
            $result = CategoryService::getCategoryList($params['search']);
            
            $response = [
                'items' => $result['list'],
                'total' => $result['total'],
            ];
            
            $response = Hook::apply_filters('restful_category_list_response', $response);
            
            ResponseHelper::success($response);
        } catch (Exception $e) {
            ResponseHelper::error('获取分类列表失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 获取单个分类
     * GET /restful/v1/categories/{id}
     */
    public static function show(int $id): void
    {
        try {
            // 调用服务层
            $category = CategoryService::getCategoryById($id);
            
            if (!$category) {
                ResponseHelper::error('分类不存在', null, 404);
            }
            
            ResponseHelper::success($category);
        } catch (Exception $e) {
            ResponseHelper::error('获取分类失败: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * 创建分类
     * POST /restful/v1/categories
     */
    public static function store(): void
    {
        try {
            $data = RequestHelper::getInput();
            
            if (empty($data['name'])) {
                ResponseHelper::error('名称不能为空', null, 400);
            }
            
            $data = Hook::apply_filters('restful_category_before_create', $data);
            
            $category = CategoryService::createCategory($data);
            
            Hook::do_action('restful_category_after_create', $category);
            
            ResponseHelper::success($category, '创建成功', 201);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 更新分类
     * PUT /restful/v1/categories/{id}
     */
    public static function update(int $id): void
    {
        try {
            $data = RequestHelper::getInput();
            
            $data = Hook::apply_filters('restful_category_before_update', $data, ['id' => $id]);
            
            $category = CategoryService::updateCategory($id, $data);
            
            Hook::do_action('restful_category_after_update', $id, $category);
            
            ResponseHelper::success($category, '更新成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    /**
     * 删除分类
     * DELETE /restful/v1/categories/{id}
     */
    public static function destroy(int $id): void
    {
        try {
            $category = CategoryService::getCategoryById($id);
            
            Hook::do_action('restful_category_before_delete', $id, $category);
            
            CategoryService::deleteCategory($id);
            
            Hook::do_action('restful_category_after_delete', $id, $category);
            
            ResponseHelper::success(null, '删除成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
    
    
    /**
     * 批量删除分类
     * DELETE /restful/v1/categories/batch
     */
    public static function batchDestroy(): void
    {
        try {
            if (!Admin::isAdmin()) {
                ResponseHelper::error('需要管理员权限', null, 403);
            }
            
            $data = RequestHelper::getInput();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                ResponseHelper::error('请提供要删除的分类ID数组', null, 400);
            }
            
            $ids = $data['ids'];
            
            $result = CategoryService::batchDeleteCategories($ids);
            
            Hook::do_action('restful_category_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                ResponseHelper::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            ResponseHelper::success($result, "批量删除成功，共删除 {$result['success']} 个分类");
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
}
