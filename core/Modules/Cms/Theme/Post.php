<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 文章/页面对象类
 * 提供类似 Typecho 的链式调用 API
 */
class Anon_Cms_Post
{
    /**
     * @var array 文章数据
     */
    private $data;

    /**
     * @param array $data 文章数据数组
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 获取文章 ID
     * @return int
     */
    public function id(): int
    {
        return (int)($this->data['id'] ?? 0);
    }

    /**
     * 获取标题
     * @return string
     */
    public function title(): string
    {
        return (string)($this->data['title'] ?? '');
    }

    /**
     * 获取内容
     * @return string
     */
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }

    /**
     * 获取摘要
     * @param int $length 长度
     * @return string
     */
    public function excerpt(int $length = 150): string
    {
        $content = $this->content();
        if (empty($content)) {
            return '';
        }

        $text = strip_tags($content);
        $text = preg_replace('/<!--markdown-->/', '', $text);
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/`[^`]*`/u', ' ', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/\[[^\]]*\]\([^)]+\)/u', ' ', $text);
        $text = preg_replace('/(^|\s)#+\s+/u', ' ', $text);
        $text = preg_replace('/[*_~>#-]{1,3}/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }

    /**
     * 获取 Slug
     * @return string
     */
    public function slug(): string
    {
        return (string)($this->data['slug'] ?? '');
    }

    /**
     * 获取类型
     * @return string
     */
    public function type(): string
    {
        return (string)($this->data['type'] ?? '');
    }

    /**
     * 获取状态
     * @return string
     */
    public function status(): string
    {
        return (string)($this->data['status'] ?? '');
    }

    /**
     * 获取创建时间
     * @return int
     */
    public function created(): int
    {
        $createdAt = $this->data['created_at'] ?? null;
        if (is_string($createdAt)) {
            return (int)strtotime($createdAt);
        }
        return (int)$createdAt;
    }

    /**
     * 获取创建时间 格式化
     * @param string $format 格式，默认 'Y-m-d H:i:s'
     * @return string
     */
    public function date(string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = $this->created();
        return $timestamp > 0 ? date($format, $timestamp) : '';
    }

    /**
     * 获取更新时间
     * @return int
     */
    public function modified(): int
    {
        $updatedAt = $this->data['updated_at'] ?? null;
        if (is_string($updatedAt)) {
            return (int)strtotime($updatedAt);
        }
        return (int)$updatedAt;
    }

    /**
     * 获取分类 ID
     * @return int|null
     */
    public function category(): ?int
    {
        $categoryId = $this->data['category_id'] ?? null;
        return $categoryId && $categoryId > 0 ? (int)$categoryId : null;
    }

    /**
     * 获取分类 ID，category() 的别名
     * @return int|null
     */
    public function categoryId(): ?int
    {
        return $this->category();
    }

    /**
     * 获取原始数据
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * 获取字段值
     * @param string $key 字段名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return $default;
    }

    /**
     * 检查字段是否存在
     * @param string $key 字段名
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}
