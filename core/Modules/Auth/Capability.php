<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 权限系统
 */
class Anon_Auth_Capability
{
    /**
     * @var Anon_Auth_Capability|null 实例
     */
    private static $instance = null;

    /**
     * @var Anon_Database|null 数据库实例
     */
    private $db = null;

    /**
     * @var array 角色权限配置
     */
    private $capabilities = [
        'admin' => [
            'manage_options',
            'manage_users',
            'manage_plugins',
            'manage_widgets',
            'post:read',
            'post:create',
            'post:edit',
            'post:delete',
            'post:publish',
            'category:read',
            'category:create',
            'category:edit',
            'category:delete',
            'tag:read',
            'tag:create',
            'tag:edit',
            'tag:delete',
            'attachment:read',
            'attachment:upload',
            'attachment:delete',
            'user:read',
            'user:create',
            'user:edit',
            'user:delete',
            'settings:read',
            'settings:edit',
            'statistics:read',
            'edit_posts',
            'delete_posts',
            'publish_posts',
        ],
        'editor' => [
            'post:read',
            'post:create',
            'post:edit',
            'post:delete',
            'post:publish',
            'category:read',
            'category:create',
            'category:edit',
            'category:delete',
            'tag:read',
            'tag:create',
            'tag:edit',
            'tag:delete',
            'attachment:read',
            'attachment:upload',
            'attachment:delete',
            'statistics:read',
            'edit_posts',
            'delete_posts',
            'publish_posts',
        ],
        'author' => [
            'post:read',
            'post:create',
            'edit_own_posts',
            'delete_own_posts',
            'publish_own_posts',
            'attachment:read',
            'attachment:upload',
        ],
        'user' => [
            'read',
            'post:read',
            'attachment:read',
        ],
    ];
    
    private function __construct()
    {
    }
    
    /**
     * 获取单例实例
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 获取数据库实例
     * @return Anon_Database
     */
    private function getDatabase(): Anon_Database
    {
        if ($this->db === null) {
            $this->db = Anon_Database::getInstance();
        }
        return $this->db;
    }
    
    /**
     * 初始化权限系统
     * 通过 Hook 允许修改和移除权限配置
     * @return void
     */
    public function init(): void
    {
        $this->capabilities = Anon_System_Hook::apply_filters('anon_auth_capabilities', $this->capabilities);
        
        $removeList = Anon_System_Hook::apply_filters('anon_auth_capabilities_remove', []);
        if (!empty($removeList) && is_array($removeList)) {
            foreach ($removeList as $role => $capabilities) {
                if (!is_string($role)) {
                    continue;
                }
                
                if (is_string($capabilities)) {
                    $this->removeCapability($role, $capabilities);
                } elseif (is_array($capabilities)) {
                    foreach ($capabilities as $capability) {
                        if (is_string($capability)) {
                            $this->removeCapability($role, $capability);
                        }
                    }
                }
            }
        }
        
        Anon_System_Hook::do_action('capabilities_init', $this->capabilities);
    }
    
    /**
     * 为角色添加权限
     * @param string $role 角色名称
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'
     * @return void
     */
    public function addCapability(string $role, string $capability): void
    {
        if (!isset($this->capabilities[$role])) {
            $this->capabilities[$role] = [];
        }
        
        if (!in_array($capability, $this->capabilities[$role])) {
            $this->capabilities[$role][] = $capability;
        }
    }
    
    /**
     * 移除角色权限
     * @param string $role 角色名称
     * @param string $capability 权限标识
     * @return void
     */
    public function removeCapability(string $role, string $capability): void
    {
        if (isset($this->capabilities[$role])) {
            $this->capabilities[$role] = array_diff($this->capabilities[$role], [$capability]);
        }
    }
    
    /**
     * 检查指定用户是否拥有权限
     * @param int $userId 用户ID
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'
     * @return bool
     */
    public function userCan(int $userId, string $capability): bool
    {
        $db = $this->getDatabase();
        $user = $db->getUserInfo($userId);
        
        if (!$user) {
            return false;
        }
        
        $role = $user['group'] ?? 'user';
        return $this->roleCan($role, $capability);
    }
    
    /**
     * 检查角色是否拥有权限
     * 支持资源级权限和通配符匹配，如 'user:read' 可匹配 'user:*'、'*:read' 或 '*:*'
     * @param string $role 角色名称
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'
     * @return bool
     */
    public function roleCan(string $role, string $capability): bool
    {
        if (!isset($this->capabilities[$role])) {
            return false;
        }
        
        if (in_array($capability, $this->capabilities[$role])) {
            return true;
        }
        
        if (strpos($capability, ':') !== false) {
            list($resource, $action) = explode(':', $capability, 2);
            
            $wildcardChecks = [
                "{$resource}:*",
                "*:{$action}",
                '*:*'
            ];
            
            foreach ($wildcardChecks as $wildcard) {
                if (in_array($wildcard, $this->capabilities[$role])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 检查当前登录用户是否拥有权限
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'
     * @return bool
     */
    public function currentUserCan(string $capability): bool
    {
        if (!Anon_Check::isLoggedIn()) {
            return false;
        }
        
        $userId = Anon_Http_Request::getUserId();
        if (!$userId) {
            return false;
        }
        
        return $this->userCan($userId, $capability);
    }
    
    /**
     * 要求当前用户必须拥有指定权限，否则返回 403 错误
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'
     * @return void
     */
    public function requireCapability(string $capability): void
    {
        if (!$this->currentUserCan($capability)) {
            Anon_Common::Header(403);
            Anon_Http_Response::forbidden('权限不足');
            exit;
        }
    }
    
    /**
     * 获取指定角色的所有权限
     * @param string $role 角色名称
     * @return array
     */
    public function getCaps(string $role): array
    {
        return $this->capabilities[$role] ?? [];
    }
    
    /**
     * 获取所有角色的权限配置
     * @return array
     */
    public function all(): array
    {
        return $this->capabilities;
    }
}

