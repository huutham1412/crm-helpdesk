<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'impersonated_by',
        'action',
        'log_level',
        'subject_type',
        'subject_id',
        'description',
        'ip_address',
        'user_agent',
        'tags',
        'properties',
    ];

    protected $casts = [
        'tags' => 'array',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ActivityLogDetail::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByActions($query, array $actions)
    {
        return $query->whereIn('action', $actions);
    }

    public function scopeBySubject($query, $subjectType, $subjectId = null)
    {
        return $query->where('subject_type', $subjectType)
                    ->when($subjectId, fn($q) => $q->where('subject_id', $subjectId));
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeByLogLevel($query, $level)
    {
        return $query->where('log_level', $level);
    }

    public function scopeWithTags($query, array $tags)
    {
        return $query->whereJsonContains('tags', $tags);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('description', 'like', "%{$search}%");
    }

    // Accessors
    public function getActionLabelAttribute(): string
    {
        return config('activity-log.actions.' . $this->action, $this->action);
    }

    public function getReadableDescriptionAttribute(): string
    {
        return $this->description ?? $this->getDefaultDescription();
    }

    public function getIconAttribute(): string
    {
        return $this->getIconForAction();
    }

    public function getColorAttribute(): string
    {
        return $this->getColorForAction();
    }

    protected function getDefaultDescription(): string
    {
        return "{$this->action} on {$this->subject_type}";
    }

    protected function getIconForAction(): string
    {
        return match (true) {
            str_starts_with($this->action, 'login') => 'mdi-login',
            str_starts_with($this->action, 'logout') => 'mdi-logout',
            str_starts_with($this->action, 'user.') => 'mdi-account',
            str_starts_with($this->action, 'ticket.') => 'mdi-ticket',
            str_starts_with($this->action, 'message.') => 'mdi-message',
            str_starts_with($this->action, 'role.') => 'mdi-shield-account',
            str_starts_with($this->action, 'category.') => 'mdi-folder',
            str_starts_with($this->action, 'escalation') => 'mdi-alert',
            default => 'mdi-information',
        };
    }

    protected function getColorForAction(): string
    {
        return match ($this->log_level) {
            'emergency', 'alert', 'critical', 'error' => 'red',
            'warning' => 'orange',
            'notice' => 'blue',
            default => 'gray',
        };
    }

    public function hasDetails(): bool
    {
        return $this->details()->count() > 0;
    }

    public function getChangedFields(): array
    {
        return $this->details->pluck('field_name')->toArray();
    }
}
