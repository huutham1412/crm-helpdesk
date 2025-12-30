<?php

namespace App\Services;

use App\Models\Attachment;
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

        // Generate unique filename
        $filename = $this->generateFilename($file);

        // Store file
        $path = $file->storeAs(
            'attachments/' . date('Y/m'),
            $filename,
            'public'
        );

        if (!$path) {
            throw new Exception('Failed to store file');
        }

        // Create attachment record
        $attachment = Attachment::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message_id' => $messageId,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_extension' => strtolower($file->getClientOriginalExtension()),
        ]);

        // Get full URL instead of relative URL
        $url = Storage::url($path);
        if (!str_starts_with($url, 'http')) {
            $url = config('app.url') . $url;
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
        // Delete from storage
        Storage::disk('public')->delete($attachment->file_path);

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
