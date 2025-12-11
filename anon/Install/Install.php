<?php

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Install
{
    const sqlfile = __DIR__ . '/MySQL.sql';
    const lockfile = __DIR__ . '/Install.lock';
    const envfile = __DIR__ . '/../../env.php';
    /**
     * 读取 SQL 文件内容
     */
    private static function getSqlContent(): string
    {
        $sqlFile = self::sqlfile;
        if (!file_exists($sqlFile)) {
            throw new RuntimeException('安装SQL文件不存在: ' . $sqlFile);
        }
        $content = file_get_contents($sqlFile);
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('安装SQL文件为空或无法读取: ' . $sqlFile);
        }
        return $content;
    }

    /**
     * 安装页面主入口
     */
    public static function index()
    {
        // 只有在系统未安装时才能访问
        if (Anon_Config::isInstalled()) {
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

            // 重新加载配置文件
            $envConfig = require self::envfile;
            if (class_exists('Anon_Env')) {
                Anon_Env::init($envConfig);
            }

            // 连接到数据库
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($conn->connect_error) {
                throw new Exception("数据库连接失败: " . $conn->connect_error);
            }
            echo "数据库连接成功！<br>";

            // 执行 SQL 文件
            self::executeSqlContent($conn, $db_prefix);
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
                    // 创建安装锁文件到 Install 目录
                    $lockFile = self::lockfile;
                    file_put_contents($lockFile, 'Installed at ' . date('Y-m-d H:i:s'));
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
        // 读取配置（优先从常量读取，如果未定义则从配置文件读取）
        if (defined('ANON_DB_HOST')) {
            $db_host = ANON_DB_HOST;
            $db_port = defined('ANON_DB_PORT') ? ANON_DB_PORT : 3306;
            $db_user = ANON_DB_USER;
            $db_pass = defined('ANON_DB_PASSWORD') ? ANON_DB_PASSWORD : '';
            $db_name = ANON_DB_DATABASE;
            $db_prefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : 'anon_';
        } else {
            // 如果常量未定义，尝试从配置文件读取
            $configFile = self::envfile;
            if (file_exists($configFile)) {
                $config = require $configFile;
                $db_host = $config['system']['db']['host'] ?? 'localhost';
                $db_port = $config['system']['db']['port'] ?? 3306;
                $db_user = $config['system']['db']['user'] ?? 'root';
                $db_pass = $config['system']['db']['password'] ?? '';
                $db_name = $config['system']['db']['database'] ?? '';
                $db_prefix = $config['system']['db']['prefix'] ?? 'anon_';
            } else {
                // 默认值
                $db_host = 'localhost';
                $db_port = 3306;
                $db_user = 'root';
                $db_pass = '';
                $db_name = '';
                $db_prefix = 'anon_';
            }
        }

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
        
        // 读取现有配置
        $config = [];
        if (file_exists($configFile)) {
            try {
                $config = require $configFile;
            } catch (Exception $e) {
                // 如果读取失败，使用默认配置
                $config = [];
            }
        }
        
        // 初始化 system 配置
        if (!isset($config['system'])) {
            $config['system'] = [];
        }
        
        $config['system']['db'] = [
            'host' => $dbHost,
            'port' => $dbPort,
            'prefix' => $dbPrefix,
            'user' => $dbUser,
            'password' => $dbPass,
            'database' => $dbName,
            'charset' => $config['system']['db']['charset'] ?? 'utf8mb4',
        ];
        $config['system']['installed'] = true;
        
        if (!isset($config['app'])) {
            $config['app'] = [];
        }
        if (!isset($config['app']['debug'])) {
            $config['app']['debug'] = [];
        }
        if (!isset($config['app']['debug']['router'])) {
            $config['app']['debug']['router'] = true;
        }
        if (!isset($config['app']['debug']['global'])) {
            $config['app']['debug']['global'] = false;
        }
        if (!isset($config['app']['avatar'])) {
            $config['app']['avatar'] = 'https://www.cravatar.cn/avatar';
        }
        
        // 生成配置文件内容
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Anon环境配置\n";
        $content .= " */\n";
        $content .= "return " . var_export($config, true) . ";\n";
        
        file_put_contents($configFile, $content);
    }

    /**
     * 读取执行SQL
     */
    private static function executeSqlContent($conn, $tablePrefix)
    {
        $sqlContent = str_replace('{prefix}', $tablePrefix, self::getSqlContent());

            $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
        foreach ($queries as $query) {
            if (!empty($query) && !$conn->query($query)) {
                error_log("SQL 执行错误: " . $conn->error);
                throw new RuntimeException("SQL 执行错误");
            }
        }
    }

    /**
     * 插入初始用户数据
     */
    private static function insertUserData($conn, $username, $password, $email, $tablePrefix, $group = 'admin')
    {
        $tableName = $tablePrefix . 'users';
        $stmt = $conn->prepare("INSERT INTO $tableName (name, password, email, `group`) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            error_log("SQL 语句错误: " . $conn->error);
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
     * 自定义错误处理
     */
    private static function handleError($message)
    {
        error_log($message); // 记录到日志
        echo "发生错误，请稍后重试。";
        exit;
    }
}
