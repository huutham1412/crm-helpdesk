<?php

namespace App\Services;

use App\Models\Attachment;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Exception;

class FileUploadService
{
    private array $allowedTypes = [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        // PDF
        'application/pdf',
        // Documents
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-zip-compressed',
    ];

    private array $maxSizes = [
        'image' => 5 * 1024 * 1024,      // 5MB
        'pdf' => 10 * 1024 * 1024,        // 10MB
        'document' => 10 * 1024 * 1024,   // 10MB
        'archive' => 20 * 1024 * 1024,    // 20MB
    ];

    private array $extensionMap = [
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'webp' => 'image',
        'pdf' => 'pdf',
        'doc' => 'document',
        'docx' => 'document',
        'xls' => 'document',
        'xlsx' => 'document',
        'txt' => 'document',
        'zip' => 'archive',
        'rar' => 'archive',
    ];

    private ?Cloudinary $cloudinary = null;

    public function __construct()
    {
        // Initialize Cloudinary nếu credentials được cấu hình
        if (
            config('cloudinary.cloud_name') &&
            config('cloudinary.api_key') &&
            config('cloudinary.api_secret')
        ) {
            $this->cloudinary = new Cloudinary([
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ]);
        }
    }

    /**
     * Upload a file and create attachment record.
     */
    public function upload(UploadedFile $file, int $ticketId, int $userId, ?int $messageId = null): array
    {
        // Validate file type
        if (!$this->isValidType($file)) {
            throw new Exception('File type not allowed. Allowed types: jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip, rar');
        }

        // Validate file size
        if (!$this->isValidSize($file)) {
            $maxSize = $this->getMaxSizeForFile($file);
            throw new Exception('File size exceeds limit of ' . $this->formatBytes($maxSize));
        }

        $cloudinaryPublicId = null;
        $cloudinaryUrl = null;
        $filePath = null;

        // Try upload to Cloudinary first (if configured)
        if ($this->cloudinary) {
            try {
                $isImage = $this->isImage($file);
                $folder = config('cloudinary.folder', 'crm-helpdesk/attachments') . '/' . $ticketId;

                $uploadResult = $this->cloudinary->uploadApi()->upload(
                    $file->getRealPath(),
                    [
                        'folder' => $folder,
                        'public_id' => uniqid(),
                        'resource_type' => $isImage ? 'image' : 'raw',
                        'use_filename' => false,
                    ]
                );

                $cloudinaryPublicId = $uploadResult['public_id'];
                $cloudinaryUrl = $uploadResult['secure_url'] ?? null;
                $filePath = $uploadResult['public_id'];

            } catch (\Exception $e) {
                // Log error but fallback to local storage
                \Log::error('Cloudinary upload failed: ' . $e->getMessage() . '. Falling back to local storage.');
            }
        }

        // Fallback to local storage if Cloudinary failed or not configured
        if (!$cloudinaryUrl) {
            $filename = $this->generateFilename($file);
            $filePath = $file->storeAs(
                'attachments/' . date('Y/m'),
                $filename,
                'public'
            );

            if (!$filePath) {
                throw new Exception('Failed to store file');
            }
        }

        // Create attachment record
        $attachment = Attachment::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message_id' => $messageId,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'cloudinary_public_id' => $cloudinaryPublicId,
            'cloudinary_url' => $cloudinaryUrl,
        ]);

        // Get URL
        $url = $cloudinaryUrl;
        if (!$url) {
            $url = Storage::url($filePath);
            if (!str_starts_with($url, 'http')) {
                $url = config('app.url') . $url;
            }
        }

        return [
            'id' => $attachment->id,
            'url' => $url,
            'filename' => $attachment->filename,
            'size' => $attachment->getReadableSize(),
            'type' => $attachment->file_type,
            'extension' => $attachment->file_extension,
            'is_image' => $attachment->isImage(),
        ];
    }

    /**
     * Delete an attachment file and record.
     */
    public function delete(Attachment $attachment): bool
    {
        // Delete from Cloudinary if exists
        if ($attachment->cloudinary_public_id && $this->cloudinary) {
            try {
                $this->cloudinary->uploadApi()->destroy($attachment->cloudinary_public_id);
            } catch (\Exception $e) {
                \Log::error('Failed to delete from Cloudinary: ' . $e->getMessage());
            }
        }

        // Delete from local storage if exists (fallback)
        if (!$attachment->cloudinary_public_id) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // Delete record
        return $attachment->delete();
    }

    /**
     * Check if file type is allowed.
     */
    private function isValidType(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), $this->allowedTypes);
    }

    /**
     * Check if file size is within limits.
     */
    private function isValidSize(UploadedFile $file): bool
    {
        $maxSize = $this->getMaxSizeForFile($file);
        return $file->getSize() <= $maxSize;
    }

    /**
     * Get max file size for a specific file.
     */
    private function getMaxSizeForFile(UploadedFile $file): int
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $category = $this->extensionMap[$extension] ?? 'document';

        return $this->maxSizes[$category] ?? $this->maxSizes['document'];
    }

    /**
     * Check if file is an image.
     */
    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'image/');
    }

    /**
     * Generate unique filename.
     */
    private function generateFilename(UploadedFile $file): string
    {
        return uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
