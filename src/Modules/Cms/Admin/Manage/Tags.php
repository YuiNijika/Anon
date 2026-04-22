<?php
namespace Anon\Modules\Cms\AdminManage;

use Anon\Modules\Cms\ServicesTag as TagService;



use Exception;
use Manage;
use Tag;
use Anon\Modules\Database\Database;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Tags
{
    /**
     * 获取标签列表
     * @return array
     */
    private static function getTagList()
    {
        $db = Database::getInstance();
        return $db->db('metas')
            ->where('type', 'tag')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * 获取标签信息
     * @param int $id
     * @return array|null
     */
    private static function getTagById($id)
    {
        $db = Database::getInstance();
        return $db->db('metas')
            ->where('id', $id)
            ->where('type', 'tag')
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
        $db = Database::getInstance();
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
        $db = Database::getInstance();
        return $db->db('metas')->insert([
            'name' => $name,
            'slug' => $slug,
            'type' => 'tag',
        ]);
    }

    /**
     * 更新标签
     * @param int $id
     * @param array $data
     * @return bool
     */
    private static function updateTag($id, $data)
    {
        $db = Database::getInstance();
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
        $db = Database::getInstance();
        return $db->db('metas')->where('id', $id)->delete();
    }

    /**
     * 获取标签列表
     * @return void
     */
    public static function get()
    {
        try {
            $result = TagService::getTagList();
            ResponseHelper::success($result['list'], '获取标签列表成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 创建标签
     * @return void
     */
    public static function create()
    {
        try {
            $data = RequestHelper::getInput();
            
            if (empty($data['name'])) {
                ResponseHelper::error('标签名称不能为空', null, 400);
            }
            
            $tag = TagService::createTag($data);
            ResponseHelper::success($tag, '创建标签成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }

    /**
     * 更新标签
     * @return void
     */
    public static function update()
    {
        try {
            $data = RequestHelper::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                ResponseHelper::error('标签 ID 无效', null, 400);
            }
            
            if (empty($data['name'])) {
                ResponseHelper::error('标签名称不能为空', null, 400);
            }
            
            $tag = TagService::updateTag($id, $data);
            ResponseHelper::success($tag, '更新标签成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }

    /**
     * 删除标签
     * @return void
     */
    public static function delete()
    {
        try {
            $data = RequestHelper::getInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                ResponseHelper::error('标签 ID 无效', null, 400);
            }
            
            TagService::deleteTag($id);
            ResponseHelper::success(null, '删除标签成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 400);
        }
    }
}
