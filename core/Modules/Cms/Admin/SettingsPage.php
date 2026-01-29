<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 页面设置管理类
 * 处理 CMS 路由规则相关的设置
 */
class Anon_Cms_Admin_SettingsPage
{
    /**
     * 获取页面设置
     * @return void
     */
    public static function get()
    {
        try {
            $routesValue = Anon_Cms_Options::get('routes', '');
            $routes = [];
            
            if (is_array($routesValue)) {
                $routes = $routesValue;
            } elseif (is_string($routesValue) && !empty($routesValue)) {
                $decoded = json_decode($routesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $routes = $decoded;
                }
            }
            
            $settings = [
                'routes' => $routes,
            ];
            
            Anon_Http_Response::success($settings, '获取页面设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存页面设置
     * @return void
     */
    public static function save()
    {
        try {
            $data = Anon_Http_Request::getInput();
            
            if (empty($data)) {
                Anon_Http_Response::error('请求数据不能为空', 400);
                return;
            }

            /**
             * 处理 URL 规则配置
             */
            $routes = [];
            if (isset($data['routes']) && is_array($data['routes'])) {
                $routes = $data['routes'];
            }

            Anon_Cms_Options::set('routes', json_encode($routes, JSON_UNESCAPED_UNICODE));
            
            /**
             * 清除选项缓存，确保设置立即生效
             */
            Anon_Cms_Options::clearCache();
            
            Anon_Http_Response::success([
                'routes' => $routes,
            ], '保存页面设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

