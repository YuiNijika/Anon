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
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('user_before_get_info', $uid);
        }
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'email', '`group`'])
            ->where('uid', '=', (int)$uid)
            ->first();

        if (!$row) {
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_not_found', $uid);
            }
            return null;
        }
        
        $userInfo = [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'email' => $row['email'],
            'avatar' => $this->buildAvatar($row['email']),
            'group' => $row['group'],
        ];
        
        if (class_exists('Anon_Hook')) {
            $userInfo = Anon_Hook::apply_filters('user_info', $userInfo, $uid);
            Anon_Hook::do_action('user_after_get_info', $userInfo, $uid);
        }
        
        return $userInfo;
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
     * 检查用户是否有管理员或作者的内容管理权限
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
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('user_before_get_info_by_name', $name);
        }
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'password', 'email', '`group`'])
            ->where('name', '=', $name)
            ->first();
        
        if (!$row) {
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_not_found_by_name', $name);
            }
            return false;
        }
        
        $userInfo = [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'password' => $row['password'],
            'email' => $row['email'],
            'group' => $row['group']
        ];
        
        if (class_exists('Anon_Hook')) {
            $userInfo = Anon_Hook::apply_filters('user_info_by_name', $userInfo, $name);
            Anon_Hook::do_action('user_after_get_info_by_name', $userInfo, $name);
        }
        
        return $userInfo;
    }

    /**
     * 添加支持不同用户组的用户
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $group
     * @return bool 添加成功返回true，否则返回false
     */
    public function addUser($name, $email, $password, $group = 'user')
    {
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('user_before_add', $name, $email, $group);
        }
        
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        
        if (class_exists('Anon_Hook')) {
            $name = Anon_Hook::apply_filters('user_name_before_add', $name);
            $email = Anon_Hook::apply_filters('user_email_before_add', $email);
            $password = Anon_Hook::apply_filters('user_password_before_add', $password);
            $group = Anon_Hook::apply_filters('user_group_before_add', $group);
        }
        
        $this->conn->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');
            $id = $this->db('users')->insert([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                '`group`' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
            $success = $id > 0;
            $this->conn->commit();
            
            if ($success && class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_after_add', $id, $name, $email, $group);
            }
            
            return $success;
        } catch (Exception $e) {
            $this->conn->rollback();
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_add_failed', $name, $email, $e);
            }
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
        if (class_exists('Anon_Hook')) {
            $oldGroup = $this->getUserInfo($uid)['group'] ?? null;
            Anon_Hook::do_action('user_before_update_group', $uid, $oldGroup, $group);
        }
        
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        
        if (class_exists('Anon_Hook')) {
            $group = Anon_Hook::apply_filters('user_group_before_update', $group, $uid);
        }
        
        $this->conn->begin_transaction();
        try {
            $affected = $this->db('users')
                ->update(['`group`' => $group])
                ->where('uid', '=', (int)$uid)
                ->execute();
            $this->conn->commit();
            
            if ($affected > 0 && class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_after_update_group', $uid, $oldGroup ?? null, $group);
            }
            
            return $affected > 0;
        } catch (Exception $e) {
            $this->conn->rollback();
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_update_group_failed', $uid, $group, $e);
            }
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
        $avatarUrl = 'https://www.cravatar.cn/avatar';
        if (class_exists('Anon_Env') && Anon_Env::isInitialized()) {
            $avatarUrl = Anon_Env::get('app.avatar', $avatarUrl);
        }
        
        if (!$email) {
            return "{$avatarUrl}/?s={$size}&d=retro";
        }
        $trimmedEmail = trim(strtolower($email));
        $hash = md5($trimmedEmail);
        return "{$avatarUrl}/{$hash}?s={$size}&d=retro";
    }
}
