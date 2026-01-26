<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 标签管理类
 * 处理标签的增删改查功能
 */
class Anon_Cms_Admin_Tags
{
    /**
     * 获取标签列表
     * @return void
     */
    public static function get()
    {
        try {
            $db = Anon_Database::getInstance();
            $tags = $db->db('metas')
                ->where('type', 'tag')
                ->orderBy('created_at', 'DESC')
                ->get();
            
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
            $db = Anon_Database::getInstance();
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('标签名称不能为空', 400);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            
            /**
             * 检查标签是否已存在
             */
            $existing = $db->db('metas')
                ->where('slug', $slug)
                ->where('type', 'tag')
                ->first();
            
            if ($existing) {
                Anon_Http_Response::success($existing, '标签已存在');
                return;
            }
            
            $result = $db->db('metas')->insert([
                'name' => $name,
                'slug' => $slug,
                'type' => 'tag',
            ]);
            
            if ($result) {
                $tag = $db->db('metas')->where('id', $result)->first();
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
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('标签 ID 无效', 400);
                return;
            }
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('标签名称不能为空', 400);
                return;
            }
            
            $tag = $db->db('metas')
                ->where('id', $id)
                ->where('type', 'tag')
                ->first();
            
            if (!$tag) {
                Anon_Http_Response::error('标签不存在', 404);
                return;
            }
            
            $name = trim($data['name']);
            $slug = isset($data['slug']) && !empty($data['slug']) ? trim($data['slug']) : $name;
            
            /**
             * 检查别名是否已被其他标签使用
             */
            $existing = $db->db('metas')
                ->where('slug', $slug)
                ->where('type', 'tag')
                ->where('id', '!=', $id)
                ->first();
            
            if ($existing) {
                Anon_Http_Response::error('标签别名已存在', 400);
                return;
            }
            
            $result = $db->db('metas')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'slug' => $slug,
                ]);
            
            if ($result !== false) {
                $updated = $db->db('metas')->where('id', $id)->first();
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
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('标签 ID 无效', 400);
                return;
            }
            
            $tag = $db->db('metas')
                ->where('id', $id)
                ->where('type', 'tag')
                ->first();
            
            if (!$tag) {
                Anon_Http_Response::error('标签不存在', 404);
                return;
            }
            
            $result = $db->db('metas')->where('id', $id)->delete();
            
            if ($result) {
                Anon_Http_Response::success(null, '删除标签成功');
            } else {
                Anon_Http_Response::error('删除标签失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

