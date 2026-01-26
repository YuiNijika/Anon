<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 附件管理类
 * 处理附件的上传、查询和删除功能
 */
class Anon_Cms_Admin_Attachments
{
    /**
     * 初始化附件静态路由
     * @return void
     */
    public static function initStaticRoutes()
    {
        $uploadDir = Anon_Main::APP_DIR . 'Upload/';
        Anon_System_Config::addStaticRoute(
            '/anon/static/upload/{filename}',
            /**
             * 获取附件文件路径
             * @return string|null 文件路径，失败返回 null
             */
            function () use ($uploadDir) {
                $filename = $_GET['filename'] ?? '';
                if (empty($filename)) {
                    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
                    if (preg_match('#/anon/static/upload/([^/]+)#', $requestPath, $matches)) {
                        $filename = $matches[1];
                    }
                }
                
                if (empty($filename)) {
                    return null;
                }
                
                $filePath = $uploadDir . basename($filename);
                if (!file_exists($filePath) || !is_readable($filePath)) {
                    return null;
                }
                
                return $filePath;
            },
            /**
             * 获取附件文件的 MIME 类型
             * @return string MIME 类型
             */
            function () use ($uploadDir) {
                $filename = $_GET['filename'] ?? '';
                if (empty($filename)) {
                    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
                    if (preg_match('#/anon/static/upload/([^/]+)#', $requestPath, $matches)) {
                        $filename = $matches[1];
                    }
                }
                
                if (empty($filename)) {
                    return 'application/octet-stream';
                }
                
                $filePath = $uploadDir . basename($filename);
                if (!file_exists($filePath)) {
                    return 'application/octet-stream';
                }
                
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    'mp4' => 'video/mp4',
                    'mp3' => 'audio/mpeg',
                    'pdf' => 'application/pdf',
                ];
                
                return $mimeTypes[$ext] ?? 'application/octet-stream';
            },
            31536000,
            false,
            [
                'header' => false,
                'requireLogin' => false,
                'requireAdmin' => false,
                'method' => 'GET',
                'token' => false,
            ]
        );
    }

    /**
     * 获取附件列表
     * @return void
     */
    public static function get()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
            $mimeType = isset($data['mime_type']) ? trim($data['mime_type']) : null;
            
            /**
             * 构建查询条件
             */
            $baseQuery = $db->db('attachments');
            
            if ($mimeType) {
                if (strpos($mimeType, '/') !== false) {
                    /**
                     * 精确匹配 MIME 类型
                     */
                    $baseQuery->where('mime_type', $mimeType);
                } else {
                    /**
                     * 类型匹配
                     */
                    $baseQuery->where('mime_type', 'LIKE', $mimeType . '/%');
                }
            }
            
            /**
             * 获取总数
             */
            $countQuery = $db->db('attachments');
            if ($mimeType) {
                if (strpos($mimeType, '/') !== false) {
                    $countQuery->where('mime_type', $mimeType);
                } else {
                    $countQuery->where('mime_type', 'LIKE', $mimeType . '/%');
                }
            }
            $total = $countQuery->count();
            
            /**
             * 获取列表数据
             */
            $attachments = $baseQuery
                ->orderBy('created_at', 'DESC')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();
            
            Anon_Http_Response::success([
                'list' => $attachments ?: [],
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取附件列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 上传附件
     * @return void
     */
    public static function upload()
    {
        try {
            /**
             * 文件上传处理
             */
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Anon_Http_Response::error('文件上传失败', 400);
                return;
            }
            
            $file = $_FILES['file'];
            $originalName = $file['name'];
            $tmpPath = $file['tmp_name'];
            $mimeType = $file['type'];
            $fileSize = $file['size'];
            
            /**
             * 生成唯一文件名
             */
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            
            /**
             * 上传目录
             */
            $uploadDir = Anon_Main::APP_DIR . 'Upload/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $filename;
            $url = '/anon/static/upload/' . $filename;
            
            if (!move_uploaded_file($tmpPath, $filePath)) {
                Anon_Http_Response::error('保存文件失败', 500);
                return;
            }
            
            $db = Anon_Database::getInstance();
            $userId = Anon_Http_Request::getUserId();
            
            $result = $db->db('attachments')->insert([
                'user_id' => $userId,
                'filename' => $filename,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'file_path' => $filePath,
                'url' => $url,
            ]);
            
            if ($result) {
                $attachment = $db->db('attachments')->where('id', $result)->first();
                Anon_Http_Response::success($attachment, '上传成功');
            } else {
                Anon_Http_Response::error('保存附件记录失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除附件
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $db = Anon_Database::getInstance();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                Anon_Http_Response::error('附件 ID 无效', 400);
                return;
            }
            
            $attachment = $db->db('attachments')->where('id', $id)->first();
            if (!$attachment) {
                Anon_Http_Response::error('附件不存在', 404);
                return;
            }
            
            /**
             * 删除文件
             */
            if (file_exists($attachment['file_path'])) {
                @unlink($attachment['file_path']);
            }
            
            /**
             * 删除记录
             */
            $result = $db->db('attachments')->where('id', $id)->delete();
            
            if ($result) {
                Anon_Http_Response::success(null, '删除成功');
            } else {
                Anon_Http_Response::error('删除失败', 500);
            }
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}

