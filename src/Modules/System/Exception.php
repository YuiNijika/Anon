<?php
namespace Anon\Modules\System;



use System;
use Throwable;
use InvalidArgumentException;if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 异常上下文工具（无继承模型）
 */
class Exception
{
    public static function resolveHttpCode(Throwable $exception): int
    {
        $code = (int) $exception->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        if ($exception instanceof InvalidArgumentException) {
            return 400;
        }

        return 500;
    }

    public static function resolveData(Throwable $exception): array
    {
        return [];
    }
}
