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
     * @var array 角色配置
     */
    private $capabilities = [
        'admin' => [
            'manage_options',
            'manage_users',
            'manage_plugins',
            'manage_widgets',
            'edit_posts',
            'delete_posts',
            'publish_posts',
        ],
        'editor' => [
            'edit_posts',
            'delete_posts',
            'publish_posts',
        ],
        'author' => [
            'edit_own_posts',
            'delete_own_posts',
            'publish_own_posts',
        ],
        'user' => [
            'read',
        ],
    ];
    
    /**
     * 构造函数
     */
    private function __construct()
    {
    }
    
    /**
     * 获取实例
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
     * 初始化
     * @return void
     */
    public function init(): void
    {
        // 允许通过 Hook 修改角色配置
        $this->capabilities = Anon_System_Hook::apply_filters('anon_auth_capabilities', $this->capabilities);
        
        // 允许通过 Hook 移除权限
        // 格式 ['role' => ['capability1', 'capability2']] 或 ['role' => 'capability']
        $removeList = Anon_System_Hook::apply_filters('anon_auth_capabilities_remove', []);
        if (!empty($removeList) && is_array($removeList)) {
            foreach ($removeList as $role => $capabilities) {
                if (!is_string($role)) {
                    continue;
                }
                
                if (is_string($capabilities)) {
                    // 单个权限字符串
                    $this->removeCapability($role, $capabilities);
                } elseif (is_array($capabilities)) {
                    // 权限数组
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
     * 添加能力
     * @param string $role 角色
     * @param string $capability 权限标识
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
     * 移除能力
     * @param string $role 角色
     * @param string $capability 权限标识
     */
    public function removeCapability(string $role, string $capability): void
    {
        if (isset($this->capabilities[$role])) {
            $this->capabilities[$role] = array_diff($this->capabilities[$role], [$capability]);
        }
    }
    
    /**
     * 检查用户权限
     * @param int $userId 用户ID
     * @param string $capability 权限标识
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
     * 检查角色权限
     * @param string $role 角色
     * @param string $capability 权限标识
     * @return bool
     */
    public function roleCan(string $role, string $capability): bool
    {
        if (!isset($this->capabilities[$role])) {
            return false;
        }
        
        // 直接匹配
        if (in_array($capability, $this->capabilities[$role])) {
            return true;
        }
        
        // 支持资源级权限：如果请求 'user:read'，检查是否有 'user:*' 或 '*:read' 或 '*:*'
        if (strpos($capability, ':') !== false) {
            list($resource, $action) = explode(':', $capability, 2);
            
            // 检查通配符权限
            $wildcardChecks = [
                "{$resource}:*",  // 资源的所有操作
                "*:{$action}",    // 所有资源的该操作
                '*:*'             // 所有权限
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
     * 检查当前用户权限
     * @param string $capability 权限标识
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
     * 要求权限
     * @param string $capability 权限标识
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
     * 获取角色能力
     * @param string $role 角色
     * @return array
     */
    public function getCaps(string $role): array
    {
        return $this->capabilities[$role] ?? [];
    }
    
    /**
     * 获取所有配置
     * @return array
     */
    public function all(): array
    {
        return $this->capabilities;
    }
}

