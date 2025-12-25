<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'sent_telegram',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'sent_telegram' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // Scopes
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    // Helper methods
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function isUnread(): bool
    {
        return !$this->is_read;
    }

    /**
     * Type constants for easy reference
     */
    public const TYPE_NEW_TICKET = 'new_ticket';
    public const TYPE_TICKET_ASSIGNED = 'ticket_assigned';
    public const TYPE_TICKET_UPDATED = 'ticket_updated';
    public const TYPE_NEW_MESSAGE = 'new_message';
    public const TYPE_TICKET_RESOLVED = 'ticket_resolved';
    public const TYPE_TICKET_CLOSED = 'ticket_closed';
}
