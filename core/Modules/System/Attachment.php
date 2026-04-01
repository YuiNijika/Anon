<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 通用附件处理系统
 * 支持 API 和 CMS 双模式
 */
class Anon_System_Attachment
{
    /**
     * 附件访问入口
     * @return void
     */
    public static function index()
    {
        Anon_Debug::info('Attachment request', [
            'filetype' => $_GET['filetype'] ?? '',
            'filename' => $_GET['filename'] ?? '',
            'imgtype' => $_GET['imgtype'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        $fileType = $_GET['filetype'] ?? '';
        $filename = $_GET['filename'] ?? '';
        $imgType = $_GET['imgtype'] ?? '';

        if (empty($fileType) || empty($filename)) {
            Anon_Debug::warn('Missing required parameters', [
                'filetype' => $fileType,
                'filename' => $filename
            ]);
            Anon::error('Invalid parameters', 400);
        }

        $fileType = self::sanitizeFileType($fileType);
        $filename = self::sanitizeFilename($filename);
        $imgType = self::sanitizeImgType($imgType);

        $attachment = self::findAttachment([
            'filename' => $filename . '%',
            'fileType' => $fileType,
        ]);

        if (!$attachment) {
            Anon::error('Attachment not found', 404);
        }

        $filePath = self::getRealFilePath($attachment, $fileType);

        if (!is_file($filePath) || !is_readable($filePath)) {
            Anon::error('File not found or not readable', 404);
        }

        self::serveWithStrongCache($filePath, $attachment, $imgType);
    }

    /**
     * 获取附件信息
     * @param int $id 附件 ID
     * @return array|null
     */
    public static function getAttachment(int $id): ?array
    {
        try {
            $db = Anon_Database::getInstance();
            return $db->db('attachments')->where('id', '=', $id)->first();
        } catch (Exception $e) {
            Anon_Debug::error('Failed to get attachment', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 上传附件
     * @param string $field 表单字段名
     * @param string $fileType 文件类型
     * @return array 上传结果
     */
    public static function upload(string $field = 'file', string $fileType = 'other'): array
    {
        if (!isset($_FILES[$field])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $file = $_FILES[$field];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
        }

        // 验证文件类型
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = self::getFileExtensionsByType($fileType);
        
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }

        // 生成唯一文件名
        $filename = uniqid() . '.' . $extension;
        $uploadRoot = Anon_Main::APP_DIR . 'Upload/';
        $targetDir = $uploadRoot . $fileType . '/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }

        // 记录到数据库
        $attachmentId = self::saveAttachment([
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_type' => $fileType,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
        ]);

        if (!$attachmentId) {
            unlink($targetPath);
            return ['success' => false, 'message' => 'Failed to save attachment record'];
        }

        return [
            'success' => true,
            'id' => $attachmentId,
            'filename' => $filename,
            'url' => self::getUrl($attachmentId, $fileType),
        ];
    }

    /**
     * 保存附件记录
     * @param array $data 附件数据
     * @return int|false 附件 ID
     */
    private static function saveAttachment(array $data)
    {
        try {
            $db = Anon_Database::getInstance();
            return $db->db('attachments')->insert([
                'filename' => $data['filename'],
                'original_name' => $data['original_name'] ?? '',
                'file_type' => $data['file_type'] ?? 'other',
                'file_size' => $data['file_size'] ?? 0,
                'mime_type' => $data['mime_type'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            Anon_Debug::error('Failed to save attachment', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取附件 URL
     * @param int $id 附件 ID
     * @param string $fileType 文件类型
     * @return string
     */
    public static function getUrl(int $id, string $fileType = 'other'): string
    {
        $baseUrl = Anon_System_Env::get('app.baseUrl', '/');
        return $baseUrl . 'anon/attachment?filetype=' . urlencode($fileType) . '&filename=' . urlencode(self::getFilenameById($id));
    }

    /**
     * 根据 ID 获取文件名
     * @param int $id 附件 ID
     * @return string
     */
    private static function getFilenameById(int $id): string
    {
        $attachment = self::getAttachment($id);
        return $attachment['filename'] ?? '';
    }

    /**
     * 删除附件
     * @param int $id 附件 ID
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $attachment = self::getAttachment($id);
        if (!$attachment) {
            return false;
        }

        $fileType = $attachment['file_type'] ?? 'other';
        $filePath = self::getRealFilePath($attachment, $fileType);
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        try {
            $db = Anon_Database::getInstance();
            return $db->db('attachments')->where('id', '=', $id)->delete() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 查找附件记录
     * @param array $conditions 查询条件
     * @return array|null
     */
    private static function findAttachment(array $conditions): ?array
    {
        try {
            $db = Anon_Database::getInstance();
            $query = $db->db('attachments');

            $query->where('filename', 'LIKE', $conditions['filename']);

            if (isset($conditions['fileType']) && !empty($conditions['fileType'])) {
                $fileExtensions = self::getFileExtensionsByType($conditions['fileType']);
                if (!empty($fileExtensions)) {
                    $query->where(function ($subQuery) use ($fileExtensions) {
                        $first = true;
                        foreach ($fileExtensions as $ext) {
                            if ($first) {
                                $subQuery->where('filename', 'LIKE', '%.' . $ext);
                                $first = false;
                            } else {
                                $subQuery->orWhere('filename', 'LIKE', '%.' . $ext);
                            }
                        }
                    });
                }
            }

            return $query->first();
        } catch (Exception $e) {
            Anon_Debug::error('Failed to query attachment', [
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取实际文件路径
     * @param array $attachment 附件记录
     * @param string $fileType 文件类型
     * @return string
     */
    private static function getRealFilePath(array $attachment, string $fileType): string
    {
        $uploadRoot = Anon_Main::APP_DIR . 'Upload/';
        $filename = $attachment['filename'] ?? '';
        
        if (!empty($fileType)) {
            return $uploadRoot . $fileType . '/' . $filename;
        }

        $possibleTypes = ['image', 'video', 'audio', 'document', 'other'];
        foreach ($possibleTypes as $type) {
            $possiblePath = $uploadRoot . $type . '/' . $filename;
            if (file_exists($possiblePath)) {
                return $possiblePath;
            }
        }

        return $uploadRoot . 'other/' . $filename;
    }

    /**
     * 通过强缓存机制提供文件服务
     * @param string $filePath 文件路径
     * @param array $attachment 附件记录
     * @param string $imgType 图片类型转换
     * @return void
     */
    private static function serveWithStrongCache(string $filePath, array $attachment, string $imgType = '')
    {
        $expire = 315360000;
        
        header('Cache-Control: public, max-age=' . $expire);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
        
        $etag = md5($filePath . filemtime($filePath));
        header('ETag: "' . $etag . '"');

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        if (
            $ifNoneMatch === '"' . $etag . '"' ||
            (!empty($ifModifiedSince) && strtotime($ifModifiedSince) >= filemtime($filePath))
        ) {
            http_response_code(304);
            exit;
        }

        if (!empty($imgType) && self::isImageFile($filePath)) {
            self::serveConvertedImage($filePath, $imgType, $attachment);
            return;
        }

        $mimeType = self::getMimeType($filePath, $attachment);
        Anon_Http_StaticResource::serveFile($filePath, $mimeType, 315360000, false);
    }

    /**
     * 提供转换后的图片
     * @param string $filePath 原始文件路径
     * @param string $imgType 图片处理参数
     * @param array $attachment 附件信息
     * @return void
     */
    private static function serveConvertedImage(string $filePath, string $imgType, array $attachment)
    {
        $imgType = strtolower(trim($imgType));
        
        $targetFormat = null;
        $width = $height = null;
        
        $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        
        if (in_array($imgType, $supportedFormats, true)) {
            $targetFormat = $imgType;
        } elseif (preg_match('/^(\d+)x(\d+)$/', $imgType, $matches)) {
            $width = (int)$matches[1];
            $height = (int)$matches[2];
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $targetFormat = $ext === 'jpg' ? 'jpeg' : $ext;
        } elseif (strpos($imgType, '_') !== false) {
            $parts = explode('_', $imgType);
            if (count($parts) >= 2) {
                $format = $parts[0];
                $dimensions = $parts[1];
                
                if (in_array($format, $supportedFormats, true) && 
                    preg_match('/^(\d+)x(\d+)$/', $dimensions, $dimMatches)) {
                    $targetFormat = $format;
                    $width = (int)$dimMatches[1];
                    $height = (int)$dimMatches[2];
                }
            }
        }
        
        if (!$targetFormat) {
            Anon::error('Invalid image processing parameters', 400);
        }
        
        if (($width || $height) && ($width < 1 || $width > 5000 || $height < 1 || $height > 5000)) {
            Anon::error('Invalid dimensions', 400);
        }

        if (!extension_loaded('gd')) {
            Anon::error('GD extension not available', 500);
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            Anon::error('Failed to read source image', 500);
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            Anon::error('Invalid image file', 500);
        }

        if (in_array($targetFormat, ['png', 'webp'], true)) {
            @imagealphablending($src, true);
            @imagesavealpha($src, true);
        }

        $originalWidth = imagesx($src);
        $originalHeight = imagesy($src);
        
        if ($width && $height) {
            $scale = min($width / $originalWidth, $height / $originalHeight, 1);
            $newWidth = (int)($originalWidth * $scale);
            $newHeight = (int)($originalHeight * $scale);
            
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            
            if ($targetFormat === 'png' || pathinfo($filePath, PATHINFO_EXTENSION) === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            imagedestroy($src);
            $src = $dst;
        }
        
        if (!$targetFormat) {
            $targetFormat = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($targetFormat === 'jpg') $targetFormat = 'jpeg';
        }

        $outputFunction = null;
        switch ($targetFormat) {
            case 'webp':
                $outputFunction = 'imagewebp';
                break;
            case 'jpg':
            case 'jpeg':
                $outputFunction = 'imagejpeg';
                break;
            case 'png':
                $outputFunction = 'imagepng';
                break;
            case 'gif':
                $outputFunction = 'imagegif';
                break;
            case 'avif':
                $outputFunction = 'imageavif';
                break;
        }

        if (!$outputFunction) {
            imagedestroy($src);
            Anon::error('Unsupported output format', 400);
        }

        $outputMime = 'application/octet-stream';
        switch ($targetFormat) {
            case 'webp':
                $outputMime = 'image/webp';
                break;
            case 'jpg':
            case 'jpeg':
                $outputMime = 'image/jpeg';
                break;
            case 'png':
                $outputMime = 'image/png';
                break;
            case 'gif':
                $outputMime = 'image/gif';
                break;
            case 'avif':
                $outputMime = 'image/avif';
                break;
        }

        header('Content-Type: ' . $outputMime);
        header('Cache-Control: public, max-age=315360000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 315360000) . ' GMT');
        
        $quality = 85;
        switch ($targetFormat) {
            case 'webp':
                imagewebp($src, null, $quality);
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($src, null, $quality);
                break;
            case 'png':
                imagepng($src);
                break;
            case 'gif':
                imagegif($src);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($src, null, $quality);
                } else {
                    imagedestroy($src);
                    Anon::error('AVIF support not available', 500);
                }
                break;
        }

        imagedestroy($src);
        exit;
    }

    /**
     * 判断是否为图片文件
     * @param string $filePath 文件路径
     * @return bool
     */
    private static function isImageFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($extension, $imageExtensions, true);
    }

    /**
     * 获取文件 MIME 类型
     * @param string $filePath 文件路径
     * @param array $attachment 附件信息
     * @return string
     */
    private static function getMimeType(string $filePath, array $attachment): string
    {
        if (!empty($attachment['mime_type'])) {
            return $attachment['mime_type'];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/vnd.microsoft.icon',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * 根据文件类型获取扩展名列表
     * @param string $fileType 文件类型
     * @return array
     */
    private static function getFileExtensionsByType(string $fileType): array
    {
        $typeMap = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'ico'],
            'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
        ];

        return $typeMap[$fileType] ?? [];
    }

    /**
     * 安全过滤文件类型
     * @param string $fileType 文件类型
     * @return string
     */
    private static function sanitizeFileType(string $fileType): string
    {
        $fileType = strtolower(trim($fileType));
        $allowedTypes = ['image', 'video', 'audio', 'document', 'other'];
        
        if (in_array($fileType, $allowedTypes, true)) {
            return $fileType;
        }
        
        return '';
    }

    /**
     * 安全过滤文件名
     * @param string $filename 文件名
     * @return string
     */
    private static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }

    /**
     * 安全过滤图片类型
     * @param string $imgType 图片类型
     * @return string
     */
    private static function sanitizeImgType(string $imgType): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $imgType);
    }
    
    /**
     * 获取附件列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string|null $mimeType MIME 类型筛选
     * @param string $sort 排序方式：new=新到老，old=老到新
     * @return array
     */
    public static function getAttachmentList(int $page = 1, int $pageSize = 20, ?string $mimeType = null, string $sort = 'new'): array
    {
        try {
            $db = Anon_Database::getInstance();
            $baseQuery = $db->db('attachments');

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

            $countQuery = $db->db('attachments');
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
                    $countQuery->where(function ($query) use ($extensions) {
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
                        $countQuery->where(function ($query) use ($mimeExtMap, $mimeType) {
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
            $total = $countQuery->count();

            $orderDirection = $sort === 'old' ? 'ASC' : 'DESC';
            $attachments = $baseQuery
                ->orderBy('updated_at', $orderDirection)
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();

            return [
                'list' => $attachments ?: [],
                'total' => $total,
            ];
        } catch (Exception $e) {
            Anon_Debug::error('Failed to get attachment list', ['error' => $e->getMessage()]);
            return ['list' => [], 'total' => 0];
        }
    }
    
    /**
     * 根据 MIME 类型推断文件分类目录
     * @param string $mimeType MIME 类型
     * @return string
     */
    public static function getFileTypeByMime(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (strpos($mimeType, 'audio/') === 0) return 'audio';
        if ($mimeType === 'application/pdf') return 'document';
        return 'other';
    }
    
    /**
     * 通过文件头检测真实的 MIME 类型
     * @param string $filePath 文件路径
     * @return string|null
     */
    public static function detectMimeTypeFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

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

        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType && $mimeType !== 'application/octet-stream') {
                return $mimeType;
            }
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo && isset($imageInfo['mime'])) {
            return $imageInfo['mime'];
        }

        return null;
    }
    
    /**
     * 验证文件扩展名与 MIME 类型是否匹配
     * @param string $ext 文件扩展名
     * @param string $mimeType MIME 类型
     * @return bool
     */
    public static function isExtensionMatchMimeType(string $ext, string $mimeType): bool
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
            'image/vnd.microsoft.icon' => ['ico'],
            'video/mp4' => ['mp4'],
            'video/quicktime' => ['mov'],
            'video/x-msvideo' => ['avi'],
            'video/x-ms-wmv' => ['wmv'],
            'video/x-flv' => ['flv'],
            'video/webm' => ['webm'],
            'video/ogg' => ['ogv'],
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/ogg' => ['ogg', 'oga'],
            'audio/mp4' => ['m4a'],
            'audio/aac' => ['aac'],
            'audio/flac' => ['flac'],
            'audio/x-ms-wma' => ['wma'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/vnd.ms-powerpoint' => ['ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
            'text/plain' => ['txt'],
            'application/zip' => ['zip'],
            'application/x-rar-compressed' => ['rar'],
            'application/x-7z-compressed' => ['7z'],
            'application/gzip' => ['gz'],
        ];

        if (!isset($mimeExtMap[$mimeType])) {
            return false;
        }

        return in_array($ext, $mimeExtMap[$mimeType], true);
    }
    
    /**
     * 生成上传文件名
     * @param string $ext 文件扩展名
     * @return array
     */
    public static function buildRandomFilename(string $ext): array
    {
        $ext = strtolower(trim($ext));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $rand = bin2hex(random_bytes(8));
        $base = $rand . '-' . time();
        $filename = $ext ? ($base . '.' . $ext) : $base;
        return ['base' => $base, 'filename' => $filename];
    }
    
    /**
     * 处理附件管理 - 获取列表
     * @return void
     */
    public static function handleGetList(): void
    {
        $data = array_merge($_GET, Anon_Http_Request::getInput());
        $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
        $pageSize = isset($data['page_size']) ? max(1, min(100, (int)$data['page_size'])) : 20;
        $mimeType = isset($data['mime_type']) ? trim($data['mime_type']) : null;
        $sort = isset($data['sort']) ? trim($data['sort']) : 'new';

        if (!in_array($sort, ['new', 'old'], true)) {
            $sort = 'new';
        }

        $result = self::getAttachmentList($page, $pageSize, $mimeType, $sort);
        $list = [];
        foreach ($result['list'] as $a) {
            if (!empty($a['filename'])) {
                $base = pathinfo($a['filename'], PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($a['filename'], PATHINFO_EXTENSION));
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
                $a['url'] = '/anon/attachment/' . $fileType . '/' . $base;
                $a['mime_type'] = $mimeType;
                $a['name'] = $a['original_name'] ?: $a['filename'];
                $a['size'] = (int)($a['file_size'] ?? 0);
                $a['created_at'] = isset($a['updated_at']) ? (is_numeric($a['updated_at']) ? (int)$a['updated_at'] : strtotime($a['updated_at'])) : 0;
            }
            $list[] = $a;
        }

        Anon_Http_Response::success([
            'list' => $list,
            'total' => $result['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ], '获取附件列表成功');
    }
    
    /**
     * 处理附件管理 - 上传
     * @return void
     */
    public static function handleUpload(): void
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Anon_Http_Response::error('文件上传失败', 400);
            return;
        }
        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            Anon_Http_Response::error('文件必须包含扩展名', 400);
        }
        $realMimeType = self::detectMimeTypeFromFile($file['tmp_name']);
        if (empty($realMimeType)) {
            Anon_Http_Response::error('无法识别文件类型', 400);
        }
        if (!self::isExtensionMatchMimeType($ext, $realMimeType)) {
            Anon_Http_Response::error("文件扩展名与 MIME 类型不匹配", 400);
        }
        $fileType = self::getFileTypeByMime($realMimeType);
        $built = self::buildRandomFilename($ext);
        $uploadDir = Anon_Main::APP_DIR . 'Upload/' . $fileType . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $built['filename'])) {
            Anon_Http_Response::error('保存文件失败', 500);
        }
        $db = Anon_Database::getInstance();
        $id = $db->db('attachments')->insert(['uid' => Anon_Http_Request::getUserId(), 'filename' => $built['filename'], 'original_name' => $file['name'], 'file_size' => $file['size']]);
        if (!$id) {
            Anon_Http_Response::error('保存记录失败', 500);
        }
        $attachment = self::getAttachment($id);
        $attachment['url'] = '/anon/attachment/' . $fileType . '/' . $built['base'];
        $attachment['mime_type'] = $realMimeType;
        Anon_Http_Response::success($attachment, '上传成功');
    }
    
    /**
     * 处理附件管理 - 删除
     * @return void
     */
    public static function handleDelete(): void
    {
        $data = Anon_Http_Request::getInput();
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            Anon_Http_Response::error('附件 ID 无效', 400);
        }
        $attachment = self::getAttachment($id);
        if (!$attachment) {
            Anon_Http_Response::error('附件不存在', 404);
        }
        self::delete($id);
        Anon_Http_Response::success(null, '删除成功');
    }
}
