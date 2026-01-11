<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 基础异常
 */
class Anon_System_Exception extends Exception
{
    protected $httpCode = 500;
    protected $data = [];

    public function __construct(string $message = '', int $httpCode = 500, array $data = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->data = $data;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * 未授权异常
 */
class Anon_UnauthorizedException extends Anon_System_Exception
{
    public function __construct(string $message = '未授权访问', array $data = [])
    {
        parent::__construct($message, 401, $data);
    }
}

/**
 * 禁止访问异常
 */
class Anon_ForbiddenException extends Anon_System_Exception
{
    public function __construct(string $message = '禁止访问', array $data = [])
    {
        parent::__construct($message, 403, $data);
    }
}

/**
 * 未找到异常
 */
class Anon_NotFoundException extends Anon_System_Exception
{
    public function __construct(string $message = '资源未找到', array $data = [])
    {
        parent::__construct($message, 404, $data);
    }
}

/**
 * 验证异常
 */
class Anon_ValidationException extends Anon_System_Exception
{
    public function __construct(string $message = '参数验证失败', array $errors = [])
    {
        parent::__construct($message, 422, ['errors' => $errors]);
    }
}

