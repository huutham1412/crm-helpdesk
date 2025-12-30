<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message_id',
        'filename',
        'file_path',
        'file_type',
        'file_size',
        'file_extension',
    ];

    /**
     * Get the ticket that owns the attachment.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user that uploaded the attachment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the message that owns the attachment.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }

    /**
     * Get the URL for the attachment.
     */
    public function getUrlAttribute(): string
    {
        $url = Storage::url($this->file_path);
        // Return full URL if not already absolute
        if (!str_starts_with($url, 'http')) {
            $url = config('app.url') . $url;
        }
        return $url;
    }

    /**
     * Get file size in human readable format.
     */
    public function getReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file icon class based on file type.
     */
    public function getIconClass(): string
    {
        if ($this->isImage()) {
            return 'text-purple-500';
        }

        if ($this->isPdf()) {
            return 'text-red-500';
        }

        if (in_array($this->file_extension, ['doc', 'docx'])) {
            return 'text-blue-500';
        }

        if (in_array($this->file_extension, ['xls', 'xlsx'])) {
            return 'text-green-500';
        }

        if (in_array($this->file_extension, ['zip', 'rar'])) {
            return 'text-yellow-500';
        }

        return 'text-slate-500';
    }

    /**
     * Scope: Get attachments for a specific ticket.
     */
    public function scopeForTicket($query, int $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    /**
     * Scope: Get attachments for a specific message.
     */
    public function scopeForMessage($query, int $messageId)
    {
        return $query->where('message_id', $messageId);
    }
}
