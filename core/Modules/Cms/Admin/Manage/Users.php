<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_Users
{
    /**
     * 获取用户列表
     * @return void
     */
    public static function get()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $userRepo = new Anon_Database_UserRepository();
            
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $search = isset($data['search']) ? trim($data['search']) : null;
            $group = isset($data['group']) ? trim($data['group']) : null;
            
            if ($group === 'editor') {
                $group = 'author';
            }
            
            $result = $userRepo->getList($page, $pageSize, $search, $group);
            
            if (!empty($result['list']) && is_array($result['list'])) {
                foreach ($result['list'] as &$user) {
                    if (isset($user['group']) && $user['group'] === 'author') {
                        $user['group'] = 'editor';
                    }
                }
                unset($user);
            }
            
            Anon_Http_Response::success([
                'list' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取用户列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 创建用户
     * @return void
     */
    public static function create()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            
            if (empty($data['name'])) {
                Anon_Http_Response::error('用户名不能为空', 400);
                return;
            }
            
            if (empty($data['email'])) {
                Anon_Http_Response::error('邮箱不能为空', 400);
                return;
            }
            
            if (empty($data['password'])) {
                Anon_Http_Response::error('密码不能为空', 400);
                return;
            }
            
            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];
            $displayName = isset($data['display_name']) ? trim($data['display_name']) : null;
            $group = isset($data['group']) ? trim($data['group']) : 'user';
            $avatar = isset($data['avatar']) ? trim($data['avatar']) : null;
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Anon_Http_Response::error('邮箱格式不正确', 400);
                return;
            }
            
            if (strlen($password) < 6) {
                Anon_Http_Response::error('密码长度至少6位', 400);
                return;
            }
            
            $validGroups = ['admin', 'editor', 'user'];
            if (!in_array($group, $validGroups)) {
                Anon_Http_Response::error('用户组无效', 400);
                return;
            }
            
            $userRepo = new Anon_Database_UserRepository();
            
            if ($userRepo->getUserInfoByName($name) || $userRepo->isEmailExists($email)) {
                if ($userRepo->getUserInfoByName($name)) {
                    Anon_Http_Response::error('用户名已存在', 400);
                } else {
                    Anon_Http_Response::error('邮箱已存在', 400);
                }
                return;
            }
            
            $dbGroup = $group === 'editor' ? 'author' : $group;
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $uid = $userRepo->addUser($name, $email, $hashedPassword, $dbGroup, $displayName, $avatar);
            
            if ($uid) {
                $user = $userRepo->getUserInfoForAdmin($uid);
                Anon_Http_Response::success($user, '创建用户成功');
            } else {
                Anon_Http_Response::error('创建用户失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 更新用户
     * @return void
     */
    public static function update()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $userRepo = new Anon_Database_UserRepository();
            $uid = isset($data['uid']) ? (int)$data['uid'] : 0;
            
            if ($uid <= 0) {
                Anon_Http_Response::error('用户 ID 无效', 400);
                return;
            }
            
            $user = $userRepo->getUserInfo($uid);
            if (!$user) {
                Anon_Http_Response::error('用户不存在', 404);
                return;
            }
            
            $updateData = [];
            
            if (isset($data['name']) && !empty($data['name'])) {
                $name = trim($data['name']);
                if ($userRepo->checkNameExists($name, $uid)) {
                    Anon_Http_Response::error('用户名已存在', 400);
                    return;
                }
                $updateData['name'] = $name;
            }
            
            if (isset($data['email']) && !empty($data['email'])) {
                $email = trim($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Anon_Http_Response::error('邮箱格式不正确', 400);
                    return;
                }
                if ($userRepo->checkEmailExistsExclude($email, $uid)) {
                    Anon_Http_Response::error('邮箱已存在', 400);
                    return;
                }
                $updateData['email'] = $email;
            }
            
            if (isset($data['display_name'])) {
                $updateData['display_name'] = !empty($data['display_name']) ? trim($data['display_name']) : null;
            }
            
            if (isset($data['group'])) {
                $currentUserId = Anon_Http_Request::getUserId();
                $group = trim($data['group']);
                $dbGroup = $group === 'editor' ? 'author' : $group;
                
                // 检查用户组是否发生变化
                $currentGroup = $user['group'] ?? null;
                $currentGroupForCheck = $currentGroup === 'author' ? 'editor' : $currentGroup;
                
                // 只有当用户组值发生变化时才处理
                if ($currentGroupForCheck !== $group) {
                    if ($uid === $currentUserId) {
                        Anon_Http_Response::error('不能更改自己的用户组', 400);
                        return;
                    }
                    
                    $validGroups = ['admin', 'editor', 'user'];
                    if (!in_array($group, $validGroups)) {
                        Anon_Http_Response::error('用户组无效', 400);
                        return;
                    }
                    
                    $updateData['group'] = $dbGroup;
                }
            }
            
            if (isset($data['avatar'])) {
                $updateData['avatar'] = !empty($data['avatar']) ? trim($data['avatar']) : null;
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $password = $data['password'];
                if (strlen($password) < 6) {
                    Anon_Http_Response::error('密码长度至少6位', 400);
                    return;
                }
                $updateData['password'] = $password;
            }
            
            if (empty($updateData)) {
                Anon_Http_Response::error('没有需要更新的数据', 400);
                return;
            }
            
            $result = $userRepo->updateUser($uid, $updateData);
            
            if ($result) {
                $updated = $userRepo->getUserInfoForAdmin($uid);
                if ($updated && isset($updated['group']) && $updated['group'] === 'author') {
                    $updated['group'] = 'editor';
                }
                Anon_Http_Response::success($updated, '更新用户成功');
            } else {
                Anon_Http_Response::error('更新用户失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除用户
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $userRepo = new Anon_Database_UserRepository();
            $uid = isset($data['uid']) ? (int)$data['uid'] : 0;
            
            if ($uid <= 0) {
                Anon_Http_Response::error('用户 ID 无效', 400);
                return;
            }
            
            $user = $userRepo->getUserInfo($uid);
            if (!$user) {
                Anon_Http_Response::error('用户不存在', 404);
                return;
            }
            
            $currentUserId = Anon_Http_Request::getUserId();
            if ($uid === $currentUserId) {
                Anon_Http_Response::error('不能删除当前登录用户', 400);
                return;
            }
            
            $result = $userRepo->deleteUser($uid);
            
            if ($result) {
                Anon_Http_Response::success(null, '删除用户成功');
            } else {
                Anon_Http_Response::error('删除用户失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

