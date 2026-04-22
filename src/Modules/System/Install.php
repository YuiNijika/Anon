<?php
namespace Anon\Modules\System;

use Anon\Modules\Common;
use Anon\Modules\Database\QueryBuilder;
use Anon\Modules\Debug;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;
use Exception;
use Anon\Modules\System\Env;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Install
{
    const envConfigFile = __DIR__ . '/../../../app/useEnv.php';

    /**
     * 获取建表 SQL
     * @param string $tablePrefix 表前缀
     * @param string $mode 安装模式：'api' 或 'cms'
     * @return array SQL 语句数组
     */
    private static function getSqlStatements(string $tablePrefix, string $mode = 'api'): array
    {
        $sqlConfigFile = __DIR__ . '/../../../app/useSQL.php';
        $installSql = file_exists($sqlConfigFile) ? require $sqlConfigFile : [];

        $sqlStatements = [];

        if (isset($installSql['api']) || isset($installSql['cms'])) {
            $globalTables = [];
            $modeTables = [];

            foreach ($installSql as $key => $value) {
                if ($key === 'api' || $key === 'cms') {
                    continue;
                }
                if (is_string($value)) {
                    $globalTables[$key] = $value;
                }
            }

            if (isset($installSql[$mode]) && is_array($installSql[$mode])) {
                $modeTables = $installSql[$mode];
            }

            $allTables = array_merge($globalTables, $modeTables);
        } else {
            $allTables = $installSql;
        }

        foreach ($allTables as $tableName => $sql) {
            if (is_string($sql)) {
                $sql = str_replace('{prefix}', $tablePrefix, $sql);
                $sqlStatements[] = $sql;
            }
        }

        return $sqlStatements;
    }

    /**
     * 页面入口
     */
    public static function index()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        // 启动会话
        if (!session_id()) {
            session_start();
        }

        // 生成 CSRF 令牌
        if (!isset($_SESSION['install_csrf_token'])) {
            $_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
        }

        // 检查是否已配置安装模式
        $installMode = Env::get('app.install.mode', null);

        // 如果已配置模式，则跳过模式选择步骤
        if ($installMode !== null && in_array($installMode, ['api', 'cms'])) {
            if (!isset($_SESSION['install_mode'])) {
                $_SESSION['install_mode'] = $installMode;
            }
            $defaultStep = 'database';
        } else {
            $defaultStep = 'mode';
        }

        // 显示页面
        $step = $_GET['step'] ?? $defaultStep;
        if (!in_array($step, ['mode', 'database', 'overwrite', 'admin'])) {
            $step = $defaultStep;
        }
        self::renderInstallPage($step);
    }

    /**
     * 返回上一步
     */
    public static function apiBack()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        // 根据当前步骤决定返回哪里
        $currentStep = $_GET['step'] ?? 'mode';
        if ($currentStep === 'admin') {
            // 从管理员页面返回，清除数据库配置，回到数据库配置页面
            unset($_SESSION['install_db_config']);
            ResponseHelper::success([
                'redirect' => '/anon/install?step=database'
            ], '已返回上一步');
        } else {
            // 从数据库配置页面返回，清除模式选择，回到模式选择页面
            unset($_SESSION['install_mode']);
            ResponseHelper::success([
                'redirect' => '/anon/install'
            ], '已返回上一步');
        }
    }

    /**
     * 获取 CSRF Token
     */
    public static function apiGetToken()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['install_csrf_token'])) {
            $_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
        }

        ResponseHelper::success([
            'csrf_token' => $_SESSION['install_csrf_token']
        ]);
    }

    /**
     * 获取当前选择的模式
     */
    public static function apiGetMode()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        // 优先从配置中读取
        $installMode = Env::get('app.install.mode', null);

        // 如果配置中已设置，则使用配置的值
        if ($installMode !== null && in_array($installMode, ['api', 'cms'], true)) {
            if (!isset($_SESSION['install_mode'])) {
                $_SESSION['install_mode'] = $installMode;
            }
            $mode = $installMode;
        } else {
            // 否则从 session 中读取
            $mode = $_SESSION['install_mode'] ?? null;
            if (!in_array($mode, ['api', 'cms'], true)) {
                $mode = null;
            }
        }

        ResponseHelper::success([
            'mode' => $mode
        ]);
    }

    /**
     * 选择模式
     */
    public static function apiSelectMode()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        try {
            $data = RequestHelper::validate([
                'csrf_token' => 'CSRF Token 不能为空',
                'app_mode' => '安装模式不能为空'
            ]);

            if (!isset($_SESSION['install_csrf_token']) || $data['csrf_token'] !== $_SESSION['install_csrf_token']) {
                ResponseHelper::error('CSRF验证失败，请重新提交表单。', null, 403);
            }

            $app_mode = $data['app_mode'];
            if (!in_array($app_mode, ['api', 'cms'], true)) {
                $app_mode = 'api';
            }

            $_SESSION['install_mode'] = $app_mode;

            ResponseHelper::success([
                'mode' => $app_mode,
                'redirect' => '/anon/install?step=database'
            ], '模式选择成功');
        } catch (Exception $e) {
            ResponseHelper::handleException($e);
        }
    }

    /**
     * 配置数据库
     */
    public static function apiDatabaseConfig()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        try {
            $input = RequestHelper::getInput();

            if (!isset($input['csrf_token']) || empty($input['csrf_token'])) {
                ResponseHelper::error('CSRF Token 不能为空', null, 400);
            }

            if (!isset($_SESSION['install_csrf_token']) || $input['csrf_token'] !== $_SESSION['install_csrf_token']) {
                ResponseHelper::error('CSRF验证失败，请重新提交表单。', null, 403);
            }

            // 获取已选择的模式
            $app_mode = isset($_SESSION['install_mode']) ? $_SESSION['install_mode'] : 'api';
            if (!in_array($app_mode, ['api', 'cms'], true)) {
                $app_mode = 'api';
            }

            $db_host = isset($input['db_host']) ? trim($input['db_host']) : '';
            $db_port = isset($input['db_port']) ? (int)$input['db_port'] : 3306;
            $db_user = isset($input['db_user']) ? trim($input['db_user']) : '';
            $db_pass = isset($input['db_pass']) ? trim($input['db_pass']) : '';
            $db_name = isset($input['db_name']) ? trim($input['db_name']) : '';
            $db_prefix = isset($input['db_prefix']) ? trim($input['db_prefix']) : '';

            if (empty($db_host)) {
                ResponseHelper::error('数据库主机不能为空', null, 400);
            }
            if (empty($db_user)) {
                ResponseHelper::error('数据库用户名不能为空', null, 400);
            }
            if (empty($db_pass)) {
                ResponseHelper::error('数据库密码不能为空', null, 400);
            }
            if (empty($db_name)) {
                ResponseHelper::error('数据库名称不能为空', null, 400);
            }
            if (empty($db_prefix)) {
                ResponseHelper::error('数据表前缀不能为空', null, 400);
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_prefix)) {
                ResponseHelper::error('数据表前缀只能包含字母、数字和下划线。', null, 400);
            }

            // 测试数据库连接
            if ($conn->connect_error) {
                ResponseHelper::error('数据库连接失败: ' . $conn->connect_error, null, 500);
            }

            // 检测表是否存在
            $existingTables = self::checkExistingTables($conn, $db_prefix, $app_mode);
            $conn->close();

            // 保存数据库配置到 session
            $_SESSION['install_db_config'] = [
                'db_host' => $db_host,
                'db_port' => $db_port,
                'db_user' => $db_user,
                'db_pass' => $db_pass,
                'db_name' => $db_name,
                'db_prefix' => $db_prefix,
                'app_mode' => $app_mode
            ];

            if (!empty($existingTables)) {
                ResponseHelper::success([
                    'mode' => $app_mode,
                    'tables_exist' => true,
                    'existing_tables' => $existingTables
                ], '检测到已存在的表，请选择是否覆盖安装');
            } else {
                ResponseHelper::success([
                    'mode' => $app_mode,
                    'tables_exist' => false
                ], '数据库配置成功');
            }
        } catch (Exception $e) {
            ResponseHelper::handleException($e);
        }
    }

    /**
     * 配置站点信息
     */
    public static function apiSiteConfig()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        try {
            $data = RequestHelper::validate([
                'csrf_token' => 'CSRF Token 不能为空',
                'username' => '用户名不能为空',
                'email' => '邮箱不能为空',
                'password' => '密码不能为空',
                'site_title' => '网站标题不能为空'
            ]);

            if (!isset($_SESSION['install_csrf_token']) || $data['csrf_token'] !== $_SESSION['install_csrf_token']) {
                ResponseHelper::error('CSRF验证失败，请重新提交表单。', null, 403);
            }

            // 从 session 获取数据库配置
            if (!isset($_SESSION['install_db_config'])) {
                ResponseHelper::error('请先配置数据库。', null, 400);
            }

            $db_config = $_SESSION['install_db_config'];
            $app_mode = $db_config['app_mode'];

            if ($app_mode !== 'cms') {
                ResponseHelper::error('只有 CMS 模式需要配置站点信息。', null, 400);
            }

            // 验证密码
            $password = trim($data['password']);
            if (strlen($password) < 8) {
                ResponseHelper::error('密码长度至少需要8个字符。', null, 400);
            }

            // 验证邮箱
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ResponseHelper::error('邮箱格式不正确。', null, 400);
            }

            // 保存站点配置到 session
            $_SESSION['install_site_config'] = [
                'username' => trim($data['username']),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'site_title' => trim($data['site_title']),
                'site_description' => isset($data['site_description']) ? trim($data['site_description']) : ''
            ];

            // 执行安装
            self::executeInstall();
        } catch (Exception $e) {
            ResponseHelper::handleException($e);
        }
    }

    /**
     * API模式执行安装
     */
    public static function apiInstall()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装，无法重复安装。', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        try {
            $data = RequestHelper::validate([
                'csrf_token' => 'CSRF Token 不能为空',
                'username' => '用户名不能为空',
                'password' => '密码不能为空',
                'email' => '邮箱不能为空'
            ]);

            if (!isset($_SESSION['install_csrf_token']) || $data['csrf_token'] !== $_SESSION['install_csrf_token']) {
                ResponseHelper::error('CSRF验证失败，请重新提交表单。', null, 403);
            }

            // 从 session 获取数据库配置
            if (!isset($_SESSION['install_db_config'])) {
                ResponseHelper::error('请先配置数据库。', null, 400);
            }

            // 创建管理员
            $password = trim($data['password']);
            $email = trim($data['email']);

            if (empty($username) || empty($password) || empty($email)) {
                ResponseHelper::error('所有字段都是必填的。', null, 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ResponseHelper::error('邮箱格式不正确。', null, 400);
            }

            if (strlen($password) < 8) {
                ResponseHelper::error('密码长度至少需要8个字符。', null, 400);
            }

            // 保存管理员信息到 session
            $_SESSION['install_admin_config'] = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email
            ];

            // 执行安装
            self::executeInstall();
        } catch (Exception $e) {
            ResponseHelper::handleException($e);
        }
    }


    /**
     * 渲染页面
     */
    private static function renderInstallPage($step = 'mode')
    {
        header('Content-Type: text/html; charset=utf-8');

        try {
            Common::Components('Install/Index');
        } catch (RuntimeException $e) {
            ResponseHelper::error('安装页面文件不存在', null, 500);
        }
        exit;
    }

    /**
     * 更新配置
     */
    private static function updateConfig($dbHost, $dbUser, $dbPass, $dbName, $dbPrefix, $dbPort = 3306, $appMode = 'api')
    {
        $appKey = 'base64:' . base64_encode(random_bytes(32));

        $dir = dirname(self::envConfigFile);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new Exception('配置文件不存在: ' . $dir);
        }

        $escape = function ($value): string {
            return "'" . addcslashes((string) $value, "'\\") . "'";
        };

        $content = "<?php\nif (!defined('ANON_ALLOWED_ACCESS')) exit;\n\n";
        $content .= "define('ANON_DB_HOST', " . $escape($dbHost) . ");\n";
        $content .= "define('ANON_DB_PORT', " . ((int) $dbPort) . ");\n";
        $content .= "define('ANON_DB_PREFIX', " . $escape($dbPrefix) . ");\n";
        $content .= "define('ANON_DB_USER', " . $escape($dbUser) . ");\n";
        $content .= "define('ANON_DB_PASSWORD', " . $escape($dbPass) . ");\n";
        $content .= "define('ANON_DB_DATABASE', " . $escape($dbName) . ");\n";
        $content .= "define('ANON_DB_CHARSET', 'utf8mb4');\n\n";
        $content .= "define('ANON_APP_KEY', " . $escape($appKey) . ");\n";
        $content .= "define('ANON_APP_MODE', " . $escape($appMode) . ");\n\n";
        $content .= "define('ANON_INSTALLED', true);\n";

        if (file_put_contents(self::envConfigFile, $content) === false) {
            throw new Exception('无法读取配置文件: ' . self::envConfigFile);
        }
    }

    // 生成随机 APP_KEY
    private static function executeInstall()
    {
        $db_config = $_SESSION['install_db_config'];
        $db_host = $db_config['db_host'];
        $db_port = $db_config['db_port'];
        $db_user = $db_config['db_user'];
        $db_pass = $db_config['db_pass'];
        $db_name = $db_config['db_name'];
        $db_prefix = $db_config['db_prefix'];
        $app_mode = $db_config['app_mode'];

        // 每次安装都强制更新 APP_KEY
        self::updateConfig($db_host, $db_user, $db_pass, $db_name, $db_prefix, $db_port, $app_mode);

        // 先连接数据库，用于创建表
        $conn = new \mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            ResponseHelper::error('数据库连接失败: ' . $conn->connect_error, null, 500);
        }

        // 检查是否需要覆盖安装
        $overwrite = isset($_SESSION['install_overwrite']) && $_SESSION['install_overwrite'] === true;

        // 执行 SQL
        self::executeSqlStatements($conn, $db_prefix, $app_mode, $overwrite);

        // 表创建完成后，再初始化环境配置
        $appConfigFile = __DIR__ . '/../../../app/useApp.php';
        $appConfig = file_exists($appConfigFile) ? require $appConfigFile : [];

        $envConfig = [
            'system' => [
                'db' => [
                    'host' => defined('ANON_DB_HOST') ? ANON_DB_HOST : 'localhost',
                    'port' => defined('ANON_DB_PORT') ? ANON_DB_PORT : 3306,
                    'prefix' => defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '',
                    'user' => defined('ANON_DB_USER') ? ANON_DB_USER : 'root',
                    'password' => defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : '',
                    'database' => defined('ANON_DB_DATABASE') ? ANON_DB_DATABASE : '',
                    'charset' => defined('ANON_DB_CHARSET') ? ANON_DB_CHARSET : 'utf8mb4',
                ],
                'installed' => defined('ANON_INSTALLED') ? ANON_INSTALLED : false,
            ],
        ];
        $envConfig = array_merge_recursive($envConfig, $appConfig);

        Env::init($envConfig);

        // 检查是否需要覆盖安装
        if ($app_mode === 'cms') {
            if (isset($_SESSION['install_site_config'])) {
                $site_config = $_SESSION['install_site_config'];
                $site_title = $site_config['site_title'] ?: 'Anon CMS';
                $site_description = $site_config['site_description'] ?? '';
            } else {
                $site_title = 'Anon CMS';
                $site_description = '';
            }
            self::insertDefaultOptions($conn, $db_prefix, $site_title, $site_description);
        }

        // 执行 SQL
        if (isset($_SESSION['install_site_config'])) {
            $admin_config = $_SESSION['install_site_config'];
            $username = $admin_config['username'];
            $password = $admin_config['password'];
            $email = $admin_config['email'];
        } elseif (isset($_SESSION['install_admin_config'])) {
            $admin_config = $_SESSION['install_admin_config'];
            $username = $admin_config['username'];
            $password = $admin_config['password'];
            $email = $admin_config['email'];
        } else {
            $conn->close();
            ResponseHelper::error('管理员信息未配置。', null, 400);
        }

        $userId = self::insertUserData($conn, $username, $password, $email, $db_prefix, 'admin');
        if (!$userId) {
            $conn->close();
            ResponseHelper::error('用户数据插入失败: ' . $conn->error, null, 500);
        }

        // 插入默认数据
        if ($app_mode === 'cms') {
            /**
             * 插入默认分类并获取分类ID
             */
            $categoryId = self::insertDefaultMeta($conn, $db_prefix);

            /**
             * 插入默认文章，关联默认分类
             */
            self::insertDefaultPost($conn, $db_prefix, $userId, $categoryId);
        }

        $conn->close();
        unset($_SESSION['install_mode']);
        unset($_SESSION['install_db_config']);
        unset($_SESSION['install_site_config']);
        unset($_SESSION['install_admin_config']);

        ResponseHelper::success([
            'redirect' => '/'
        ], '安装成功');
    }

    /**
     * 检查存在的表
     */
    private static function checkExistingTables($conn, $tablePrefix, string $mode): array
    {
        $sqlStatements = self::getSqlStatements($tablePrefix, $mode);
        $existingTables = [];

        foreach ($sqlStatements as $sql) {
            // 解析表名
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?([^`\s]+)`?/i', $sql, $matches)) {
                $tableName = $matches[1];
                $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
                if ($result && $result->num_rows > 0) {
                    $existingTables[] = $tableName;
                }
                if ($result) {
                    $result->free();
                }
            }
        }

        return $existingTables;
    }

    /**
     * 确认覆盖安装
     */
    public static function apiConfirmOverwrite()
    {
        if (Config::isInstalled()) {
            ResponseHelper::error('系统已安装', null, 400);
        }

        if (!session_id()) {
            session_start();
        }

        try {
            $input = RequestHelper::getInput();

            if (!isset($input['csrf_token']) || empty($input['csrf_token'])) {
                ResponseHelper::error('CSRF Token 不能为空', null, 400);
            }

            if (!isset($_SESSION['install_csrf_token']) || $input['csrf_token'] !== $_SESSION['install_csrf_token']) {
                ResponseHelper::error('CSRF验证失败，请重新提交表单。', null, 403);
            }

            if (!isset($input['confirm']) || $input['confirm'] !== 'yes') {
                ResponseHelper::error('请确认是否覆盖安装', null, 400);
            }

            // 标记已确认覆盖
            $_SESSION['install_overwrite'] = true;

            ResponseHelper::success([], '确认成功，请继续安装');
        } catch (\Exception $e) {
            ResponseHelper::handleException($e);
        }
    }
    /**
     * 执行 SQL
     */
    private static function executeSqlStatements($conn, $tablePrefix, string $mode = 'api', bool $overwrite = false)
    {
        $sqlStatements = self::getSqlStatements($tablePrefix, $mode);

        if ($overwrite) {
            // 先删除已存在的表
            foreach ($sqlStatements as $sql) {
                if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?([^`\s]+)`?/i', $sql, $matches)) {
                    $tableName = $matches[1];
                    $dropSql = "DROP TABLE IF EXISTS `{$tableName}`";
                    if (!$conn->query($dropSql)) {
                        $errorMsg = self::sanitizeError($conn->error);
                        Debug::error('删除表失败', ['table' => $tableName, 'error' => $errorMsg]);
                    }
                }
            }
        }

        foreach ($sqlStatements as $sql) {
            if (!empty($sql) && !$conn->query($sql)) {
                $errorMsg = self::sanitizeError($conn->error);
                Debug::error('SQL 执行错误', ['error' => $errorMsg]);
                throw new \RuntimeException('SQL 执行错误: ' . $conn->error);
            }
        }
    }

    /**
     * 获取表名
     */
    private static function getTableName(string $tablePrefix, string $table): string
    {
        return $tablePrefix . $table;
    }

    /**
     * 插入默认配置
     */
    private static function insertDefaultOptions($conn, $tablePrefix, $siteTitle, $siteDescription = '')
    {
        $tableName = self::getTableName($tablePrefix, 'options');
        $defaultOptions = [
            'site_title' => $siteTitle,
            'site_description' => $siteDescription,
            'site_url' => self::getSiteUrl(),
            'site_logo' => '',
            'active_theme' => 'default',
            'posts_per_page' => 10,
            'comment_status' => 'open',
            'user_registration' => 'closed',
            'routes:pages' => json_encode([
                '/{slug}' => 'page',
            ], JSON_UNESCAPED_UNICODE),
            'routes:posts' => json_encode([
                '/post/{slug}' => 'post',
                '/post/{slug}/{page}' => 'post',
                '/category/{slug}' => 'category',
                '/category/{slug}/{page}' => 'category',
                '/tag/{slug}' => 'tag',
                '/tag/{slug}/{page}' => 'tag',
                '/user/{uid}' => 'user',
            ], JSON_UNESCAPED_UNICODE),
            'plugins:active' => json_encode([], JSON_UNESCAPED_UNICODE),
        ];

        $queryBuilder = new QueryBuilder($conn, $tableName);

        foreach ($defaultOptions as $name => $value) {
            $existing = $queryBuilder->select('*')->where('name', $name)->first();

            if ($existing) {
                $result = $queryBuilder->where('name', $name)->update(['value' => $value]);
            } else {
                $result = $queryBuilder->insert([
                    'name' => $name,
                    'value' => $value
                ]);
            }

            if (!$result) {
                $errorMsg = self::sanitizeError($conn->error);
                Debug::error("插入选项失败", ['error' => $errorMsg]);
                throw new \RuntimeException("插入选项失败: " . $name);
            }

            // 重置查询构建器状态，避免影响下一次查询
            $queryBuilder = new QueryBuilder($conn, $tableName);
        }

        return true;
    }

    /**
     * 插入用户数据
     * @param \mysqli $conn 数据库连接
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email 邮箱
     * @param string $tablePrefix 表前缀
     * @param string $group 用户组
     * @return int|false 返回用户 ID 或 false
     */
    private static function insertUserData($conn, $username, $password, $email, $tablePrefix, $group = 'admin')
    {
        $tableName = self::getTableName($tablePrefix, 'users');
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $queryBuilder = new QueryBuilder($conn, $tableName);
        $userId = $queryBuilder->insert([
            'name' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'group' => $group,
            'display_name' => null,
            'avatar' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return $userId;
    }

    /**
     * 插入默认分类
     * @param \mysqli $conn 数据库连接
     * @param string $tablePrefix 表前缀
     * @return int|false 返回分类 ID 或 false
     */
    private static function insertDefaultMeta($conn, $tablePrefix)
    {
        $tableName = self::getTableName($tablePrefix, 'metas');
        $now = date('Y-m-d H:i:s');

        $queryBuilder = new QueryBuilder($conn, $tableName);
        $categoryId = $queryBuilder->insert([
            'name' => '默认分类',
            'slug' => 'default',
            'type' => 'category',
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return $categoryId;
    }

    /**
     * 插入默认文章
     * @param \mysqli $conn 数据库连接
     * @param string $tablePrefix 表前缀
     * @param int $authorId 作者 ID
     * @param int|null $categoryId 分类 ID
     * @return bool
     */
    private static function insertDefaultPost($conn, $tablePrefix, $authorId, $categoryId = null)
    {
        $tableName = self::getTableName($tablePrefix, 'posts');
        $now = date('Y-m-d H:i:s');

        /**
         * 确保内容以 <!--markdown--> 开头
         */
        $content = '<!--markdown-->欢迎使用 `AnonEcho`';

        $insertData = [
            'type' => 'post',
            'title' => 'Hello World!',
            'slug' => 'hello-world',
            'content' => $content,
            'status' => 'publish',
            'author_id' => $authorId,
            'category_id' => $categoryId,
            'tag_ids' => null,
            'views' => 0,
            'comment_status' => 'open',
            'created_at' => $now,
            'updated_at' => $now
        ];

        $queryBuilder = new QueryBuilder($conn, $tableName);
        $result = $queryBuilder->insert($insertData);

        return $result !== false;
    }

    /**
     * 验证输入
     */
    private static function validateInput($data)
    {
        return htmlspecialchars(trim($data));
    }

    /**
     * 清理错误信息
     * @param string $error 原始错误
     * @return string 清理后错误
     */
    private static function sanitizeError(string $error): string
    {
        // 检查详细日志
        $logDetailed = false;
        if (class_exists('Anon\Modules\System\Env') && Env::isInitialized()) {
            $logDetailed = Env::get('app.debug.logDetailedErrors', false);
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
            $logDetailed = false; // 默认不记录
        }

        // 移除敏感路径
        if (!$logDetailed) {
            $error = preg_replace('/\/[^\s]+\.php:\d+/', '[file]:[line]', $error);
        }

        // 移除敏感数据
        if (!$logDetailed) {
            $error = preg_replace('/\b(?:database|table|column|user|password)\s*[=:]\s*[\'"]?[^\'"\s]+[\'"]?/i', '[sensitive]', $error);
        }

        return $error;
    }

    /**
     * 错误处理
     */
    private static function handleError($message)
    {
        Debug::error("安装错误", ['message' => $message]);
        ResponseHelper::error('发生错误，请稍后重试。', null, 500);
    }
}
