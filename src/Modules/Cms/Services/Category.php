<?php
namespace Anon\Modules\Cms\Services;



use Exception;
use Services;
use Anon\Modules\Database\Database;
use Anon\Modules\System\Hook;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CMS 分类服务层
 */
class Category
{
    /**
     * 获取单个分类
     * @param int $id 分类ID
     * @return array|null
     */
    public static function getCategoryById(int $id): ?array
    {
        $db = Database::getInstance();
        $category = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'category')
            ->first();
        
        if (!$category) {
            return null;
        }
        
        return self::formatCategory($category);
    }

    /**
     * 获取分类列表
     * @param string|null $search 搜索关键词
     * @return array
     */
    public static function getCategoryList(?string $search = null): array
    {
        $params = Hook::apply_filters('cms_category_list_params', [
            'search' => $search,
        ]);
        
        $search = $params['search'];
        
        $db = Database::getInstance();
        $baseQuery = $db->db('metas')
            ->where('type', 'category');
        
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $baseQuery->where(function($query) use ($searchTerm) {
                $query->where('name', 'LIKE', $searchTerm)
                      ->orWhere('slug', 'LIKE', $searchTerm)
                      ->orWhere('description', 'LIKE', $searchTerm);
            });
        }
        
        $categories = $baseQuery
            ->orderBy('created_at', 'DESC')
            ->get();
        
        // 格式化数据
        if (is_array($categories)) {
            foreach ($categories as &$category) {
                $category = self::formatCategory($category);
            }
            unset($category);
        }
        
        $result = [
            'list' => $categories ?: [],
            'total' => count($categories ?: []),
        ];
        
        return Hook::apply_filters('cms_category_list_result', $result, [
            'search' => $search,
        ]);
    }

    /**
     * 创建分类
     * @param array $data 分类数据
     * @return array|null 创建后的分类数据
     */
    public static function createCategory(array $data): ?array
    {
        $db = Database::getInstance();
        
        // 处理名称和别名
        $name = trim($data['name']);
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
        $description = isset($data['description']) ? trim($data['description']) : null;
        $parentId = isset($data['parent_id']) && $data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
        
        if (self::checkCategorySlugExists($slug)) {
            throw new Exception('分类别名已存在');
        }
        
        if ($parentId !== null && !self::checkCategoryExists($parentId)) {
            throw new Exception('父分类不存在');
        }
        
        $insertData = Hook::apply_filters('cms_category_before_insert', [
            'name' => $name,
            'slug' => $slug,
            'type' => 'category',
            'description' => $description,
            'parent_id' => $parentId,
        ], ['action' => 'create']);
        
        // 插入分类
        $categoryId = $db->db('metas')->insert($insertData);
        
        if (!$categoryId) {
            throw new Exception('创建分类失败');
        }
        
        Hook::do_action('cms_category_after_create', $categoryId, $insertData);
        
        return self::getCategoryById($categoryId);
    }

    /**
     * 更新分类
     * @param int $id 分类ID
     * @param array $data 更新数据
     * @return array|null 更新后的分类数据
     */
    public static function updateCategory(int $id, array $data): ?array
    {
        $db = Database::getInstance();
        
        // 检查分类是否存在
        $existingCategory = self::getCategoryById($id);
        if (!$existingCategory) {
            throw new Exception('分类不存在');
        }
        
        // 处理名称和别名
        $name = isset($data['name']) ? trim($data['name']) : $existingCategory['name'];
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
        $description = isset($data['description']) ? trim($data['description']) : $existingCategory['description'];
        $parentId = isset($data['parent_id']) && $data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
        
        if (self::checkCategorySlugExists($slug, $id)) {
            throw new Exception('分类别名已存在');
        }
        
        if ($parentId !== null && !self::checkCategoryExists($parentId)) {
            throw new Exception('父分类不存在');
        }
        
        if ($parentId === $id) {
            throw new Exception('不能将分类设置为自己的子分类');
        }
        
        $updateData = Hook::apply_filters('cms_category_before_update', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parentId,
        ], ['id' => $id, 'action' => 'update']);
        
        $result = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'category')
            ->update($updateData);
        
        if ($result === false) {
            throw new Exception('更新分类失败');
        }
        
        Hook::do_action('cms_category_after_update', $id, $updateData);
        
        return self::getCategoryById($id);
    }

    /**
     * 删除分类
     * @param int $id 分类ID
     * @return bool
     */
    public static function deleteCategory(int $id): bool
    {
        $db = Database::getInstance();
        
        // 检查分类是否存在
        $category = self::getCategoryById($id);
        if (!$category) {
            throw new Exception('分类不存在');
        }
        
        if (self::hasChildren($id)) {
            throw new Exception('该分类下还有子分类，无法删除');
        }
        
        Hook::do_action('cms_category_before_delete', $id, $category);
        
        // 删除分类
        $result = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'category')
            ->delete();
        
        if ($result !== false) {
            Hook::do_action('cms_category_after_delete', $id, $category);
        }
        
        return $result !== false;
    }

    /**
     * 批量删除分类
     * @param array $ids 分类ID数组
     * @return array 结果数组 ['success' => int, 'failed' => int, 'errors' => array]
     */
    public static function batchDeleteCategories(array $ids): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            try {
                $id = (int)$id;
                if ($id <= 0) {
                    $failed++;
                    $errors[] = ['id' => $id, 'error' => '无效的分类ID'];
                    continue;
                }
                
                self::deleteCategory($id);
                $success++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * 格式化分类数据
     * @param array $category 原始分类数据
     * @return array 格式化后的分类数据
     */
    private static function formatCategory(array $category): array
    {
        // 处理时间
        if (isset($category['created_at'])) {
            $category['created_at'] = is_string($category['created_at']) 
                ? strtotime($category['created_at']) 
                : $category['created_at'];
        }
        if (isset($category['updated_at'])) {
            $category['updated_at'] = is_string($category['updated_at']) 
                ? strtotime($category['updated_at']) 
                : $category['updated_at'];
        }
        
        return $category;
    }

    /**
     * 检查分类是否存在
     * @param int $categoryId 分类ID
     * @return bool
     */
    private static function checkCategoryExists(int $categoryId): bool
    {
        $db = Database::getInstance();
        $category = $db->db('metas')
            ->where('id', $categoryId)
            ->where('type', 'category')
            ->first();
        
        return $category !== null;
    }

    /**
     * 检查分类别名是否已存在
     * @param string $slug 别名
     * @param int $excludeId 排除的分类ID（更新时使用）
     * @return bool
     */
    private static function checkCategorySlugExists(string $slug, int $excludeId = 0): bool
    {
        $db = Database::getInstance();
        $query = $db->db('metas')
            ->where('slug', $slug)
            ->where('type', 'category');
        
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->first() !== null;
    }

    /**
     * 检查分类是否有子分类
     * @param int $id 分类ID
     * @return bool
     */
    private static function hasChildren(int $id): bool
    {
        $db = Database::getInstance();
        $count = $db->db('metas')
            ->where('type', 'category')
            ->where('parent_id', $id)
            ->count();
        
        return $count > 0;
    }
}
