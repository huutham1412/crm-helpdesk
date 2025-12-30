<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'message_type',
        'attachments',
        'is_internal',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
        'read_at' => 'datetime',
    ];

    protected $appends = ['attachments_data'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachmentObjects(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    // Helper methods
    public function isSystemMessage(): bool
    {
        return $this->message_type === 'system';
    }

    public function isFileMessage(): bool
    {
        return $this->message_type === 'file';
    }

    public function isTextMessage(): bool
    {
        return $this->message_type === 'text';
    }

    /**
     * Check if message is visible to regular users (not internal)
     */
    public function isVisibleToUser(): bool
    {
        return !$this->is_internal && !$this->isSystemMessage();
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Get attachments data with full URLs for API response
     */
    public function getAttachmentsDataAttribute(): array
    {
        if ($this->relationLoaded('attachmentObjects')) {
            return $this->attachmentObjects->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'url' => $attachment->url,
                    'size' => $attachment->getReadableSize(),
                    'type' => $attachment->file_type,
                    'extension' => $attachment->file_extension,
                    'is_image' => $attachment->isImage(),
                ];
            })->toArray();
        }

        // Fallback: if relation not loaded, load it
        return $this->attachmentObjects()->get()->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'url' => $attachment->url,
                'size' => $attachment->getReadableSize(),
                'type' => $attachment->file_type,
                'extension' => $attachment->file_extension,
                'is_image' => $attachment->isImage(),
            ];
        })->toArray();
    }
}
