<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * CLI 控制台工具
 * 提供命令行接口用于执行框架命令
 */
class Anon_Console
{
    /**
     * @var array 注册的命令
     */
    private static $commands = [];

    /**
     * @var array 命令别名
     */
    private static $aliases = [];

    /**
     * 注册命令
     * @param string $name 命令名
     * @param callable|string $handler 命令处理器，可以是闭包或类名
     * @param string|null $description 命令描述
     * @return void
     */
    public static function command(string $name, $handler, ?string $description = null): void
    {
        self::$commands[$name] = [
            'handler' => $handler,
            'description' => $description ?? ''
        ];
    }

    /**
     * 注册命令别名
     * @param string $alias 别名
     * @param string $command 原始命令名
     * @return void
     */
    public static function alias(string $alias, string $command): void
    {
        self::$aliases[$alias] = $command;
    }

    /**
     * 运行命令
     * @param array $argv 命令行参数
     * @return int 退出码
     */
    public static function run(array $argv): int
    {
        // 移除脚本名
        array_shift($argv);

        if (empty($argv)) {
            self::showHelp();
            return 0;
        }

        $commandName = $argv[0];
        $args = array_slice($argv, 1);

        // 检查别名
        if (isset(self::$aliases[$commandName])) {
            $commandName = self::$aliases[$commandName];
        }

        // 检查命令是否存在
        if (!isset(self::$commands[$commandName])) {
            self::error("未知命令: {$commandName}");
            self::showHelp();
            return 1;
        }

        $command = self::$commands[$commandName];

        try {
            // 解析命令处理器
            $handler = self::resolveHandler($command['handler']);

            // 执行命令
            $result = $handler($args);

            return is_int($result) ? $result : 0;
        } catch (Exception $e) {
            self::error("执行命令时发生错误: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 解析命令处理器
     * @param callable|string $handler 处理器
     * @return callable
     */
    private static function resolveHandler($handler): callable
    {
        // 如果是闭包，直接返回
        if (is_callable($handler) && !is_string($handler)) {
            return $handler;
        }

        // 如果是类名，尝试从容器解析或直接实例化
        if (is_string($handler) && class_exists($handler)) {
            $instance = Anon_Container::getInstance()->make($handler);

            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }

            if (is_callable($instance)) {
                return $instance;
            }
        }

        throw new RuntimeException("无效的命令处理器: " . gettype($handler));
    }

    /**
     * 显示帮助信息
     * @return void
     */
    private static function showHelp(): void
    {
        self::info("Anon Framework CLI");
        self::line("");
        self::info("可用命令:");
        self::line("");

        foreach (self::$commands as $name => $command) {
            $description = $command['description'] ? " - {$command['description']}" : "";
            self::line("  {$name}{$description}");
        }

        self::line("");
    }

    /**
     * 输出信息
     * @param string $message 消息
     * @return void
     */
    public static function info(string $message): void
    {
        self::line($message);
    }

    /**
     * 输出错误
     * @param string $message 消息
     * @return void
     */
    public static function error(string $message): void
    {
        if (self::isCli()) {
            self::line("\033[31m{$message}\033[0m");
        } else {
            self::line($message);
        }
    }

    /**
     * 输出成功消息
     * @param string $message 消息
     * @return void
     */
    public static function success(string $message): void
    {
        if (self::isCli()) {
            self::line("\033[32m{$message}\033[0m");
        } else {
            self::line($message);
        }
    }

    /**
     * 输出警告
     * @param string $message 消息
     * @return void
     */
    public static function warning(string $message): void
    {
        if (self::isCli()) {
            self::line("\033[33m{$message}\033[0m");
        } else {
            self::line($message);
        }
    }

    /**
     * 输出一行
     * @param string $message 消息
     * @return void
     */
    public static function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * 检查是否在 CLI 环境
     * @return bool
     */
    private static function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * 获取所有注册的命令
     * @return array
     */
    public static function getCommands(): array
    {
        return self::$commands;
    }
}

