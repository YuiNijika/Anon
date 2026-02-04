<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    '基础设置' => [
        'color_scheme' => [
            'type' => 'select',
            'label' => '配色方案',
            'default' => 'dark',
            'options' => [
                'dark' => '深色',
                'light' => '浅色'
            ],
        ],
        'post_count' => [
            'type' => 'number',
            'label' => '首页文章数',
            'default' => 10,
            'min' => 1,
            'max' => 50,
        ],
        'navbar_links' => [
            'type' => 'text_list',
            'label' => '顶部导航',
            'description' => '网站顶部导航, 格式为 title|url',
            'default' => [
                '首页|' . $this->siteUrl(),
            ],
            'listPlaceholder' => 'GitHub|https://github.com/YuiNijika/Anon',
        ],
        'icp_code' => [
            'type' => 'text',
            'label' => 'ICP备案号',
            'default' => '',
        ]
    ],
    '自定义代码' => [
        'custom_code_head' => [
            'type' => 'textarea',
            'label' => '头部代码',
            'description' => '在 <head> 标签内插入代码',
            'default' => '',
        ],
        'custom_code_foot' => [
            'type' => 'textarea',
            'label' => '底部代码',
            'description' => '在 </body> 标签前插入代码',
            'default' => '',
        ],
    ],
    '关于主题' => [

    ],
];
