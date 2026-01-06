<?php

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_System_Install
{
    const envfile = __DIR__ . '/../../../env.php';

    /**
     * 获取数据库表创建 SQL 语句
     * @param string $tablePrefix 表前缀
     * @return array SQL 语句数组
     */
    private static function getSqlStatements(string $tablePrefix): array
    {
        $sqlConfigFile = __DIR__ . '/../../../app/useSQL.php';
        $installSql = file_exists($sqlConfigFile) ? require $sqlConfigFile : [];
        
        $sqlStatements = [];
        foreach ($installSql as $tableName => $sql) {
            if (is_string($sql)) {
                $sql = str_replace('{prefix}', $tablePrefix, $sql);
                $sqlStatements[] = $sql;
            }
        }
        
        return $sqlStatements;
    }

    /**
     * 安装页面主入口
     */
    public static function index()
    {
        // 只有在系统未安装时才能访问
        if (Anon_System_Config::isInstalled()) {
            Anon_Common::Header(400);
            echo json_encode(
                [
                    'code' => 400,
                    'error' => '系统已安装，无法重复安装。'
                ]
            );
            exit;
        }

        // 启动会话用于CSRF保护
        if (!session_id()) {
            session_start();
        }

        // 生成CSRF令牌
        if (!isset($_SESSION['install_csrf_token'])) {
            $_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
        }

        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host'])) {
            self::handleInstallSubmit();
            return;
        }

        // 显示安装页面
        self::renderInstallPage();
    }

    /**
     * 处理安装表单提交
     */
    private static function handleInstallSubmit()
    {
        try {
            // CSRF验证
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['install_csrf_token']) {
                throw new Exception("CSRF验证失败，请重新提交表单。");
            }

            // 获取并验证数据库连接信息
            $db_host = self::validateInput($_POST['db_host']);
            $db_port = isset($_POST['db_port']) ? (int)$_POST['db_port'] : 3306;
            $db_user = self::validateInput($_POST['db_user']);
            $db_pass = self::validateInput($_POST['db_pass'] ?? '');
            $db_name = self::validateInput($_POST['db_name']);
            $db_prefix = self::validateInput($_POST['db_prefix']);

            if (empty($db_host) || empty($db_user) || empty($db_name)) {
                throw new Exception("所有数据库连接字段都是必填的。");
            }

            // 更新配置文件
            self::updateConfig($db_host, $db_user, $db_pass, $db_name, $db_prefix, $db_port);

            require_once self::envfile;
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
            
            if (class_exists('Anon_System_Env')) {
                Anon_System_Env::init($envConfig);
            }

            // 连接到数据库
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($conn->connect_error) {
                throw new Exception("数据库连接失败: " . $conn->connect_error);
            }
            echo "数据库连接成功！<br>";

            // 执行 SQL 语句
            self::executeSqlStatements($conn, $db_prefix);
            echo "数据表创建成功！<br>";

            // 创建初始用户
            if (isset($_POST['username'])) {
                $username = self::validateInput($_POST['username']);
                $password = self::validateInput($_POST['password']);
                $email = self::validateInput($_POST['email']);

                if (empty($username) || empty($password) || empty($email)) {
                    throw new Exception("所有字段都是必填的。");
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("邮箱格式不正确。");
                }

                if (self::insertUserData($conn, $username, $password, $email, $db_prefix, 'admin')) {
                    echo "<script>alert('安装成功！'); window.location.href='/';</script>";
                    exit;
                } else {
                    throw new Exception("用户数据插入失败: " . $conn->error);
                } 
            }

            $conn->close();
        } catch (Exception $e) {
            self::handleError("安装过程中发生错误: " . $e->getMessage());
        }
    }

    /**
     * 渲染安装页面
     */
    private static function renderInstallPage()
    {
        // 从常量读取配置，否则使用默认值
        $db_host = defined('ANON_DB_HOST') ? ANON_DB_HOST : 'localhost';
        $db_port = defined('ANON_DB_PORT') ? ANON_DB_PORT : 3306;
        $db_user = defined('ANON_DB_USER') ? ANON_DB_USER : 'root';
        $db_pass = defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : '';
        $db_name = defined('ANON_DB_DATABASE') ? ANON_DB_DATABASE : '';
        $db_prefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : 'anon_';

        $csrf = htmlspecialchars($_SESSION['install_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
        $db_host_h = htmlspecialchars($db_host, ENT_QUOTES, 'UTF-8');
        $db_port_h = htmlspecialchars((string)$db_port, ENT_QUOTES, 'UTF-8');
        $db_user_h = htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8');
        $db_pass_h = htmlspecialchars($db_pass, ENT_QUOTES, 'UTF-8');
        $db_name_h = htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8');
        $db_prefix_h = htmlspecialchars($db_prefix, ENT_QUOTES, 'UTF-8');
        $error_msg = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; padding: 20px; background: #f8f9fa; color: #333; }
        .container { max-width: 720px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { text-align: center; margin: 0 0 20px; color: #2c3e50; }
        h3 { margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid #eee; color: #3498db; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; transition: border-color .2s, box-shadow .2s; }
        input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52,152,219,.2); }
        button { width: 100%; padding: 12px; background: #3498db; color: #fff; border: none; border-radius: 4px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background .2s; }
        button:hover { background: #2980b9; }
        .alert { padding: 12px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 16px; }
        .password-strength { height: 4px; background: #eee; margin-top: 6px; border-radius: 2px; overflow: hidden; }
        .password-strength-bar { height: 100%; width: 0; background: #e74c3c; transition: width .3s, background .3s; }
        .requirements { font-size: 12px; color: #666; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>系统安装向导</h1>
<?php
        if ($error_msg !== '') {
            echo '<div class="alert">' . $error_msg . '</div>';
        }
?>
        <form method="post" id="installForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <h3>数据库配置</h3>
            <div class="form-group">
                <label for="db_host">数据库主机</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo $db_host_h; ?>" required>
            </div>
            <div class="form-group">
                <label for="db_port">数据库端口</label>
                <input type="number" id="db_port" name="db_port" min="1" max="65535" value="<?php echo $db_port_h; ?>" required>
            </div>
            <div class="form-group">
                <label for="db_user">数据库用户名</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo $db_user_h; ?>" required>
            </div>
            <div class="form-group">
                <label for="db_pass">数据库密码</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo $db_pass_h; ?>">
            </div>
            <div class="form-group">
                <label for="db_name">数据库名称</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo $db_name_h; ?>" required>
            </div>
            <div class="form-group">
                <label for="db_prefix">数据表前缀</label>
                <input type="text" id="db_prefix" name="db_prefix" pattern="[a-zA-Z0-9_]+" value="<?php echo $db_prefix_h; ?>" required>
                <div class="requirements">只能包含字母、数字和下划线</div>
            </div>

            <h3>管理员账号</h3>
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" value="admin" required>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required minlength="8">
                <div class="password-strength"><div class="password-strength-bar" id="passwordStrengthBar"></div></div>
                <div class="requirements">至少8个字符，建议包含大小写字母、数字和符号</div>
            </div>
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" id="submitBtn">开始安装</button>
        </form>
    </div>

    <script>
        // 密码强度检测
        document.getElementById("password").addEventListener("input", function(e) {
            const password = e.target.value;
            const bar = document.getElementById("passwordStrengthBar");
            let strength = 0;
            if (password.length >= 8) strength += 20;
            if (password.length >= 12) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            bar.style.width = strength + "%";
            bar.style.backgroundColor = strength < 40 ? "#e74c3c" : (strength < 70 ? "#f39c12" : "#2ecc71");
        });
        // 表单提交前验证
        document.getElementById("installForm").addEventListener("submit", function(e) {
            const password = document.getElementById("password").value;
            const email = document.getElementById("email").value;
            if (password.length < 8) { alert("密码长度至少需要8个字符"); e.preventDefault(); return; }
            if (!email.includes("@")) { alert("请输入有效的邮箱地址"); e.preventDefault(); return; }
            const btn = document.getElementById("submitBtn"); btn.disabled = true; btn.textContent = "安装中...";
        });
    </script>
</body>
</html>
<?php
    }

    /**
     * 更新配置文件
     */
    private static function updateConfig($dbHost, $dbUser, $dbPass, $dbName, $dbPrefix, $dbPort = 3306)
    {
        $configFile = self::envfile;
        
        if (!file_exists($configFile)) {
            throw new Exception('配置文件不存在: ' . $configFile);
        }
        
        $lines = file($configFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new Exception('无法读取配置文件: ' . $configFile);
        }
        
        $escapeValue = function($str) {
            return "'" . addcslashes($str, "'\\") . "'";
        };

        // 生成随机 APP_KEY
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        
        foreach ($lines as $index => $line) {
            // 每次安装都强制更新 APP_KEY
            if (preg_match("/define\s*\(\s*['\"]ANON_APP_KEY['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_APP_KEY', " . $escapeValue($appKey) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }

            if (preg_match("/define\s*\(\s*['\"]ANON_DB_HOST['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_HOST', " . $escapeValue($dbHost) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_DB_PORT['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_PORT', " . $dbPort . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_DB_PREFIX['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_PREFIX', " . $escapeValue($dbPrefix) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_DB_USER['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_USER', " . $escapeValue($dbUser) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_DB_PASSWORD['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_PASSWORD', " . $escapeValue($dbPass) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_DB_DATABASE['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_DB_DATABASE', " . $escapeValue($dbName) . ");" . ($comment ? ' ' . $comment : '');
                continue;
            }
            
            if (preg_match("/define\s*\(\s*['\"]ANON_INSTALLED['\"]/", $line)) {
                $comment = preg_match('/\/\/.*$/', $line, $matches) ? $matches[0] : '';
                $lines[$index] = "define('ANON_INSTALLED', true);" . ($comment ? ' ' . $comment : '');
                continue;
            }
        }
        
        $content = implode("\n", $lines) . "\n";
        if (file_put_contents($configFile, $content) === false) {
            throw new Exception('无法写入配置文件: ' . $configFile);
        }
    }

    /**
     * 执行 SQL 语句
     */
    private static function executeSqlStatements($conn, $tablePrefix)
    {
        $sqlStatements = self::getSqlStatements($tablePrefix);
        
        foreach ($sqlStatements as $sql) {
            if (!empty($sql) && !$conn->query($sql)) {
                $errorMsg = self::sanitizeError($conn->error);
                error_log("SQL 执行错误: " . $errorMsg);
                throw new RuntimeException("SQL 执行错误: " . $conn->error);
            }
        }
    }

    /**
     * 插入初始用户数据
     */
    private static function insertUserData($conn, $username, $password, $email, $tablePrefix, $group = 'admin')
    {
        $tableName = $tablePrefix . 'users';
        $stmt = $conn->prepare("INSERT INTO $tableName (name, password, email, `group`, display_name, avatar, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, NULL, NOW(), NOW())");
        if (!$stmt) {
            $errorMsg = self::sanitizeError($conn->error);
            error_log("SQL 语句错误: " . $errorMsg);
            throw new RuntimeException("SQL 语句错误");
        }
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt->bind_param("ssss", $username, $hashedPassword, $email, $group);
        return $stmt->execute();
    }

    /**
     * 验证表单输入
     */
    private static function validateInput($data)
    {
        return htmlspecialchars(trim($data));
    }

    /**
     * 清理错误信息，移除可能的敏感信息
     * @param string $error 原始错误信息
     * @return string 清理后的错误信息
     */
    private static function sanitizeError(string $error): string
    {
        // 检查是否允许记录详细错误
        $logDetailed = false;
        if (class_exists('Anon_System_Env') && Anon_System_Env::isInitialized()) {
            $logDetailed = Anon_System_Env::get('app.debug.logDetailedErrors', false);
        } elseif (defined('ANON_DEBUG') && ANON_DEBUG) {
            $logDetailed = false; // 默认不记录详细错误
        }
        
        // 移除可能的敏感路径信息
        if (!$logDetailed) {
            $error = preg_replace('/\/[^\s]+\.php:\d+/', '[file]:[line]', $error);
        }
        
        // 移除可能的数据库名、表名等敏感信息
        if (!$logDetailed) {
            $error = preg_replace('/\b(?:database|table|column|user|password)\s*[=:]\s*[\'"]?[^\'"\s]+[\'"]?/i', '[sensitive]', $error);
        }
        
        return $error;
    }

    /**
     * 自定义错误处理
     */
    private static function handleError($message)
    {
        error_log($message);
        echo "发生错误，请稍后重试。";
        exit;
    }
}

