<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Attachment
{
    /**
     * 附件访问入口
     * @return void
     */
    public static function index()
    {
        Anon_Debug::info('ATTACHMENT CONTROLLER CALLED', [
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
            Anon_Http_Response::error('Invalid parameters', 400);
        }

        $fileType = self::sanitizeFileType($fileType);
        $filename = self::sanitizeFilename($filename);
        $imgType = self::sanitizeImgType($imgType);

        $conditions = [
            'filename' => $filename . '%',
        ];

        if (!empty($fileType)) {
            $fileExtensions = self::getFileExtensionsByType($fileType);
            if (!empty($fileExtensions)) {
                $conditions['file_extensions'] = $fileExtensions;
            }
        }

        $attachment = self::findAttachment($conditions);

        if (!$attachment) {
            Anon_Http_Response::error('Attachment not found', 404);
        }

        $filePath = self::getRealFilePath($attachment, $fileType);

        if (!is_file($filePath) || !is_readable($filePath)) {
            Anon_Http_Response::error('File not found or not readable', 404);
        }



        self::serveWithStrongCache($filePath, $attachment, $imgType);
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

            if (isset($conditions['file_extensions']) && !empty($conditions['file_extensions'])) {
                $query->where(function ($subQuery) use ($conditions) {
                    $first = true;
                    foreach ($conditions['file_extensions'] as $ext) {
                        if ($first) {
                            $subQuery->where('filename', 'LIKE', '%.' . $ext);
                            $first = false;
                        } else {
                            $subQuery->orWhere('filename', 'LIKE', '%.' . $ext);
                        }
                    }
                });
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
     * @param array $attachment 附件信息
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
        
        // Discuz风格: 直接解析参数
        $targetFormat = null;
        $width = $height = null;
        
        // 支持格式: jpg/jpeg/png/gif/webp
        $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        
        // 如果参数本身就是支持的格式
        if (in_array($imgType, $supportedFormats, true)) {
            $targetFormat = $imgType;
        }
        // 如果是尺寸格式 672x378
        elseif (preg_match('/^(\d+)x(\d+)$/', $imgType, $matches)) {
            $width = (int)$matches[1];
            $height = (int)$matches[2];
            // 默认保持原格式
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $targetFormat = $ext === 'jpg' ? 'jpeg' : $ext;
        }
        // 如果是格式_尺寸格式 webp_672x378
        elseif (strpos($imgType, '_') !== false) {
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
        
        // 验证参数
        if (!$targetFormat) {
            Anon_Http_Response::error('Invalid image processing parameters', 400);
        }
        
        if (($width || $height) && ($width < 1 || $width > 5000 || $height < 1 || $height > 5000)) {
            Anon_Http_Response::error('Invalid dimensions', 400);
        }

        if (!extension_loaded('gd')) {
            Anon_Http_Response::error('GD extension not available', 500);
        }

        // 读取原始图片
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            Anon_Http_Response::error('Failed to read source image', 500);
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            Anon_Http_Response::error('Invalid image file', 500);
        }

        // 设置透明度支持
        if (in_array($targetFormat, ['png', 'webp'], true)) {
            @imagealphablending($src, true);
            @imagesavealpha($src, true);
        }

        $originalWidth = imagesx($src);
        $originalHeight = imagesy($src);
        
        // 尺寸处理: 简单等比缩放
        if ($width && $height) {
            // 等比缩放
            $scale = min($width / $originalWidth, $height / $originalHeight, 1);
            $newWidth = (int)($originalWidth * $scale);
            $newHeight = (int)($originalHeight * $scale);
            
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            
            // 保持透明度
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
        
        // 如果只有格式转换需求
        if (!$targetFormat) {
            $targetFormat = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($targetFormat === 'jpg') $targetFormat = 'jpeg';
        }

        // 转换格式
        $outputFunction = match($targetFormat) {
            'webp' => 'imagewebp',
            'jpg', 'jpeg' => 'imagejpeg',
            'png' => 'imagepng',
            'gif' => 'imagegif',
            'avif' => 'imageavif',
            default => null
        };

        if (!$outputFunction) {
            imagedestroy($src);
            Anon_Http_Response::error('Unsupported output format', 400);
        }

        // 设置输出MIME类型
        $outputMime = match($targetFormat) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => 'application/octet-stream'
        };

        // 输出转换后的图片
        header('Content-Type: ' . $outputMime);
        header('Cache-Control: public, max-age=315360000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 315360000) . ' GMT');
        
        $quality = 85; // JPEG/WebP质量
        switch ($targetFormat) {
            case 'webp':
                imagewebp($src, null, $quality);
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($src, null, $quality);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($src, null, $quality);
                } else {
                    imagedestroy($src);
                    Anon_Http_Response::error('AVIF support not available', 500);
                }
                break;
            default:
                $outputFunction($src);
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
        // 首先尝试从附件记录中获取
        if (!empty($attachment['mime_type'])) {
            return $attachment['mime_type'];
        }

        // 通过文件扩展名推断
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
        // 移除危险字符，只保留字母数字、点、连字符、下划线
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // 限制长度
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


}