<?php

/**
 * CMS 主题辅助对象
 *
 * 主题/插件内通过 $this->theme() 获取，提供主题名、主题选项、站点/主题 URL 等，类似 Typecho 的 $this->options 与 themeUrl。
 * 需在 Cms/Theme/Options.php 与 Cms/Theme/Theme.php 加载后使用。
 *
 * @package Anon/Core/Widgets/Cms
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Theme_Helper
{
    /**
     * @var string 当前主题名
     */
    private $themeName;

    /**
     * @var Anon_Cms_Options_Proxy|null 选项代理缓存
     */
    private $optionsProxy;

    /**
     * 构造主题辅助对象
     * @param string $themeName 主题名，通常由 Anon_Cms_Theme::getCurrentTheme() 传入
     */
    public function __construct(string $themeName)
    {
        $this->themeName = $themeName;
    }

    /**
     * 当前主题名
     * @return string
     */
    public function name(): string
    {
        return $this->themeName;
    }

    /**
     * 获取主题信息
     * @param string|null $key 键名，如 name, version, author, description, url, screenshot
     * @return mixed
     */
    public function info(?string $key = null)
    {
        return Anon_Cms_Theme::info($key);
    }

    /**
     * 仅读当前主题选项，不包含插件与系统选项
     * @param string $key 选项键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Anon_Theme_Options::get($key, $default, $this->themeName);
    }

    /**
     * 统一选项代理，优先级 theme > plugin > system
     * 
     * 如果传入参数，则尝试获取具体选项值，类似 Typecho 的 options->key
     * 如果不传参数，则返回代理对象，支持 options->key 访问
     * 
     * @param string|null $name 选项名
     * @param mixed $default 默认值
     * @param bool|string $outputOrPriority 输出或优先级
     * @param string|null $priority 优先级
     * @return mixed|Anon_Cms_Options_Proxy
     */
    public function options(?string $name = null, $default = null, $outputOrPriority = false, ?string $priority = null)
    {
        if ($this->optionsProxy === null) {
            $this->optionsProxy = new Anon_Cms_Options_Proxy('theme', null, $this->themeName);
        }

        if ($name === null) {
            return $this->optionsProxy;
        }

        try {
            $result = $this->optionsProxy->get($name, $default, $outputOrPriority, $priority);

            if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                Anon_Debug::debug('[Theme Helper] options() 调用结果', [
                    'theme' => $this->name(),
                    'option_name' => $name,
                    'default' => $default,
                    'outputOrPriority' => $outputOrPriority,
                    'priority' => $priority,
                    'result' => $result,
                    'result_type' => gettype($result),
                    'result_is_null' => is_null($result),
                    'result_is_empty' => empty($result),
                    'result_is_object' => is_object($result),
                    'result_class' => is_object($result) ? get_class($result) : null
                ]);
            }

            if (is_object($result)) {
                if ($result instanceof Anon_Cms_Options_Proxy) {
                    if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                        Anon_Debug::error('[Theme Helper] options() 返回了代理对象', [
                            'theme' => $this->name(),
                            'option_name' => $name,
                            'result_type' => get_class($result),
                            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                        ]);
                    }
                    return $default;
                }
                if (method_exists($result, '__toString')) {
                    $stringValue = (string) $result;
                    if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                        Anon_Debug::warn('[Theme Helper] options() 返回了对象，已转换为字符串', [
                            'theme' => $this->name(),
                            'option_name' => $name,
                            'result_type' => get_class($result),
                            'converted_value' => $stringValue
                        ]);
                    }
                    return $stringValue;
                }
                if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                    Anon_Debug::error('[Theme Helper] options() 返回了无法转换的对象', [
                        'theme' => $this->name(),
                        'option_name' => $name,
                        'default' => $default,
                        'result_type' => get_class($result),
                        'result_methods' => get_class_methods($result),
                        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                    ]);
                }
                return $default;
            }
            return $result;
        } catch (Throwable $e) {
            if (class_exists('Anon_Debug') && Anon_Debug::isEnabled()) {
                Anon_Debug::error('[Theme Helper] options() 发生异常', [
                    'theme' => $this->name(),
                    'option_name' => $name,
                    'default' => $default,
                    'outputOrPriority' => $outputOrPriority,
                    'priority' => $priority,
                    'exception_type' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
                ]);
            }
            return $default;
        }
    }

    /**
     * 站点根 URL，带参数时拼接相对路径，类似 Typecho options->siteUrl
     * @param string $suffix 相对路径，如 /about、/post/1
     * @return string
     */
    public function siteUrl(string $suffix = ''): string
    {
        $base = Anon_Cms_Theme::getSiteBaseUrl();
        if ($suffix === '') {
            return $base;
        }
        $suffix = '/' . ltrim($suffix, '/');
        return $base . $suffix;
    }

    /**
     * 当前主题资源 URL，无参返回站点根 URL，带参返回该资源完整 URL，类似 Typecho options->themeUrl
     * @param string $path 资源相对路径，如 style.css、js/main.js
     * @return string
     */
    public function themeUrl(string $path = ''): string
    {
        $base = Anon_Cms_Theme::getSiteBaseUrl();
        if ($path === '') {
            return rtrim($base, '/');
        }
        $url = Anon_Cms_Theme::getAssetUrl($path);
        return $url !== '' ? $base . $url : rtrim($base, '/') . '/assets/files/' . ltrim($path, '/');
    }

    /**
     * 主题资源 URL，等同 themeUrl($path)，便于链式书写
     * @param string $path 资源相对路径
     * @return string
     */
    public function url(string $path = ''): string
    {
        return $this->themeUrl($path);
    }

    /**
     * 站点首页 URL，类似 Typecho options->index
     * @return string
     */
    public function index(): string
    {
        return Anon_Cms_Theme::getSiteBaseUrl();
    }

    /**
     * 输出或获取主题资源
     * @param string $path 资源路径
     * @param bool|string|null $forceNoCacheOrType 强制无缓存或指定类型
     * @param array $attributes 属性
     * @return string
     */
    public function assets(string $path, $forceNoCacheOrType = null, array $attributes = []): string
    {
        return Anon_Cms_Theme::assets($path, $forceNoCacheOrType, $attributes);
    }

    /**
     * 包含模板片段
     * @param string $partialName 片段名
     * @param array $data 数据
     * @return void
     */
    public function partial(string $partialName, array $data = []): void
    {
        Anon_Cms_Theme::partial($partialName, $data);
    }

    /**
     * 包含组件
     * @param string $componentPath 组件路径
     * @param array $data 数据
     * @return void
     */
    public function component(string $componentPath, array $data = []): void
    {
        Anon_Cms_Theme::components($componentPath, $data);
    }
    public function components(string $componentPath, array $data = []): void
    {
        Anon_Cms_Theme::components($componentPath, $data);
    }

    /**
     * 输出页面标题
     * @param string|null $title 标题
     * @param string $separator 分隔符
     * @param bool $reverse 反转
     * @return void
     */
    public function title(?string $title = null, string $separator = ' - ', bool $reverse = false): void
    {
        Anon_Cms_Theme::title($title, $separator, $reverse);
    }

    /**
     * 输出 SEO meta 标签
     * @param array $meta
     * @return void
     */
    public function meta(array $meta = []): void
    {
        Anon_Cms_Theme::meta($meta);
    }

    /**
     * 输出头部 Meta
     * @param array $overrides 覆盖
     * @return void
     */
    public function headMeta(array $overrides = []): void
    {
        Anon_Cms_Theme::headMeta($overrides);
    }
    public function header(array $overrides = []): void
    {
        Anon_Cms_Theme::headMeta($overrides);
    }

    /**
     * 输出底部 Meta
     * @return void
     */
    public function footMeta(): void
    {
        Anon_Cms_Theme::footMeta();
    }
    public function footer(): void
    {
        Anon_Cms_Theme::footMeta();
    }

    /**
     * 获取主题目录绝对路径
     * @return string
     */
    public function dir(): string
    {
        return Anon_Cms_Theme::getThemeDir();
    }

    /**
     * 检查是否为当前激活主题
     * @return bool
     */
    public function isActive(): bool
    {
        return Anon_Cms_Theme::getCurrentTheme() === $this->themeName;
    }

    /**
     * 输出样式表链接
     * @param string|array $styles 样式文件路径
     * @param array $attributes 额外属性
     * @return void
     */
    public function stylesheet($styles, array $attributes = []): void
    {
        Anon_Cms_Theme::stylesheet($styles, $attributes);
    }

    /**
     * 输出脚本标签
     * @param string|array $scripts 脚本文件路径
     * @param array $attributes 额外属性
     * @return void
     */
    public function script($scripts, array $attributes = []): void
    {
        Anon_Cms_Theme::script($scripts, $attributes);
    }

    /**
     * 输出 favicon 链接
     * @param string $path Favicon 路径
     * @return void
     */
    public function favicon(string $path = 'favicon.ico'): void
    {
        Anon_Cms_Theme::favicon($path);
    }

    /**
     * 转义 HTML 输出
     * @param string $text 文本
     * @param int $flags 转义标志
     * @return string
     */
    public function escape(string $text, int $flags = ENT_QUOTES): string
    {
        return Anon_Cms_Theme::escape($text, $flags);
    }

    /**
     * 渲染 Markdown 内容为 HTML
     * @param string $content
     * @return string
     */
    public function markdown(string $content): string
    {
        return Anon_Cms_Theme::markdown($content);
    }

    /**
     * 输出 JSON-LD 结构化数据
     * @param array $data 结构化数据数组
     * @return void
     */
    public function jsonLd(array $data): void
    {
        Anon_Cms_Theme::jsonLd($data);
    }

    /**
     * 判断当前页面类型
     * @param string $type
     * @return bool
     */
    public function is(string $type): bool
    {
        return Anon_Cms_Theme::is($type);
    }

    /**
     * 输出框架信息
     * @return string
     */
    public function framework(string $type): string
    {
        if ($type === 'name') {
            $frame = Anon_Common::NAME;
        } elseif ($type === 'version') {
            $frame = Anon_Common::VERSION;
        } elseif ($type === 'author') {
            $frame = Anon_Common::AUTHOR;
        } elseif ($type === 'author_url') {
            $frame = Anon_Common::AUTHOR_URL;
        } elseif ($type === 'license') {
            $frame = Anon_Common::LICENSE;
        } elseif ($type === 'license_text') {
            $frame = Anon_Common::LICENSE_TEXT();
        } elseif ($type === 'github') {
            $frame = Anon_Common::GITHUB;
        } else {
            $frame = Anon_Common::NAME;
        }
        return $frame;
    }

    /**
     * 输出服务器信息
     * @param string $type
     * @return string
     */
    public function server(string $type): string
    {
        if ($type === 'name') {
            $server = Anon_Common::server('name');
        } elseif ($type === 'version') {
            $server = Anon_Common::server('version');
        } elseif ($type === 'os') {
            $server = Anon_Common::server('os');
        } elseif ($type === 'os_version') {
            $server = Anon_Common::server('os_version');
        } elseif ($type === 'ip') {
            $server = Anon_Common::server('ip');
        } elseif ($type === 'port') {
            $server = (string)Anon_Common::server('port');
        } elseif ($type === 'url') {
            $server = Anon_Common::server('url');
        } elseif ($type === 'protocol') {
            $server = Anon_Common::server('protocol');
        } elseif ($type === 'domain') {
            $server = Anon_Common::server('domain');
        } elseif ($type === 'isHttps') {
            $server = Anon_Common::server('is_https') ? 'true' : 'false';
        } elseif ($type === 'php') {
            $server = Anon_Common::server('php');
        } else {
            $server = Anon_Common::server('name');
        }
        return (string)$server;
    }
}
