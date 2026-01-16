<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Statistics
{
    /**
     * 获取所有统计数据
     * @return array
     */
    public static function getAll(): array
    {
        return [
            'posts' => self::getPostsCount(),
            'comments' => self::getCommentsCount(),
            'attachments' => self::getAttachmentsCount(),
            'categories' => self::getCategoriesCount(),
            'tags' => self::getTagsCount(),
            'users' => self::getUsersCount(),
            'published_posts' => self::getPublishedPostsCount(),
            'draft_posts' => self::getDraftPostsCount(),
            'pending_comments' => self::getPendingCommentsCount(),
            'approved_comments' => self::getApprovedCommentsCount(),
        ];
    }

    /**
     * 安全执行查询并返回结果
     * @param callable $callback 查询回调函数
     * @param int $default 默认返回值
     * @return int
     */
    private static function safeQuery(callable $callback, int $default = 0): int
    {
        try {
            $result = $callback();
            return is_numeric($result) ? (int)$result : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * 获取文章总数
     * @return int
     */
    public static function getPostsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('posts')->count();
        });
    }

    /**
     * 获取已发布文章数
     * @return int
     */
    public static function getPublishedPostsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('posts')
                ->where('status', 'publish')
                ->count();
        });
    }

    /**
     * 获取草稿文章数
     * @return int
     */
    public static function getDraftPostsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('posts')
                ->where('status', 'draft')
                ->count();
        });
    }

    /**
     * 获取评论总数
     * @return int
     */
    public static function getCommentsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('comments')->count();
        });
    }

    /**
     * 获取待审核评论数
     * @return int
     */
    public static function getPendingCommentsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('comments')
                ->where('status', 'pending')
                ->count();
        });
    }

    /**
     * 获取已通过评论数
     * @return int
     */
    public static function getApprovedCommentsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('comments')
                ->where('status', 'approved')
                ->count();
        });
    }

    /**
     * 获取附件总数
     * @return int
     */
    public static function getAttachmentsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('attachments')->count();
        });
    }

    /**
     * 获取附件总大小（字节）
     * @return int
     */
    public static function getAttachmentsSize(): int
    {
        return self::safeQuery(function () {
            $result = Anon_Database_QueryBuilder::table('attachments')
                ->select('SUM(`file_size`) as total_size')
                ->first();
            return (int)($result['total_size'] ?? 0);
        });
    }

    /**
     * 获取分类总数
     * @return int
     */
    public static function getCategoriesCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('metas')
                ->where('type', 'category')
                ->count();
        });
    }

    /**
     * 获取标签总数
     * @return int
     */
    public static function getTagsCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('metas')
                ->where('type', 'tag')
                ->count();
        });
    }

    /**
     * 获取用户总数
     * @return int
     */
    public static function getUsersCount(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('users')->count();
        });
    }

    /**
     * 获取文章浏览量总和
     * @return int
     */
    public static function getTotalViews(): int
    {
        return self::safeQuery(function () {
            $result = Anon_Database_QueryBuilder::table('posts')
                ->select('SUM(`views`) as total_views')
                ->first();
            return (int)($result['total_views'] ?? 0);
        });
    }
}

