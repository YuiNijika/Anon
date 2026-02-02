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
     * 获取附件总大小
     * @return int
     */
    public static function getAttachmentsSize(): int
    {
        return self::safeQuery(function () {
            return Anon_Database_QueryBuilder::table('attachments')
                ->sum('file_size');
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
            return Anon_Database_QueryBuilder::table('posts')
                ->where('type', 'post')
                ->where('status', 'publish')
                ->sum('views');
        });
    }

    /**
     * 获取访问量趋势数据
     * @param int $days 天数，默认7天
     * @return array
     */
    public static function getViewsTrend(int $days = 7): array
    {
        try {
            $db = Anon_Database::getInstance();
            
            $firstLog = $db->db('access_logs')
                ->select(['created_at'])
                ->where('type', 'page')
                ->orderBy('created_at', 'ASC')
                ->first();
            
            if (!$firstLog || !isset($firstLog['created_at'])) {
                return [];
            }
            
            $firstDate = date('Y-m-d', strtotime($firstLog['created_at']));
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $daysDiff = (strtotime($endDate) - strtotime($firstDate)) / 86400;
            
            if ($daysDiff < $days) {
                $startDate = $firstDate;
            }
            
            $logs = $db->db('access_logs')
                ->select(['created_at'])
                ->where('created_at', '>=', $startDate . ' 00:00:00')
                ->where('created_at', '<=', $endDate . ' 23:59:59')
                ->where('type', 'page')
                ->get();
            
            $dateMap = [];
            foreach ($logs as $log) {
                $date = date('Y-m-d', strtotime($log['created_at']));
                if (!isset($dateMap[$date])) {
                    $dateMap[$date] = 0;
                }
                $dateMap[$date]++;
            }
            
            $result = [];
            $currentDate = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            
            while ($currentDate <= $endTimestamp) {
                $date = date('Y-m-d', $currentDate);
                $result[] = [
                    'date' => $date,
                    'count' => $dateMap[$date] ?? 0
                ];
                $currentDate = strtotime('+1 day', $currentDate);
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取最近30天文章发布趋势数据
     * @return array
     */
    public static function getPostsTrend(): array
    {
        try {
            $db = Anon_Database::getInstance();
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-29 days'));
            
            $posts = $db->db('posts')
                ->select(['created_at'])
                ->where('type', 'post')
                ->where('created_at', '>=', $startDate . ' 00:00:00')
                ->where('created_at', '<=', $endDate . ' 23:59:59')
                ->get();
            
            $dateMap = [];
            foreach ($posts as $post) {
                $date = date('Y-m-d', strtotime($post['created_at']));
                if (!isset($dateMap[$date])) {
                    $dateMap[$date] = 0;
                }
                $dateMap[$date]++;
            }
            
            $result = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $result[] = [
                    'date' => $date,
                    'count' => $dateMap[$date] ?? 0
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取文章状态分布数据
     * @return array
     */
    public static function getPostsStatusDistribution(): array
    {
        try {
            $db = Anon_Database::getInstance();
            $allPosts = $db->db('posts')
                ->select(['status'])
                ->where('type', 'post')
                ->get();
            
            $statusCounts = [];
            foreach ($allPosts as $post) {
                $status = $post['status'] ?? 'draft';
                if (!isset($statusCounts[$status])) {
                    $statusCounts[$status] = 0;
                }
                $statusCounts[$status]++;
            }
            
            $result = [];
            $statusMap = [
                'publish' => '已发布',
                'draft' => '草稿',
                'private' => '私有'
            ];
            
            foreach ($statusCounts as $status => $count) {
                $label = $statusMap[$status] ?? $status;
                $result[] = [
                    'type' => $label,
                    'value' => $count
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取评论状态分布数据
     * @return array
     */
    public static function getCommentsStatusDistribution(): array
    {
        try {
            $db = Anon_Database::getInstance();
            $allComments = $db->db('comments')
                ->select(['status'])
                ->get();
            
            $statusCounts = [];
            foreach ($allComments as $comment) {
                $status = $comment['status'] ?? 'pending';
                if (!isset($statusCounts[$status])) {
                    $statusCounts[$status] = 0;
                }
                $statusCounts[$status]++;
            }
            
            $result = [];
            $statusMap = [
                'pending' => '待审核',
                'approved' => '已通过',
                'spam' => '垃圾评论',
                'trash' => '已删除'
            ];
            
            foreach ($statusCounts as $status => $count) {
                $label = $statusMap[$status] ?? $status;
                $result[] = [
                    'type' => $label,
                    'value' => $count
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
}

