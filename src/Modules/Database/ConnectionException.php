<?php
namespace Anon\Modules\Database;

use RuntimeException;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 数据库连接异常
 * 
 * 当数据库连接失败时抛出此异常
 */
class ConnectionException extends RuntimeException
{
    /**
     * @var string 数据库主机
     */
    private $host;

    /**
     * @var int 数据库端口
     */
    private $port;

    /**
     * @var string 数据库名称
     */
    private $database;

    /**
     * 构造函数
     * 
     * @param string $message 错误消息
     * @param string $host 数据库主机
     * @param int $port 数据库端口
     * @param string $database 数据库名称
     * @param int $code 错误代码
     * @param \Throwable|null $previous 上一个异常
     */
    public function __construct(
        string $message = '',
        string $host = '',
        int $port = 3306,
        string $database = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;

        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取数据库主机
     * 
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取数据库端口
     * 
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 获取数据库名称
     * 
     * @return string
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * 获取友好的错误提示
     * 
     * @return string
     */
    public function getFriendlyMessage(): string
    {
        $message = "数据库连接失败";
        
        if ($this->host && $this->port) {
            $message .= " ({$this->host}:{$this->port})";
        }
        
        if ($this->database) {
            $message .= " - 数据库: {$this->database}";
        }
        
        $parentMessage = $this->getMessage();
        if ($parentMessage) {
            $message .= ": {$parentMessage}";
        }
        
        return $message;
    }
}
