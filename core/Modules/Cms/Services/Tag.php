<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CMS 标签服务层
 */
class Anon_Cms_Services_Tag
{
    /**
     * 获取单个标签
     * @param int $id 标签ID
     * @return array|null
     */
    public static function getTagById(int $id): ?array
    {
        $db = Anon_Database::getInstance();
        $tag = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
            ->first();
        
        if (!$tag) {
            return null;
        }
        
        return self::formatTag($tag);
    }

    /**
     * 获取标签列表
     * @param string|null $search 搜索关键词
     * @return array
     */
    public static function getTagList(?string $search = null): array
    {
        $params = Anon_System_Hook::apply_filters('cms_tag_list_params', [
            'search' => $search,
        ]);
        
        $search = $params['search'];
        
        $db = Anon_Database::getInstance();
        $baseQuery = $db->db('metas')
            ->where('type', 'tag');
        
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $baseQuery->where(function($query) use ($searchTerm) {
                $query->where('name', 'LIKE', $searchTerm)
                      ->orWhere('slug', 'LIKE', $searchTerm);
            });
        }
        
        $tags = $baseQuery
            ->orderBy('created_at', 'DESC')
            ->get();
        
        // 格式化数据
        if (is_array($tags)) {
            foreach ($tags as &$tag) {
                $tag = self::formatTag($tag);
            }
            unset($tag);
        }
        
        $result = [
            'list' => $tags ?: [],
            'total' => count($tags ?: []),
        ];
        
        return Anon_System_Hook::apply_filters('cms_tag_list_result', $result, [
            'search' => $search,
        ]);
    }

    /**
     * 创建标签
     * @param array $data 标签数据
     * @return array|null 创建后的标签数据
     */
    public static function createTag(array $data): ?array
    {
        $db = Anon_Database::getInstance();
        
        // 处理名称和别名
        $name = trim($data['name']);
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
        
        $existing = self::checkTagSlugExists($slug);
        if ($existing) {
            return self::formatTag($existing);
        }
        
        $insertData = Anon_System_Hook::apply_filters('cms_tag_before_insert', [
            'name' => $name,
            'slug' => $slug,
            'type' => 'tag',
        ], ['action' => 'create']);
        
        // 插入标签
        $tagId = $db->db('metas')->insert($insertData);
        
        if (!$tagId) {
            throw new Exception('创建标签失败');
        }
        
        Anon_System_Hook::do_action('cms_tag_after_create', $tagId, $insertData);
        
        return self::getTagById($tagId);
    }

    /**
     * 更新标签
     * @param int $id 标签ID
     * @param array $data 更新数据
     * @return array|null 更新后的标签数据
     */
    public static function updateTag(int $id, array $data): ?array
    {
        $db = Anon_Database::getInstance();
        
        // 检查标签是否存在
        $existingTag = self::getTagById($id);
        if (!$existingTag) {
            throw new Exception('标签不存在');
        }
        
        // 处理名称和别名
        $name = isset($data['name']) ? trim($data['name']) : $existingTag['name'];
        $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
        
        if (self::checkTagSlugExists($slug, $id)) {
            throw new Exception('标签别名已存在');
        }
        
        $updateData = Anon_System_Hook::apply_filters('cms_tag_before_update', [
            'name' => $name,
            'slug' => $slug,
        ], ['id' => $id, 'action' => 'update']);
        
        $result = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
            ->update($updateData);
        
        if ($result === false) {
            throw new Exception('更新标签失败');
        }
        
        Anon_System_Hook::do_action('cms_tag_after_update', $id, $updateData);
        
        return self::getTagById($id);
    }

    /**
     * 删除标签
     * @param int $id 标签ID
     * @return bool
     */
    public static function deleteTag(int $id): bool
    {
        $db = Anon_Database::getInstance();
        
        if (!$tag) {
            throw new Exception('标签不存在');
        }
        
        Anon_System_Hook::do_action('cms_tag_before_delete', $id, $tag);
        
        // 删除标签
        $result = $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
            ->delete();
        
        if ($result !== false) {
            Anon_System_Hook::do_action('cms_tag_after_delete', $id, $tag);
        }
        
        return $result !== false;
    }

    /**
     * 批量删除标签
     * @param array $ids 标签ID数组
     * @return array 结果数组 ['success' => int, 'failed' => int, 'errors' => array]
     */
    public static function batchDeleteTags(array $ids): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            try {
                $id = (int)$id;
                if ($id <= 0) {
                    $failed++;
                    $errors[] = ['id' => $id, 'error' => '无效的标签ID'];
                    continue;
                }
                
                self::deleteTag($id);
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
     * 格式化标签数据
     * @param array $tag 原始标签数据
     * @return array 格式化后的标签数据
     */
    private static function formatTag(array $tag): array
    {
        // 处理时间
        if (isset($tag['created_at'])) {
            $tag['created_at'] = is_string($tag['created_at']) 
                ? strtotime($tag['created_at']) 
                : $tag['created_at'];
        }
        if (isset($tag['updated_at'])) {
            $tag['updated_at'] = is_string($tag['updated_at']) 
                ? strtotime($tag['updated_at']) 
                : $tag['updated_at'];
        }
        
        return $tag;
    }

    /**
     * 检查标签别名是否已存在
     * @param string $slug 别名
     * @param int $excludeId 排除的标签ID（更新时使用）
     * @return array|null
     */
    private static function checkTagSlugExists(string $slug, int $excludeId = 0): ?array
    {
        $db = Anon_Database::getInstance();
        $query = $db->db('metas')
            ->where('slug', $slug)
            ->where('type', 'tag');
        
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->first();
    }
}
