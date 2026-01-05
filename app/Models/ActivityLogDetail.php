<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLogDetail extends Model
{
    protected $fillable = [
        'activity_log_id',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class);
    }

    public function getFieldLabelAttribute(): string
    {
        return config('activity-log.field_labels.' . $this->field_name, ucfirst(str_replace('_', ' ', $this->field_name)));
    }

    public function getChangedValueAttribute(): string
    {
        $old = $this->old_value ?? '-';
        $new = $this->new_value ?? '-';

        if (strlen($old) > 50 || strlen($new) > 50) {
            $old = substr($old, 0, 50) . '...';
            $new = substr($new, 0, 50) . '...';
        }

        return "{$old} → {$new}";
    }
}
