<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_UI_Navbar
{
    /**
     * 获取顶部导航菜单
     * @return array
     */
    private static function getHeader()
    {
        $items = [
            [
                'key' => '/console',
                'label' => '控制台',
            ],
            [
                'key' => '/manage/posts',
                'label' => '文章管理',
            ],
            [
                'key' => '/manage/comments',
                'label' => '评论管理',
            ],
        ];

        /**
         * 允许插件通过钩子扩展顶部导航
         * 钩子名称 admin_navbar_header，参数 $items 数组，返回修改后的 $items 数组
         */
        $items = Anon_System_Hook::apply_filters('admin_navbar_header', $items);

        return $items;
    }

    /**
     * 获取侧边导航菜单
     * @return array
     */
    private static function getSidebar()
    {
        $items = [
            [
                'key' => '/console',
                'icon' => 'DashboardOutlined',
                'label' => '控制台',
            ],
            [
                'key' => '/statistics',
                'icon' => 'BarChartOutlined',
                'label' => '统计',
            ],
            [
                'key' => '/write',
                'icon' => 'EditOutlined',
                'label' => '撰写',
            ],
            [
                'key' => '/themes',
                'icon' => 'BgColorsOutlined',
                'label' => '主题',
            ],
            [
                'key' => '/plugins',
                'icon' => 'AppstoreOutlined',
                'label' => '插件',
            ],
            [
                'key' => 'manage',
                'icon' => 'FolderOutlined',
                'label' => '管理',
                'children' => [
                    [
                        'key' => '/manage/posts',
                        'icon' => 'EditOutlined',
                        'label' => '文章',
                    ],
                    [
                        'key' => '/manage/comments',
                        'icon' => 'CommentOutlined',
                        'label' => '评论',
                    ],
                    [
                        'key' => '/manage/users',
                        'icon' => 'UserOutlined',
                        'label' => '用户',
                    ],
                    [
                        'key' => '/manage/categories',
                        'icon' => 'FolderOutlined',
                        'label' => '分类',
                    ],
                    [
                        'key' => '/manage/tags',
                        'icon' => 'TagsOutlined',
                        'label' => '标签',
                    ],
                    [
                        'key' => '/manage/files',
                        'icon' => 'FileOutlined',
                        'label' => '附件',
                    ],
                ],
            ],
            [
                'key' => 'settings',
                'icon' => 'SettingOutlined',
                'label' => '设置',
                'children' => [
                    [
                        'key' => '/settings/basic',
                        'icon' => 'AppstoreOutlined',
                        'label' => '常规设置',
                    ],
                    [
                        'key' => '/settings/page',
                        'icon' => 'LinkOutlined',
                        'label' => '链接设置',
                    ],
                    [
                        'key' => '/settings/permission',
                        'icon' => 'SettingOutlined',
                        'label' => '权限设置',
                    ],
                ],
            ],
        ];

        /**
         * 允许插件通过钩子扩展侧边导航
         * 钩子名称 admin_navbar_sidebar，参数 $items 数组，返回修改后的 $items 数组
         */
        $items = Anon_System_Hook::apply_filters('admin_navbar_sidebar', $items);

        return $items;
    }

    /**
     * 辅助方法：将菜单项挂载到指定组
     * @param array $items 菜单数组引用
     * @param string $groupKey 目标组 Key
     * @param array $newItem 新菜单项
     * @return bool 是否成功挂载
     */
    public static function mount(array &$items, string $groupKey, array $newItem)
    {
        foreach ($items as &$item) {
            if (isset($item['key']) && $item['key'] === $groupKey) {
                if (!isset($item['children'])) {
                    $item['children'] = [];
                }
                $item['children'][] = $newItem;
                return true;
            }
            if (isset($item['children']) && is_array($item['children'])) {
                if (self::mount($item['children'], $groupKey, $newItem)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取导航数据
     * @return void
     */
    public static function get()
    {
        try {
            Anon_Http_Response::success([
                'header' => self::getHeader(),
                'sidebar' => self::getSidebar(),
            ], '获取导航成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}
