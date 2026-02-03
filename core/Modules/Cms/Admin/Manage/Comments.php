<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Admin_Comments
{
    public static function initJavaScript()
    {
        try {
            Anon_System_Hook::add_action('theme_foot', function () {
                // 始终输出评论脚本并注册 __anonInitComments，供 Pjax 切页后重载评论区
?>
<script>
	(function() {
	    function runCommentsApp() {
	        var el = document.getElementById('comments-app');
	        if (!el || typeof Vue === 'undefined' || !Vue.createApp) return;
	        var postId = parseInt(el.getAttribute('data-post-id') || '0', 10);
	        if (!postId) return;
	        var isLoggedIn = el.getAttribute('data-comment-logged-in') === '1';
	
	        var template = el.innerHTML;
	        el.innerHTML = '';
	
	        Vue.createApp({
	            data: function() {
	                return {
	                    postId: postId,
	                    comments: [],
	                    isLoggedIn: isLoggedIn,
	                    message: '',
	                    messageType: 'info',
	                    loading: false,
	                    replyingTo: null,
	                    form: {
	                        name: '',
	                        email: '',
	                        url: '',
	                        content: '',
	                        parent_id: null
	                    }
	                };
	            },
	            computed: {
	                topLevelComments: function() {
	                    return this.comments || [];
	                },
	                totalCommentCount: function() {
	                    var list = this.comments || [];
	                    var n = 0;
	                    for (var i = 0; i < list.length; i++) {
	                        n += 1;
	                        n += (list[i].children && list[i].children.length) ? list[i].children.length : 0;
	                    }
	                    return n;
	                }
	            },
	            mounted: function() {
	                this.loadComments();
	            },
	            methods: {
	                setReplyTo: function(comment) {
	                    this.replyingTo = {
	                        id: comment.id,
	                        name: comment.name || '?'
	                    };
	                    this.form.parent_id = comment.id;
	                },
	                cancelReply: function() {
	                    this.replyingTo = null;
	                    this.form.parent_id = null;
	                },
	                formatDate: function(createdAt) {
	                    if (createdAt == null || createdAt === '') return '';
	                    var t;
	                    if (typeof createdAt === 'number') {
	                        t = createdAt < 1e12 ? createdAt * 1000 : createdAt;
	                    } else {
	                        t = (new Date(createdAt)).getTime();
	                    }
	                    if (!t || isNaN(t)) return '';
	                    var d = new Date(t);
	                    var y = d.getFullYear(),
	                        m = String(d.getMonth() + 1).padStart(2, '0'),
	                        day = String(d.getDate()).padStart(2, '0');
	                    var h = String(d.getHours()).padStart(2, '0'),
	                        min = String(d.getMinutes()).padStart(2, '0');
	                    return y + '-' + m + '-' + day + ' ' + h + ':' + min;
	                },
	                loadComments: function() {
	                    var vm = this;
	                    fetch('/anon/cms/comments?post_id=' + encodeURIComponent(this.postId), {
	                            method: 'GET',
	                            headers: {
	                                'Accept': 'application/json'
	                            },
	                            credentials: 'same-origin'
	                        })
	                        .then(function(r) {
	                            return r.json();
	                        })
	                        .then(function(res) {
	                            var data = res && res.data ? res.data : {};
	                            vm.comments = data.comments || [];
	                        })
	                        .catch(function() {
	                            vm.comments = [];
	                        });
	                },
	                submitComment: function() {
	                    var vm = this;
	                    this.message = '';
	                    if (!this.form.content.trim()) {
	                        this.messageType = 'error';
	                        this.message = this.isLoggedIn ? '请填写评论内容' : '请填写名称、邮箱和评论内容';
	                        return;
	                    }
	                    if (!this.isLoggedIn && (!this.form.name.trim() || !this.form.email.trim())) {
	                        this.messageType = 'error';
	                        this.message = '请填写名称、邮箱和评论内容';
	                        return;
	                    }
	                    this.loading = true;
	                    var params = {
	                        post_id: String(this.postId),
	                        content: this.form.content.trim(),
	                        url: this.form.url.trim()
	                    };
	                    if (this.form.parent_id) params.parent_id = String(this.form.parent_id);
	                    if (!this.isLoggedIn) {
	                        params.name = this.form.name.trim();
	                        params.email = this.form.email.trim();
	                    }
	                    var body = new URLSearchParams(params);
	                    fetch('/anon/cms/comments', {
	                            method: 'POST',
	                            headers: {
	                                'X-Requested-With': 'XMLHttpRequest',
	                                'Content-Type': 'application/x-www-form-urlencoded'
	                            },
	                            credentials: 'same-origin',
	                            body: body.toString()
	                        })
	                        .then(function(r) {
	                            return r.json();
	                        })
	                        .then(function(res) {
	                            vm.loading = false;
	                            if (res && res.code === 200) {
	                                vm.messageType = 'info';
	                                vm.message = res.message || '评论已提交，待审核通过后会显示。';
	                                vm.form = {
	                                    name: '',
	                                    email: '',
	                                    url: '',
	                                    content: '',
	                                    parent_id: null
	                                };
	                                vm.replyingTo = null;
	                                vm.loadComments();
	                            } else {
	                                vm.messageType = 'error';
	                                vm.message = (res && res.message) ? res.message : '提交失败，请重试。';
	                            }
	                        })
	                        .catch(function() {
	                            vm.loading = false;
	                            vm.messageType = 'error';
	                            vm.message = '提交失败，请检查网络后重试。';
	                        });
	                }
	            },
	            template: template
	        }).mount('#comments-app');
	    }
	    runCommentsApp();
	    window.__anonInitComments = runCommentsApp;
	})();
</script>
<?php
            });
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 解析 User-Agent，返回可读的浏览器与系统摘要
     * @param string|null $ua
     * @return array{browser: string, os: string}
     */
    private static function parseUserAgent($ua): array
    {
        $browser = '';
        $os = '';
        if (!is_string($ua) || $ua === '') {
            return ['browser' => '-', 'os' => '-'];
        }
        if (preg_match('/Edge\/(\d+)/i', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/Edg\/(\d+)/i', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/OPR\/(\d+)/i', $ua, $m)) {
            $browser = 'Opera ' . $m[1];
        } elseif (preg_match('/Chrome\/(\d+)/i', $ua, $m)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('/Safari\/(\d+)/i', $ua, $m) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari ' . $m[1];
        } elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/MSIE (\d+)|Trident\/.*rv:(\d+)/i', $ua, $m)) {
            $browser = 'IE ' . (isset($m[2]) && $m[2] ? $m[2] : $m[1]);
        } else {
            $browser = strlen($ua) > 60 ? substr($ua, 0, 57) . '...' : $ua;
        }
        if (preg_match('/Windows NT 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/Windows NT 6\.2/i', $ua)) {
            $os = 'Windows 8';
        } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } else {
            $os = '-';
        }
        if ($browser === '') {
            $browser = '-';
        }
        return ['browser' => $browser, 'os' => $os];
    }

    /**
     * 评论列表，支持高级筛选
     * 参数: page, page_size, status, post_id, type, keyword, is_reply, date_from, date_to
     */
    public static function getList()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $get = $_GET ?? [];
            $page = isset($data['page']) ? max(1, (int) $data['page']) : (isset($get['page']) ? max(1, (int) $get['page']) : 1);
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int) $data['page_size'])) : (isset($get['page_size']) ? max(1, min(100, (int) $get['page_size'])) : 20);
            $status = isset($data['status']) ? trim((string) $data['status']) : (isset($get['status']) ? trim((string) $get['status']) : '');
            $postId = isset($data['post_id']) ? (int) $data['post_id'] : (isset($get['post_id']) ? (int) $get['post_id'] : 0);
            $type = isset($data['type']) ? trim((string) $data['type']) : (isset($get['type']) ? trim((string) $get['type']) : '');
            $keyword = isset($data['keyword']) ? trim((string) $data['keyword']) : (isset($get['keyword']) ? trim((string) $get['keyword']) : '');
            $isReply = isset($data['is_reply']) ? $data['is_reply'] : (isset($get['is_reply']) ? $get['is_reply'] : null);
            $dateFrom = isset($data['date_from']) ? trim((string) $data['date_from']) : (isset($get['date_from']) ? trim((string) $get['date_from']) : '');
            $dateTo = isset($data['date_to']) ? trim((string) $data['date_to']) : (isset($get['date_to']) ? trim((string) $get['date_to']) : '');

            $db = Anon_Database::getInstance();
            $countQuery = $db->db('comments');
            $listQuery = $db->db('comments');

            if ($status !== '') {
                $countQuery->where('status', $status);
                $listQuery->where('status', $status);
            }
            if ($postId > 0) {
                $countQuery->where('post_id', $postId);
                $listQuery->where('post_id', $postId);
            }
            if ($type === 'user' || $type === 'guest') {
                $countQuery->where('type', $type);
                $listQuery->where('type', $type);
            }
            if ($keyword !== '') {
                $countQuery->where('content', 'LIKE', '%' . $keyword . '%');
                $listQuery->where('content', 'LIKE', '%' . $keyword . '%');
            }
            if ($isReply !== null && $isReply !== '') {
                $isReplyInt = (int) $isReply;
                if ($isReplyInt === 1) {
                    $countQuery->whereNested(function ($q) {
                        $q->whereNull('parent_id')->orWhere('parent_id', 0);
                    });
                    $listQuery->whereNested(function ($q) {
                        $q->whereNull('parent_id')->orWhere('parent_id', 0);
                    });
                } elseif ($isReplyInt === 2) {
                    $countQuery->whereNotNull('parent_id')->where('parent_id', '>', 0);
                    $listQuery->whereNotNull('parent_id')->where('parent_id', '>', 0);
                }
            }
            if ($dateFrom !== '') {
                $ts = is_numeric($dateFrom) ? (int) $dateFrom : strtotime($dateFrom . ' 00:00:00');
                if ($ts) {
                    $countQuery->where('created_at', '>=', date('Y-m-d H:i:s', $ts));
                    $listQuery->where('created_at', '>=', date('Y-m-d H:i:s', $ts));
                }
            }
            if ($dateTo !== '') {
                $ts = is_numeric($dateTo) ? (int) $dateTo : strtotime($dateTo . ' 23:59:59');
                if ($ts) {
                    $countQuery->where('created_at', '<=', date('Y-m-d H:i:s', $ts));
                    $listQuery->where('created_at', '<=', date('Y-m-d H:i:s', $ts));
                }
            }

            $total = $countQuery->count();
            $rows = $listQuery
                ->orderBy('created_at', 'DESC')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();
            if (!is_array($rows)) {
                $rows = [];
            }
            $postIds = array_unique(array_column($rows, 'post_id'));
            $postTitles = [];
            if (!empty($postIds)) {
                $posts = $db->db('posts')->select(['id', 'title'])->whereIn('id', array_values($postIds))->get();
                if (is_array($posts)) {
                    foreach ($posts as $p) {
                        $postTitles[(int) $p['id']] = $p['title'] ?? '';
                    }
                }
            }
            $uids = [];
            foreach ($rows as $r) {
                if (!empty($r['uid']) && (int) $r['uid'] > 0) {
                    $uids[(int) $r['uid']] = true;
                }
            }
            $userRepo = isset($db->userRepository) ? $db->userRepository : null;
            $userMap = [];
            if (!empty($uids) && $userRepo && method_exists($userRepo, 'getUserInfo')) {
                foreach (array_keys($uids) as $uid) {
                    $user = $userRepo->getUserInfo($uid);
                    if ($user) {
                        $userMap[$uid] = [
                            'name' => isset($user['display_name']) && (string) $user['display_name'] !== ''
                                ? (string) $user['display_name']
                                : (string) ($user['name'] ?? ''),
                            'email' => (string) ($user['email'] ?? ''),
                            'avatar' => (string) ($user['avatar'] ?? ''),
                        ];
                    }
                }
            }
            foreach ($rows as &$row) {
                $row['post_title'] = $postTitles[(int) ($row['post_id'] ?? 0)] ?? '';
                if (!empty($row['type']) && $row['type'] === 'user' && !empty($row['uid']) && isset($userMap[(int) $row['uid']])) {
                    $row['name'] = $userMap[(int) $row['uid']]['name'];
                    $row['email'] = $userMap[(int) $row['uid']]['email'];
                    $row['avatar'] = $userMap[(int) $row['uid']]['avatar'];
                } elseif ((!isset($row['avatar']) || $row['avatar'] === '') && !empty($row['email']) && $userRepo && method_exists($userRepo, 'getAvatarByEmail')) {
                    $row['avatar'] = $userRepo->getAvatarByEmail($row['email']);
                }
                if (isset($row['created_at']) && is_string($row['created_at'])) {
                    $row['created_at'] = strtotime($row['created_at']);
                }
                $uaParsed = self::parseUserAgent($row['user_agent'] ?? null);
                $row['ua_browser'] = $uaParsed['browser'];
                $row['ua_os'] = $uaParsed['os'];
                $row['is_reply'] = !empty($row['parent_id']) && (int) $row['parent_id'] > 0;
            }
            unset($row);
            $byId = [];
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }
            foreach ($rows as &$row) {
                if (!empty($row['parent_id']) && isset($byId[(int) $row['parent_id']])) {
                    $row['reply_to_name'] = (string) ($byId[(int) $row['parent_id']]['name'] ?? '');
                }
            }
            unset($row);

            Anon_Http_Response::success([
                'list' => $rows,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取评论列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /** PUT body: id, [status], [content] */
    public static function update()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            if ($id <= 0) {
                Anon_Http_Response::error('参数无效', 400);
                return;
            }
            $db = Anon_Database::getInstance();
            $exists = $db->db('comments')->where('id', $id)->first();
            if (!$exists) {
                Anon_Http_Response::error('评论不存在', 404);
                return;
            }
            $update = [];
            $status = isset($data['status']) ? trim((string) $data['status']) : '';
            $allowedStatus = ['approved', 'pending', 'spam', 'trash'];
            if ($status !== '' && in_array($status, $allowedStatus, true)) {
                $update['status'] = $status;
            }
            if (array_key_exists('content', $data)) {
                $content = is_string($data['content']) ? trim($data['content']) : '';
                $update['content'] = $content;
            }
            if (empty($update)) {
                Anon_Http_Response::error('请提供 status 或 content', 400);
                return;
            }
            $db->db('comments')->where('id', $id)->update($update);
            Anon_Http_Response::success(null, '更新成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /** Delete comment */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            if ($id <= 0) {
                Anon_Http_Response::error('参数无效', 400);
                return;
            }
            $db = Anon_Database::getInstance();
            $affected = $db->db('comments')->where('id', $id)->delete();
            if ($affected > 0) {
                Anon_Http_Response::success(null, '删除成功');
            } else {
                Anon_Http_Response::error('评论不存在或已删除', 404);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

Anon_Cms_Admin_Comments::initJavaScript();
