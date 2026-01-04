<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Capability
{
    private static $instance = null;
    private $db = null;
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
    
    private function __construct()
    {
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getDatabase(): Anon_Database
    {
        if ($this->db === null) {
            $this->db = Anon_Database::getInstance();
        }
        return $this->db;
    }
    
    public function init(): void
    {
        Anon_Hook::do_action('capabilities_init', $this->capabilities);
    }
    
    /**
     * 添加权限（支持资源级权限，如 'user:read', 'post:edit'）
     * @param string $role 角色
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'（如 'user:read'）
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
    
    public function removeCapability(string $role, string $capability): void
    {
        if (isset($this->capabilities[$role])) {
            $this->capabilities[$role] = array_diff($this->capabilities[$role], [$capability]);
        }
    }
    
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
     * 检查角色是否有权限（支持资源级权限）
     * @param string $role 角色
     * @param string $capability 权限标识，支持格式：'capability' 或 'resource:action'（如 'user:read'）
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
    
    public function currentUserCan(string $capability): bool
    {
        if (!Anon_Check::isLoggedIn()) {
            return false;
        }
        
        $userId = Anon_RequestHelper::getUserId();
        if (!$userId) {
            return false;
        }
        
        return $this->userCan($userId, $capability);
    }
    
    public function requireCapability(string $capability): void
    {
        if (!$this->currentUserCan($capability)) {
            Anon_Common::Header(403);
            Anon_ResponseHelper::forbidden('权限不足');
            exit;
        }
    }
    
    public function getCaps(string $role): array
    {
        return $this->capabilities[$role] ?? [];
    }
    
    public function all(): array
    {
        return $this->capabilities;
    }
}

