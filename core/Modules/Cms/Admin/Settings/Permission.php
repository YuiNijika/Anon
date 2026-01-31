<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 权限设置管理类
 * 处理用户访问和 API 相关的权限设置
 */
class Anon_Cms_Admin_SettingsPermission
{
    /**
     * 获取权限设置
     * @return void
     */
    public static function get()
    {
        try {
            /**
             * 将各种格式的值转换为布尔值
             * @param mixed $value 待转换的值
             * @return bool
             */
            $toBool = function($value) {
                if ($value === true || $value === 'true' || $value === '1' || $value === 1) {
                    return true;
                }
                return false;
            };
            
            $settings = [
                'allow_register' => $toBool(Anon_Cms_Options::get('allow_register', '0')),
                'access_log_enabled' => $toBool(Anon_Cms_Options::get('access_log_enabled', '1')),
                'api_prefix' => Anon_Cms_Options::get('apiPrefix', '/api'),
                'api_enabled' => $toBool(Anon_Cms_Options::get('api_enabled', '0')),
            ];
            
            Anon_Http_Response::success($settings, '获取权限设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存权限设置
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
             * 辅助函数：将各种格式的布尔值转换为 '1' 或 '0'
             * @param mixed $value 待转换的值
             * @return string '1' 或 '0'
             */
            $toBoolString = function($value) {
                if ($value === true) {
                    return '1';
                }
                if ($value === false) {
                    return '0';
                }
                if (is_string($value)) {
                    $lowerValue = strtolower(trim($value));
                    if ($lowerValue === 'true' || $lowerValue === '1') {
                        return '1';
                    }
                    return '0';
                }
                if (is_numeric($value)) {
                    return ($value == 1) ? '1' : '0';
                }
                return '0';
            };
            
            /**
             * 处理布尔值字段，如果字段不存在则使用默认值
             */
            $allowRegister = isset($data['allow_register']) ? $toBoolString($data['allow_register']) : '0';
            $apiPrefix = isset($data['api_prefix']) ? trim($data['api_prefix']) : '/api';
            $apiEnabled = isset($data['api_enabled']) ? $toBoolString($data['api_enabled']) : '0';
            $accessLogEnabled = isset($data['access_log_enabled']) ? $toBoolString($data['access_log_enabled']) : '1';

            if (empty($apiPrefix) || $apiPrefix[0] !== '/') {
                Anon_Http_Response::error('API 前缀必须以 / 开头', 400);
                return;
            }

            Anon_Cms_Options::set('allow_register', $allowRegister);
            Anon_Cms_Options::set('apiPrefix', $apiPrefix);
            Anon_Cms_Options::set('api_enabled', $apiEnabled);
            Anon_Cms_Options::set('access_log_enabled', $accessLogEnabled);
            
            /**
             * 清除选项缓存，确保设置立即生效
             */
            Anon_Cms_Options::clearCache();
            
            Anon_Http_Response::success([
                'allow_register' => $allowRegister === '1',
                'api_prefix' => $apiPrefix,
                'api_enabled' => $apiEnabled === '1',
                'access_log_enabled' => $accessLogEnabled === '1',
            ], '保存权限设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

