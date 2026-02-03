<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Database_UserRepository extends Anon_Database_Connection
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取用户信息
     * @param int $uid
     * @return array|null
     */
    public function getUserInfo($uid)
    {
        Anon_System_Hook::do_action('user_before_get_info', $uid);
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`'])
            ->where('uid', '=', (int)$uid)
            ->first();

        if (!$row) {
            Anon_System_Hook::do_action('user_not_found', $uid);
            return null;
        }
        
        $avatar = !empty($row['avatar']) ? $row['avatar'] : $this->buildAvatar($row['email']);
        
        $userInfo = [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'display_name' => $row['display_name'] ?? $row['name'],
            'email' => $row['email'],
            'avatar' => $avatar,
            'group' => $row['group'],
        ];
        
        $userInfo = Anon_System_Hook::apply_filters('user_info', $userInfo, $uid);
        Anon_System_Hook::do_action('user_after_get_info', $userInfo, $uid);
        
        return $userInfo;
    }

    /**
     * 检查用户是否属于指定用户组
     * @param int $uid
     * @param string $group
     * @return bool
     */
    public function isUserInGroup($uid, $group)
    {
        return $this->db('users')
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', $group)
            ->exists();
    }

    /**
     * 检查用户是否为管理员
     * @param int $uid
     * @return bool
     */
    public function isUserAdmin($uid)
    {
        return $this->db('users')
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', 'admin')
            ->exists();
    }

    /**
     * 检查用户是否为作者
     * @param int $uid
     * @return bool
     */
    public function isUserAuthor($uid)
    {
        return $this->db('users')
            ->where('uid', '=', (int)$uid)
            ->where('`group`', '=', 'author')
            ->exists();
    }

    /**
     * 检查用户是否有内容管理权限
     * @param int $uid
     * @return bool
     */
    public function hasContentManagementPermission($uid)
    {
        return $this->db('users')
            ->where('uid', '=', (int)$uid)
            ->whereIn('`group`', ['admin', 'author'])
            ->exists();
    }

    /**
     * 获取用户权限等级
     * @param int $uid
     * @return int 0=普通用户, 1=作者, 2=管理员
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
     * @param string $name
     * @return array|false
     */
    public function getUserInfoByName($name)
    {
        Anon_System_Hook::do_action('user_before_get_info_by_name', $name);
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'password', 'email', 'avatar', '`group`'])
            ->where('name', '=', $name)
            ->first();
        
        if (!$row) {
            Anon_System_Hook::do_action('user_not_found_by_name', $name);
            return false;
        }
        
        $avatar = !empty($row['avatar']) ? $row['avatar'] : $this->buildAvatar($row['email']);
        
        $userInfo = [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'display_name' => $row['display_name'] ?? $row['name'],
            'password' => $row['password'],
            'email' => $row['email'],
            'avatar' => $avatar,
            'group' => $row['group']
        ];
        
        $userInfo = Anon_System_Hook::apply_filters('user_info_by_name', $userInfo, $name);
        Anon_System_Hook::do_action('user_after_get_info_by_name', $userInfo, $name);
        
        return $userInfo;
    }

    /**
     * 通过邮箱获取用户信息
     * @param string $email
     * @return array|false
     */
    public function getUserInfoByEmail($email)
    {
        Anon_System_Hook::do_action('user_before_get_info_by_email', $email);
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'password', 'email', 'avatar', '`group`'])
            ->where('email', '=', $email)
            ->first();
        
        if (!$row) {
            Anon_System_Hook::do_action('user_not_found_by_email', $email);
            return false;
        }
        
        $avatar = !empty($row['avatar']) ? $row['avatar'] : $this->buildAvatar($row['email']);
        
        $userInfo = [
            'uid' => $row['uid'],
            'name' => $row['name'],
            'display_name' => $row['display_name'] ?? $row['name'],
            'password' => $row['password'],
            'email' => $row['email'],
            'avatar' => $avatar,
            'group' => $row['group']
        ];
        
        $userInfo = Anon_System_Hook::apply_filters('user_info_by_email', $userInfo, $email);
        Anon_System_Hook::do_action('user_after_get_info_by_email', $userInfo, $email);
        
        return $userInfo;
    }

    /**
     * 添加用户
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $group
     * @param string|null $displayName
     * @param string|null $avatar
     * @return int|false
     */
    public function addUser($name, $email, $password, $group = 'user', $displayName = null, $avatar = null)
    {
        Anon_System_Hook::do_action('user_before_add', $name, $email, $group);
        
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        
        $name = Anon_System_Hook::apply_filters('user_name_before_add', $name);
        $email = Anon_System_Hook::apply_filters('user_email_before_add', $email);
        $password = Anon_System_Hook::apply_filters('user_password_before_add', $password);
        $group = Anon_System_Hook::apply_filters('user_group_before_add', $group);
        
        $this->conn->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');
            $insertData = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                '`group`' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            
            if ($displayName !== null) {
                $insertData['display_name'] = $displayName;
            }
            
            if ($avatar !== null) {
                $insertData['avatar'] = $avatar;
            }
            
            $id = $this->db('users')->insert($insertData);
            $success = $id > 0;
            $this->conn->commit();
            
            if ($success) {
                Anon_System_Hook::do_action('user_after_add', $id, $name, $email, $group);
            }
            
            return $success ? $id : false;
    } catch (Exception $e) {
        $this->conn->rollback();
        Anon_System_Hook::do_action('user_add_failed', $name, $email, $e);
        throw $e;
    }
    }

    /**
     * 添加管理员用户
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $group
     * @return int|false
     */
    public function addAdminUser($name, $email, $password, $group = 'admin')
    {
        return $this->addUser($name, $email, $password, $group);
    }

    /**
     * 修改用户密码
     * @param int $uid
     * @param string $newPassword
     * @return bool
     */
    public function updateUserPassword($uid, $newPassword)
    {
        $this->conn->begin_transaction();
        try {
            $affected = $this->db('users')
                ->where('uid', '=', (int)$uid)
                ->update(['password' => $newPassword]);
            $this->conn->commit();
            return $affected > 0;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * 修改用户组
     * @param int $uid
     * @param string $group
     * @return bool
     */
    public function updateUserGroup($uid, $group)
    {
        $oldGroup = $this->getUserInfo($uid)['group'] ?? null;
        Anon_System_Hook::do_action('user_before_update_group', $uid, $oldGroup, $group);
        
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups)) {
            throw new Exception('无效的用户组');
        }
        
        $group = Anon_System_Hook::apply_filters('user_group_before_update', $group, $uid);
        
        $this->conn->begin_transaction();
        try {
            $affected = $this->db('users')
                ->where('uid', '=', (int)$uid)
                ->update(['`group`' => $group]);
            $this->conn->commit();
            
            if ($affected > 0) {
                Anon_System_Hook::do_action('user_after_update_group', $uid, $oldGroup ?? null, $group);
            }
            
            return $affected > 0;
        } catch (Exception $e) {
            $this->conn->rollback();
            Anon_System_Hook::do_action('user_update_group_failed', $uid, $group, $e);
            throw $e;
        }
    }

    /**
     * 检查邮箱是否已存在
     * @param string $email
     * @return bool
     */
    public function isEmailExists($email)
    {
        return $this->db('users')
            ->where('email', '=', $email)
            ->exists();
    }

    /**
     * 记录用户登录
     * @param int|null $uid
     * @param string|null $username
     * @param bool $status
     * @param string|null $message
     * @return bool
     */
    public function logLogin($uid = null, $username = null, $status = true, $message = null)
    {
        try {
            if ($uid === null && $username !== null && $username !== '') {
                $userInfo = $this->getUserInfoByName($username);
                if ($userInfo && isset($userInfo['uid'])) {
                    $uid = (int)$userInfo['uid'];
                }
            }
            
            $ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = '0.0.0.0';
            }
            
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
            if ($domain !== null) {
                $domain = mb_substr(trim($domain), 0, 255, 'UTF-8');
            }
            
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent !== null) {
                $userAgent = mb_substr(trim($userAgent), 0, 500, 'UTF-8');
            }
            
            if ($username !== null) {
                $username = mb_substr(trim($username), 0, 255, 'UTF-8');
            }
            
            if ($message !== null) {
                $message = mb_substr(trim($message), 0, 255, 'UTF-8');
            }
            
            $id = $this->db('login_logs')->insert([
                'uid' => $uid,
                'username' => $username,
                'ip' => $ip,
                'domain' => $domain,
                'user_agent' => $userAgent,
                'status' => $status ? 1 : 0,
                'message' => $message,
            ]);
            
            return $id > 0;
        } catch (Exception $e) {
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::error("Failed to log login: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * 清理数组中的null值
     * @param array $data
     * @return array
     */
    private function cleanNullInArray(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $cleaned[$key] = '';
            } elseif (is_array($value)) {
                $cleaned[$key] = $this->cleanNullInArray($value);
            } else {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }

    /**
     * 获取用户登录记录
     * @param int $uid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLoginLogs($uid, $limit = 20, $offset = 0)
    {
        $uid = (int)$uid;
        $limit = max(1, min(100, (int)$limit));
        $offset = max(0, (int)$offset);
        
        $logs = $this->db('login_logs')
            ->select(['id', 'uid', 'username', 'ip', 'domain', 'user_agent', 'status', 'message', 'created_at'])
            ->where('uid', '=', $uid)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        
        return array_map([$this, 'cleanNullInArray'], $logs);
    }

    /**
     * 获取IP登录记录
     * @param string $ip
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLoginLogsByIp($ip, $limit = 20, $offset = 0)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }
        
        $limit = max(1, min(100, (int)$limit));
        $offset = max(0, (int)$offset);
        
        $logs = $this->db('login_logs')
            ->select(['id', 'uid', 'username', 'ip', 'domain', 'user_agent', 'status', 'message', 'created_at'])
            ->where('ip', '=', $ip)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        
        return array_map([$this, 'cleanNullInArray'], $logs);
    }

    /**
     * 清理过期的登录记录
     * @param int $days
     * @return int
     */
    public function cleanExpiredLoginLogs($days = 90)
    {
        try {
            $days = max(1, (int)$days);
            $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $affected = $this->db('login_logs')
                ->where('created_at', '<', $date)
                ->delete();
            
            return $affected;
        } catch (Exception $e) {
            if (Anon_Debug::isEnabled()) {
                Anon_Debug::error("Failed to clean expired login logs: " . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * 获取用户列表
     * @param int $page
     * @param int $pageSize
     * @param string|null $search
     * @param string|null $group
     * @return array
     */
    public function getList($page = 1, $pageSize = 20, $search = null, $group = null)
    {
        $baseQuery = $this->db('users');
        
        if ($search) {
            $baseQuery->where(function($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%')
                      ->orWhere('display_name', 'LIKE', '%' . $search . '%');
            });
        }
        
        if ($group) {
            $baseQuery->where('group', $group);
        }
        
        $countQuery = $this->db('users');
        if ($search) {
            $countQuery->where(function($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%')
                      ->orWhere('display_name', 'LIKE', '%' . $search . '%');
            });
        }
        if ($group) {
            $countQuery->where('group', $group);
        }
        $total = $countQuery->count();
        
        $users = $baseQuery
            ->orderBy('created_at', 'DESC')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get();
        
        foreach ($users as &$user) {
            unset($user['password']);
            if (isset($user['email']) && (!isset($user['avatar']) || $user['avatar'] === null || $user['avatar'] === '')) {
                $user['avatar'] = $this->buildAvatar($user['email']);
            }
            if (isset($user['created_at']) && is_string($user['created_at'])) {
                $user['created_at'] = strtotime($user['created_at']);
            }
            if (isset($user['updated_at']) && is_string($user['updated_at'])) {
                $user['updated_at'] = strtotime($user['updated_at']);
            }
        }
        
        return [
            'list' => $users,
            'total' => $total,
        ];
    }

    /**
     * 检查用户名是否已存在
     * @param string $name
     * @param int $excludeUid
     * @return array|null
     */
    public function checkNameExists($name, $excludeUid = 0)
    {
        $query = $this->db('users')->where('name', $name);
        if ($excludeUid > 0) {
            $query->where('uid', '!=', $excludeUid);
        }
        return $query->first();
    }

    /**
     * 检查邮箱是否已存在
     * @param string $email
     * @param int $excludeUid
     * @return array|null
     */
    public function checkEmailExistsExclude($email, $excludeUid = 0)
    {
        $query = $this->db('users')->where('email', $email);
        if ($excludeUid > 0) {
            $query->where('uid', '!=', $excludeUid);
        }
        return $query->first();
    }

    /**
     * 更新用户信息
     * @param int $uid
     * @param array $data
     * @return bool
     */
    public function updateUser($uid, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db('users')
            ->where('uid', $uid)
            ->update($data) !== false;
    }

    /**
     * 删除用户
     * @param int $uid
     * @return bool
     */
    public function deleteUser($uid)
    {
        return $this->db('users')->where('uid', $uid)->delete();
    }

    /**
     * 获取用户信息（管理端）
     * @param int $uid
     * @return array|null
     */
    public function getUserInfoForAdmin($uid)
    {
        $user = $this->db('users')->where('uid', $uid)->first();
        if ($user) {
            unset($user['password']);
            if (isset($user['email']) && (!isset($user['avatar']) || $user['avatar'] === null || $user['avatar'] === '')) {
                $user['avatar'] = $this->buildAvatar($user['email']);
            }
            if (isset($user['created_at']) && is_string($user['created_at'])) {
                $user['created_at'] = strtotime($user['created_at']);
            }
            if (isset($user['updated_at']) && is_string($user['updated_at'])) {
                $user['updated_at'] = strtotime($user['updated_at']);
            }
        }
        return $user;
    }

    /**
     * @param string|null $email
     * @param int $size
     * @return string
     */
    public function getAvatarByEmail($email = null, $size = 640)
    {
        return $this->buildAvatar($email, $size);
    }

    private function buildAvatar($email = null, $size = 640)
    {
        $avatarUrl = 'https://www.cravatar.cn/avatar';
        if (Anon_System_Env::isInitialized()) {
            $avatarUrl = Anon_System_Env::get('app.avatar', $avatarUrl);
        }
        
        if (!$email) {
            return "{$avatarUrl}/?s={$size}&d=retro";
        }
        $trimmedEmail = trim(strtolower($email));
        $hash = md5($trimmedEmail);
        return "{$avatarUrl}/{$hash}?s={$size}&d=retro";
    }
}
