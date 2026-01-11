<?php
/**
 * 自定义代码文件
 * 
 * - 注册钩子
 *      使用 Anon_System_Hook::add_action 和 Anon_System_Hook::add_filter 注册钩子
 * - 扩展权限系统
 *      使用 anon_auth_capabilities 过滤器扩展角色权限配置
 *      使用 anon_auth_capabilities_remove 过滤器移除角色权限
 * - 注册自定义路由
 *      使用 Anon_System_Config::addRoute 注册自定义路由
 * - 注册错误处理器
 *      使用 Anon_System_Config::addErrorHandler 注册错误处理器
 * - 添加自定义函数和类
 *      在文件中添加自定义函数和类
 * - 执行任何初始化代码
 *      在文件中执行任何初始化代码
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册动作钩子
// Anon_System_Hook::add_action('router_before_init', function () {
//     Anon_Debug::info('路由初始化前');
// });

// Anon_System_Hook::add_action('user_after_add', function ($userId, $name, $email, $group) {
//     // 用户添加后执行
//     Anon_Debug::info("新用户注册: {$name} ({$email})");
// });

// Anon_System_Hook::add_action('auth_after_set_cookies', function ($userId, $username) {
//     // 用户登录后执行
//     Anon_Debug::info("用户登录: {$username} (ID: {$userId})");
// });

// 注册过滤器钩子
// Anon_System_Hook::add_filter('response_data', function ($data) {
//     // 修改响应数据
//     return $data;
// });

// Anon_System_Hook::add_filter('user_info', function ($userInfo, $uid) {
//     // 修改用户信息
//     return $userInfo;
// });

// 权限检查
// $capability = Anon_Auth_Capability::getInstance();
// if ($capability->currentUserCan('manage_options')) {
//     // 有权限执行
// }

// 扩展权限系统配置
// Anon_System_Hook::add_filter('anon_auth_capabilities', function($capabilities) {
//     // 为现有角色添加新权限
//     $capabilities['admin'][] = 'manage_custom_feature';
//     $capabilities['editor'][] = 'edit_custom_content';
//     
//     // 添加新角色
//     $capabilities['moderator'] = [
//         'edit_posts',
//         'delete_posts',
//         'moderate_comments',
//     ];
//     
//     // 为特定角色添加资源级权限
//     $capabilities['author'][] = 'post:create';
//     $capabilities['author'][] = 'post:edit';
//     
//     return $capabilities;
// });

// 移除权限系统配置
// Anon_System_Hook::add_filter('anon_auth_capabilities_remove', function($removeList) {
//     // 从 admin 角色移除 manage_widgets 权限
//     $removeList['admin'] = ['manage_widgets'];
//     
//     // 从 editor 角色移除多个权限
//     $removeList['editor'] = ['delete_posts', 'publish_posts'];
//     
//     return $removeList;
// });

// 注册自定义路由
// Anon_System_Config::addRoute('/api/custom', function () {
//     Anon_Common::Header();
//     Anon_Http_Response::success(['message' => '自定义路由']);
// });

// 注册错误处理器
// Anon_System_Config::addErrorHandler(404, function () {
//     Anon_Common::Header(404);
//     Anon_Http_Response::notFound('页面不存在');
// });