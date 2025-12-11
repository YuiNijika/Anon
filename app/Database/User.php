<?php

/**
 * 用户数据库
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_UserRepository extends Anon_Database_Connection
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取用户信息
     * @param int $uid 用户ID
     * @return array 用户信息
     */
    public function getUserInfo($uid)
    {
        $row = $this->db('users')
            ->select(['uid', 'name', 'email', '`group`'])
            ->where('uid', '=', (int)$uid)
            ->first();

        if (!$row) return null;
        return [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'email' => $row['email'],
            'avatar' => $this->buildAvatar($row['email']),
            'group' => $row['group'],
        ];
    }

    /**
     * 检查用户是否属于指定用户组
     * 
     * @param int $uid 用户ID
     * @param string $group 用户组名称
     * @return bool 返回用户是否属于指定用户组
     */
    public function isUserInGroup($uid, $group)
    {
        return (bool)$this->db('users')
            ->exists()
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', $group)
            ->scalar();
    }

    /**
     * 检查用户是否为管理员
     * 
     * @param int $uid 用户ID
     * @return bool 返回用户是否为管理员
     */
    public function isUserAdmin($uid)
    {
        return (bool)$this->db('users')
            ->exists()
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', 'admin')
            ->scalar();
    }

    /**
     * 检查用户是否为作者
     * 
     * @param int $uid 用户ID
     * @return bool 返回用户是否为作者
     */
    public function isUserAuthor($uid)
    {
        return (bool)$this->db('users')
            ->exists()
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', 'author')
            ->scalar();
    }

    /**
     * 检查用户是否有内容管理权限（管理员或作者）
     * 
     * @param int $uid 用户ID
     * @return bool 返回用户是否有内容管理权限
     */
    public function hasContentManagementPermission($uid)
    {
        return (bool)$this->db('users')
            ->exists()
            ->where('uid', '=', (int)$uid)
            ->whereIn('`group`', ['admin', 'author'])
            ->scalar();
    }

    /**
     * 获取用户权限等级
     * 
     * @param int $uid 用户ID
     * @return int 权限等级：0=普通用户, 1=作者, 2=管理员
     */
    public function getUserPermissionLevel($uid)
    {
        $row = $this->db('users')
            ->select(['`group`'])
            ->where('uid', '=', (int)$uid)
            ->first();
        if (!$row) return 0;
        switch ($row['group']) {
            case 'admin':
                return 2;
            case 'author':
                return 1;
            default:
                return 0;
        }
    }

    /**
     * 通过用户名获取用户信息
     * @param string $name 用户名
     * @return array|bool 返回用户信息数组或false
     */
    public function getUserInfoByName($name)
    {
        $row = $this->db('users')
            ->select(['uid', 'name', 'password', 'email', '`group`'])
            ->where('name', '=', $name)
            ->first();
        if (!$row) return false;
        return [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'password' => $row['password'],
            'email' => $row['email'],
            'group' => $row['group']
        ];
    }

    /**
     * 添加用户（支持不同用户组）
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $group
     * @return bool 添加成功返回true，否则返回false
     */
    public function addUser($name, $email, $password, $group = 'user')
    {
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        $this->conn->begin_transaction();
        try {
            $id = $this->db('users')->insert([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                '`group`' => $group,
            ])->execute();
            $success = $id > 0;
            $this->conn->commit();
            return $success;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * 添加管理员用户
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $group
     * @return bool 添加成功返回true，否则返回false
     */
    public function addAdminUser($name, $email, $password, $group = 'admin')
    {
        return $this->addUser($name, $email, $password, $group);
    }

    /**
     * 修改用户密码
     * @param int $uid 用户ID
     * @param string $newPassword 新密码
     * @return bool 修改成功返回true，否则返回false
     */
    public function updateUserPassword($uid, $newPassword)
    {
        $this->conn->begin_transaction();
        try {
            $affected = $this->db('users')
                ->update(['password' => $newPassword])
                ->where('uid', '=', (int)$uid)
                ->execute();
            $this->conn->commit();
            return $affected > 0;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * 修改用户组
     * @param int $uid 用户ID
     * @param string $group 新的用户组
     * @return bool 修改成功返回true，否则返回false
     */
    public function updateUserGroup($uid, $group)
    {
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        $this->conn->begin_transaction();
        try {
            $affected = $this->db('users')
                ->update(['`group`' => $group])
                ->where('uid', '=', (int)$uid)
                ->execute();
            $this->conn->commit();
            return $affected > 0;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * 检查邮箱是否已被注册
     * 
     * @param string $email 邮箱
     * @return bool 返回邮箱是否已存在
     */
    public function isEmailExists($email)
    {
        return (bool)$this->db('users')
            ->exists()
            ->where('email', '=', $email)
            ->scalar();
    }

    private function buildAvatar($email = null, $size = 640)
    {
        if (!$email) {
            return "https://www.cravatar.cn/avatar/?s={$size}&d=retro";
        }
        $trimmedEmail = trim(strtolower($email));
        $hash = md5($trimmedEmail);
        return "https://www.cravatar.cn/avatar/{$hash}?s={$size}&d=retro";
    }
}
