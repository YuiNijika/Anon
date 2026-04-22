<?php
namespace Anon\Modules\Cms;


use Anon\Modules\System\AccessLog as SystemAccessLog;
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class AccessLog
{
    public static function __callStatic(string $name, array $arguments)
    {
        if (method_exists(SystemAccessLog::class, $name)) {
            return SystemAccessLog::$name(...$arguments);
        }

        throw new \BadMethodCallException("Undefined method {$name} on " . __CLASS__);
    }
}
