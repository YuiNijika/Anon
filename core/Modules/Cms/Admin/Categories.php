<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 分类管理类
 * 处理分类的增删改查功能
 */
class Anon_Cms_Admin_Categories
{
    /**
     * 获取分类列表
     * @return void
     */
    public static function get()
    {
        try {
            $db = Anon_Database::getInstance();
            $categories = $db->db('metas')
                ->where('type', 'category')
                ->orderBy('created_at', 'DESC')
                ->get();
            
            Anon_Http_Response::success($categories, '获取分类列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 创建分类
     * @return void
     */
    public static function create()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('分类名称不能为空', 400);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            $parentId = isset($data['parent_id']) && $data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
            
            /**
             * 检查别名是否已存在
             */
            $existing = $db->db('metas')
                ->where('slug', $slug)
                ->where('type', 'category')
                ->first();
            
            if ($existing) {
                Anon_Http_Response::error('分类别名已存在', 400);
                return;
            }
            
            $result = $db->db('metas')->insert([
                'name' => $name,
                'slug' => $slug,
                'type' => 'category',
                'parent_id' => $parentId,
            ]);
            
            if ($result) {
                $category = $db->db('metas')->where('id', $result)->first();
                Anon_Http_Response::success($category, '创建分类成功');
            } else {
                Anon_Http_Response::error('创建分类失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 更新分类
     * @return void
     */
    public static function update()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('分类 ID 无效', 400);
                return;
            }
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('分类名称不能为空', 400);
                return;
            }
            
            $category = $db->db('metas')
                ->where('id', $id)
                ->where('type', 'category')
                ->first();
            
            if (!$category) {
                Anon_Http_Response::error('分类不存在', 404);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            $parentId = isset($data['parent_id']) && $data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
            
            /**
             * 检查别名是否已被其他分类使用
             */
            $existing = $db->db('metas')
                ->where('slug', $slug)
                ->where('type', 'category')
                ->where('id', '!=', $id)
                ->first();
            
            if ($existing) {
                Anon_Http_Response::error('分类别名已存在', 400);
                return;
            }
            
            $updateData = [
                'name' => $name,
                'slug' => $slug,
            ];
            
            if ($parentId !== null) {
                $updateData['parent_id'] = $parentId;
            }
            
            $result = $db->db('metas')
                ->where('id', $id)
                ->update($updateData);
            
            if ($result !== false) {
                $updated = $db->db('metas')->where('id', $id)->first();
                Anon_Http_Response::success($updated, '更新分类成功');
            } else {
                Anon_Http_Response::error('更新分类失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除分类
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('分类 ID 无效', 400);
                return;
            }
            
            $category = $db->db('metas')
                ->where('id', $id)
                ->where('type', 'category')
                ->first();
            
            if (!$category) {
                Anon_Http_Response::error('分类不存在', 404);
                return;
            }
            
            /**
             * 检查是否有子分类
             */
            $children = $db->db('metas')
                ->where('type', 'category')
                ->where('parent_id', $id)
                ->count();
            
            if ($children > 0) {
                Anon_Http_Response::error('该分类下还有子分类，无法删除', 400);
                return;
            }
            
            $result = $db->db('metas')->where('id', $id)->delete();
            
            if ($result) {
                Anon_Http_Response::success(null, '删除分类成功');
            } else {
                Anon_Http_Response::error('删除分类失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

