<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'message_type',
        'attachments',
        'is_internal',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
