<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'category_id',
        'priority',
        'status',
        'subject',
        'description',
        'resolution',
        'resolved_at',
        'closed_at',
        'last_status_change_at',
        'first_escalated_at',
        'is_escalated',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_status_change_at' => 'datetime',
        'first_escalated_at' => 'datetime',
        'is_escalated' => 'boolean',
    ];

    /**
     * Generate ticket number automatically when creating
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $year = date('Y');
                $lastTicket = static::withTrashed()
                    ->whereYear('created_at', $year)
                    ->orderBy('id', 'desc')
                    ->first();

                $sequence = $lastTicket
                    ? (int) substr($lastTicket->ticket_number, -6) + 1
                    : 1;

                $ticket->ticket_number = 'TKT-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function escalations()
    {
        return $this->hasMany(TicketEscalation::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'processing']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Helper methods
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'processing']);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Mới',
            'processing' => 'Đang xử lý',
            'pending' => 'Chờ phản hồi',
            'resolved' => 'Đã giải quyết',
            'closed' => 'Đã đóng',
            default => $this->status,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Thấp',
            'medium' => 'Trung bình',
            'high' => 'Cao',
            'urgent' => 'Khẩn cấp',
            default => $this->priority,
        };
    }

    public function markAsResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function markAsClosed(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Check if ticket has been rated
     */
    public function hasBeenRated(): bool
    {
        return $this->rating()->exists();
    }

    // ==================== SLA / Escalation Methods ====================

    /**
     * Get response time in minutes based on priority
     */
    public function getResponseTimeMinutes(): int
    {
        return config('sla.response_time.' . $this->priority, 30);
    }

    /**
     * Get escalation threshold in minutes (response_time * multiplier)
     */
    public function getEscalationThresholdMinutes(): int
    {
        return (int) floor($this->getResponseTimeMinutes() * config('sla.escalation_multiplier', 1.5));
    }

    /**
     * Get the timestamp to calculate response time from
     */
    public function getResponseStartTime(): \Carbon\Carbon
    {
        return $this->last_status_change_at ?? $this->created_at;
    }

    /**
     * Get minutes since response start time
     */
    public function getMinutesSinceResponseStart(): int
    {
        return $this->getResponseStartTime()->diffInMinutes(now());
    }

    /**
     * Check if ticket should escalate to warning level (Telegram notification)
     */
    public function shouldEscalateWarning(): bool
    {
        // Only check open tickets
        if ($this->status !== 'open') {
            return false;
        }

        // Don't escalate if already has unresolved warning
        if ($this->escalations()->unresolved()->warning()->exists()) {
            return false;
        }

        // Check if response time exceeded
        return $this->getMinutesSinceResponseStart() >= $this->getResponseTimeMinutes();
    }

    /**
     * Check if ticket should escalate to admin level
     */
    public function shouldEscalateToAdmin(): bool
    {
        // Only check open tickets
        if ($this->status !== 'open') {
            return false;
        }

        // Don't escalate if already escalated to admin
        if ($this->escalations()->unresolved()->escalated()->exists()) {
            return false;
        }

        // Must have warning escalation first
        $warningEscalation = $this->escalations()->unresolved()->warning()->first();
        if (!$warningEscalation) {
            return false;
        }

        // Check if escalation threshold exceeded
        return $this->getMinutesSinceResponseStart() >= $this->getEscalationThresholdMinutes();
    }

    /**
     * Mark escalations as resolved when status changes
     */
    public function resolveEscalations(): void
    {
        $this->escalations()->unresolved()->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Update last status change timestamp
     */
    public function updateLastStatusChange(): void
    {
        $this->update([
            'last_status_change_at' => now(),
        ]);
    }
}
