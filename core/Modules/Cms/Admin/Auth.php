<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 认证管理类
 * 处理用户认证相关的功能
 */
class Anon_Cms_Admin_Auth
{
    /**
     * 获取用户认证 Token
     * @return void
     */
    public static function getToken()
    {
        try {
            $userId = Anon_Http_Request::getUserId();
            $username = $_SESSION['username'] ?? '';
            
            if (!$userId || !$username) {
                Anon_Http_Response::unauthorized('用户未登录，无法获取 Token');
                return;
            }
            
            $token = Anon_Http_Request::getUserToken($userId, $username);
            
            Anon_Http_Response::success([
                'token' => $token,
            ], '获取 Token 成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e, '获取 Token 时发生错误');
        }
    }

    /**
     * 检查登录状态
     * @return void
     */
    public static function checkLogin()
    {
        try {
            $isLoggedIn = Anon_Check::isLoggedIn();
            $message = $isLoggedIn ? '用户已登录' : '用户未登录';
            
            Anon_Http_Response::success([
                'loggedIn' => $isLoggedIn,
                'logged_in' => $isLoggedIn,
            ], $message);
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e, '检查登录状态时发生错误');
        }
    }

    /**
     * 获取用户信息
     * @return void
     */
    public static function getUserInfo()
    {
        try {
            $userInfo = Anon_Http_Request::requireAuth();
            
            Anon_Http_Response::success($userInfo, '获取用户信息成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e, '获取用户信息时发生错误');
        }
    }
}

