<?php
namespace Anon\Modules\Database;




use Anon\WidgetsConnection;
use BadMethodCallException;
use InvalidArgumentException;
use RuntimeException;

use Anon\Modules\Common;
use Anon\Modules\Debug;
use Anon\Modules\System\Env;
use Anon\Widgets\Connection;
use Throwable;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Database
{
    private static ?self $instance = null;

    protected array $instances = [];

    private function __construct()
    {
        $this->bootstrapInstances();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }

    private static array $bootstrapedClasses = [];
    private static bool $bootstraped = false;

    protected function bootstrapInstances(): void
    {
        if (self::$bootstraped) {
            $this->instances = self::$bootstrapedClasses;
            return;
        }

        $classesToCheck = [];
        foreach (get_declared_classes() as $class) {
            if (preg_match('/^Anon\\\\Modules\\\\Database\\\\([A-Za-z0-9_]+)(Repository|Service)$/', $class, $m)) {
                $classesToCheck[] = ['class' => $class, 'matches' => $m];
            }
        }

        foreach ($classesToCheck as $item) {
            $class = $item['class'];
            $m = $item['matches'];

            if (!isset(self::$bootstrapedClasses[$class])) {
                $obj = new $class();
                $short = $m[1] . $m[2];
                $camel = lcfirst($short);

                self::$bootstrapedClasses[$class] = $obj;
                self::$bootstrapedClasses[$short] = $obj;
                self::$bootstrapedClasses[$camel] = $obj;
            }
        }

        $this->instances = self::$bootstrapedClasses;
        self::$bootstraped = true;
    }

    private static ?Connection $connection = null;

    public static function getQueryCount(): int
    {
        $connection = self::getInstance()->getConnection();
        return method_exists($connection, 'getQueryCount') ? $connection->getQueryCount() : 0;
    }

    private function getConnection(): Connection
    {
        if (self::$connection === null) {
            self::$connection = Connection::getInstance();
        }
        return self::$connection;
    }

    public function db(string $table): QueryBuilder
    {
        if ($table === '') {
            throw new InvalidArgumentException('表名不能为空且必须是字符串');
        }

        $tablePrefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';
        $fullTableName = $tablePrefix . $table;

        $connection = $this->getConnection();
        $queryBuilder = new QueryBuilder($connection, $fullTableName);
        return $queryBuilder;
    }

    public function shard(string $table, $shardKey, string $strategy = 'id'): QueryBuilder
    {
        if (class_exists(Sharding::class)) {
            $table = Sharding::getTableName($table, $shardKey, $strategy);
        }
        return $this->db($table);
    }

    public function getShardTables(string $table): array
    {
        if (class_exists(Sharding::class)) {
            return Sharding::getAllShardTables($table);
        }
        return [$table];
    }

    public function batchInsert(string $table, array $data, int $batchSize = 1000): int
    {
        return $this->db($table)->batchInsert($data, $batchSize);
    }

    public function batchUpdate(string $table, array $data, string $keyColumn = 'id', int $batchSize = 1000): int
    {
        return $this->db($table)->batchUpdate($data, $keyColumn, $batchSize);
    }

    public function query(string $sql, bool $allowRawSql = false)
    {
        $rawSqlEnabled = Env::get('app.database.allowRawSql', false);

        if (!$allowRawSql && !$rawSqlEnabled) {
            throw new RuntimeException("直接执行原生 SQL 已被禁用。请使用 QueryBuilder 构建查询。如需启用，请在配置中设置 'app.database.allowRawSql' => true");
        }

        if (defined('ANON_DEBUG') && ANON_DEBUG) {
            Debug::warn("执行原生 SQL 查询（存在安全风险）", [
                'sql_preview' => substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : '')
            ]);
        }

        return $this->getConnection()->query($sql);
    }

    public function createTable(string $table, array $columns, array $options = []): bool
    {
        return $this->db($table)->createTable($columns, $options);
    }

    public function addColumn(string $table, string $column, array $options): bool
    {
        return $this->db($table)->addColumn($column, $options);
    }

    public function dropColumn(string $table, string $column): bool
    {
        return $this->db($table)->dropColumn($column);
    }

    public function dropTable(string $table, bool $ifExists = true): bool
    {
        return $this->db($table)->dropTable($ifExists);
    }

    public function tableExists(string $table): bool
    {
        return $this->db($table)->tableExists();
    }

    public function prepare(string $sql, array $params = [])
    {
        return $this->getConnection()->prepare($sql, $params);
    }

    public function getUserInfo(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`'])
            ->where('uid', '=', $uid)
            ->first();

        if (!$row) {
            return null;
        }

        if (isset($row['email']) && (!isset($row['avatar']) || $row['avatar'] === null || $row['avatar'] === '')) {
            $row['avatar'] = $this->buildAvatar($row['email']);
        }

        return $row;
    }

    public function getUserInfoByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`'])
            ->where('name', '=', $name)
            ->first();

        if (!$row) {
            return null;
        }

        if (isset($row['email']) && (!isset($row['avatar']) || $row['avatar'] === null || $row['avatar'] === '')) {
            $row['avatar'] = $this->buildAvatar($row['email']);
        }

        return $row;
    }

    public function getUserAuthByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'password', 'email', 'avatar', '`group`'])
            ->where('name', '=', $name)
            ->first();

        if (!$row) {
            return null;
        }

        if (isset($row['email']) && (!isset($row['avatar']) || $row['avatar'] === null || $row['avatar'] === '')) {
            $row['avatar'] = $this->buildAvatar($row['email']);
        }

        return $row;
    }

    public function getUserInfoByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $row = $this->db('users')
            ->select(['uid', 'name', 'display_name', 'email', 'avatar', '`group`'])
            ->where('email', '=', $email)
            ->first();

        if (!$row) {
            return null;
        }

        if (isset($row['email']) && (!isset($row['avatar']) || $row['avatar'] === null || $row['avatar'] === '')) {
            $row['avatar'] = $this->buildAvatar($row['email']);
        }

        return $row;
    }

    public function isEmailExists(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }
        return $this->db('users')->where('email', '=', $email)->exists();
    }

    public function addUser(string $name, string $email, string $password, string $group = 'user', ?string $displayName = null, ?string $avatar = null)
    {
        $validGroups = ['admin', 'author', 'user'];
        if (!in_array($group, $validGroups, true)) {
            throw new RuntimeException('无效的用户组');
        }

        $now = date('Y-m-d H:i:s');
        $insert = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'group' => $group,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($displayName !== null) {
            $insert['display_name'] = $displayName;
        }
        if ($avatar !== null) {
            $insert['avatar'] = $avatar;
        }

        $id = $this->db('users')->insert($insert);
        return is_int($id) && $id > 0 ? $id : false;
    }

    public function logLogin(?int $uid = null, ?string $username = null, bool $status = true, ?string $message = null): bool
    {
        try {
            if ($uid === null && $username !== null && $username !== '') {
                $u = $this->getUserInfoByName($username);
                if ($u && isset($u['uid'])) {
                    $uid = (int) $u['uid'];
                }
            }

            $ip = Common::GetClientIp() ?? '0.0.0.0';
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = '0.0.0.0';
            }

            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $id = $this->db('login_logs')->insert([
                'uid' => $uid,
                'username' => $username,
                'ip' => $ip,
                'domain' => $domain,
                'user_agent' => $userAgent,
                'status' => $status ? 1 : 0,
                'message' => $message,
            ]);

            return is_int($id) ? $id > 0 : (bool) $id;
        } catch (Throwable $e) {
            if (class_exists(Debug::class) && Debug::isEnabled()) {
                Debug::warn('Failed to log login', ['message' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function isUserAdmin(int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }

        return $this->db('users')
            ->where('uid', '=', $uid)
            ->where('`group`', '=', 'admin')
            ->exists();
    }

    public function getAvatarByEmail(?string $email = null, int $size = 640): string
    {
        return $this->buildAvatar($email, $size);
    }

    private function buildAvatar(?string $email = null, int $size = 640): string
    {
        $avatarUrl = 'https://www.cravatar.cn/avatar';
        if (Env::isInitialized()) {
            $avatarUrl = Env::get('app.base.gravatar', $avatarUrl);
        }

        if ($email === null || trim($email) === '') {
            return "{$avatarUrl}/?s={$size}&d=retro";
        }

        $trimmedEmail = trim(strtolower($email));
        $hash = md5($trimmedEmail);
        return "{$avatarUrl}/{$hash}?s={$size}&d=retro";
    }

    public function __get($name)
    {
        return $this->instances[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->instances[$name]);
    }

    public function __call($name, $arguments)
    {
        $target = $this->resolveForwardTarget($name);
        if ($target && method_exists($target, $name)) {
            return call_user_func_array([$target, $name], $arguments);
        }
        foreach ($this->uniqueInstances() as $repo) {
            if ($repo && method_exists($repo, $name)) {
                return call_user_func_array([$repo, $name], $arguments);
            }
        }
        throw new BadMethodCallException("方法 '{$name}' 不存在于 Database 或其仓库/服务中");
    }

    private function resolveForwardTarget($method)
    {
        if (preg_match('/^(get|is|add|update|delete)([A-Z][A-Za-z0-9_]*)/', $method, $m)) {
            $subject = $m[2];
            $candidates = [
                $subject . 'Repository',
                lcfirst($subject . 'Repository'),
                'Anon\\Modules\\Database\\' . $subject . 'Repository',
                $subject . 'Service',
                lcfirst($subject . 'Service'),
                'Anon\\Modules\\Database\\' . $subject . 'Service',
            ];
            foreach ($candidates as $key) {
                if (isset($this->instances[$key])) {
                    return $this->instances[$key];
                }
            }
        }
        return null;
    }

    protected function uniqueInstances(): array
    {
        $seen = [];
        $uniq = [];
        foreach ($this->instances as $obj) {
            if (is_object($obj)) {
                $hash = spl_object_hash($obj);
                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    array_push($uniq, $obj);
                }
            }
        }
        return $uniq;
    }
}
