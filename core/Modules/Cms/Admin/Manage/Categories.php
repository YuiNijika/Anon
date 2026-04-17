<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_Categories
{
    /**
     * 获取分类列表
     * @return array
     */
    private static function getCategoryList()
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('type', 'category')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * 获取分类信息
     * @param int $id
     * @return array|null
     */
    private static function getCategoryById($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('id', $id)
            ->where('type', 'category')
            ->first();
    }

    /**
     * 检查别名是否已存在
     * @param string $slug
     * @param int $excludeId
     * @return array|null
     */
    private static function checkSlugExists($slug, $excludeId = 0)
    {
        $db = Anon_Database::getInstance();
        $query = $db->db('metas')
            ->where('slug', $slug)
            ->where('type', 'category');
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    /**
     * 获取子分类数量
     * @param int $id
     * @return int
     */
    private static function getChildrenCount($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('type', 'category')
            ->where('parent_id', $id)
            ->count();
    }

    /**
     * 创建分类
     * @param string $name
     * @param string $slug
     * @param string|null $description
     * @param int|null $parentId
     * @return int|false
     */
    private static function createCategory($name, $slug, $description = null, $parentId = null)
    {
        $db = Anon_Database::getInstance();
        $insertData = [
            'name' => $name,
            'slug' => $slug,
            'type' => 'category',
            'parent_id' => $parentId,
        ];
        if ($description !== null) {
            $insertData['description'] = $description;
        }
        return $db->db('metas')->insert($insertData);
    }

    /**
     * 更新分类
     * @param int $id
     * @param array $data
     * @return bool
     */
    private static function updateCategory($id, $data)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('id', $id)
            ->where('type', 'category')
            ->update($data) !== false;
    }

    /**
     * 删除分类
     * @param int $id
     * @return bool
     */
    private static function deleteCategory($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')->where('id', $id)->delete();
    }

    /**
     * 获取分类列表
     * @return void
     */
    public static function get()
    {
        try {
            $result = Anon_Cms_Services_Category::getCategoryList();
            Anon_Http_Response::success($result['list'], '获取分类列表成功');
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
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('分类名称不能为空', 400);
                return;
            }
            
            $category = Anon_Cms_Services_Category::createCategory($data);
            Anon_Http_Response::success($category, '创建分类成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
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
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('分类 ID 无效', 400);
                return;
            }
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('分类名称不能为空', 400);
                return;
            }
            
            $category = Anon_Cms_Services_Category::updateCategory($id, $data);
            Anon_Http_Response::success($category, '更新分类成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
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
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('分类 ID 无效', 400);
                return;
            }
            
            Anon_Cms_Services_Category::deleteCategory($id);
            Anon_Http_Response::success(null, '删除分类成功');
        } catch (Exception $e) {
            Anon_Http_Response::error($e->getMessage(), 400);
        }
    }
}
