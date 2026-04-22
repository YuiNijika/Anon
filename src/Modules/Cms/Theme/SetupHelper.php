<?php
namespace Anon\Modules\Cms\Theme;

use Anon\Widgets\CmsThemeHelper;
use Anon\Modules\Cms\Theme\Theme;
use Anon\Widgets\Cms\ThemeHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Setup 文件辅助类
 * 为 app/setup.php 提供 $this 上下文，支持调用 siteUrl() 等方法
 * 通过组合 ThemeHelper 复用其方法
 */
class SetupHelper
{
    private $helper;

    public function __construct(string $themeName)
    {
        $this->helper = new ThemeHelper($themeName);
    }

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
     * @return ThemeHelper
     */
    public function theme(): ThemeHelper
    {
        return new ThemeHelper($this->name());
    }

    public function name(): string
    {
        return $this->helper->name();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->helper->$name(...$arguments);
    }
}
