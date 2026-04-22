<?php
namespace Anon\Modules\Cms\AdminSettings;





use Exception;
use Settings;
use Anon\Modules\Cms\Cms;
use Options;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 链接设置
 * 读写 options 表 routes 字段，供后台链接设置页使用
 *
 * @package Anon/Cms/Admin/Settings
 */
class Page
{
    /**
     * 获取链接设置
     * @return void
     */
    public static function get(): void
    {
        try {
            $routesValue = Options::get('routes', '');
            $routes = [];
            if (is_array($routesValue)) {
                $routes = $routesValue;
            } elseif (is_string($routesValue) && $routesValue !== '') {
                $decoded = json_decode($routesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $routes = $decoded;
                }
            }

            // 如果路由为空，使用默认路由
            if (empty($routes)) {
                $routes = Cms::DEFAULT_ROUTES;
            }

            ResponseHelper::success(['routes' => $routes], '获取链接设置成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 保存链接设置
     * @return void
     */
    public static function save(): void
    {
        try {
            $data = RequestHelper::getInput();
            $routes = isset($data['routes']) && is_array($data['routes']) ? $data['routes'] : [];
            Options::set('routes', json_encode($routes, JSON_UNESCAPED_UNICODE));
            Options::clearCache();
            ResponseHelper::success(['routes' => $routes], '保存链接设置成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }
}
