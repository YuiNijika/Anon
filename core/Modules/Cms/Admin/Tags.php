<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_Tags
{
    /**
     * 获取标签列表
     * @return void
     */
    public static function get()
    {
        try {
            $tags = self::getTagList();
            Anon_Http_Response::success($tags, '获取标签列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 创建标签
     * @return void
     */
    public static function create()
    {
        try {
            $data = Anon_Http_Request::getInput();
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('标签名称不能为空', 400);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            
            $existing = self::checkSlugExists($slug);
            if ($existing) {
                Anon_Http_Response::success($existing, '标签已存在');
                return;
            }
            
            $id = self::createTag($name, $slug);
            
            if ($id) {
                $tag = self::getTagById($id);
                Anon_Http_Response::success($tag, '创建标签成功');
            } else {
                Anon_Http_Response::error('创建标签失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 更新标签
     * @return void
     */
    public static function update()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('标签 ID 无效', 400);
                return;
            }
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('标签名称不能为空', 400);
                return;
            }
            
            $tag = self::getTagById($id);
            if (!$tag) {
                Anon_Http_Response::error('标签不存在', 404);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            
            if (self::checkSlugExists($slug, $id)) {
                Anon_Http_Response::error('标签别名已存在', 400);
                return;
            }
            
            $result = self::updateTag($id, [
                'name' => $name,
                'slug' => $slug,
            ]);
            
            if ($result) {
                $updated = self::getTagById($id);
                Anon_Http_Response::success($updated, '更新标签成功');
            } else {
                Anon_Http_Response::error('更新标签失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除标签
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('标签 ID 无效', 400);
                return;
            }
            
            $tag = self::getTagById($id);
            if (!$tag) {
                Anon_Http_Response::error('标签不存在', 404);
                return;
            }
            
            $result = self::deleteTag($id);
            
            if ($result) {
                Anon_Http_Response::success(null, '删除标签成功');
            } else {
                Anon_Http_Response::error('删除标签失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 获取标签列表
     * @return array
     */
    private static function getTagList()
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('type', 'tag')
            ->orderBy('created_at', 'DESC')
            ->get();
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
            ->where('type', 'tag');
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    /**
     * 创建标签
     * @param string $name
     * @param string $slug
     * @return int|false
     */
    private static function createTag($name, $slug)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')->insert([
            'name' => $name,
            'slug' => $slug,
            'type' => 'tag',
        ]);
    }

    /**
     * 获取标签信息
     * @param int $id
     * @return array|null
     */
    private static function getTagById($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
            ->first();
    }

    /**
     * 更新标签
     * @param int $id
     * @param array $data
     * @return bool
     */
    private static function updateTag($id, $data)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
            ->update($data) !== false;
    }

    /**
     * 删除标签
     * @param int $id
     * @return bool
     */
    private static function deleteTag($id)
    {
        $db = Anon_Database::getInstance();
        return $db->db('metas')->where('id', $id)->delete();
    }
}

