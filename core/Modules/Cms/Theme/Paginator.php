<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 分页器类
 * 提供类似 Typecho 的分页功能
 */
class Anon_Cms_Paginator
{
    /**
     * @var int 当前页码
     */
    private $currentPage;

    /**
     * @var int 每页数量
     */
    private $pageSize;

    /**
     * @var int 总记录数
     */
    private $total;

    /**
     * @var int 总页数
     */
    private $totalPages;

    /**
     * @param int $currentPage 当前页码
     * @param int $pageSize 每页数量
     * @param int $total 总记录数
     * @param int $totalPages 总页数
     */
    public function __construct(int $currentPage, int $pageSize, int $total, int $totalPages)
    {
        $this->currentPage = $currentPage;
        $this->pageSize = $pageSize;
        $this->total = $total;
        $this->totalPages = $totalPages;
    }

    /**
     * 获取当前页码
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 获取每页数量
     * @return int
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * 获取总记录数
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 获取总页数
     * @return int
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * 判断是否有上一页
     * @return bool
     */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * 判断是否有下一页
     * @return bool
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * 获取上一页页码
     * @return int
     */
    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    /**
     * 获取下一页页码
     * @return int
     */
    public function nextPage(): int
    {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /**
     * 生成分页链接
     * @param int $page 页码
     * @return string
     */
    public function pageLink(int $page): string
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedUrl = parse_url($currentUrl);
        $path = $parsedUrl['path'] ?? '/';
        $query = [];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        if ($page <= 1) {
            unset($query['page']);
        } else {
            $query['page'] = $page;
        }

        $url = $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * 获取所有页码数组
     * @param int $range 当前页前后显示的页码数量
     * @return array
     */
    public function getPageNumbers(int $range = 2): array
    {
        $pages = [];
        $start = max(1, $this->currentPage - $range);
        $end = min($this->totalPages, $this->currentPage + $range);

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }
}
