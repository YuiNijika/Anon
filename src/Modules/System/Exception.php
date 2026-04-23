<?php
namespace Anon\Modules\System;

use Anon\Modules\Database\ConnectionException;
use Throwable;
use InvalidArgumentException;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 异常上下文工具
 */
class Exception
{
    public static function resolveHttpCode(Throwable $exception): int
    {
        $code = (int) $exception->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        // 数据库连接异常返回 503 Service Unavailable
        if ($exception instanceof ConnectionException) {
            return 503;
        }

        if ($exception instanceof InvalidArgumentException) {
            return 400;
        }

        return 500;
    }

    public static function resolveData(Throwable $exception): array
    {
        // 数据库连接异常返回友好信息
        if ($exception instanceof ConnectionException) {
            return [
                'error_type' => 'database_connection',
                'host' => $exception->getHost(),
                'port' => $exception->getPort(),
                'database' => $exception->getDatabase(),
            ];
        }

        return [];
    }
}
