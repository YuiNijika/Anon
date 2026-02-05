<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Setup 文件辅助类
 * 为 app/setup.php 提供 $this 上下文，支持调用 siteUrl() 等方法
 * 继承自 ThemeHelper 以复用其所有方法
 */
class Anon_Cms_Theme_Setup_Helper extends Anon_Cms_Theme_Helper
{
    /**
     * 加载 setup.php 文件
     * @param string $setupFile setup.php 文件路径
     * @return mixed
     */
    public function loadSetupFile(string $setupFile)
    {
        return include $setupFile;
    }

    /**
     * 获取主题辅助对象
     * @return Anon_Cms_Theme_Helper
     */
    public function theme(): Anon_Cms_Theme_Helper
    {
        return new Anon_Cms_Theme_Helper($this->name());
    }
}
