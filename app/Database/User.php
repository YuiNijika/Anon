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
     * 通过邮箱获取用户信息
     * @param string $email 邮箱地址
     * @return array|bool 返回用户信息数组或false
     */
    public function getUserInfoByEmail($email)
    {
        if (class_exists('Anon_Hook')) {
            Anon_Hook::do_action('user_before_get_info_by_email', $email);
        }
        
        $row = $this->db('users')
            ->select(['uid', 'name', 'password', 'email', '`group`'])
            ->where('email', '=', $email)
            ->first();
        
        if (!$row) {
            if (class_exists('Anon_Hook')) {
                Anon_Hook::do_action('user_not_found_by_email', $email);
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
            $userInfo = Anon_Hook::apply_filters('user_info_by_email', $userInfo, $email);
            Anon_Hook::do_action('user_after_get_info_by_email', $userInfo, $email);
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

    /**
     * 记录用户登录
     * @param int|null $uid 用户ID，登录失败时为null
     * @param string|null $username 用户名
     * @param bool $status 登录状态：true=成功，false=失败
     * @param string|null $message 登录信息
     * @return bool 记录成功返回true，否则返回false
     */
    public function logLogin($uid = null, $username = null, $status = true, $message = null)
    {
        try {
            // 验证IP地址格式，无效IP设为默认值
            $ip = Anon_Common::GetClientIp() ?? '0.0.0.0';
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = '0.0.0.0';
            }
            
            // 获取并清理域名，优先使用HTTP_HOST，其次使用SERVER_NAME
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
            if ($domain !== null) {
                $domain = mb_substr(trim($domain), 0, 255, 'UTF-8');
            }
            
            // 清理并限制User-Agent长度最大500字符
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent !== null) {
                $userAgent = mb_substr(trim($userAgent), 0, 500, 'UTF-8');
            }
            
            // 防止XSS清理用户名并限制长度最大255字符
            if ($username !== null) {
                $username = mb_substr(trim($username), 0, 255, 'UTF-8');
            }
            
            // 防止XSS清理消息并限制长度最大255字符
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
            ])->execute();
            
            return $id > 0;
        } catch (Exception $e) {
            // 记录失败不影响登录流程，仅记录错误日志
            if (class_exists('Anon_Debug')) {
                Anon_Debug::error("Failed to log login: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * 清理数组中的null值
     * @param array $data 原始数据
     * @return array 清理后的数据
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
     * @param int $uid 用户ID
     * @param int $limit 限制数量（最大100）
     * @param int $offset 偏移量
     * @return array 登录记录数组
     */
    public function getLoginLogs($uid, $limit = 20, $offset = 0)
    {
        // 参数验证和限制，limit范围1-100，offset不能为负数
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
        
        // 清理null值
        return array_map([$this, 'cleanNullInArray'], $logs);
    }

    /**
     * 获取IP登录记录
     * @param string $ip IP地址
     * @param int $limit 限制数量（最大100）
     * @param int $offset 偏移量
     * @return array 登录记录数组
     */
    public function getLoginLogsByIp($ip, $limit = 20, $offset = 0)
    {
        // 验证IP地址格式，无效IP返回空数组
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }
        
        // 参数验证和限制，limit范围1-100，offset不能为负数
        $limit = max(1, min(100, (int)$limit));
        $offset = max(0, (int)$offset);
        
        $logs = $this->db('login_logs')
            ->select(['id', 'uid', 'username', 'ip', 'domain', 'user_agent', 'status', 'message', 'created_at'])
            ->where('ip', '=', $ip)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        
        // 清理null值
        return array_map([$this, 'cleanNullInArray'], $logs);
    }

    /**
     * 清理过期的登录记录
     * @param int $days 保留天数，默认90天
     * @return int 删除的记录数
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
            // 清理失败不影响主流程，仅记录错误日志
            if (class_exists('Anon_Debug')) {
                Anon_Debug::error("Failed to clean expired login logs: " . $e->getMessage());
            }
            return 0;
        }
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
