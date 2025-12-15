<?php
/**
 * 自定义代码文件
 * 
 * - 注册钩子
 *      使用 Anon_Hook::add_action 和 Anon_Hook::add_filter 注册钩子
 * - 注册自定义路由
 *      使用 Anon_Config::addRoute 注册自定义路由
 * - 注册错误处理器
 *      使用 Anon_Config::addErrorHandler 注册错误处理器
 * - 添加自定义函数和类
 *      在文件中添加自定义函数和类
 * - 执行任何初始化代码
 *      在文件中执行任何初始化代码
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册动作钩子
// Anon_Hook::add_action('router_before_init', function () {
//     Anon_Debug::info('路由初始化前');
// });

// Anon_Hook::add_action('user_after_add', function ($userId, $name, $email, $group) {
//     // 用户添加后执行
//     Anon_Debug::info("新用户注册: {$name} ({$email})");
// });

// Anon_Hook::add_action('auth_after_set_cookies', function ($userId, $username) {
//     // 用户登录后执行
//     Anon_Debug::info("用户登录: {$username} (ID: {$userId})");
// });

// 注册过滤器钩子
// Anon_Hook::add_filter('response_data', function ($data) {
//     // 修改响应数据
//     return $data;
// });

// Anon_Hook::add_filter('user_info', function ($userInfo, $uid) {
//     // 修改用户信息
//     return $userInfo;
// });

// 注册 Widget
// $widget = Anon_Widget::getInstance();
// $widget->register('my_widget', '我的组件', function ($args) {
//     echo '<div>' . Anon_Helper::escHtml($args['title'] ?? '') . '</div>';
// }, ['description' => '这是一个示例组件']);

// 权限检查
// $capability = Anon_Capability::getInstance();
// if ($capability->currentUserCan('manage_options')) {
//     // 有权限执行
// }

// 注册自定义路由
// Anon_Config::addRoute('/api/custom', function () {
//     Anon_Common::Header();
//     Anon_ResponseHelper::success(['message' => '自定义路由']);
// });

// 注册错误处理器
// Anon_Config::addErrorHandler(404, function () {
//     Anon_Common::Header(404);
//     Anon_ResponseHelper::notFound('页面不存在');
// });


Anon_Config::addRoute('/test', function () {
    Anon_Common::Header();

    try {
        Anon_RequestHelper::requireMethod('GET');
        
        if (Anon_Check::isLoggedIn()) {
            // 已登录
            Anon_ResponseHelper::success(['message' => '已登录']);
        } else {
            // 未登录
            Anon_ResponseHelper::error('未登录');
        }

    } catch (Exception $e) {
        Anon_ResponseHelper::handleException($e);
    }
});