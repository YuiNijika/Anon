<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 附件管理类
 * 处理附件的上传、查询和删除功能
 */
class Anon_Cms_Admin_Attachments
{
    /**
     * 根据 MIME 类型推断文件分类目录
     * @param string $mimeType
     * @return string
     */
    private static function getFileTypeByMime(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (strpos($mimeType, 'audio/') === 0) return 'audio';
        if ($mimeType === 'application/pdf') return 'document';
        return 'other';
    }

    /**
     * 获取允许的文件后缀列表
     * @param string $fileType 文件类型：image, video, audio, document, other
     * @return array
     */
    private static function getAllowedExtensions(string $fileType): array
    {
        $uploadAllowedTypesValue = Anon_Cms_Options::get('upload_allowed_types', '');
        $uploadAllowedTypes = [];
        
        if (is_array($uploadAllowedTypesValue)) {
            $uploadAllowedTypes = $uploadAllowedTypesValue;
        } elseif (is_string($uploadAllowedTypesValue) && !empty($uploadAllowedTypesValue)) {
            $decoded = json_decode($uploadAllowedTypesValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $uploadAllowedTypes = $decoded;
            }
        }
        
        if (empty($uploadAllowedTypes)) {
            $uploadAllowedTypes = [
                'image' => Anon_Cms_Options::get('upload_allowed_image', 'gif,jpg,jpeg,png,tiff,bmp,webp,avif'),
                'media' => Anon_Cms_Options::get('upload_allowed_media', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv'),
                'document' => Anon_Cms_Options::get('upload_allowed_document', 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'),
                'other' => Anon_Cms_Options::get('upload_allowed_other', ''),
            ];
        }
        
        $allowedStr = $uploadAllowedTypes[$fileType] ?? '';
        if (empty($allowedStr)) {
            return [];
        }
        
        $extensions = array_map('trim', explode(',', $allowedStr));
        return array_filter($extensions, function($ext) {
            return !empty($ext);
        });
    }

    /**
     * 通过文件头检测真实的 MIME 类型
     * @param string $filePath 文件路径
     * @return string|null MIME 类型，失败返回 null
     */
    private static function detectMimeTypeFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }
        
        // 优先使用 finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mimeType && $mimeType !== 'application/octet-stream') {
                    return $mimeType;
                }
            }
        }
        
        // 回退到 mime_content_type
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType && $mimeType !== 'application/octet-stream') {
                return $mimeType;
            }
        }
        
        // 图片文件使用 getimagesize
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo && isset($imageInfo['mime'])) {
            return $imageInfo['mime'];
        }
        
        return null;
    }

    /**
     * 验证文件后缀是否允许
     * @param string $ext 文件后缀
     * @param string $fileType 文件类型
     * @return bool
     */
    private static function isExtensionAllowed(string $ext, string $fileType): bool
    {
        $ext = strtolower(trim($ext));
        if (empty($ext)) {
            return false;
        }
        
        $allowedExtensions = self::getAllowedExtensions($fileType);
        if (empty($allowedExtensions)) {
            return true;
        }
        
        return in_array($ext, $allowedExtensions, true);
    }

    /**
     * 验证文件扩展名与 MIME 类型是否匹配
     * @param string $ext 文件扩展名
     * @param string $mimeType MIME 类型
     * @return bool
     */
    private static function isExtensionMatchMimeType(string $ext, string $mimeType): bool
    {
        $ext = strtolower(trim($ext));
        $mimeType = strtolower(trim($mimeType));
        
        $mimeExtMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'image/bmp' => ['bmp'],
            'image/tiff' => ['tiff', 'tif'],
            'image/avif' => ['avif'],
            'video/mp4' => ['mp4'],
            'video/quicktime' => ['mov'],
            'video/x-msvideo' => ['avi'],
            'video/x-ms-wmv' => ['wmv'],
            'video/x-flv' => ['flv'],
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/ogg' => ['ogg', 'oga'],
            'audio/mp4' => ['m4a'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        ];
        
        if (!isset($mimeExtMap[$mimeType])) {
            return false;
        }
        
        return in_array($ext, $mimeExtMap[$mimeType], true);
    }

    /**
     * 生成上传文件名
     * @param string $ext
     * @return array{base:string, filename:string}
     */
    private static function buildRandomFilename(string $ext): array
    {
        $ext = strtolower(trim($ext));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $rand = bin2hex(random_bytes(8));
        $base = $rand . '-' . time();
        $filename = $ext ? ($base . '.' . $ext) : $base;
        return ['base' => $base, 'filename' => $filename];
    }

    /**
     * 初始化附件静态路由
     * @return void
     */
    public static function initStaticRoutes()
    {
        $uploadRoot = Anon_Main::APP_DIR . 'Upload/';

        /**
         * 获取原始文件路径
         * @param string $fileType
         * @param string $base
         * @return string|null
         */
        $resolveOriginalFile = function (string $fileType, string $base) use ($uploadRoot) {
            $fileType = strtolower(trim($fileType));
            $fileType = preg_replace('/[^a-z0-9_-]/', '', $fileType);
            if (empty($fileType)) {
                return null;
            }

            $base = basename($base);
            $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
            if (empty($base)) {
                return null;
            }

            $dir = $uploadRoot . $fileType . '/';
            if (!is_dir($dir)) {
                return null;
            }

            $matches = glob($dir . $base . '.*');
            if (empty($matches)) {
                return null;
            }
            return $matches[0];
        };

        /**
         * 图片格式转换
         * @param string $base
         * @param string $format
         * @return string|null
         */
        $resolveProcessedImage = function (string $fileType, string $base, string $format) use ($uploadRoot, $resolveOriginalFile) {
            $format = strtolower(trim($format));
            if (!in_array($format, ['webp', 'jpg', 'jpeg', 'png'], true)) {
                return null;
            }

            $originalPath = $resolveOriginalFile($fileType, $base);
            if (empty($originalPath) || !file_exists($originalPath) || !is_readable($originalPath)) {
                return null;
            }

            $info = @getimagesize($originalPath);
            if (!$info || empty($info['mime'])) {
                return null;
            }
            if (strpos($info['mime'], 'image/') !== 0) {
                return null;
            }

            $processedDir = $uploadRoot . strtolower(trim($fileType)) . '/processed/';
            if (!is_dir($processedDir)) {
                @mkdir($processedDir, 0755, true);
            }

            // 生成处理后的缓存文件
            $base = basename($base);
            $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
            if (empty($base)) {
                return null;
            }
            $targetPath = $processedDir . $base . '.' . $format;

            // 命中缓存直接返回
            if (file_exists($targetPath) && filemtime($targetPath) >= filemtime($originalPath)) {
                return $targetPath;
            }

            // GD 不可用时返回失败
            if (!function_exists('imagecreatefromstring')) {
                return null;
            }

            $raw = @file_get_contents($originalPath);
            if ($raw === false) {
                return null;
            }

            $src = @imagecreatefromstring($raw);
            if (!$src) {
                return null;
            }

            // PNG 与 WebP 保持透明背景
            if (in_array($format, ['png', 'webp'], true)) {
                @imagealphablending($src, true);
                @imagesavealpha($src, true);
            }

            $ok = false;
            if ($format === 'webp') {
                $quality = 80;
                $ok = function_exists('imagewebp') ? @imagewebp($src, $targetPath, $quality) : false;
            } elseif ($format === 'png') {
                // PNG 压缩等级 0 到 9
                $ok = @imagepng($src, $targetPath, 6);
            } else {
                // JPG 质量 0 到 100
                $ok = @imagejpeg($src, $targetPath, 80);
            }

            @imagedestroy($src);

            if (!$ok || !file_exists($targetPath)) {
                return null;
            }

            return $targetPath;
        };

        Anon_System_Config::addStaticRoute(
            '/anon/static/upload/{filetype}/{file}/{format}',
            function () use ($resolveProcessedImage) {
                $fileType = $_GET['filetype'] ?? '';
                $file = $_GET['file'] ?? '';
                $format = $_GET['format'] ?? '';
                if (empty($fileType) || empty($file) || empty($format)) {
                    return null;
                }
                return $resolveProcessedImage($fileType, $file, $format);
            },
            function () {
                $format = strtolower(trim($_GET['format'] ?? ''));
                $mimeMap = [
                    'webp' => 'image/webp',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                ];
                return $mimeMap[$format] ?? 'application/octet-stream';
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

        Anon_System_Config::addStaticRoute(
            '/anon/static/upload/{filetype}/{file}',
            /**
             * 获取附件文件路径
             * @return string|null 文件路径，失败返回 null
             */
            function () use ($resolveOriginalFile) {
                $fileType = $_GET['filetype'] ?? '';
                $file = $_GET['file'] ?? '';
                if (empty($fileType) || empty($file)) {
                    return null;
                }

                $filePath = $resolveOriginalFile($fileType, $file);
                if (empty($filePath)) {
                    return null;
                }
                if (!file_exists($filePath) || !is_readable($filePath)) {
                    return null;
                }
                
                return $filePath;
            },
            /**
             * 获取附件文件的 MIME 类型
             * @return string MIME 类型
             */
            function () use ($resolveOriginalFile) {
                $fileType = $_GET['filetype'] ?? '';
                $file = $_GET['file'] ?? '';
                if (empty($fileType) || empty($file)) {
                    return 'application/octet-stream';
                }

                $filePath = $resolveOriginalFile($fileType, $file);
                if (empty($filePath)) {
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
            
            // 根据文件名推断 MIME 类型进行筛选
            if ($mimeType) {
                $extensions = [];
                if ($mimeType === 'image') {
                    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                } elseif ($mimeType === 'video') {
                    $extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv'];
                } elseif ($mimeType === 'audio') {
                    $extensions = ['mp3', 'wav', 'ogg', 'm4a'];
                } elseif ($mimeType === 'document') {
                    $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                }
                
                if (!empty($extensions)) {
                    $baseQuery->where(function ($query) use ($extensions) {
                        $first = true;
                        foreach ($extensions as $ext) {
                            if ($first) {
                                $query->where('filename', 'LIKE', '%.' . $ext);
                                $first = false;
                            } else {
                                $query->orWhere('filename', 'LIKE', '%.' . $ext);
                            }
                        }
                    });
                } elseif (strpos($mimeType, '/') !== false) {
                    // 精确 MIME 类型匹配，通过文件扩展名推断
                    $mimeExtMap = [
                        'image/jpeg' => ['jpg', 'jpeg'],
                        'image/png' => ['png'],
                        'image/gif' => ['gif'],
                        'image/webp' => ['webp'],
                        'video/mp4' => ['mp4'],
                        'audio/mpeg' => ['mp3'],
                        'application/pdf' => ['pdf'],
                    ];
                    if (isset($mimeExtMap[$mimeType])) {
                        $baseQuery->where(function ($query) use ($mimeExtMap, $mimeType) {
                            $first = true;
                            foreach ($mimeExtMap[$mimeType] as $ext) {
                                if ($first) {
                                    $query->where('filename', 'LIKE', '%.' . $ext);
                                    $first = false;
                                } else {
                                    $query->orWhere('filename', 'LIKE', '%.' . $ext);
                                }
                            }
                        });
                    }
                }
            }
            
            /**
             * 获取总数
             */
            $countQuery = clone $baseQuery;
            $total = $countQuery->count();
            
            /**
             * 获取列表数据
             */
            $attachments = $baseQuery
                ->orderBy('updated_at', 'DESC')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();

            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as &$a) {
                    if (!empty($a['filename']) && is_string($a['filename'])) {
                        $base = pathinfo($a['filename'], PATHINFO_FILENAME);
                        $ext = strtolower(pathinfo($a['filename'], PATHINFO_EXTENSION));
                        
                        // 根据扩展名推断文件类型
                        $mimeType = '';
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                            $mimeType = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                        } elseif (in_array($ext, ['mp4', 'avi', 'mov'], true)) {
                            $mimeType = 'video/' . ($ext === 'mp4' ? 'mp4' : 'quicktime');
                        } elseif (in_array($ext, ['mp3', 'wav'], true)) {
                            $mimeType = 'audio/' . ($ext === 'mp3' ? 'mpeg' : 'wav');
                        } elseif ($ext === 'pdf') {
                            $mimeType = 'application/pdf';
                        }
                        
                        $fileType = self::getFileTypeByMime($mimeType);
                        $a['url'] = '/anon/static/upload/' . $fileType . '/' . $base;
                        $a['mime_type'] = $mimeType;
                    }
                    
                    // 添加前端需要的字段
                    if (!isset($a['original_name'])) {
                        $a['original_name'] = '';
                    }
                    if (isset($a['updated_at'])) {
                        // MySQL TIMESTAMP 返回字符串格式，转换为 Unix 时间戳
                        if (is_string($a['updated_at'])) {
                            $a['created_at'] = strtotime($a['updated_at']);
                        } elseif (is_numeric($a['updated_at'])) {
                            $a['created_at'] = (int)$a['updated_at'];
                        } else {
                            $a['created_at'] = 0;
                        }
                    } else {
                        $a['created_at'] = 0;
                    }
                }
                unset($a);
            }
            
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
            $clientMimeType = $file['type'];
            $fileSize = $file['size'];
            
            /**
             * 获取文件后缀
             */
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            if (empty($ext)) {
                Anon_Http_Response::error('文件必须包含扩展名', 400);
                return;
            }
            
            /**
             * 通过文件头检测真实的 MIME 类型
             */
            $realMimeType = self::detectMimeTypeFromFile($tmpPath);
            if (empty($realMimeType)) {
                Anon_Http_Response::error('无法识别文件类型，请确保文件格式正确', 400);
                return;
            }
            
            /**
             * 验证文件扩展名与真实 MIME 类型是否匹配
             */
            if (!self::isExtensionMatchMimeType((string)$ext, $realMimeType)) {
                Anon_Http_Response::error("文件扩展名 .{$ext} 与文件实际类型 ({$realMimeType}) 不匹配，可能存在安全风险", 400);
                return;
            }
            
            /**
             * 使用真实的 MIME 类型进行分类
             */
            $fileType = self::getFileTypeByMime($realMimeType);
            
            /**
             * 验证文件后缀是否允许
             */
            if (!self::isExtensionAllowed((string)$ext, $fileType)) {
                $allowedExtensions = self::getAllowedExtensions($fileType);
                $allowedStr = empty($allowedExtensions) ? '无' : implode(', ', $allowedExtensions);
                Anon_Http_Response::error("不允许上传 {$fileType} 类型的 .{$ext} 文件，允许的后缀：{$allowedStr}", 400);
                return;
            }
            
            /**
             * 使用真实的 MIME 类型
             */
            $mimeType = $realMimeType;
            
            /**
             * 生成唯一文件名
             */
            $built = self::buildRandomFilename((string)$ext);
            $baseName = $built['base'];
            $filename = $built['filename'];
            
            /**
             * 上传目录
             */
            $uploadRoot = Anon_Main::APP_DIR . 'Upload/';
            if (!is_dir($uploadRoot)) {
                mkdir($uploadRoot, 0755, true);
            }

            $uploadDir = $uploadRoot . $fileType . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $filename;
            $url = '/anon/static/upload/' . $fileType . '/' . $baseName;
            
            if (!move_uploaded_file($tmpPath, $filePath)) {
                Anon_Http_Response::error('保存文件失败', 500);
                return;
            }
            
            $db = Anon_Database::getInstance();
            $userId = Anon_Http_Request::getUserId();
            
            $result = $db->db('attachments')->insert([
                'uid' => $userId,
                'filename' => $filename,
                'original_name' => $originalName,
                'file_size' => $fileSize,
            ]);
            
            if ($result) {
                $attachment = $db->db('attachments')->where('id', $result)->first();
                if (is_array($attachment) && !empty($attachment['filename'])) {
                    $base = pathinfo($attachment['filename'], PATHINFO_FILENAME);
                    $attachmentType = self::getFileTypeByMime($mimeType);
                    $attachment['url'] = '/anon/static/upload/' . $attachmentType . '/' . $base;
                    $attachment['mime_type'] = $mimeType;
                    if (!isset($attachment['original_name'])) {
                        $attachment['original_name'] = $originalName;
                    }
                    if (isset($attachment['updated_at'])) {
                        // MySQL TIMESTAMP 返回字符串格式，转换为 Unix 时间戳
                        if (is_string($attachment['updated_at'])) {
                            $attachment['created_at'] = strtotime($attachment['updated_at']);
                        } elseif (is_numeric($attachment['updated_at'])) {
                            $attachment['created_at'] = (int)$attachment['updated_at'];
                        } else {
                            $attachment['created_at'] = time();
                        }
                    } else {
                        $attachment['created_at'] = time();
                    }
                }
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
            $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
            
            if ($id <= 0) {
                Anon_Http_Response::error('附件 ID 无效', 400);
                return;
            }
            
            $db = Anon_Database::getInstance();
            $attachment = $db->db('attachments')->where('id', $id)->first();
            if (!$attachment) {
                Anon_Http_Response::error('附件不存在', 404);
                return;
            }
            
            // 根据文件名推断文件路径
            $filename = $attachment['filename'] ?? '';
            if (empty($filename)) {
                $db->db('attachments')->where('id', $id)->delete();
                Anon_Http_Response::success(null, '删除成功');
                return;
            }
            
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $uploadRoot = Anon_Main::APP_DIR . 'Upload/';
            
            // 尝试所有可能的文件类型目录
            $fileTypes = ['image', 'video', 'audio', 'document', 'other'];
            $filePath = null;
            
            foreach ($fileTypes as $fileType) {
                $possiblePath = $uploadRoot . $fileType . '/' . $filename;
                if (file_exists($possiblePath)) {
                    $filePath = $possiblePath;
                    break;
                }
            }
            
            // 如果找到文件，删除文件和处理后的缓存
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath);
                
                // 根据文件扩展名推断文件类型，删除处理后的缓存文件
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                
                if ($isImage) {
                    // 尝试所有可能的图片类型目录
                    $imageTypes = ['image'];
                    foreach ($imageTypes as $fileType) {
                        $processedDir = $uploadRoot . $fileType . '/processed/';
                        if (is_dir($processedDir)) {
                            $processedPatterns = ['webp', 'jpg', 'jpeg', 'png'];
                            foreach ($processedPatterns as $format) {
                                $processedPath = $processedDir . $base . '.' . $format;
                                if (file_exists($processedPath)) {
                                    @unlink($processedPath);
                                }
                            }
                        }
                    }
                }
            }
            
            // 删除记录
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

