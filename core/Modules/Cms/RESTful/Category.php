<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_RESTful_Category
{
    /**
     * 获取分类列表
     * GET /restful/v1/categories
     */
    public static function index(): array
    {
        try {
            $search = Anon_Http_Request::get('search', null);
            
            $params = Anon_System_Hook::apply_filters('restful_category_list_params', [
                'search' => $search,
            ]);
            
            // 调用服务层
            $result = Anon_Cms_Services_Category::getCategoryList($params['search']);
            
            $response = [
                'items' => $result['list'],
                'total' => $result['total'],
            ];
            
            $response = Anon_System_Hook::apply_filters('restful_category_list_response', $response);
            
            return Anon_Http_Response::success($response);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取分类列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取单个分类
     * GET /restful/v1/categories/{id}
     */
    public static function show(int $id): array
    {
        try {
            // 调用服务层
            $category = Anon_Cms_Services_Category::getCategoryById($id);
            
            if (!$category) {
                return Anon_Http_Response::error('分类不存在', 404);
            }
            
            return Anon_Http_Response::success($category);
        } catch (Exception $e) {
            return Anon_Http_Response::error('获取分类失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 创建分类
     * POST /restful/v1/categories
     */
    public static function store(): array
    {
        try {
            $data = Anon_Http_Request::all();
            
            if (empty($data['name'])) {
                return Anon_Http_Response::error('名称不能为空', 400);
            }
            
            $data = Anon_System_Hook::apply_filters('restful_category_before_create', $data);
            
            $category = Anon_Cms_Services_Category::createCategory($data);
            
            Anon_System_Hook::do_action('restful_category_after_create', $category);
            
            return Anon_Http_Response::success($category, '创建成功', 201);
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 更新分类
     * PUT /restful/v1/categories/{id}
     */
    public static function update(int $id): array
    {
        try {
            $data = Anon_Http_Request::all();
            
            $data = Anon_System_Hook::apply_filters('restful_category_before_update', $data, ['id' => $id]);
            
            $category = Anon_Cms_Services_Category::updateCategory($id, $data);
            
            Anon_System_Hook::do_action('restful_category_after_update', $id, $category);
            
            return Anon_Http_Response::success($category, '更新成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 删除分类
     * DELETE /restful/v1/categories/{id}
     */
    public static function destroy(int $id): array
    {
        try {
            $category = Anon_Cms_Services_Category::getCategoryById($id);
            
            Anon_System_Hook::do_action('restful_category_before_delete', $id, $category);
            
            Anon_Cms_Services_Category::deleteCategory($id);
            
            Anon_System_Hook::do_action('restful_category_after_delete', $id, $category);
            
            return Anon_Http_Response::success(null, '删除成功');
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * 批量删除分类
     * DELETE /restful/v1/categories/batch
     */
    public static function batchDestroy(): array
    {
        try {
            if (!Anon_Cms_Admin::isAdmin()) {
                return Anon_Http_Response::error('需要管理员权限', 403);
            }
            
            $data = Anon_Http_Request::all();
            
            if (empty($data['ids']) || !is_array($data['ids'])) {
                return Anon_Http_Response::error('请提供要删除的分类ID数组', 400);
            }
            
            $ids = $data['ids'];
            
            $result = Anon_Cms_Services_Category::batchDeleteCategories($ids);
            
            Anon_System_Hook::do_action('restful_category_after_batch_delete', $result);
            
            if ($result['failed'] > 0) {
                return Anon_Http_Response::success(
                    $result,
                    "批量删除完成：成功 {$result['success']} 个，失败 {$result['failed']} 个"
                );
            }
            
            return Anon_Http_Response::success($result, "批量删除成功，共删除 {$result['success']} 个分类");
        } catch (Exception $e) {
            return Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
