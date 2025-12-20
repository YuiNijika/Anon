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
            $this->db = new Anon_Database();
        }
        return $this->db;
    }
    
    public function init(): void
    {
        Anon_Hook::do_action('capabilities_init', $this->capabilities);
    }
    
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
    
    public function roleCan(string $role, string $capability): bool
    {
        if (!isset($this->capabilities[$role])) {
            return false;
        }
        
        return in_array($capability, $this->capabilities[$role]);
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

