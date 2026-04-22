<?php
namespace Anon\Modules\Cms\AdminManage;




use Exception;
use Manage;
use Anon\Modules\Database\Database;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Users
{
    private static function mapGroupForOutput(?string $group): string
    {
        if ($group === 'author') {
            return 'editor';
        }
        if ($group === 'editor') {
            return 'author';
        }
        return $group ?? 'user';
    }

    private static function mapGroupForStorage(?string $group): string
    {
        if ($group === 'author') {
            return 'editor';
        }
        if ($group === 'editor') {
            return 'author';
        }
        return $group ?? 'user';
    }

    private static function normalizeUserRow(array $row): array
    {
        $row['group'] = self::mapGroupForOutput($row['group'] ?? ($row['`group`'] ?? null));
        unset($row['`group`'], $row['password']);

        if (!isset($row['avatar']) || $row['avatar'] === null || $row['avatar'] === '') {
            $row['avatar'] = Database::getInstance()->getAvatarByEmail($row['email'] ?? null);
        }

        if (isset($row['created_at'])) {
            $row['created_at'] = strtotime($row['created_at']) ?: $row['created_at'];
        }
        if (isset($row['updated_at'])) {
            $row['updated_at'] = strtotime($row['updated_at']) ?: $row['updated_at'];
        }

        return $row;
    }

    public static function get()
    {
        try {
            $getParams = $_GET;
            $postData = RequestHelper::getInput();
            $data = array_merge($getParams, $postData);

            $page = isset($data['page']) ? (int) $data['page'] : 1;
            $pageSize = isset($data['page_size']) ? (int) $data['page_size'] : 20;
            $search = isset($data['search']) && $data['search'] !== '' ? trim((string) $data['search']) : null;
            $group = isset($data['group']) && $data['group'] !== '' ? trim((string) $data['group']) : null;

            if ($page < 1) $page = 1;
            if ($pageSize < 1) $pageSize = 20;
            if ($pageSize > 100) $pageSize = 100;

            $db = Database::getInstance();
            $query = $db->db('users')->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`', 'created_at', 'updated_at']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('display_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            if ($group) {
                $query->where('`group`', '=', self::mapGroupForStorage($group));
            }

            $countQuery = clone $query;
            $total = $countQuery->count();

            $list = $query
                ->orderBy('uid', 'DESC')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();

            $list = array_map([self::class, 'normalizeUserRow'], is_array($list) ? $list : []);

            ResponseHelper::success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取用户列表成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    public static function create()
    {
        try {
            $data = RequestHelper::getInput();

            $name = isset($data['name']) ? trim((string) $data['name']) : '';
            $email = isset($data['email']) ? trim((string) $data['email']) : '';
            $password = isset($data['password']) ? (string) $data['password'] : '';
            $displayName = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
            $group = isset($data['group']) ? trim((string) $data['group']) : 'user';

            if ($name === '') {
                ResponseHelper::error('用户名不能为空', null, 400);
            }
            if ($email === '') {
                ResponseHelper::error('邮箱不能为空', null, 400);
            }
            if ($password === '') {
                ResponseHelper::error('密码不能为空', null, 400);
            }

            $db = Database::getInstance();

            if ($db->db('users')->where('name', '=', $name)->exists()) {
                ResponseHelper::error('用户名已存在', null, 400);
            }
            if ($db->db('users')->where('email', '=', $email)->exists()) {
                ResponseHelper::error('邮箱已存在', null, 400);
            }

            $storedGroup = self::mapGroupForStorage($group);
            $avatar = $db->getAvatarByEmail($email);

            $uid = $db->db('users')->insert([
                'name' => $name,
                'display_name' => $displayName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                '`group`' => $storedGroup,
                'avatar' => $avatar,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$uid) {
                ResponseHelper::error('创建用户失败', null, 500);
            }

            $user = $db->db('users')
                ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`', 'created_at', 'updated_at'])
                ->where('uid', '=', (int) $uid)
                ->first();

            ResponseHelper::success(self::normalizeUserRow($user ?: []), '创建用户成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    public static function update()
    {
        try {
            $data = RequestHelper::getInput();

            $uid = isset($data['uid']) ? (int) $data['uid'] : 0;
            if ($uid <= 0) {
                ResponseHelper::error('用户 ID 无效', null, 400);
            }

            $db = Database::getInstance();
            $existing = $db->db('users')->where('uid', '=', $uid)->first();
            if (!$existing) {
                ResponseHelper::error('用户不存在', null, 404);
            }

            $update = [];

            if (isset($data['name'])) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    ResponseHelper::error('用户名不能为空', null, 400);
                }
                $exists = $db->db('users')->where('name', '=', $name)->where('uid', '!=', $uid)->exists();
                if ($exists) {
                    ResponseHelper::error('用户名已存在', null, 400);
                }
                $update['name'] = $name;
            }

            if (isset($data['display_name'])) {
                $update['display_name'] = trim((string) $data['display_name']);
            }

            if (isset($data['email'])) {
                $email = trim((string) $data['email']);
                if ($email === '') {
                    ResponseHelper::error('邮箱不能为空', null, 400);
                }
                $exists = $db->db('users')->where('email', '=', $email)->where('uid', '!=', $uid)->exists();
                if ($exists) {
                    ResponseHelper::error('邮箱已存在', null, 400);
                }
                $update['email'] = $email;
                if (!isset($data['avatar'])) {
                    $update['avatar'] = $db->getAvatarByEmail($email);
                }
            }

            if (isset($data['avatar'])) {
                $update['avatar'] = trim((string) $data['avatar']);
            }

            if (isset($data['password']) && $data['password'] !== '') {
                $update['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }

            if (isset($data['group'])) {
                $currentUserId = (int) (RequestHelper::getUserId() ?? 0);
                if ($currentUserId === $uid) {
                    ResponseHelper::error('不能修改自己的用户组', null, 400);
                }
                $update['`group`'] = self::mapGroupForStorage(trim((string) $data['group']));
            }

            if (empty($update)) {
                ResponseHelper::success(self::normalizeUserRow($existing), '无需更新');
            }

            $update['updated_at'] = date('Y-m-d H:i:s');

            $ok = $db->db('users')->where('uid', '=', $uid)->update($update);
            if ($ok === false) {
                ResponseHelper::error('更新用户失败', null, 500);
            }

            $user = $db->db('users')
                ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`', 'created_at', 'updated_at'])
                ->where('uid', '=', $uid)
                ->first();

            ResponseHelper::success(self::normalizeUserRow($user ?: []), '更新用户成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    public static function delete()
    {
        try {
            $data = RequestHelper::getInput();
            $uid = isset($data['uid']) ? (int) $data['uid'] : 0;

            if ($uid <= 0) {
                ResponseHelper::error('用户 ID 无效', null, 400);
            }

            $currentUserId = (int) (RequestHelper::getUserId() ?? 0);
            if ($currentUserId === $uid) {
                ResponseHelper::error('不能删除自己', null, 400);
            }

            $db = Database::getInstance();
            $existing = $db->db('users')->where('uid', '=', $uid)->first();
            if (!$existing) {
                ResponseHelper::error('用户不存在', null, 404);
            }

            $deleted = $db->db('users')->where('uid', '=', $uid)->delete();
            if ($deleted === false) {
                ResponseHelper::error('删除用户失败', null, 500);
            }

            ResponseHelper::success(null, '删除用户成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }
}

